<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\CameraSetting;
use App\Services\Go2RtcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Category\Entities\Category;

/**
 * CRUD Camera an ninh — port kiến trúc client từ 365home-cms, bỏ nhánh MiniHouse (theo building),
 * gắn theo branch_id (chi nhánh) thay vì theo phòng/sân.
 *
 * GET/POST      /api/admin/cameras(/{id})     → ?branch_id=&status=1|0|all
 */
class CameraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Camera::query()->visibleTo($request->user())->with('branch:id,name');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status', true));
        }

        $cameras = $query->orderBy('name')->get();

        return response()->json(['data' => $cameras->map(fn (Camera $c) => $this->transform($c))]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $camera = Camera::visibleTo($request->user())->with('branch:id,name')->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        return response()->json(['data' => $this->transform($camera)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'branch_id'            => ['required', 'integer', 'exists:categories,id'],
            'stream_key'           => ['required', 'string', 'max:255', Rule::unique('cameras')->where('branch_id', $request->input('branch_id'))],
            'frigate_camera_name'  => ['nullable', 'string', 'max:255'],
            'rtsp_url'             => ['nullable', 'string'],
            'note'                 => ['nullable', 'string'],
            'status'               => ['nullable', 'boolean'],
            'alert_enabled'        => ['nullable', 'boolean'],
            'alert_start_time'     => ['nullable', 'date_format:H:i'],
            'alert_end_time'       => ['nullable', 'date_format:H:i'],
        ]);

        $branch = $this->resolveBranch($request, (int) $data['branch_id']);
        if (! $branch) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        // Camera thuộc đối tác của CHI NHÁNH được chọn (super_admin không có partner_id riêng).
        $data['partner_id'] = $branch->partner_id;

        $camera = Camera::create($data);

        if ($camera->rtsp_url) {
            app(Go2RtcClient::class)->addStream($camera->stream_key, $camera->rtsp_url, $camera->partner_id);
        }

        return response()->json(['data' => $this->transform($camera->fresh('branch'))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $camera = Camera::visibleTo($request->user())->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        $data = $request->validate([
            'name'                => ['sometimes', 'required', 'string', 'max:255'],
            'branch_id'           => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'stream_key'          => ['sometimes', 'required', 'string', 'max:255', Rule::unique('cameras')->ignore($camera->id)->where('branch_id', $request->input('branch_id', $camera->branch_id))],
            'frigate_camera_name' => ['nullable', 'string', 'max:255'],
            'rtsp_url'            => ['nullable', 'string'],
            'note'                => ['nullable', 'string'],
            'status'              => ['nullable', 'boolean'],
            'alert_enabled'       => ['nullable', 'boolean'],
            'alert_start_time'    => ['nullable', 'date_format:H:i'],
            'alert_end_time'      => ['nullable', 'date_format:H:i'],
        ]);

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== (int) $camera->branch_id) {
            $branch = $this->resolveBranch($request, (int) $data['branch_id']);
            if (! $branch) {
                return response()->json(['message' => 'You do not have access to this branch.'], 403);
            }
            $data['partner_id'] = $branch->partner_id;
        }

        $oldStreamKey = $camera->stream_key;
        $camera->update($data);

        if (isset($data['stream_key']) && $data['stream_key'] !== $oldStreamKey) {
            app(Go2RtcClient::class)->deleteStream($oldStreamKey, $camera->partner_id);
        }

        if ($camera->rtsp_url) {
            app(Go2RtcClient::class)->addStream($camera->stream_key, $camera->rtsp_url, $camera->partner_id);
        }

        return response()->json(['data' => $this->transform($camera->fresh('branch'))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $camera = Camera::visibleTo($request->user())->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        $camera->delete();

        return response()->json(['message' => 'Camera deleted.']);
    }

    // Chi nhánh gốc mà user được phép gán camera vào (super_admin: mọi chi nhánh gốc).
    private function resolveBranch(Request $request, int $branchId): ?Category
    {
        $branch = Category::query()->whereNull('parent_id')->where('category_type', 'product')->find($branchId);
        if (! $branch) {
            return null;
        }

        $user = $request->user();

        return $user->isSuperAdmin() || in_array($branch->id, $user->rootProductCategoryIds(), true) ? $branch : null;
    }

    private function transform(Camera $camera): array
    {
        return [
            'id'                   => $camera->id,
            'name'                 => $camera->name,
            'stream_key'           => $camera->stream_key,
            'frigate_camera_name'  => $camera->frigate_camera_name,
            'branch'               => $camera->branch ? ['id' => $camera->branch->id, 'name' => $camera->branch->name] : null,
            'status'               => $camera->status,
            'alert_enabled'        => $camera->alert_enabled,
            'alert_start_time'     => $camera->alert_start_time,
            'alert_end_time'       => $camera->alert_end_time,
            'note'                 => $camera->note,
            'ws_url'               => $camera->wsProxyUrl(),
            'go2rtc_configured'    => CameraSetting::forPartner($camera->partner_id)->isConfigured(),
        ];
    }
}
