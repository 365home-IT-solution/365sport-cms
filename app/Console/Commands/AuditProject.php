<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use App\Support\AdminPanelContext;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

// Tự kiểm tra toàn dự án, không cần con người tick tay:
//  1. Filament  — mở thật từng trang (list / create / edit / view) của MỌI tài nguyên và trang, bằng tài khoản super admin.
//  2. Tương ứng — mỗi tài nguyên Filament cần những thao tác nào (list, xem, tạo, sửa, xoá) thì API admin đã có route tương ứng chưa.
//  3. API       — gọi thử MỌI route API (đúng loại tài khoản; id thật cho GET, id giả cho ghi) để bắt lỗi 500, và gọi lại KHÔNG đăng nhập để
//                 xem route có chặn đúng không.
//  4. Nghiệp vụ — đối chiếu danh sách endpoint mà nghiệp vụ đặt sân cần (config/audit.php) với route thực tế.
//  5. Client    — mở thật các trang web phía khách.
// Kết quả ghi ra JSON để dựng trang báo cáo. CHỈ chạy trên CSDL có chữ "test" vì có gọi thử các route ghi dữ liệu.
//
// Chạy: DB_DATABASE=sport365_test php -d memory_limit=-1 artisan audit:project
class AuditProject extends Command
{
    protected $signature = 'audit:project {--out=storage/app/audit/audit.json : Tệp JSON kết quả} {--skip-filament : Bỏ qua mở trang Filament} {--skip-live : Bỏ qua gọi thử API}';

    protected $description = 'Tự kiểm tra Filament, API và trang client của toàn dự án (chỉ chạy trên CSDL test)';

    private HttpKernel $kernel;
    private int $requests = 0;
    private ?User $admin = null;
    private ?Customer $customer = null;

    public function handle(): int
    {
        $db = (string) config('database.connections.' . config('database.default') . '.database');
        if (! str_contains($db, 'test')) {
            $this->error("Từ chối: CSDL '$db' không phải CSDL test (lệnh này gọi thử cả route ghi dữ liệu).");

            return self::FAILURE;
        }

        $this->kernel = app(HttpKernel::class);
        $this->admin = User::role('super_admin')->first();
        $this->customer = Customer::where('status', Customer::STATUS_ACTIVE)->first();
        if (! $this->admin || ! $this->customer) {
            $this->error('Cần ít nhất 1 super_admin và 1 khách đang hoạt động trong CSDL test.');

            return self::FAILURE;
        }

        AdminPanelContext::markActive();
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $cfg = config('audit');
        $routes = $this->collectRoutes();
        $this->info(count($routes) . ' route API');

        // khởi động ứng dụng 1 lần để request đầu không dính lỗi khởi tạo
        $this->web('GET', '/', null);

        $filament = $this->option('skip-filament') ? [] : $this->auditFilament($panel);
        $live = $this->option('skip-live') ? [] : $this->auditLive($routes, $cfg);
        $web = $this->auditWebPages();

        $result = $this->assemble($cfg, $routes, $filament, $live, $web);
        $out = base_path((string) $this->option('out'));
        File::ensureDirectoryExists(dirname($out));
        File::put($out, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
        $this->info("Đã ghi $out");

        return self::SUCCESS;
    }

    // ── Inventory ────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{method:string, uri:string, norm:string, middleware:array, action:string}> keyed "METHOD norm" */
    private function collectRoutes(): array
    {
        $out = [];
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            $isApi = str_starts_with($uri, 'api/') || preg_match('#^admin/api/#', $uri);
            if (! $isApi) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $norm = $this->norm($uri);
                $out["$method $norm|" . $uri] = [
                    'method' => $method, 'uri' => $uri, 'norm' => $norm,
                    'middleware' => array_map('strval', $route->gatherMiddleware()),
                    'action' => ltrim($route->getActionName(), '\\'),
                ];
            }
        }

        return $out;
    }

    private function norm(string $uri): string
    {
        return preg_replace('/\{[^}]+\}/', '{}', trim($uri, '/'));
    }

    private function hasRoute(array $routes, string $method, string $norm): bool
    {
        foreach ($routes as $r) {
            if ($r['norm'] === $norm && $r['method'] === $method) {
                return true;
            }
        }

        return false;
    }

    // ── HTTP helpers ─────────────────────────────────────────────────────────────────────────

    /** @return array{0:int, 1:?string} */
    private function api(string $method, string $uri, array $body, $actor, bool $web = false): array
    {
        app('auth')->forgetGuards();
        if ($actor) {
            $web ? auth('web')->setUser($actor) : Sanctum::actingAs($actor, ['*']);
        }
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_HOST' => '365sport-cms.test', 'HTTPS' => 'on',
            'REMOTE_ADDR' => '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254)];
        $request = $method === 'GET'
            ? Request::create($uri, 'GET', $body, [], [], $server)
            : Request::create($uri, $method, [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], json_encode($body));

        return $this->dispatch($request);
    }

    /** @return array{0:int, 1:?string} */
    private function web(string $method, string $uri, $actor): array
    {
        app('auth')->forgetGuards();
        if ($actor) {
            auth('web')->setUser($actor);
        }
        $server = ['HTTP_ACCEPT' => 'text/html', 'HTTP_HOST' => '365sport-cms.test', 'HTTPS' => 'on',
            'REMOTE_ADDR' => '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254)];

        return $this->dispatch(Request::create($uri, $method, [], [], [], $server));
    }

    /** @return array{0:int, 1:?string} */
    private function dispatch(Request $request): array
    {
        try {
            $response = $this->kernel->handle($request);
            $status = $response->getStatusCode();
            $ex = $response->exception ?? null;
            $msg = $ex ? mb_substr($ex->getMessage(), 0, 220) . ' @' . basename($ex->getFile()) . ':' . $ex->getLine() : null;
        } catch (\Throwable $e) {
            $status = 599;
            $msg = mb_substr($e->getMessage(), 0, 220) . ' @' . basename($e->getFile()) . ':' . $e->getLine();
        }

        // Giới hạn của môi trường kiểm tra trong 1 tiến trình: phản hồi chuyển hướng của Livewire không qua được StartSession.
        // Thực tế đây là một lần chuyển hướng (302) bình thường, không phải lỗi của trang.
        if ($status >= 500 && $msg && str_contains($msg, 'addCookieToResponse')) {
            $status = 302;
            $msg = 'chuyển hướng (bỏ qua giới hạn của môi trường kiểm tra)';
        }

        // Giữ bộ nhớ và giới hạn tốc độ không làm sai lệch kết quả.
        if (++$this->requests % 40 === 0) {
            try { Cache::flush(); } catch (\Throwable) {}
            gc_collect_cycles();
        }

        return [$status, $msg];
    }

    // ── 1+2. Filament ────────────────────────────────────────────────────────────────────────

    private function auditFilament($panel): array
    {
        $items = [];
        foreach ($panel->getResources() as $class) {
            $short = class_basename($class);
            $pages = array_keys($class::getPages());
            $model = $class::getModel();
            Filament::setCurrentPanel($panel);
            auth('web')->setUser($this->admin);

            $record = null;
            try { $record = $model::withoutGlobalScopes()->first(); } catch (\Throwable) {}

            $info = ['kind' => 'resource', 'class' => $class, 'name' => $short, 'label' => (string) $class::getPluralModelLabel(),
                'pages' => [], 'ops' => [], 'delete_action' => $this->usesDelete($class)];

            foreach (['index', 'create', 'edit', 'view'] as $page) {
                if (! in_array($page, $pages, true)) {
                    continue;
                }
                if (in_array($page, ['edit', 'view'], true) && ! $record) {
                    $info['pages'][$page] = ['status' => null, 'note' => 'chưa có dữ liệu mẫu'];
                    continue;
                }
                try {
                    Filament::setCurrentPanel($panel);
                    $url = $class::getUrl($page, in_array($page, ['edit', 'view'], true) ? ['record' => $record] : [], false);
                } catch (\Throwable $e) {
                    $info['pages'][$page] = ['status' => 599, 'note' => mb_substr($e->getMessage(), 0, 160)];
                    continue;
                }
                [$status, $msg] = $this->web('GET', parse_url($url, PHP_URL_PATH) ?: $url, $this->admin);
                $info['pages'][$page] = ['status' => $status, 'note' => $msg];
            }

            // Filament cho phép làm gì (quyết định API cần gì).
            $can = fn (callable $f) => (bool) (function () use ($f) { try { return $f(); } catch (\Throwable) { return false; } })();
            $info['ops'] = [
                'list' => in_array('index', $pages, true),
                'show' => in_array('edit', $pages, true) || in_array('view', $pages, true),
                'create' => in_array('create', $pages, true) && $can(fn () => $class::canCreate()),
                'update' => in_array('edit', $pages, true) && $can(fn () => $class::canEdit($record ?? new $model)),
                'delete' => $info['delete_action'] && $can(fn () => $class::canDelete($record ?? new $model)),
            ];
            $items[] = $info;
            $this->line("  resource $short");
        }

        foreach ($panel->getPages() as $class) {
            $short = class_basename($class);
            try {
                Filament::setCurrentPanel($panel);
                $url = $class::getUrl([], false);
                [$status, $msg] = $this->web('GET', parse_url($url, PHP_URL_PATH) ?: $url, $this->admin);
            } catch (\Throwable $e) {
                $status = 599; $msg = mb_substr($e->getMessage(), 0, 160);
            }
            $items[] = ['kind' => 'page', 'class' => $class, 'name' => $short, 'label' => $short, 'pages' => ['index' => ['status' => $status, 'note' => $msg]], 'ops' => ['list' => true], 'delete_action' => false];
            $this->line("  page $short");
        }

        return $items;
    }

    private function usesDelete(string $class): bool
    {
        $file = (new ReflectionClass($class))->getFileName();
        $dir = dirname($file) . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME);
        $files = [$file];
        if (is_dir($dir)) {
            foreach (File::allFiles($dir) as $f) {
                $files[] = $f->getPathname();
            }
        }
        foreach ($files as $f) {
            if (preg_match('/\b(DeleteAction|DeleteBulkAction|ForceDeleteAction)\b/', (string) file_get_contents($f))) {
                return true;
            }
        }

        return false;
    }

    // ── 3. Gọi thử toàn bộ route API ─────────────────────────────────────────────────────────

    /** @return array<string, array> keyed "METHOD uri" */
    private function auditLive(array $routes, array $cfg): array
    {
        $skip = $cfg['skip_live'] ?? [];
        $live = [];
        $n = count($routes); $i = 0;

        foreach ($routes as $r) {
            $i++;
            $key = $r['method'] . ' ' . $r['uri'];
            $isWebPanel = str_starts_with($r['uri'], 'admin/api/');
            $mw = $r['middleware'];
            $needsAdmin = in_array('admin.api', $mw, true) || $isWebPanel;
            $needsAuth = $needsAdmin || (bool) array_filter($mw, fn ($m) => str_starts_with($m, 'auth'));

            if (in_array($r['method'] . ' ' . $r['norm'], $skip, true) || in_array($key, $skip, true)) {
                $live[$key] = ['status' => null, 'skipped' => 'tránh phá huỷ tài khoản kiểm tra'];
                continue;
            }
            if ($r['method'] === 'DELETE' && ! str_contains($r['uri'], '{')) {
                $live[$key] = ['status' => null, 'skipped' => 'xoá hàng loạt, không gọi thử'];
                continue;
            }

            $real = $r['method'] === 'GET';
            $path = $this->fillParams($r['uri'], $real);
            if ($path === null) {
                $live[$key] = ['status' => null, 'skipped' => 'không có dữ liệu mẫu cho tham số'];
                continue;
            }

            $actor = $needsAdmin ? $this->admin : ($needsAuth ? $this->customer : null);
            $body = $r['norm'] === 'api/graphql' ? ['query' => '{ __typename }'] : [];

            [$status, $msg] = $this->api($r['method'], '/' . $path, $body, $actor, $isWebPanel);
            $row = ['status' => $status, 'message' => $status >= 500 ? $msg : null, 'actor' => $needsAdmin ? 'admin' : ($needsAuth ? 'khách' : 'công khai')];

            if ($actor) {
                [$anon] = $this->api($r['method'], '/' . $path, $body, null, $isWebPanel);
                $row['anon'] = $anon;
            }
            $live[$key] = $row;
            if ($i % 50 === 0) {
                $this->line("  api $i/$n");
            }
        }

        return $live;
    }

    /** Thay {tham số} bằng giá trị mẫu. GET dùng id thật (để chạm nhánh 200); ghi dùng id giả (không gây tác dụng phụ). */
    private function fillParams(string $uri, bool $real): ?string
    {
        $segments = explode('/', $uri);
        foreach ($segments as $idx => $seg) {
            if (! preg_match('/^\{(\w+)\??\}$/', $seg, $m)) {
                continue;
            }
            $name = $m[1];
            $prev = $segments[$idx - 1] ?? '';
            $value = $real ? $this->realSample($name, $prev) : $this->fakeSample($name);
            if ($value === null) {
                if (str_ends_with($seg, '?}')) { $segments[$idx] = ''; continue; }

                return null;
            }
            $segments[$idx] = (string) $value;
        }

        return trim(implode('/', array_filter($segments, fn ($s) => $s !== '')), '/');
    }

    private function fakeSample(string $name): string
    {
        return match (true) {
            str_contains($name, 'code') => 'ZZZZZZZZ',
            $name === 'slug' => 'khong-ton-tai',
            in_array($name, ['customer_id', 'customer'], true) => '00000000-0000-4000-8000-000000000000',
            default => '999999999',
        };
    }

    private function realSample(string $name, string $prev): ?string
    {
        static $cache = [];
        $k = $name . '|' . $prev;
        if (array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        $val = null;
        $first = fn (string $t, string $col = 'id') => Schema::hasTable($t) ? DB::table($t)->orderBy($col)->value($col) : null;

        if ($name === 'order_code' || $name === 'order' || ($name === 'code' && in_array($prev, ['orders', 'package-orders', 'guest-orders'], true))) {
            $val = $first('orders', 'order_code');
        } elseif ($name === 'slug') {
            $val = $first('categories', 'slug');
        } elseif ($name === 'code' && $prev === 'teams') {
            $val = $first('teams', 'code');
        } elseif ($name === 'code' && $prev === 'booking-funds') {
            $val = $first('booking_funds', 'invite_code');
        } elseif ($name === 'code') {
            $val = $first('wards', 'code');
        } elseif ($name === 'customer_id') {
            $val = $first('customers');
        } elseif ($name === 'id' || str_ends_with($name, 'Id') || $name === 'branch' || $name === 'coach') {
            $table = match ($prev) {
                'rooms', 'products' => 'products', 'branches', 'categories' => 'categories', 'orders' => 'orders',
                'customers' => 'customers', 'room-types' => 'room_types', 'price-boards' => 'price_boards',
                'package-plans' => 'package_plans', 'package-orders' => 'orders', 'customer-packages' => 'customer_packages',
                'corporate-accounts' => 'corporate_accounts', 'warehouse' => 'warehouse_items', 'items' => 'warehouse_items',
                'categories_' => 'warehouse_categories', 'units' => 'warehouse_units', 'stock-ins' => 'warehouse_stock_ins',
                'stock-outs' => 'warehouse_stock_outs', 'stock-checks' => 'warehouse_stock_checks', 'item-rentals' => 'warehouse_item_rentals',
                'cameras' => 'cameras', 'camera-alerts' => 'camera_alerts', 'open-matches' => 'open_matches', 'chat' => 'chat_conversations',
                'notifications' => 'notification_fcm', 'popups', 'ask-user' => 'ask_users', 'provinces' => 'provinces', 'wards' => 'wards',
                'waitlist' => 'slot_waitlists', 'payments' => 'subscription_payments',
                default => str_replace('-', '_', $prev),
            };
            $val = Schema::hasTable($table) ? (string) DB::table($table)->orderBy('id')->value('id') : null;
            $val = ($val === '' || $val === null) ? ($name === 'id' ? '1' : null) : $val;
        }

        return $cache[$k] = ($val !== null ? (string) $val : null);
    }

    // ── 5. Trang web client ──────────────────────────────────────────────────────────────────

    private function auditWebPages(): array
    {
        $deny = '#^(admin|api|livewire|log-viewer|filament|sanctum|storage|up|build|vendor|webhook|_|horizon|telescope|broadcasting|oauth|auth|settings|viewer)#';
        $pages = [];
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || preg_match($deny, $uri)) {
                continue;
            }
            $path = $this->fillWebParams($uri);
            if ($path === null) {
                $pages[] = ['uri' => '/' . ltrim($uri, '/'), 'status' => null, 'note' => 'không có dữ liệu mẫu cho tham số'];
                continue;
            }
            // Mỗi request thật bắt đầu với panel mặc định; trong 1 tiến trình dài, các trang admin/tài liệu đã đổi panel hiện tại nên phải đặt lại.
            Filament::setCurrentPanel(Filament::getDefaultPanel());
            [$status, $msg] = $this->web('GET', '/' . ltrim($path, '/'), null);
            $pages[] = ['uri' => '/' . ltrim($uri, '/'), 'status' => $status, 'note' => $status >= 500 ? $msg : null, 'name' => $route->getName()];
        }
        $this->line('  ' . count($pages) . ' trang web');

        return $pages;
    }

    private function fillWebParams(string $uri): ?string
    {
        $v = fn (string $t, string $c) => Schema::hasTable($t) && Schema::hasColumn($t, $c) ? DB::table($t)->value($c) : null;
        $slugCategory = Schema::hasTable('categories') ? DB::table('categories')->where('category_type', 'product')->value('slug') : null;
        $map = [
            'bai-viet' => $v('posts', 'slug'),
            'branch' => $slugCategory,
            'room' => $v('products', 'slug'),
            's' => $v('provinces', 'slug'),
            'thong-tin-dat-phong' => $v('orders', 'order_code'),
            'quy-nhom' => $v('booking_funds', 'invite_code'),
            'mau-giao-dien' => $v('themes', 'slug'),
        ];
        $segments = explode('/', $uri);
        foreach ($segments as $i => $seg) {
            if (! preg_match('/\{(\w+)(\?)?\}/', $seg, $m)) {
                continue;
            }
            $v = $map[$segments[$i - 1] ?? ''] ?? ($m[1] === 'slug' ? $slugCategory : null);
            if ($v === null) {
                if (($m[2] ?? '') === '?') { $segments[$i] = ''; continue; }

                return null;
            }
            $segments[$i] = str_replace($m[0], (string) $v, $seg);
        }

        return implode('/', array_filter($segments, fn ($s) => $s !== ''));
    }

    // ── Ghép kết quả theo module ─────────────────────────────────────────────────────────────

    private function assemble(array $cfg, array $routes, array $filament, array $live, array $web): array
    {
        $resourceModule = [];
        $pageModule = [];
        foreach ($cfg['modules'] as $id => $m) {
            foreach ($m['resources'] as $r) { $resourceModule[$r] = $id; }
            foreach ($m['pages'] as $p) { $pageModule[$p] = $id; }
        }

        $modules = [];
        foreach ($cfg['modules'] as $id => $m) {
            $modules[$id] = ['id' => $id, 'name' => $m['name'], 'group' => $m['group'], 'code' => $m['code'],
                'admin' => ['resources' => [], 'extra' => [], 'routes' => ['total' => 0, 'ok' => 0, 'errors' => [], 'open' => [], 'skipped' => 0]],
                'client' => ['endpoints' => [], 'web' => [], 'routes' => ['total' => 0, 'ok' => 0, 'errors' => [], 'open' => [], 'skipped' => 0]]];
        }
        $modules['other'] = ['id' => 'other', 'name' => 'Chưa phân loại', 'group' => 'Nội dung & hệ thống', 'code' => [],
            'admin' => ['resources' => [], 'extra' => [], 'routes' => ['total' => 0, 'ok' => 0, 'errors' => [], 'open' => [], 'skipped' => 0]],
            'client' => ['endpoints' => [], 'web' => [], 'routes' => ['total' => 0, 'ok' => 0, 'errors' => [], 'open' => [], 'skipped' => 0]]];

        // Filament + tương ứng API
        $adminApi = $cfg['admin_api'];
        $apiPages = $cfg['admin_api_pages'] ?? [];
        $optional = $cfg['api_optional'];
        foreach ($filament as $item) {
            $name = $item['name'];
            $mod = $item['kind'] === 'resource' ? ($resourceModule[$name] ?? 'other') : ($pageModule[$name] ?? 'other');
            $prefixes = $item['kind'] === 'resource' ? ($adminApi[$name] ?? []) : ($apiPages[$name] ?? []);
            $parity = [];
            $required = array_keys(array_filter($item['ops']));
            foreach ($required as $op) {
                $parity[$op] = $item['kind'] === 'page' ? $this->anyPrefix($routes, $prefixes, 'GET', '') : $this->opPresent($routes, $prefixes, $op);
            }
            $present = count(array_filter($parity));
            $isOptional = in_array($name, $optional, true);
            $status = $present === count($required) && count($required) > 0 ? 'full' : ($present > 0 ? 'partial' : 'none');
            if ($isOptional && $status !== 'full') {
                $status = 'optional';
            }
            $pageErrors = array_filter($item['pages'], fn ($p) => ($p['status'] ?? 0) >= 500);
            $modules[$mod]['admin']['resources'][] = [
                'name' => $name, 'label' => $item['label'], 'kind' => $item['kind'], 'pages' => $item['pages'], 'api' => $prefixes,
                'required' => $required, 'parity' => $parity, 'status' => $status, 'optional' => $isOptional, 'page_errors' => count($pageErrors),
            ];
        }

        foreach ($cfg['admin_extra'] as $mid => $list) {
            foreach ($list as $e) {
                [$label, $method, $norm] = $e;
                $exists = $this->hasRoute($routes, $method, $norm);
                $modules[$mid]['admin']['extra'][] = ['label' => $label, 'method' => $method, 'uri' => '/' . $norm, 'note' => $e[3] ?? null, 'exists' => $exists, 'live' => $exists ? $this->liveFor($routes, $live, $method, $norm) : null];
            }
        }
        foreach ($cfg['client'] as $mid => $list) {
            foreach ($list as $e) {
                [$label, $method, $norm, $auth] = $e;
                $exists = $this->hasRoute($routes, $method, $norm);
                $modules[$mid]['client']['endpoints'][] = ['label' => $label, 'method' => $method, 'uri' => '/' . $norm, 'auth' => $auth, 'note' => $e[4] ?? null, 'exists' => $exists, 'live' => $exists ? $this->liveFor($routes, $live, $method, $norm) : null];
            }
        }

        // Gán từng route đã gọi thử cho module (khớp tiền tố dài nhất, rồi khớp spec, rồi theo cấu hình đoạn đầu).
        $prefixModule = [];
        foreach ($adminApi as $res => $prefs) {
            foreach ($prefs as $p) { $prefixModule[$p] = $resourceModule[$res] ?? 'other'; }
        }
        foreach ($cfg['route_modules'] ?? [] as $p => $mid) { $prefixModule[$p] = $mid; }
        foreach ($cfg['admin_extra'] as $mid => $list) {
            foreach ($list as $e) { $prefixModule[$e[2]] = $mid; }
        }
        $specModule = [];
        foreach ($cfg['client'] as $mid => $list) {
            foreach ($list as $e) { $specModule[$e[1] . ' ' . $e[2]] = $mid; }
        }
        uksort($prefixModule, fn ($a, $b) => strlen($b) <=> strlen($a));

        $unmatched = [];
        foreach ($routes as $r) {
            $key = $r['method'] . ' ' . $r['uri'];
            $isAdmin = str_starts_with($r['uri'], 'api/admin/') || str_starts_with($r['uri'], 'admin/api/');
            $mid = null;
            if (! $isAdmin) { $mid = $specModule[$r['method'] . ' ' . $r['norm']] ?? null; }
            if ($mid === null) {
                foreach ($prefixModule as $p => $m) {
                    if ($r['norm'] === $p || str_starts_with($r['norm'], $p . '/')) { $mid = $m; break; }
                }
            }
            if ($mid === null) { $mid = $isAdmin ? null : 'other'; }
            if ($mid === null) { $unmatched[] = $key; $mid = 'other'; }

            $side = $isAdmin ? 'admin' : 'client';
            $bucket = &$modules[$mid][$side]['routes'];
            $bucket['total']++;
            $l = $live[$key] ?? null;
            if (! $l || $l['status'] === null) { $bucket['skipped']++; unset($bucket); continue; }
            if ($l['status'] >= 500) {
                $bucket['errors'][] = ['route' => $key, 'status' => $l['status'], 'message' => $l['message'] ?? ''];
            } else {
                $bucket['ok']++;
            }
            if (isset($l['anon']) && $l['anon'] >= 200 && $l['anon'] < 300 && $isAdmin) {
                $bucket['open'][] = ['route' => $key, 'status' => $l['anon']];
            }
            unset($bucket);
        }

        foreach ($web as $w) {
            $uri = $w['uri'];
            $mid = match (true) {
                str_contains($uri, 'quy-nhom') => 'community',
                str_contains($uri, 'thanh-toan'), str_contains($uri, 'dat-phong') => 'orders',
                str_contains($uri, 'tai-khoan'), str_contains($uri, 'dang-nhap'), str_contains($uri, 'dang-ky') && ! str_contains($uri, 'hop-tac') => 'auth',
                str_contains($uri, 'doi-tac'), str_contains($uri, 'hop-tac') => 'partner',
                str_contains($uri, 'ban-do'), str_contains($uri, 'kham-pha') => 'maps',
                str_contains($uri, 'branch'), str_contains($uri, 'room'), str_starts_with($uri, '/s/') => 'venues',
                str_contains($uri, 'yeu-thich') => 'venues',
                default => 'cms',
            };
            $modules[$mid]['client']['web'][] = $w;
        }

        // Trạng thái từng phía.
        foreach ($modules as $id => &$mod) {
            $mod['admin']['status'] = $this->adminStatus($mod['admin']);
            $mod['client']['status'] = $this->clientStatus($mod['client']);
        }
        unset($mod);
        if (! $modules['other']['admin']['resources'] && ! $modules['other']['client']['endpoints'] && ! $modules['other']['client']['routes']['total']) {
            unset($modules['other']);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'database' => (string) config('database.connections.' . config('database.default') . '.database'),
            'totals' => [
                'api_routes' => count($routes),
                'api_called' => count(array_filter($live, fn ($l) => $l['status'] !== null)),
                'api_errors' => count(array_filter($live, fn ($l) => ($l['status'] ?? 0) >= 500)),
                'filament_items' => count($filament),
                'filament_page_errors' => array_sum(array_map(fn ($i) => count(array_filter($i['pages'], fn ($p) => ($p['status'] ?? 0) >= 500)), $filament)),
                'web_pages' => count($web),
                'web_errors' => count(array_filter($web, fn ($w) => ($w['status'] ?? 0) >= 500)),
            ],
            'modules' => array_values($modules),
        ];
    }

    private function opPresent(array $routes, array $prefixes, string $op): bool
    {
        foreach ($prefixes as $p) {
            $ok = match ($op) {
                'list' => $this->hasRoute($routes, 'GET', $p),
                'show' => $this->hasRoute($routes, 'GET', "$p/{}"),
                'create' => $this->hasRoute($routes, 'POST', $p),
                'update' => $this->hasRoute($routes, 'PUT', "$p/{}") || $this->hasRoute($routes, 'PATCH', "$p/{}") || $this->hasRoute($routes, 'POST', "$p/{}"),
                'delete' => $this->hasRoute($routes, 'DELETE', "$p/{}"),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    private function anyPrefix(array $routes, array $prefixes, string $method, string $suffix): bool
    {
        foreach ($prefixes as $p) {
            if ($this->hasRoute($routes, $method, $p . $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function liveFor(array $routes, array $live, string $method, string $norm): ?int
    {
        foreach ($routes as $r) {
            if ($r['method'] === $method && $r['norm'] === $norm) {
                return $live[$method . ' ' . $r['uri']]['status'] ?? null;
            }
        }

        return null;
    }

    private function adminStatus(array $a): string
    {
        $need = 0; $have = 0; $optionalOnly = false;
        foreach ($a['resources'] as $r) {
            if ($r['optional']) { $optionalOnly = true; continue; }
            $need += count($r['required']);
            $have += count(array_filter($r['parity']));
        }
        foreach ($a['extra'] as $e) {
            $need++;
            $have += $e['exists'] ? 1 : 0;
        }
        if ($need === 0) {
            return $a['resources'] || $optionalOnly ? 'optional' : 'na';
        }

        return $have === $need ? 'full' : ($have > 0 ? 'partial' : 'none');
    }

    private function clientStatus(array $c): string
    {
        $need = count($c['endpoints']);
        if ($need === 0) {
            return $c['web'] ? 'full' : 'na';
        }
        $have = count(array_filter($c['endpoints'], fn ($e) => $e['exists']));

        return $have === $need ? 'full' : ($have > 0 ? 'partial' : 'none');
    }
}
