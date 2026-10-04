<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CameraSetting;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cấu hình server go2rtc/Frigate theo từng đối tác — KHÔNG BAO GIỜ trả secret thật (api_key/
 * password) ra ngoài, chỉ trả has_api_key/has_password/is_configured.
 */
class CameraSettingsController extends Controller
{
    /** GET /api/admin/camera-settings/partners — chỉ super_admin, danh sách đối tác để chọn. */
    public function partners(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only super admin can list partners.'], 403);
        }

        return response()->json(['data' => Partner::orderBy('name')->get(['id', 'name'])]);
    }

    /** GET /api/admin/camera-settings?partner_id= */
    public function show(Request $request): JsonResponse
    {
        $partnerId = $this->resolvePartnerId($request);
        if (! $partnerId) {
            return response()->json(['message' => 'partner_id is required.'], 422);
        }

        $setting = CameraSetting::forPartner($partnerId);

        return response()->json(['data' => $this->transform($setting)]);
    }

    /** PUT /api/admin/camera-settings — field không gửi = giữ nguyên, gửi rỗng mới xoá. */
    public function update(Request $request): JsonResponse
    {
        $partnerId = $this->resolvePartnerId($request);
        if (! $partnerId) {
            return response()->json(['message' => 'partner_id is required.'], 422);
        }

        $data = $request->validate([
            'base_url' => ['nullable', 'url', 'starts_with:http://,https://', 'max:255'],
            'api_key'  => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        $setting = CameraSetting::forPartner($partnerId);
        $setting->partner_id = $partnerId;

        foreach (['base_url', 'api_key', 'username', 'password'] as $field) {
            if ($request->has($field)) {
                $setting->{$field} = $data[$field] ?? null;
            }
        }

        $setting->save();

        return response()->json(['data' => $this->transform($setting)]);
    }

    private function resolvePartnerId(Request $request): ?string
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            return $user->partner_id;
        }

        return $request->input('partner_id');
    }

    private function transform(CameraSetting $setting): array
    {
        return [
            'base_url'          => $setting->base_url,
            'username'          => $setting->username,
            'has_api_key'       => filled($setting->api_key),
            'has_password'      => filled($setting->password),
            'has_credentials'   => $setting->hasFrigateCredentials(),
            'is_configured'     => $setting->isConfigured(),
        ];
    }
}
