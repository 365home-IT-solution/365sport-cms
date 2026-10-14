<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use App\Support\AdminCrud\CrudRegistry;
use App\Support\ApiDocs\DocsFixtures;
use App\Support\FeatureRoutes;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as Printer;
use ReflectionClass;
use ReflectionMethod;

// Thu thập dữ liệu để viết tài liệu API (Word): với MỌI route API đang bật → module, mô tả, xác thực, trường request (đọc từ luật
// validate trong mã nguồn), request mẫu và response mẫu THẬT (gọi thử trên CSDL test, mỗi lần gọi nằm trong transaction rồi rollback).
class GenerateApiDocsData extends Command
{
    protected $signature = 'api:docs-data {--out=storage/app/api-docs/data.json} {--only= : chỉ lấy các route có đường dẫn chứa chuỗi này} {--no-live : không gọi thử} {--debug : in lỗi 5xx}';

    protected $description = 'Thu thập dữ liệu tài liệu API (request/response mẫu) cho bộ tài liệu Word';

    private HttpKernel $kernel;

    private $admin;

    private $customer;

    private array $astCache = [];

    private int $requests = 0;

    public function handle(): int
    {
        $db = (string) config('database.connections.' . config('database.default') . '.database');
        if (! str_contains($db, 'test')) {
            $this->error("Từ chối: CSDL '$db' không phải CSDL test (lệnh này gọi thử cả route ghi dữ liệu).");

            return self::FAILURE;
        }

        config(['queue.default' => 'sync', 'app.fcm_bypass_enabled' => true]);
        $this->bypassThrottle();
        Http::fake(fn () => Http::response(['ok' => true], 200));
        Mail::fake();
        Queue::fake();
        Storage::fake('public');

        $this->kernel = app(HttpKernel::class);
        $this->admin = User::role('super_admin')->first();
        $this->customer = Customer::where('status', Customer::STATUS_ACTIVE)
            ->withCount('orders')->orderByDesc('orders_count')->first() ?? Customer::first();
        if (! $this->admin || ! $this->customer) {
            $this->error('Cần ít nhất 1 super_admin và 1 khách trong CSDL test.');

            return self::FAILURE;
        }

        $cfg = config('audit');
        $moduleNames = collect($cfg['modules'])->map(fn ($m) => ['name' => $m['name'], 'group' => $m['group']])->all();
        $prefixModule = $this->buildPrefixModuleMap($cfg);

        $only = (string) $this->option('only');
        $out = [];
        $routes = collect(app('router')->getRoutes()->getRoutes())->sortBy(fn (Route $r) => $r->uri())->values();
        $i = 0;

        foreach ($routes as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || str_contains($uri, '{fallbackPlaceholder}') || FeatureRoutes::disabledFlagFor($uri) !== null) {
                continue;
            }
            if ($only !== '' && ! str_contains($uri, $only)) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $i++;
                $out[] = $this->document($route, $method, $prefixModule);
                if ($i % 50 === 0) {
                    $this->line("  $i route...");
                }
            }
        }

        $file = base_path((string) $this->option('out'));
        File::ensureDirectoryExists(dirname($file));
        File::put($file, json_encode(['modules' => $moduleNames, 'endpoints' => $out], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
        $this->info('Đã ghi ' . count($out) . " thao tác → $file");

        return self::SUCCESS;
    }

    // ── Phân loại module ────────────────────────────────────────────────────────────────────

    /** @return array<string,string> tiền tố (đã chuẩn hoá {}) → id module */
    private function buildPrefixModuleMap(array $cfg): array
    {
        $resourceModule = [];
        foreach ($cfg['modules'] as $id => $m) {
            foreach ($m['resources'] as $r) { $resourceModule[$r] = $id; }
        }
        $map = [];
        foreach (($cfg['admin_api'] ?? []) as $resource => $prefixes) {
            foreach ((array) $prefixes as $p) {
                if (isset($resourceModule[$resource])) { $map[$p] = $resourceModule[$resource]; }
            }
        }
        $pageModule = [];
        foreach ($cfg['modules'] as $id => $m) {
            foreach ($m['pages'] as $p) { $pageModule[$p] = $id; }
        }
        foreach (($cfg['admin_api_pages'] ?? []) as $page => $prefixes) {
            foreach ((array) $prefixes as $p) {
                if (isset($pageModule[$page])) { $map[$p] = $pageModule[$page]; }
            }
        }
        foreach (($cfg['admin_extra'] ?? []) as $mid => $list) {
            foreach ($list as $e) { $map[$e[2]] = $mid; }
        }
        foreach (($cfg['client'] ?? []) as $mid => $list) {
            foreach ($list as $e) { $map[$e[2]] = $mid; }
        }
        foreach (($cfg['route_modules'] ?? []) as $p => $mid) {
            $map[$p] = $mid; // khai báo thủ công luôn thắng
        }

        return $map;
    }

    private function moduleOf(string $uri, array $map): string
    {
        $norm = preg_replace('/\{[^}]+\}/', '{}', $uri);
        $best = null; $len = -1;
        foreach ($map as $prefix => $mid) {
            $p = preg_replace('/\{[^}]+\}/', '{}', $prefix);
            if (($norm === $p || str_starts_with($norm, rtrim($p, '/') . '/')) && strlen($p) > $len) {
                $best = $mid; $len = strlen($p);
            }
        }

        return $best ?? 'other';
    }

    // ── Một thao tác ────────────────────────────────────────────────────────────────────────

    private function document(Route $route, string $method, array $prefixModule): array
    {
        $uri = $route->uri();
        $mw = $route->gatherMiddleware();
        $isAdmin = in_array('admin.api', $mw, true);
        $needsAuth = $isAdmin || (bool) array_filter($mw, fn ($m) => is_string($m) && str_starts_with($m, 'auth'));
        $auth = $isAdmin ? 'admin' : ($needsAuth ? 'customer' : 'public');

        [$class, $action] = $this->controllerOf($route);
        [$summary, $description] = $this->describe($class, $action);
        $spec = $this->requestSpec($route, $method, $class, $action);

        $doc = [
            'method' => $method,
            'uri' => '/' . $uri,
            'module' => $this->moduleOf($uri, $prefixModule),
            'name' => $route->getName(),
            'auth' => $auth,
            'summary' => $summary,
            'description' => $description,
            'permission' => $isAdmin ? $this->permissionOf($uri, $method) : null,
            'path_params' => $this->pathParams($uri),
            'query_params' => $spec['query'],
            'body_fields' => $spec['body'],
            'content_type' => $spec['has_file'] ? 'multipart/form-data' : (in_array($method, ['POST', 'PUT', 'PATCH'], true) ? 'application/json' : null),
            'request_sample' => null,
            'responses' => [],
        ];

        if ($this->option('no-live')) {
            return $doc;
        }

        $this->liveSamples($doc, $route, $method, $spec, $isAdmin, $needsAuth);

        return $doc;
    }

    /** Quyền Shield cần có (khớp logic của middleware AdminApiAuth); super_admin luôn được qua. */
    private function permissionOf(string $uri, string $method): ?string
    {
        $segments = explode('/', $uri);
        $group = $segments[2] ?? '';
        if (in_array($group, ['login', 'logout', 'me', 'subscription'], true)) {
            return null;
        }
        $const = (new ReflectionClass(\App\Http\Middleware\AdminApiAuth::class))->getConstant('RESOURCES') ?: [];
        if ($group === 'warehouse') {
            $resource = match ($segments[3] ?? '') {
                'categories' => 'warehouse::category', 'items', 'item-rentals' => 'warehouse::item', 'units' => 'warehouse::unit',
                'stock-ins' => 'warehouse::stock::in', 'stock-outs' => 'warehouse::stock::out', 'stock-checks' => 'warehouse::stock::check',
                default => null,
            };
        } elseif ($group === 'settings') {
            $resource = match ($segments[3] ?? '') {
                'general' => 'page_ManageGeneral', 'mail' => 'page_ManageMail', 'google-login' => 'page_GoogleLoginSettings', 'directions' => 'page_DirectionsSettings',
                default => null,
            };
        } else {
            $resource = $const[$group] ?? CrudRegistry::permission($group);
        }
        if ($resource === null) {
            return null;
        }
        if (str_starts_with($resource, 'page_')) {
            return $resource;
        }

        return match ($method) {
            'GET' => 'view_any_' . $resource, 'POST' => 'create_' . $resource . ' (hoặc update_' . $resource . ' với thao tác trên bản ghi)',
            'PUT', 'PATCH' => 'update_' . $resource, 'DELETE' => 'delete_' . $resource, default => null,
        };
    }

    private function controllerOf(Route $route): array
    {
        $a = $route->getActionName();
        if (! str_contains($a, '@')) {
            return [null, null];
        }
        [$c, $m] = explode('@', $a);

        return class_exists($c) && method_exists($c, $m) ? [$c, $m] : [null, null];
    }

    private function pathParams(string $uri): array
    {
        preg_match_all('/\{(\w+)\??\}/', $uri, $m);

        return array_map(fn ($n) => ['name' => $n, 'type' => str_contains($n, 'code') ? 'string' : (in_array($n, ['id', 'customer_id']) || str_ends_with($n, 'Id') ? 'string|integer' : 'string'), 'required' => true], $m[1]);
    }

    // Dòng mô tả: docblock / các dòng // ngay trên hàm controller.
    private function describe(?string $class, ?string $method): array
    {
        if (! $class) {
            return [null, null];
        }
        $ref = new ReflectionMethod($class, $method);
        $lines = [];
        if ($doc = $ref->getDocComment()) {
            foreach (preg_split('/\R/', $doc) as $line) {
                $line = trim(preg_replace('#^\s*(/\*\*?|\*/|\*)\s?#', '', $line));
                if ($line !== '' && ! str_starts_with($line, '@')) { $lines[] = $line; }
            }
        } elseif ($ref->getFileName() && $ref->getStartLine()) {
            $src = file($ref->getFileName());
            for ($i = $ref->getStartLine() - 2; $i >= 0 && preg_match('#^\s*//#', $src[$i] ?? ''); $i--) {
                array_unshift($lines, trim(preg_replace('#^\s*//\s?#', '', $src[$i])));
            }
        }
        if (! $lines) {
            return [null, null];
        }
        $first = trim(preg_replace('#^(GET|POST|PUT|PATCH|DELETE)([|,/ ]+(GET|POST|PUT|PATCH|DELETE))*\s+/\S+\s*(?:[—–-]\s*)?#u', '', $lines[0]));

        return [$first !== '' ? $first : null, count($lines) > 1 ? implode("\n", array_slice($lines, 1, 12)) : null];
    }

    // ── Trường request (đọc luật validate trong mã nguồn) ───────────────────────────────────

    /** @return array{query: list<array>, body: list<array>, has_file: bool} */
    private function requestSpec(Route $route, string $method, ?string $class, ?string $action): array
    {
        $rules = [];
        $queryNames = [];

        if ($class === \App\Http\Controllers\Api\Admin\GenericCrudController::class) {
            $slug = $route->defaults['resource'] ?? null;
            $def = $slug ? CrudRegistry::get($slug) : null;
            if ($def) {
                if (in_array($action, ['store', 'update'], true)) {
                    $m = new ReflectionMethod($class, 'rules');
                    $m->setAccessible(true);
                    try {
                        $rules = $m->invoke(app($class), $def, $action === 'update');
                    } catch (\Throwable) {
                    }
                } elseif ($action === 'index') {
                    $queryNames = array_merge(['search', 'per_page', 'sort', 'dir', 'page'], array_keys($def['filters'] ?? []));
                }
            }
        } elseif ($class) {
            [$rules, $queryNames] = $this->astSpec($class, $action);
        }

        $query = [];
        $body = [];
        $hasFile = false;
        $isRead = in_array($method, ['GET', 'DELETE'], true);

        foreach ($rules as $field => $ruleSet) {
            $f = $this->describeRule((string) $field, $ruleSet);
            if ($f['type'] === 'file') { $hasFile = true; }
            if (str_ends_with((string) $field, '.*') && count(explode('.', (string) $field)) === 2 && $f['type'] === 'string') {
                // phần tử của mảng — vẫn liệt kê để đầy đủ
            }
            if ($isRead) { $query[] = $f; } else { $body[] = $f; }
        }

        $known = array_map(fn ($f) => $f['name'], array_merge($query, $body));
        foreach (array_unique($queryNames) as $n) {
            $n = (string) $n;
            if (in_array($n, $known, true)) { continue; }
            $entry = ['name' => $n, 'type' => $this->guessType($n), 'required' => false, 'rules' => '', 'description' => $this->guessDescription($n)];
            if ($isRead) { $query[] = $entry; } else { $body[] = $entry; }
        }

        return ['query' => $query, 'body' => $body, 'has_file' => $hasFile];
    }

    private function astSpec(string $class, string $method): array
    {
        $ref = new ReflectionMethod($class, $method);
        $file = $ref->getFileName();
        if (! $file) {
            return [[], []];
        }
        if (! isset($this->astCache[$file])) {
            try {
                $parser = (new ParserFactory())->createForNewestSupportedVersion();
                $this->astCache[$file] = $parser->parse((string) file_get_contents($file)) ?? [];
            } catch (\Throwable) {
                $this->astCache[$file] = [];
            }
        }
        $finder = new NodeFinder();
        $printer = new Printer();
        $node = $finder->findFirst($this->astCache[$file], fn (Node $n) => $n instanceof Node\Stmt\ClassMethod && $n->name->toString() === $method);
        $rules = [];
        $queryNames = [];

        // Form request: kiểu tham số kế thừa FormRequest → lấy rules().
        foreach ($ref->getParameters() as $p) {
            $t = $p->getType();
            if ($t && ! $t->isBuiltin() && is_subclass_of($t->getName(), \Illuminate\Foundation\Http\FormRequest::class)) {
                try {
                    $fr = (new ReflectionClass($t->getName()))->newInstanceWithoutConstructor();
                    foreach ((array) $fr->rules() as $k => $v) { $rules[$k] = $v; }
                } catch (\Throwable) {
                }
            }
        }

        if (! $node) {
            return [$rules, $queryNames];
        }

        // Biến $rules = [...] để dùng cho validate($rules)
        $assigned = [];
        foreach ($finder->findInstanceOf($node, Node\Expr\Assign::class) as $as) {
            if ($as->var instanceof Node\Expr\Variable && is_string($as->var->name) && $as->expr instanceof Node\Expr\Array_) {
                $assigned[$as->var->name] = $as->expr;
            }
        }
        // $rules['x'] = ...
        $dimAssign = [];
        foreach ($finder->findInstanceOf($node, Node\Expr\Assign::class) as $as) {
            if ($as->var instanceof Node\Expr\ArrayDimFetch && $as->var->var instanceof Node\Expr\Variable && $as->var->dim instanceof Node\Scalar\String_) {
                $dimAssign[$as->var->var->name][$as->var->dim->value] = $as->expr;
            }
        }

        $arrays = [];
        foreach ($finder->find($node, fn (Node $n) => $n instanceof Node\Expr\MethodCall || $n instanceof Node\Expr\StaticCall) as $call) {
            $name = $call->name instanceof Node\Identifier ? $call->name->toString() : '';
            $isValidate = in_array($name, ['validate', 'validateWithBag'], true)
                || ($call instanceof Node\Expr\StaticCall && $name === 'make' && $call->class instanceof Node\Name && $call->class->getLast() === 'Validator');
            if (! $isValidate) {
                continue;
            }
            foreach ($call->args as $arg) {
                $v = $arg->value;
                if ($v instanceof Node\Expr\Array_) {
                    $arrays[] = $v;
                    break;
                }
                if ($v instanceof Node\Expr\Variable && is_string($v->name) && isset($assigned[$v->name])) {
                    $arrays[] = $assigned[$v->name];
                    foreach ($dimAssign[$v->name] ?? [] as $k => $expr) {
                        $rules[$k] = $this->printRule($expr, $printer);
                    }
                    break;
                }
            }
        }

        foreach ($arrays as $arr) {
            foreach ($arr->items as $item) {
                if (! $item || ! $item->key instanceof Node\Scalar\String_) { continue; }
                $rules[$item->key->value] = $this->printRule($item->value, $printer);
            }
        }

        // Tham số đọc trực tiếp từ request (query/input/...): chỉ thêm khi chưa có luật.
        foreach ($finder->findInstanceOf($node, Node\Expr\MethodCall::class) as $c) {
            $n = $c->name instanceof Node\Identifier ? $c->name->toString() : '';
            if (! in_array($n, ['query', 'input', 'get', 'integer', 'boolean', 'string', 'filled', 'has', 'float', 'date', 'file', 'hasFile'], true)) { continue; }
            $var = $c->var;
            $isReq = ($var instanceof Node\Expr\Variable && $var->name === 'request') || ($var instanceof Node\Expr\FuncCall && $var->name instanceof Node\Name && $var->name->toString() === 'request');
            if (! $isReq || empty($c->args) || ! $c->args[0]->value instanceof Node\Scalar\String_) { continue; }
            $queryNames[] = $c->args[0]->value->value;
        }

        return [$rules, $queryNames];
    }

    /** Chuẩn hoá một biểu thức luật thành chuỗi "required|string|max:255". */
    private function printRule(Node\Expr $expr, Printer $printer): string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }
        if ($expr instanceof Node\Expr\Array_) {
            $parts = [];
            foreach ($expr->items as $it) {
                if (! $it) { continue; }
                $parts[] = $it->value instanceof Node\Scalar\String_ ? $it->value->value : $this->customRuleLabel($it->value, $printer);
            }

            return implode('|', $parts);
        }

        return $this->customRuleLabel($expr, $printer);
    }

    private function customRuleLabel(Node\Expr $e, Printer $printer): string
    {
        $code = $printer->prettyPrintExpr($e);
        // Biểu thức thuần (không biến): tính thật để ra đúng giá trị hợp lệ, vd 'in:' . implode(',', SkillLevels::keys()).
        if (! str_contains($code, '$') && ! preg_match('/\b(fn|function|new)\b/', $code) && ! str_starts_with($code, 'Rule::') && ! str_contains($code, 'Rule::')) {
            try {
                $v = eval('return ' . $code . ';');
                if (is_string($v)) {
                    return $v;
                }
                if (is_array($v)) {
                    return implode('|', array_map('strval', $v));
                }
            } catch (\Throwable) {
            }
        }
        if (preg_match("/Rule::in\(\s*(\[.*\]|.+)\)/s", $code, $m)) {
            return 'in:' . trim(preg_replace("/['\"\[\]\s]/", '', $m[1]));
        }
        if (preg_match("/Rule::exists\(\s*'([^']+)'/", $code, $m)) {
            return 'exists:' . $m[1];
        }
        if (preg_match("/Rule::unique\(\s*'([^']+)'/", $code, $m)) {
            return 'unique:' . $m[1];
        }
        if (str_contains($code, 'implode')) {
            if (preg_match("/'(in|between):'\s*\.\s*implode\(','\s*,\s*([^)]+)\)/", $code, $m)) {
                return $m[1] . ':(xem danh sách giá trị hợp lệ của hệ thống)';
            }
        }

        return mb_strlen($code) > 60 ? 'custom' : $code;
    }

    /** @param string|array $ruleSet */
    private function describeRule(string $name, $ruleSet): array
    {
        $tokens = is_array($ruleSet) ? array_map(fn ($r) => is_string($r) ? $r : (is_object($r) ? class_basename($r) : 'custom'), $ruleSet) : explode('|', (string) $ruleSet);
        $tokens = array_values(array_filter(array_map('trim', $tokens), fn ($t) => $t !== ''));
        $joined = implode('|', $tokens);

        $type = 'string';
        $explicitString = false;
        foreach ($tokens as $t) {
            $t0 = strtolower(explode(':', $t)[0]);
            if ($t0 === 'string') { $explicitString = true; }
            $type = match (true) {
                in_array($t0, ['integer', 'int'], true) => 'integer',
                in_array($t0, ['numeric', 'decimal'], true) => 'number',
                $t0 === 'boolean' || $t0 === 'bool' => 'boolean',
                $t0 === 'array' => 'array',
                in_array($t0, ['file', 'image', 'mimes', 'mimetypes'], true) => 'file',
                in_array($t0, ['date', 'date_format', 'after', 'after_or_equal', 'before', 'before_or_equal'], true) && $type === 'string' => 'date',
                $t0 === 'email' && $type === 'string' => 'email',
                default => $type,
            };
        }
        if ($type === 'string' && ! $explicitString) {
            $type = $this->guessType($name);
        }

        $required = in_array('required', $tokens, true);
        $enum = null;
        foreach ($tokens as $t) {
            if (str_starts_with($t, 'in:')) { $enum = explode(',', substr($t, 3)); }
        }

        return [
            'name' => $name,
            'type' => $type,
            'required' => $required,
            'rules' => $joined,
            'enum' => $enum,
            'description' => $this->guessDescription($name),
        ];
    }

    private function guessType(string $name): string
    {
        $n = strtolower($name);

        return match (true) {
            str_starts_with($n, 'is_') || str_starts_with($n, 'has_') || str_starts_with($n, 'allow_') => 'boolean',
            str_ends_with($n, '_id') || str_ends_with($n, '_count') || in_array($n, ['page', 'per_page', 'limit', 'quantity', 'year', 'month'], true) => 'integer',
            str_ends_with($n, '_at') || str_ends_with($n, '_date') || in_array($n, ['date', 'from', 'to'], true) => 'date',
            str_contains($n, 'amount') || str_contains($n, 'price') || str_contains($n, 'fee') || str_contains($n, 'total') => 'number',
            str_ends_with($n, 'email') => 'email',
            default => 'string',
        };
    }

    private function guessDescription(string $name): string
    {
        return ''; // Phần mô tả tiếng Việt do bộ sinh Word bổ sung từ từ điển tên trường.
    }

    // ── Gọi thử để lấy response mẫu ─────────────────────────────────────────────────────────

    private function liveSamples(array &$doc, Route $route, string $method, array $spec, bool $isAdmin, bool $needsAuth): void
    {
        $uri = $route->uri();
        $skip = (array) config('audit.skip_live', []);
        $norm = preg_replace('/\{[^}]+\}/', '{}', $uri);
        if (in_array($method . ' ' . $norm, $skip, true) || in_array($method . ' ' . $uri, $skip, true)) {
            $doc['responses'][] = ['status' => 200, 'label' => 'Thành công', 'body' => null, 'note' => 'Không gọi thử (thao tác làm mất phiên / tài khoản đang dùng).'];
            $doc['request_sample'] = $this->bodyFromSpec($spec['body'], false);

            return;
        }

        $actor = $isAdmin ? $this->admin : ($needsAuth ? $this->customer : null);
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        // Toàn bộ chuỗi gọi của 1 thao tác nằm trong 1 transaction rồi rollback: không để lại dữ liệu, các bản ghi
        // cha tạo tạm (nếu thiếu dữ liệu mẫu) cũng biến mất.
        DB::beginTransaction();
        try {
            $ov = $this->overrideFor($method, $uri);
            if (isset($ov['actor'])) {
                $actor = match ($ov['actor']) {
                    'customer' => $this->customer, 'admin' => $this->admin, 'none' => null,
                    'owner' => ($this->fx ?? new DocsFixtures($this->customer, $this->admin))->booking()['owner'] ?? $this->admin,
                    default => $actor,
                };
            }
            $path = $this->resolvePath($uri, $actor, $ov['path'] ?? [], $method === 'DELETE' && ! isset($ov['path']));
            if ($path === null) {
                $doc['responses'][] = ['status' => null, 'label' => 'Chưa có dữ liệu mẫu', 'body' => null, 'note' => 'Không tạo được bản ghi mẫu cho tham số đường dẫn.'];
                $doc['request_sample'] = $isWrite ? $this->bodyFromSpec($spec['body'], false) : null;

                return;
            }

            $body = $isWrite ? $this->bodyFromSpec($spec['body'], false) : [];
            $body = array_merge($body, $ov['body'] ?? []);
            if (! $isWrite || $method === 'DELETE') {
                $body = array_merge($body, $ov['query'] ?? []);
            }
            $files = $this->filesFromSpec($spec['body']);
            $result = $this->attempt($method, '/' . $path, $body, $files, $actor);

            // Nếu 422: bổ sung / sửa kiểu các trường server báo rồi gọi lại (tối đa 3 lần).
            for ($try = 0; $try < 3 && $result['status'] === 422; $try++) {
                $before = json_encode($body);
                $this->hintFromMessage($body, (string) ($result['json']['message'] ?? ''), $spec['body']);
                $this->refineBody($body, (array) ($result['json']['errors'] ?? []), $spec['body']);
                if (json_encode($body) === $before) {
                    break;
                }
                $result = $this->attempt($method, '/' . $path, $body, $files, $actor);
            }

            $doc['request_sample'] = $isWrite ? $this->shrink($body, 0) : null;
            $ok = $result['status'] < 300;
            $doc['responses'][] = ['status' => $result['status'], 'label' => $ok ? 'Thành công' : 'Kết quả khi gọi thử', 'body' => $this->shrink($result['json'] ?? $result['raw'], 0), 'success' => $ok];

            // Mẫu lỗi validate (422): gọi với body rỗng, chỉ khi có trường bắt buộc.
            if ($isWrite && $result['status'] !== 422 && array_filter($spec['body'], fn ($f) => $f['required'])) {
                $empty = $this->attempt($method, '/' . $path, [], [], $actor);
                if ($empty['status'] === 422) {
                    $doc['responses'][] = ['status' => 422, 'label' => 'Dữ liệu không hợp lệ', 'body' => $this->shrink($empty['json'], 0)];
                }
            }

            // Mẫu 404 khi tham số đường dẫn không tồn tại.
            if ($doc['path_params'] && $result['status'] !== 404) {
                $fake = $this->fillFake($uri);
                $nf = $this->attempt($method, '/' . $fake, $isWrite ? $body : [], $files, $actor);
                if ($nf['status'] === 404) {
                    $doc['responses'][] = ['status' => 404, 'label' => 'Không tìm thấy', 'body' => $this->shrink($nf['json'] ?? $nf['raw'], 0)];
                }
            }
        } finally {
            while (DB::transactionLevel() > 0) {
                try { DB::rollBack(); } catch (\Throwable) { break; }
            }
        }
    }

    // ── Dữ liệu mẫu riêng cho từng endpoint (dựng trong transaction của lần gọi thử) ───────

    private ?DocsFixtures $fx = null;

    /**
     * @return array{path?:array<string,string>, body?:array, query?:array, actor?:string}
     */
    private function overrideFor(string $method, string $uri): array
    {
        $fx = $this->fx ??= new DocsFixtures($this->customer, $this->admin);
        $key = $method . ' ' . $uri;
        $b = fn () => $fx->bookingBody();

        // Các endpoint theo mã đơn: loại đơn mẫu cần có.
        $orderKinds = [
            'api/orders/{order_code}' => ['GET' => 'paid', 'POST' => 'paid'],
            'api/orders/{order_code}/cancel' => ['POST' => 'paid'],
            'api/orders/{order_code}/cancel-preview' => ['GET' => 'paid'],
            'api/orders/{order_code}/check-in-code' => ['GET' => 'paid'],
            'api/orders/{order_code}/extra' => ['POST' => 'paid'],
            'api/orders/{order_code}/extra-charge-qr' => ['POST' => 'extra_due'],
            'api/orders/{order_code}/invoice' => ['GET' => 'paid_invoice', 'POST' => 'paid'],
            'api/orders/{order_code}/payment-proof' => ['POST' => 'pending_new'],
            'api/orders/{order_code}/payment-qr' => ['GET' => 'pending_new'],
            'api/orders/{order_code}/remaining-payment' => ['POST' => 'deposit'],
            'api/orders/{order_code}/reschedule' => ['POST' => 'paid'],
            'api/orders/{order_code}/retry-payment' => ['POST' => 'failed'],
            'api/orders/{order}/services' => ['POST' => 'paid'],
        ];
        $guestKinds = [
            'api/guest/orders/{order_code}' => ['GET' => 'paid', 'POST' => 'paid'],
            'api/guest/orders/{order_code}/extra' => ['POST' => 'paid'],
            'api/guest/orders/{order_code}/extra-charge-qr' => ['POST' => 'extra_due'],
            'api/guest/orders/{order_code}/payment-proof' => ['POST' => 'pending_new'],
            'api/guest/orders/{order_code}/payment-status' => ['GET' => 'pending_new'],
            'api/guest/orders/{order_code}/remaining-payment' => ['POST' => 'deposit'],
        ];
        $adminKinds = [
            'api/admin/orders/{order_code}' => ['GET' => 'paid', 'PUT' => 'paid', 'PATCH' => 'paid', 'POST' => 'paid', 'DELETE' => 'cancelled'],
            'api/admin/orders/{order_code}/approve-bank-transfer' => ['POST' => 'pending'],
            'api/admin/orders/{order_code}/reject-bank-transfer' => ['POST' => 'pending'],
            'api/admin/orders/{order_code}/cancel' => ['POST' => 'paid'],
            'api/admin/orders/{order_code}/check-in' => ['POST' => 'checkin_ready'],
            'api/admin/orders/{order_code}/check-out' => ['POST' => 'checked_in'],
            'api/admin/orders/{order_code}/collect-extra-cash' => ['POST' => 'extra_due'],
            'api/admin/orders/{order_code}/extra' => ['POST' => 'paid'],
            'api/admin/orders/{order_code}/extra-charge-qr' => ['POST' => 'extra_due'],
            'api/admin/orders/{order_code}/preview' => ['POST' => 'paid'],
            'api/admin/orders/{order_code}/remaining-payment' => ['POST' => 'deposit'],
            'api/admin/orders/{order_code}/reschedule' => ['POST' => 'paid'],
            'api/admin/orders/{order_code}/retry-payment' => ['POST' => 'failed'],
            'api/admin/orders/{order_code}/weather-refund' => ['POST' => 'paid'],
            'api/admin/orders/{order_code}/weather-reschedule' => ['POST' => 'paid'],
        ];

        $ov = [];
        $rescheduleAt = now()->addDays(9)->setTime(19, 0)->format('Y-m-d H:i');

        foreach ([['client', $orderKinds, 'customer'], ['guest', $guestKinds, 'none'], ['admin', $adminKinds, 'admin']] as [$scope, $table, $actor]) {
            $kind = $table[$uri][$method] ?? null;
            if ($kind === null) {
                continue;
            }
            $guest = $scope === 'guest';
            $order = $fx->order(str_replace('_invoice', '', $kind), $guest);
            if ($kind === 'paid_invoice') {
                $fx->invoiceRequest($order);
            }
            preg_match('/\{(\w+)\}/', $uri, $m);
            $ov = ['path' => [$m[1] => $order->order_code], 'actor' => $actor, 'body' => [], 'query' => []];
            if ($guest) {
                $ov['query'] = ['phone' => DocsFixtures::BUYER_PHONE];
                $ov['body'] = ['buyer_phone' => DocsFixtures::BUYER_PHONE];
            }
            break;
        }

        $extra = match ($key) {
            'POST api/orders' => ['actor' => 'customer', 'body' => $b()],
            'POST api/guest/orders' => ['actor' => 'none', 'body' => $b() + ['buyer_name' => 'Khách vãng lai', 'buyer_phone' => DocsFixtures::BUYER_PHONE]],
            'POST api/admin/orders' => ['actor' => 'admin', 'body' => $b() + ['buyer_name' => 'Khách đặt hộ', 'buyer_phone' => DocsFixtures::BUYER_PHONE]],
            'POST api/admin/orders/preview' => ['actor' => 'admin', 'body' => $b()],
            'POST api/orders/{order_code}/reschedule', 'POST api/admin/orders/{order_code}/reschedule' => ['body' => ['new_start' => $rescheduleAt]],
            'POST api/orders/{order}/services' => ['body' => ['services' => [['service_id' => (int) $fx->firstId('room_services'), 'quantity' => 1]]]],
            'POST api/admin/orders/{order_code}/cancel' => ['body' => ['reason' => 'Khách báo bận', 'refund_amount' => 0]],
            'POST api/admin/orders/{order_code}/weather-reschedule' => ['body' => ['new_checkin_date' => now()->addDays(12)->toDateString(), 'new_checkout_date' => now()->addDays(13)->toDateString()]],
            'POST api/admin/orders/{order_code}/preview' => ['body' => $b()],
            'PUT api/admin/orders/{order_code}', 'PATCH api/admin/orders/{order_code}', 'POST api/admin/orders/{order_code}' => ['body' => ['note_for_admin' => 'Khách đến sớm 10 phút']],
            'POST api/orders/{order_code}' => ['body' => ['note_for_admin' => 'Cho xin thêm 1 chai nước']],
            'POST api/orders/{order_code}/invoice' => ['body' => ['company_name' => 'Công ty TNHH Mẫu', 'tax_code' => '0312345678', 'address' => '1 Nguyễn Huệ, Quận 1, TP.HCM', 'email' => 'ketoan@example.com']],
            default => [],
        };
        foreach ($extra as $k => $v) {
            $ov[$k] = is_array($v) && isset($ov[$k]) && is_array($ov[$k]) ? array_merge($ov[$k], $v) : $v;
        }

        // Các nhóm khác cần ngữ cảnh riêng.
        $owner = $fx->booking()['owner'] ?? null;
        if (str_starts_with($uri, 'api/admin/payment-setup') || $uri === 'api/admin/subscription' || str_starts_with($uri, 'api/admin/subscription/')) {
            $ov['actor'] = 'owner';
        }
        if (in_array($method, ['PUT', 'PATCH', 'POST'], true) && $uri === 'api/admin/payment-setup') {
            $ov['body'] = ['payment_method_preference' => 'bank_transfer_manual', 'bank_bin' => '970436', 'bank_name' => 'Vietcombank',
                'bank_account_number' => '0123456789', 'bank_account_holder' => 'CONG TY MAU'];
        }
        // Mọi route theo sân: dùng sân mẫu đã sẵn sàng nhận đặt.
        $bk = $fx->booking();
        if ($bk && preg_match('#^api/(admin/)?(rooms|products)/\{id\}#', $uri) && ! isset($ov['path'])) {
            $ov['path'] = ['id' => (string) $bk['room']->id];
            if (str_ends_with($uri, '/hold')) {
                $ov['body'] = $method === 'DELETE'
                    ? ['session_id' => 'docs-session', 'timeslot_id' => (int) $bk['slots'][0]->timeslot_id, 'date' => now()->addDays(3)->format('d-m-Y')]
                    : ['checkin' => now()->addDays(3)->toDateString(), 'checkout' => now()->addDays(4)->toDateString(), 'session_id' => 'docs-session'];
            }
            if (str_ends_with($uri, '/block')) {
                $ov['body'] = ['room_time_slot_ids' => [(int) $bk['slots'][0]->id], 'date' => now()->addDays(5)->toDateString()];
            }
            if (str_ends_with($uri, '/pricing') || str_ends_with($uri, '/booking-settings')) {
                $ov['body'] = ['price' => 350000];
            }
        }
        if ($bk && $key === 'POST api/rooms/{id}/ratings') {
            $done = $fx->order('completed');
            $ov = ['actor' => 'customer', 'path' => ['id' => (string) $bk['room']->id], 'body' => ['star' => 5, 'comment' => 'Sân đẹp, nhân viên nhiệt tình']];
        }
        if ($bk && $key === 'DELETE api/rooms/{id}/ratings') {
            DB::table('room_ratings')->insert(['customer_id' => $this->customer->id, 'room_id' => $bk['room']->id, 'star' => 4, 'comment' => 'Tốt', 'created_at' => now(), 'updated_at' => now()]);
            $ov = ['actor' => 'customer', 'path' => ['id' => (string) $bk['room']->id]];
        }
        if ($key === 'POST api/wallet/redeem') {
            DB::table('customers')->where('id', $this->customer->id)->update(['loyalty_points' => 500]);
            $ov = ['actor' => 'customer', 'body' => ['points' => 100]];
        }
        if (preg_match('#^api/(notifications|guest/notifications)/\{id\}/read$#', $uri) && ! str_starts_with($uri, 'api/guest')) {
            app(\App\Services\NotificationFcmService::class)->sendToCustomer($this->customer, 'Đơn của bạn đã được xác nhận', 'Mã đơn DOC123456', 'booking');
            $ov['path'] = ['id' => (string) DB::table('notification_fcm')->max('id')];
        }
        if (preg_match('#^api/teams/\{code\}#', $uri) && $method !== 'GET' || preg_match('#^api/teams/\{code\}/(messages|share)$#', $uri)) {
            $ov['actor'] = 'customer';
            $ov['path'] = ['code' => (string) $this->createParent('api/teams', $this->customer, 'code')];
            if (str_ends_with($uri, '/messages') && $method === 'POST') {
                $ov['body'] = ['message' => 'Tối nay 19h đá nhé cả đội'];
            }
            if (str_ends_with($uri, '/invite')) {
                $other = DB::table('customers')->where('id', '!=', $this->customer->id)->value('id');
                $ov['body'] = ['customer_id' => $other, 'phone' => DB::table('customers')->where('id', $other)->value('phone')];
            }
        }
        if (preg_match('#^api/booking-funds/\{code\}#', $uri)) {
            $ov['actor'] = 'customer';
            $ov['path'] = ['code' => (string) $this->createParent('api/booking-funds', $this->customer, 'invite_code')];
        }
        if ($uri === 'api/admin/users' && $method === 'POST') {
            $ov['body'] = ['roles' => ['employee']];
        }
        if ($uri === 'api/admin/roles/{id}' && $method === 'PUT' || $uri === 'api/admin/roles/{id}' && $method === 'PATCH' || $uri === 'api/admin/roles/{id}' && $method === 'POST') {
            $rid = DB::table('roles')->insertGetId(['name' => 'Vai trò mẫu ' . random_int(100, 999), 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $ov['path'] = ['id' => (string) $rid];
            $ov['body'] = ['name' => 'Nhân viên quầy', 'permissions' => []];
        }
        if (preg_match('#^api/admin/partner-signups/\{partner\}#', $uri)) {
            $pid = $this->insertGeneric('partners');
            $ov['path'] = ['partner' => (string) $pid];
        }
        if (preg_match('#^api/admin/package-orders/\{order_code\}#', $uri)) {
            $ov['path'] = ['order_code' => $fx->order('package')->order_code];
        }
        if ($key === 'POST api/package-plans/{id}/purchase') {
            $ov['actor'] = 'customer';
            $ov['path'] = ['id' => (string) $this->insertGeneric('package_plans')];
            $ov['body'] = ['payment_method' => 'bank_transfer'];
        }

        // Đơn thời tiết
        if (in_array($key, ['POST api/admin/orders/{order_code}/weather-refund', 'POST api/admin/orders/{order_code}/weather-reschedule'], true)) {
            $ov['path'] = ['order_code' => $fx->order('weather')->order_code];
            $ov['actor'] = 'admin';
        }
        if (preg_match('#^api/admin/settings/\{group\}$#', $uri)) {
            $ov['path'] = ['group' => 'general'];
        }
        if (preg_match('#^api/admin/roles/\{id\}$#', $uri) && $method !== 'DELETE') {
            $ov['path'] = ['id' => (string) DB::table('roles')->where('name', 'partner')->value('id')];
        }
        if ($uri === 'api/admin/users/{id}' && $method === 'DELETE') {
            $ov['path'] = ['id' => (string) User::where('id', '!=', $this->admin->id)->value('id')];
        }
        if (str_starts_with($uri, 'api/player-skills/{sport}') || str_starts_with($uri, 'api/admin/player-skills/{id}')) {
            $svc = app(\App\Services\PlayerSkillService::class);
            $row = $svc->set($this->customer, 'general', 'intermediate', 'self');
            $ov['path'] = str_contains($uri, '{sport}') ? ['sport' => 'general'] : ['id' => (string) $row->id];
            $ov['body'] = ['level' => 'advanced', 'note' => 'Chơi đều tay 2 năm'];
            if (str_starts_with($uri, 'api/admin/player-skills')) {
                $ov['actor'] = 'admin';
                $ov['body'] += ['customer_id' => $this->customer->id, 'sport_type' => 'general'];
            }
        }
        if ($key === 'POST api/admin/player-skills') {
            $ov = ['actor' => 'admin', 'body' => ['customer_id' => $this->customer->id, 'sport_type' => 'general', 'level' => 'advanced', 'note' => 'Đã kiểm tra tại sân']];
        }
        if ($uri === 'api/admin/invoice-requests/{id}' || str_starts_with($uri, 'api/admin/invoice-requests/{id}/')) {
            $ov['path'] = ['id' => (string) $fx->invoiceRequest($fx->order('paid'))];
            $ov['body'] = ($key === 'POST api/admin/invoice-requests/{id}/issue') ? ['invoice_number' => 'HD000123'] : (($key === 'POST api/admin/invoice-requests/{id}/reject') ? ['reason' => 'Thông tin mã số thuế chưa đúng'] : []);
        }
        if ($key === 'POST api/admin/loyalty/adjust') {
            $ov['body'] = ['customer_id' => $this->customer->id, 'points' => 50, 'reason' => 'Tặng điểm sự kiện'];
        }
        if ($key === 'POST api/graphql') {
            $ov['body'] = ['query' => '{ __typename }'];
        }

        // Đơn đã xoá/huỷ: DELETE /api/admin/orders (xoá hàng loạt theo mã).
        if ($key === 'DELETE api/admin/orders') {
            $ov = ['actor' => 'admin', 'body' => ['order_codes' => [$fx->order('cancelled')->order_code]]];
        }

        return $ov;
    }

    /** Chèn 1 dòng tối thiểu vào bảng (mọi cột bắt buộc điền giá trị mẫu theo kiểu); trả về khoá chính. */
    private function insertGeneric(string $table): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }
        $cols = Schema::getColumns($table);
        $row = [];
        $pk = 'id';
        $partnerId = DB::table('partners')->orderBy('created_at')->value('id');

        foreach ($cols as $c) {
            $name = $c['name'];
            $type = strtolower((string) $c['type']);
            if ($c['auto_increment'] ?? false) {
                $pk = $name;
                continue;
            }
            if ($name === 'partner_id' && $partnerId) {
                $row[$name] = $partnerId;
                continue;
            }
            if (in_array($name, ['created_at', 'updated_at'], true)) {
                $row[$name] = now();
                continue;
            }
            if ($c['nullable'] || $c['default'] !== null) {
                continue;
            }
            $row[$name] = $this->genericColumnValue($name, $type);
        }

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            if (isset($row[$pk]) || ! collect($cols)->contains(fn ($c) => ($c['auto_increment'] ?? false))) {
                DB::table($table)->insert($row);
                $id = $row[$pk] ?? null;
            } else {
                $id = DB::table($table)->insertGetId($row);
            }
        } catch (\Throwable) {
            $id = null;
        } finally {
            try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable) {}
        }

        if ($id !== null) {
            $this->afterGenericInsert($table, $pk, $id);
        }

        return $id !== null ? (string) $id : null;
    }

    /** Gắn bản ghi mẫu với khách / sân đang dùng để các luật sở hữu và ràng buộc nghiệp vụ được thoả. */
    private function afterGenericInsert(string $table, string $pk, $id): void
    {
        $set = [];
        if (Schema::hasColumn($table, 'customer_id')) {
            $set['customer_id'] = $this->customer->id;
        }
        $room = ($this->fx ??= new DocsFixtures($this->customer, $this->admin))->booking()['room'] ?? null;
        if ($room) {
            if ($table === 'package_plans') {
                $set += ['product_id' => $room->id, 'is_active' => 1, 'sessions_count' => 4, 'price' => 400000, 'validity_days' => 60];
            }
            if ($table === 'customer_packages') {
                $set += ['product_id' => $room->id, 'sessions_total' => 4, 'sessions_remaining' => 4, 'price_paid' => 400000, 'purchased_at' => now(), 'expires_at' => now()->addDays(60)];
            }
            if ($table === 'partners' ) {
                $set += ['verification_status' => 'pending'];
            }
        }
        if ($set) {
            $set = array_filter($set, fn ($v, $k) => Schema::hasColumn($table, $k), ARRAY_FILTER_USE_BOTH);
            DB::table($table)->where($pk, $id)->update($set);
        }
    }

    private function genericColumnValue(string $name, string $type)
    {
        if (preg_match('/^enum\((.*)\)$/', $type, $m)) {
            preg_match("/'([^']*)'/", $m[1], $first);

            return $first[1] ?? '';
        }
        if (str_starts_with($type, 'tinyint(1)')) {
            return 0;
        }
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)) {
            return 1;
        }
        if (preg_match('/^(decimal|float|double)/', $type)) {
            return 0;
        }
        if ($type === 'date') {
            return now()->toDateString();
        }
        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
            return now();
        }
        if (str_starts_with($type, 'time')) {
            return '08:00:00';
        }
        if ($type === 'json') {
            return '[]';
        }
        $len = preg_match('/\((\d+)\)/', $type, $lm) ? (int) $lm[1] : 255;
        if ($len === 36) {
            return (string) \Illuminate\Support\Str::uuid();
        }
        $v = match (true) {
            str_contains($name, 'email') => 'sample' . random_int(100, 999) . '@example.com',
            str_contains($name, 'phone') => '0912' . random_int(100000, 999999),
            str_contains($name, 'slug') => 'mau-' . random_int(10000, 99999),
            str_contains($name, 'code') => strtoupper(\Illuminate\Support\Str::random(8)),
            str_contains($name, 'url') || str_contains($name, 'link') => 'https://example.com',
            default => 'Mẫu ' . random_int(100, 999),
        };

        return mb_substr($v, 0, max(1, $len));
    }

    /** Điền giá trị không tồn tại vào mọi tham số đường dẫn (để lấy mẫu 404). */
    private function fillFake(string $uri): string
    {
        return trim((string) preg_replace_callback('/\{(\w+)\??\}/', function ($m) {
            $n = $m[1];

            return str_contains($n, 'code') ? 'ZZZZZZZZ' : (str_contains($n, 'customer') ? '00000000-0000-4000-8000-000000000000' : '999999999');
        }, $uri), '/');
    }

    // Điền tham số đường dẫn bằng bản ghi thật; thiếu thì thử tạo bản ghi cha bằng POST vào tập hợp tương ứng (trong transaction).
    private function resolvePath(string $uri, $actor, array $given = [], bool $freshForDelete = false): ?string
    {
        $segments = explode('/', $uri);
        foreach ($segments as $idx => $seg) {
            if (! preg_match('/^\{(\w+)\??\}$/', $seg, $m)) {
                continue;
            }
            $name = $m[1];
            $prev = $segments[$idx - 1] ?? '';
            $value = $given[$name] ?? $this->realSample($name, $prev, $freshForDelete);
            if ($value === null && $name !== 'customer_id') {
                $collection = trim(implode('/', array_slice($segments, 0, $idx)), '/');
                $value = $this->createParent($collection, $actor);
            }
            if ($value === null) {
                if (str_ends_with($seg, '?}')) { $segments[$idx] = ''; continue; }

                return null;
            }
            $segments[$idx] = (string) $value;
        }

        return trim(implode('/', array_filter($segments, fn ($s) => $s !== '')), '/');
    }

    private function createParent(string $collectionUri, $actor, ?string $prefer = null): ?string
    {
        if (str_contains($collectionUri, '{')) {
            return null;
        }
        $route = null;
        foreach (app('router')->getRoutes()->getRoutes() as $r) {
            if ($r->uri() === $collectionUri && in_array('POST', $r->methods(), true)) {
                $route = $r;
                break;
            }
        }
        if (! $route) {
            return null;
        }
        [$class, $action] = $this->controllerOf($route);
        $spec = $this->requestSpec($route, 'POST', $class, $action);
        $body = $this->bodyFromSpec($spec['body'], false);
        $files = $this->filesFromSpec($spec['body']);
        $res = $this->attempt('POST', '/' . $collectionUri, $body, $files, $actor);
        for ($i = 0; $i < 3 && $res['status'] === 422; $i++) {
            $before = json_encode($body);
            $this->hintFromMessage($body, (string) ($res['json']['message'] ?? ''), $spec['body']);
            $this->refineBody($body, (array) ($res['json']['errors'] ?? []), $spec['body']);
            if (json_encode($body) === $before) { break; }
            $res = $this->attempt('POST', '/' . $collectionUri, $body, $files, $actor);
        }
        if ($res['status'] >= 300 || ! is_array($res['json'])) {
            return null;
        }
        $j = $res['json'];
        if ($prefer !== null) {
            $v = $j['data'][$prefer] ?? $j[$prefer] ?? null;
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        }
        foreach ([$j['data']['id'] ?? null, $j['id'] ?? null, $j['data']['order_code'] ?? null, $j['order']['order_code'] ?? null, $j['data']['code'] ?? null] as $cand) {
            if ($cand !== null && $cand !== '') {
                return (string) $cand;
            }
        }
        foreach ($j as $v) {
            if (is_array($v) && isset($v['id'])) {
                return (string) $v['id'];
            }
        }

        return null;
    }

    /** Sửa body theo thông báo lỗi validate của server: bổ sung trường thiếu / đổi sang đúng kiểu. */
    private function hintFromMessage(array &$body, ?string $message, array $fields): void
    {
        if (! $message) {
            return;
        }
        $byName = [];
        foreach ($fields as $f) { $byName[$f['name']] = $f; }
        if (preg_match_all('/\b([a-z]+(?:_[a-z]+)+)\b/', $message, $m)) {
            foreach (array_unique($m[1]) as $tok) {
                if (! Arr::has($body, $tok) && (str_ends_with($tok, '_id') || str_ends_with($tok, '_ids') || $tok === 'guest_count' || $tok === 'room_id')) {
                    $v = $tok === 'partner_id' ? DB::table('partners')->orderBy('created_at')->value('id') : $this->fakeValueByName($tok, $byName[$tok] ?? []);
                    Arr::set($body, $tok, $v);
                }
            }
        }
        if (preg_match('/Thiếu (?:hoặc sai )?tham số (\w+)/u', $message, $m) && ! Arr::has($body, $m[1])) {
            Arr::set($body, $m[1], $this->fakeValueByName($m[1], $byName[$m[1]] ?? []));
        }
    }

    private function refineBody(array &$body, array $errors, array $fields): void
    {
        $byName = [];
        foreach ($fields as $f) { $byName[$f['name']] = $f; }
        foreach ($errors as $key => $messages) {
            $k = (string) $key;
            $msg = mb_strtolower((string) (is_array($messages) ? ($messages[0] ?? '') : $messages));
            $f = $byName[$k] ?? $byName[preg_replace('/\.\d+(\.|$)/', '.*$1', $k)] ?? [];
            $value = match (true) {
                (bool) preg_match('/định dạng ([a-z\-:\/ ,]+)\./iu', $msg, $fm) && preg_match('/[yd]/i', $fm[1]) => now()->addDays(3)->format(trim(explode(',', $fm[1])[0])),
                str_contains($msg, 'số nguyên') => 1,
                str_contains($msg, 'là một số') || str_contains($msg, 'phải là số') => 100000,
                str_contains($msg, 'true') || str_contains($msg, 'boolean') || str_contains($msg, 'đúng hoặc sai') => true,
                str_contains($msg, 'ngày') => now()->addDays(3)->format('Y-m-d'),
                str_contains($msg, 'email') => 'sample@example.com',
                str_contains($msg, 'mảng') => [],
                str_contains($msg, 'không hợp lệ') && ! empty($f['enum']) => $f['enum'][0],
                str_contains($msg, 'không hợp lệ') => null,
                default => $this->fakeValueByName($k, $f),
            };
            if ($value === null) {
                continue;
            }
            Arr::set($body, $k, $value);
        }
    }

    /** @return array{status:int,json:mixed,raw:?string} */
    private function attempt(string $method, string $uri, array $body, array $files, $actor): array
    {
        app('auth')->forgetGuards();
        if ($actor) {
            Sanctum::actingAs($actor, ['*']);
        }
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_HOST' => '365sport-cms.test', 'HTTPS' => 'on',
            'REMOTE_ADDR' => '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254)];

        if ($method === 'GET') {
            $request = Request::create($uri, 'GET', $body, [], [], $server);
        } elseif ($files) {
            $request = Request::create($uri, $method, $body, [], $files, $server);
        } else {
            $request = Request::create($uri, $method, [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], json_encode($body));
        }

        // Lỗi giữa chừng của 1 lần gọi không được làm hỏng transaction ngoài.
        $level = DB::transactionLevel();
        try {
            $response = $this->kernel->handle($request);
            $status = $response->getStatusCode();
            $raw = $response->getContent();
            $json = json_decode((string) $raw, true);
            if ($status >= 500) {
                if ($this->option('debug') && ($ex = $response->exception ?? null)) {
                    $this->warn("  5xx {$method} {$uri}: " . mb_substr($ex->getMessage(), 0, 200) . ' @' . basename($ex->getFile()) . ':' . $ex->getLine());
                }
                $json = ['message' => 'Server Error'];
            }
        } catch (\Throwable $e) {
            $status = 500;
            $raw = null;
            $json = ['message' => 'Server Error'];
        }
        while (DB::transactionLevel() > $level) {
            try { DB::rollBack(); } catch (\Throwable) { break; }
        }

        if (++$this->requests % 40 === 0) {
            try { Cache::flush(); } catch (\Throwable) {}
            gc_collect_cycles();
        }

        return ['status' => $status, 'json' => $json, 'raw' => is_string($raw) && $json === null ? mb_substr($raw, 0, 300) : null];
    }

    private function bypassThrottle(): void
    {
        $passthrough = new class {
            public function handle($request, \Closure $next, ...$args)
            {
                return $next($request);
            }
        };
        foreach ([\Illuminate\Routing\Middleware\ThrottleRequests::class, \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class] as $c) {
            app()->bind($c, fn () => $passthrough);
        }
    }

    // ── Sinh dữ liệu mẫu ────────────────────────────────────────────────────────────────────

    /** @param list<array> $fields */
    private function bodyFromSpec(array $fields, bool $includeOptional): array
    {
        $body = [];
        foreach ($fields as $f) {
            $name = $f['name'];
            if (str_contains($name, '*') && ! preg_match('/\.\*\./', $name) && ! str_ends_with($name, '.*')) {
                continue;
            }
            if (! $f['required']) {
                continue; // chỉ gửi trường bắt buộc: tối đa khả năng thành công, tránh giá trị tuỳ chọn ràng buộc phức tạp
            }
            $value = $this->fakeValue($f);
            if ($value === null) {
                continue;
            }
            Arr::set($body, preg_replace('/\.\*(\.|$)/', '.0$1', $name), $value);
        }

        return $body;
    }

    private function filesFromSpec(array $fields): array
    {
        $files = [];
        foreach ($fields as $f) {
            if ($f['type'] === 'file' && $f['required']) {
                Arr::set($files, preg_replace('/\.\*(\.|$)/', '.0$1', $f['name']), UploadedFile::fake()->image('sample.jpg', 600, 400));
            }
        }

        return $files;
    }

    private function fakeValue(array $f)
    {
        $rules = (string) ($f['rules'] ?? '');
        $enum = $f['enum'] ?? null;
        if ($enum) {
            return $enum[0];
        }
        if (preg_match('/exists:([a-z_0-9]+)(?:,([a-z_0-9]+))?/i', $rules, $m)) {
            $table = $m[1]; $col = $m[2] ?? 'id';
            if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                $v = DB::table($table)->orderBy($col)->value($col);
                if ($v !== null) {
                    return in_array($f['type'], ['integer', 'number'], true) ? (is_numeric($v) ? $v + 0 : $v) : $v;
                }
            }
        }

        return $this->fakeValueByName($f['name'], $f);
    }

    private function fakeValueByName(string $name, array $f)
    {
        $type = $f['type'] ?? $this->guessType($name);
        $rules = (string) ($f['rules'] ?? '');
        $leaf = strtolower(preg_replace('/^.*\./', '', $name));
        $min = preg_match('/min:(\d+)/', $rules, $mm) ? (int) $mm[1] : null;

        // date_format:d-m-Y ... → sinh đúng định dạng yêu cầu
        if (preg_match('/date_format:([^|]+)/', $rules, $df)) {
            $fmt = trim(explode(',', $df[1])[0]);
            if ($fmt === 'H:i' || $fmt === 'H:i:s') {
                return '18:00';
            }

            return now()->addDays(3)->format($fmt);
        }
        if (in_array($leaf, ['lat', 'latitude', 'from_lat', 'to_lat'], true)) {
            return 10.7769;
        }
        if (in_array($leaf, ['lng', 'lon', 'longitude', 'from_lng', 'to_lng'], true)) {
            return 106.7009;
        }
        if ($leaf === 'sku') {
            return (string) (\Illuminate\Support\Facades\Schema::hasTable('warehouse_items') ? \Illuminate\Support\Facades\DB::table('warehouse_items')->value('sku') : 'SKU001');
        }
        if (str_ends_with($leaf, 'ids') && ! str_contains($name, '*')) {
            $tbl = match ($leaf) {
                'customer_ids' => 'customers', 'coupon_ids' => 'coupons', 'room_ids', 'product_ids' => 'products',
                'room_time_slot_ids' => 'room_time_slots', 'order_ids' => 'orders', default => null,
            };
            $v = $tbl && \Illuminate\Support\Facades\Schema::hasTable($tbl) ? \Illuminate\Support\Facades\DB::table($tbl)->orderBy('id')->value('id') : null;
            if ($v === null && $tbl) {
                $v = $this->insertGeneric($tbl);
            }

            return $v !== null ? [$v] : [];
        }

        return match (true) {
            str_contains($leaf, 'password') => 'Password@123',
            str_contains($leaf, 'phone') || $leaf === 'sdt' || $leaf === 'mobile' => '0912345678',
            str_contains($leaf, 'email') => 'sample@example.com',
            $leaf === 'otp' || $leaf === 'code' && str_contains($rules, 'digits') => '123456',
            $type === 'boolean' => true,
            $type === 'integer' => max(1, (int) ($min ?? 1)),
            $type === 'number' => max(1, (int) ($min ?? 100000)),
            str_contains($rules, 'date_format:H:i') || str_contains($leaf, 'time') && ! str_contains($leaf, 'date') && ! str_ends_with($leaf, '_at') => '18:00',
            $type === 'date' || str_contains($leaf, 'date') => now()->addDays(3)->format('Y-m-d'),
            $type === 'array' => [],
            str_contains($leaf, 'url') || str_contains($leaf, 'link') => 'https://example.com',
            str_contains($leaf, 'name') || str_contains($leaf, 'title') => 'Mẫu',
            str_contains($leaf, 'note') || str_contains($leaf, 'reason') || str_contains($leaf, 'message') || str_contains($leaf, 'content') || str_contains($leaf, 'comment') => 'Nội dung mẫu',
            $leaf === 'token' => str_repeat('a', 40),
            default => $min ? str_repeat('a', $min) : 'mẫu',
        };
    }

    private function realSample(string $name, string $prev, bool $fresh = false): ?string
    {
        static $cache = [];
        $k = $name . '|' . $prev;
        if (! $fresh && array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        $first = fn (string $t, string $col = 'id') => Schema::hasTable($t) && Schema::hasColumn($t, $col) ? DB::table($t)->orderBy($col)->value($col) : null;
        $val = null;

        if ($name === 'order_code' || $name === 'order' || ($name === 'code' && in_array($prev, ['orders', 'package-orders', 'guest-orders'], true))) {
            $val = $first('orders', 'order_code');
        } elseif ($name === 'slug') {
            $val = $first('categories', 'slug');
        } elseif ($name === 'code' && $prev === 'teams') {
            $val = $first('teams', 'code');
        } elseif ($name === 'teamCode' || $name === 'team_code') {
            $val = $first('teams', 'code');
        } elseif ($name === 'code' && $prev === 'booking-funds') {
            $val = $first('booking_funds', 'invite_code');
        } elseif ($name === 'code') {
            $val = $first('wards', 'code');
        } elseif ($name === 'sport') {
            $val = 'general';
        } elseif ($name === 'customer_id') {
            $val = $this->customer->id;
        } elseif ($name === 'id' || str_ends_with($name, 'Id') || str_ends_with($name, '_id') || in_array($name, ['branch', 'coach', 'regId', 'group', 'kind'], true)) {
            $slugDef = CrudRegistry::get($prev);
            $table = match (true) {
                $slugDef !== null => (new $slugDef['model'])->getTable(),
                in_array($prev, ['rooms', 'products'], true) => 'products',
                in_array($prev, ['branches', 'categories'], true) => 'categories',
                $prev === 'orders' || $prev === 'package-orders' => 'orders',
                $prev === 'customers' => 'customers',
                $prev === 'room-types' => 'room_types',
                $prev === 'price-boards' => 'price_boards',
                $prev === 'package-plans' => 'package_plans',
                $prev === 'customer-packages' => 'customer_packages',
                $prev === 'corporate-accounts' => 'corporate_accounts',
                in_array($prev, ['warehouse', 'items'], true) => 'warehouse_items',
                $prev === 'units' => 'warehouse_units',
                $prev === 'stock-ins' => 'warehouse_stock_ins',
                $prev === 'stock-outs' => 'warehouse_stock_outs',
                $prev === 'stock-checks' => 'warehouse_stock_checks',
                $prev === 'item-rentals' => 'warehouse_item_rentals',
                $prev === 'cameras' => 'cameras',
                $prev === 'camera-alerts' => 'camera_alerts',
                $prev === 'chat' => 'chat_conversations',
                $prev === 'notifications' => 'notification_fcm',
                $prev === 'fcm-notifications' => 'notification_fcm',
                $prev === 'ask-user' => 'ask_users',
                $prev === 'waitlist' => 'slot_waitlists',
                $prev === 'payments' => 'subscription_payments',
                $prev === 'ratings' => 'room_ratings',
                $prev === 'player-skills' => 'player_skill_levels',
                $prev === 'invoice-requests' => 'invoice_requests',
                $prev === 'partner-settlements' => 'partner_settlements',
                $prev === 'tournaments' => 'tournaments',
                $prev === 'partners' => 'partners',
                $prev === 'slips' => 'payroll_slips',
                $prev === 'payroll' => 'payroll_periods',
                $prev === 'attendance' => 'employee_attendances',
                $prev === 'employees' => 'employees',
                $prev === 'users' => 'users',
                $prev === 'roles' => 'roles',
                $prev === 'funds' || $prev === 'booking-funds' => 'booking_funds',
                $prev === 'my-packages' => 'customer_packages',
                $prev === 'recurring-bookings' => 'recurring_bookings',
                $prev === 'time-slots' => 'room_time_slots',
                default => str_replace('-', '_', $prev),
            };
            $v = (! $fresh && Schema::hasTable($table)) ? DB::table($table)->orderBy('id')->value('id') : null;
            if (($v === null || $v === '') && Schema::hasTable($table)) {
                // Bảng trống (hoặc cần bản ghi chưa có ràng buộc để thử xoá): dựng một bản ghi mẫu trong transaction của lần gọi.
                $created = $this->insertGeneric($table);
                if ($created !== null) {
                    return $created; // không lưu cache: bản ghi sẽ bị rollback
                }
            }
            $val = ($v === null || $v === '') ? null : $v;
        }

        return $cache[$k] = ($val !== null ? (string) $val : null);
    }

    // ── Rút gọn response mẫu ────────────────────────────────────────────────────────────────

    private function shrink($v, int $depth)
    {
        if ($depth > 7) {
            return '…';
        }
        if (is_array($v)) {
            if (array_is_list($v)) {
                $keep = array_slice($v, 0, is_array($v[0] ?? null) ? 1 : 3);

                return array_map(fn ($x) => $this->shrink($x, $depth + 1), $keep);
            }
            $out = [];
            $n = 0;
            foreach ($v as $k => $x) {
                if (++$n > 45) { break; }
                $out[$k] = $this->shrink($x, $depth + 1);
            }

            return $out;
        }
        if (is_string($v) && mb_strlen($v) > 90) {
            return mb_substr($v, 0, 90) . '…';
        }

        return $v;
    }
}
