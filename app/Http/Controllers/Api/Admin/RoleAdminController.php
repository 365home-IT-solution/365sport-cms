<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Vai trò & quyền (màn Filament Shield "Vai trò"). Đối tác chỉ thấy/sửa vai trò do CHÍNH mình tạo (roles.created_by) và chỉ gán được những
// quyền mà chính họ đang có (chống leo thang đặc quyền); vai trò hệ thống (super_admin, partner, employee, panel_user) không đổi tên/xoá được.
class RoleAdminController extends Controller
{
    private const SYSTEM_ROLES = ['super_admin', 'partner', 'employee', 'panel_user'];

    // GET /api/admin/roles
    public function index(Request $request): JsonResponse
    {
        $roles = $this->visible($request->user())->withCount('permissions')->orderBy('name')->get()
            ->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'permissions_count' => $r->permissions_count, 'is_system' => in_array($r->name, self::SYSTEM_ROLES, true)]);

        return response()->json(['data' => $roles]);
    }

    // GET /api/admin/roles/permissions — các quyền có thể gán (đối tác chỉ thấy quyền mình có), gom theo nhóm
    public function permissions(Request $request): JsonResponse
    {
        $names = $this->assignable($request->user());
        $groups = $names->groupBy(fn (string $n) => preg_replace('/^(view_any|view|create|update|delete_any|delete|force_delete_any|force_delete|restore_any|restore|replicate|reorder)_/', '', $n))
            ->map(fn ($items, $group) => ['group' => $group, 'permissions' => $items->values()])->values();

        return response()->json(['data' => $groups]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $role = $this->visible($request->user())->with('permissions:id,name')->find($id);

        return $role ? response()->json(['data' => $this->item($role)]) : response()->json(['message' => 'Role not found.'], 404);
    }

    // POST /api/admin/roles — body: name, permissions[]
    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => 'sometimes|array|max:1000', 'permissions.*' => 'string|max:150',
        ]);
        if (in_array($data['name'], self::SYSTEM_ROLES, true)) {
            return response()->json(['message' => 'This role name is reserved.'], 422);
        }
        $perms = $this->resolvePermissions($actor, $data['permissions'] ?? []);
        if ($perms === null) {
            return response()->json(['message' => 'You can only grant permissions that you have.'], 422);
        }

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web', 'created_by' => $actor->getKey()]);
        $role->syncPermissions($perms);

        return response()->json(['data' => $this->item($role->load('permissions:id,name'))], 201);
    }

    // PUT|PATCH|POST /api/admin/roles/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $role = $this->visible($actor)->find($id);
        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        $isSystem = in_array($role->name, self::SYSTEM_ROLES, true);
        if ($isSystem && ! $actor->isSuperAdmin()) {
            return response()->json(['message' => 'System roles can only be changed by a super admin.'], 403);
        }
        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'The super admin role cannot be changed.'], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
            'permissions' => 'sometimes|array|max:1000', 'permissions.*' => 'string|max:150',
        ]);
        if (isset($data['name']) && $isSystem && $data['name'] !== $role->name) {
            return response()->json(['message' => 'System roles cannot be renamed.'], 422);
        }
        if (isset($data['name']) && ! $isSystem && in_array($data['name'], self::SYSTEM_ROLES, true)) {
            return response()->json(['message' => 'This role name is reserved.'], 422);
        }

        $perms = null;
        if (isset($data['permissions'])) {
            $perms = $this->resolvePermissions($actor, $data['permissions'], $role);
            if ($perms === null) {
                return response()->json(['message' => 'You can only grant permissions that you have.'], 422);
            }
        }

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }
        if ($perms !== null) {
            $role->syncPermissions($perms);
        }

        return response()->json(['data' => $this->item($role->fresh('permissions:id,name'))]);
    }

    // DELETE /api/admin/roles/{id}
    public function destroy(Request $request, string $id): JsonResponse
    {
        $role = $this->visible($request->user())->find($id);
        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }
        if ($role->users()->exists()) {
            return response()->json(['message' => 'Accounts still use this role; reassign them first.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private function visible(User $actor)
    {
        $q = Role::query()->where('guard_name', 'web')->where('name', '!=', 'panel_user');

        return $actor->isSuperAdmin() ? $q : $q->where('created_by', $actor->getKey());
    }

    /** Quyền người này được phép gán: super_admin = tất cả, người khác = chỉ quyền mình đang có. */
    private function assignable(User $actor)
    {
        return $actor->isSuperAdmin()
            ? Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')
            : $actor->getAllPermissions()->pluck('name')->sort()->values();
    }

    /** Danh sách quyền hợp lệ để sync, hoặc null nếu có quyền ngoài phạm vi được gán. */
    private function resolvePermissions(User $actor, array $names, ?Role $current = null)
    {
        $names = array_values(array_unique($names));
        $allowed = $this->assignable($actor);
        // Khi sửa, quyền vai trò ĐANG có mà người sửa không có thì giữ nguyên (không bắt họ phải có mới được lưu).
        $keep = $current ? $current->permissions->pluck('name')->diff($allowed)->all() : [];

        foreach ($names as $n) {
            if (! $allowed->contains($n) && ! in_array($n, $keep, true)) {
                return null;
            }
        }

        return array_values(array_unique(array_merge($names, array_intersect($keep, $current ? $current->permissions->pluck('name')->all() : []))));
    }

    private function item(Role $r): array
    {
        return ['id' => $r->id, 'name' => $r->name, 'is_system' => in_array($r->name, self::SYSTEM_ROLES, true), 'permissions' => $r->permissions->pluck('name')->sort()->values()];
    }
}
