<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;

// Sinh tài liệu OpenAPI 3.0 từ danh sách route /api thật + ghi chú (docblock/comment) của từng hàm controller, rồi ghi ra public/docs/openapi.json.
// Tài liệu luôn khớp code vì sinh từ chính route đang chạy; chạy lại sau mỗi lần thêm/sửa API.
class GenerateOpenApi extends Command
{
    protected $signature = 'api:openapi {--output=public/docs/openapi.json : Đường dẫn file xuất ra}';

    protected $description = 'Sinh tài liệu OpenAPI (openapi.json) từ các route /api';

    public function handle(): int
    {
        $paths = [];
        $count = 0;

        foreach (RouteFacade::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || str_contains($uri, '{fallbackPlaceholder}') || \App\Support\FeatureRoutes::disabledFlagFor($uri) !== null) {
                continue;
            }

            $path = '/' . preg_replace('/\{(\w+)\?\}/', '{$1}', $uri);
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $paths[$path][strtolower($method)] = $this->operation($route, $method, $path);
                $count++;
            }
        }
        ksort($paths);

        $doc = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name') . ' API',
                'version' => '1.0.0',
                'description' => "API cho app/web khách hàng (/api/...) và cho quản trị đối tác (/api/admin/...). Tự sinh từ route bằng `php artisan api:openapi`.\n"
                    . 'Xác thực: Bearer token (Laravel Sanctum). Khách đăng nhập qua OTP; quản trị qua /api/admin/auth/login. Thông báo lỗi do code viết tay bằng tiếng Anh; lỗi validate tự động của Laravel bằng tiếng Việt.',
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/')]],
            'components' => ['securitySchemes' => ['sanctum' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Token Sanctum']]],
            'paths' => $paths,
        ];

        $file = base_path((string) $this->option('output'));
        File::ensureDirectoryExists(dirname($file));
        File::put($file, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));

        $this->info("Đã ghi {$count} thao tác ở " . count($paths) . " đường dẫn → {$file}");

        return self::SUCCESS;
    }

    private function operation(Route $route, string $method, string $path): array
    {
        $middleware = $route->gatherMiddleware();
        $needsAuth = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth'));
        $admin = str_starts_with($path, '/api/admin');
        $segments = explode('/', trim($path, '/'));
        $tag = $admin ? 'Admin · ' . ($segments[2] ?? 'root') : ($segments[1] ?? 'root');

        [$summary, $description] = $this->describe($route);

        $op = [
            'tags' => [$tag],
            'summary' => $summary ?: $this->fallbackSummary($route, $method, $path),
            'operationId' => strtolower($method) . '_' . trim(preg_replace('/[^a-z0-9]+/i', '_', $path), '_'),
            'parameters' => [],
            'responses' => [
                '200' => ['description' => 'Thành công'],
                '422' => ['description' => 'Dữ liệu không hợp lệ'],
            ],
        ];
        if ($description) {
            $op['description'] = $description;
        }

        preg_match_all('/\{(\w+)\}/', $path, $m);
        foreach ($m[1] as $name) {
            $op['parameters'][] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
        }
        if (! $op['parameters']) {
            unset($op['parameters']);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $op['requestBody'] = ['content' => ['application/json' => ['schema' => ['type' => 'object']]]];
        }

        if ($needsAuth) {
            $op['security'] = [['sanctum' => []]];
            $op['responses']['401'] = ['description' => 'Chưa đăng nhập'];
            if ($admin) {
                $op['responses']['403'] = ['description' => 'Không đủ quyền'];
            }
        }
        $op['responses']['404'] = ['description' => 'Không tìm thấy'];

        return $op;
    }

    // Hàm không có chú thích: đặt tên theo hành động chuẩn (index/show/store/update/destroy) + tên tài nguyên, còn lại dùng tên hàm.
    private function fallbackSummary(Route $route, string $method, string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $resource = str_replace('-', ' ', preg_replace('/^api\/(admin\/)?/', '', $segments[0] === 'api' && ($segments[1] ?? '') === 'admin' ? ($segments[2] ?? '') : ($segments[1] ?? '')));
        $action = str_contains($route->getActionName(), '@') ? explode('@', $route->getActionName())[1] : '';

        $verb = match ($action) {
            'index' => 'Danh sách', 'show' => 'Chi tiết', 'store' => 'Tạo mới', 'update' => 'Cập nhật', 'destroy' => 'Xoá',
            '' => null,
            default => ucfirst(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $action))),
        };

        return $verb ? "{$verb} — {$resource}" : $method . ' ' . $path;
    }

    // Lấy dòng mô tả từ docblock của hàm controller; nếu chỉ có comment // ngay trên hàm thì đọc từ file nguồn.
    private function describe(Route $route): array
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return [null, null];
        }

        [$class, $method] = explode('@', $action);
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return [null, null];
        }

        $ref = new ReflectionMethod($class, $method);
        $lines = [];

        if ($doc = $ref->getDocComment()) {
            foreach (preg_split('/\R/', $doc) as $line) {
                $line = trim(preg_replace('#^\s*(/\*\*?|\*/|\*)\s?#', '', $line));
                if ($line !== '' && ! str_starts_with($line, '@')) {
                    $lines[] = $line;
                }
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

        // Comment viết dạng "POST /api/x/{id} — mô tả": bỏ phần "METHOD /đường-dẫn —" lặp lại, giữ lại mô tả.
        $first = trim(preg_replace('#^(GET|POST|PUT|PATCH|DELETE)([|,/ ]+(GET|POST|PUT|PATCH|DELETE))*\s+/\S+\s*(?:[—–-]\s*)?#u', '', $lines[0]));

        return [$first !== '' ? mb_substr($first, 0, 200) : null, count($lines) > 1 ? implode("\n", array_slice($lines, 1, 12)) : null];
    }
}
