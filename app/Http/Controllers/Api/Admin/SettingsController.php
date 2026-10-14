<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DirectionsConfiguration;
use App\Models\GoogleOauthConfiguration;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\LaravelSettings\Settings;

// Các trang cấu hình hệ thống của admin qua API: cài đặt chung, email gửi đi, đăng nhập Google, dẫn đường bản đồ. Mỗi nhóm là 1 bản ghi duy nhất.
// Quyền theo từng trang Filament (page_ManageGeneral...) do middleware admin.api kiểm tra. Khoá bí mật (mật khẩu email, client secret, API key)
// KHÔNG bao giờ trả về nguyên văn: đọc ra là dạng che (••••1234); gửi lại đúng giá trị đã che nghĩa là "giữ khoá cũ".
class SettingsController extends Controller
{
    private const MASK = '••••••••';

    /** nhóm => [loại, lớp, danh sách khoá bí mật] */
    private const GROUPS = [
        'general' => ['settings', GeneralSettings::class, []],
        'mail' => ['settings', MailSettings::class, ['username', 'password']],
        'google-login' => ['model', GoogleOauthConfiguration::class, ['client_secret']],
        'directions' => ['model', DirectionsConfiguration::class, ['ors_api_key']],
    ];

    // GET /api/admin/settings/{group}
    public function show(string $group): JsonResponse
    {
        [$kind, $class, $secrets] = $this->group($group);

        return response()->json(['data' => $this->mask($this->read($kind, $class), $secrets)]);
    }

    // PUT|PATCH|POST /api/admin/settings/{group}
    public function update(Request $request, string $group): JsonResponse
    {
        [$kind, $class, $secrets] = $this->group($group);

        $rules = $kind === 'settings' ? $this->settingsRules($class) : $this->modelRules($class);
        $data = $request->validate($rules);

        // Giá trị bí mật còn nguyên dạng che → không ghi đè khoá cũ.
        foreach ($secrets as $field) {
            if (isset($data[$field]) && str_starts_with((string) $data[$field], substr(self::MASK, 0, 6))) {
                unset($data[$field]);
            }
        }

        if ($kind === 'settings') {
            /** @var Settings $settings */
            $settings = app($class);
            $settings->fill($data)->save();
        } else {
            $model = $class::first() ?? new $class;
            $model->fill($data)->save();
        }

        return response()->json(['data' => $this->mask($this->read($kind, $class), $secrets)]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private function group(string $group): array
    {
        abort_unless(isset(self::GROUPS[$group]), 404, 'Unknown settings group.');

        return self::GROUPS[$group];
    }

    private function read(string $kind, string $class): array
    {
        if ($kind === 'settings') {
            return app($class)->toArray();
        }

        $model = $class::first();

        return $model ? collect($model->toArray())->except(['id', 'created_at', 'updated_at'])->all() : [];
    }

    private function mask(array $data, array $secrets): array
    {
        foreach ($secrets as $field) {
            if (! empty($data[$field])) {
                $data[$field] = self::MASK . substr((string) $data[$field], -4);
            }
        }

        return $data;
    }

    /** Luật validate sinh từ kiểu khai báo của từng thuộc tính public (string/bool/int/float/array, cho phép null nếu khai báo ?type). */
    private function settingsRules(string $class): array
    {
        $rules = [];
        foreach ((new \ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            $type = $prop->getType();
            if (! $type instanceof ReflectionNamedType) {
                continue;
            }
            $r = ['sometimes'];
            if ($type->allowsNull()) {
                $r[] = 'nullable';
            }
            $r[] = match ($type->getName()) {
                'bool' => 'boolean', 'int' => 'integer', 'float' => 'numeric', 'array' => 'array', default => 'string',
            };
            if ($type->getName() === 'string') {
                $r[] = 'max:65535';
            }
            $rules[$prop->getName()] = $r;
        }

        return $rules;
    }

    private function modelRules(string $class): array
    {
        return match ($class) {
            GoogleOauthConfiguration::class => ['client_id' => 'sometimes|nullable|string|max:255', 'client_secret' => 'sometimes|nullable|string|max:255', 'enabled' => 'sometimes|boolean'],
            DirectionsConfiguration::class => ['enabled' => 'sometimes|boolean', 'ors_api_key' => 'sometimes|nullable|string|max:255'],
            default => [],
        };
    }
}
