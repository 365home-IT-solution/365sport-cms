<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Employee\Entities\Employee;
use Spatie\Permission\Models\Role;

// Tài khoản đăng nhập của nhân viên/chủ đối tác (màn Filament "Tài khoản nhân viên"). Cùng luật với Filament:
//  - Đối tác/nhân viên chỉ thấy và tạo tài khoản trong CHÍNH đối tác mình; chỉ gán được vai trò do CHÍNH họ tạo (roles.created_by).
//  - Chỉ super_admin tạo được tài khoản "chủ đối tác" (account_type=partner) và chọn đối tác (partner_id).
//  - Tài khoản nhân viên đi kèm một hồ sơ nhân sự (Employee) liên kết (user_id) — dùng cho chấm công và tính lương.
class UserAccountController extends Controller
{
    private const PROTECTED_ROLES = ['super_admin', 'panel_user'];

    // GET /api/admin/users — ?search=, ?role=, ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = $this->visible($request->user())->with('roles:id,name')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim((string) $request->query('search')) . '%';
                $q->where(fn ($w) => $w->where('fullname', 'like', $s)->orWhere('email', 'like', $s)->orWhere('phone', 'like', $s));
            })
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $request->query('role'))))
            ->orderBy('fullname')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $page->through(fn (User $u) => $this->item($u));

        return response()->json($page);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $this->visible($request->user())->with('roles:id,name')->find($id);

        return $user ? response()->json(['data' => $this->item($user, true)]) : response()->json(['message' => 'User not found.'], 404);
    }

    // POST /api/admin/users
    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => 'sometimes|nullable|string|max:20',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'password' => 'required|string|min:8|max:100',
            'roles' => 'required|array|min:1|max:10',
            'roles.*' => 'string|max:255',
            'account_type' => 'sometimes|in:employee,partner',
            'partner_id' => 'sometimes|nullable|uuid|exists:partners,id',
            // Hồ sơ nhân sự đi kèm (chỉ tài khoản nhân viên)
            'department_id' => 'sometimes|nullable|integer|exists:departments,id',
            'position_id' => 'sometimes|nullable|integer|exists:positions,id',
            'salary_type_id' => 'sometimes|nullable|integer|exists:salary_types,id',
            'salary_template_id' => 'sometimes|nullable|integer|exists:salary_templates,id',
            'base_amount' => 'sometimes|nullable|integer|min:0',
            'hire_date' => 'sometimes|nullable|date',
        ]);

        $isEmployee = $actor->isSuperAdmin() ? (($data['account_type'] ?? 'employee') === 'employee') : true;
        $partnerId = $actor->isSuperAdmin() ? ($data['partner_id'] ?? null) : $actor->partner_id;
        if (! $partnerId) {
            return response()->json(['message' => 'partner_id is required.'], 422);
        }

        $roles = $isEmployee ? $this->resolveRoles($actor, $data['roles']) : Role::where('name', 'partner')->where('guard_name', 'web')->get();
        if ($roles === null || $roles->isEmpty()) {
            return response()->json(['message' => 'One or more roles are not allowed.'], 422);
        }

        // Hồ sơ nhân sự trùng SĐT/email: chỉ tái sử dụng hồ sơ ĐÃ NGHỈ của cùng đối tác (tài khoản cũ đã xoá); trùng với người đang làm
        // hoặc với đối tác khác thì từ chối rõ ràng thay vì để CSDL báo lỗi trùng khoá.
        $reuse = null;
        if ($isEmployee) {
            $matches = Employee::withoutGlobalScopes()->where(function ($q) use ($data) {
                $q->where('email', $data['email']);
                if (! empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
            })->get();
            if ($matches->count() > 1 || $matches->contains(fn ($e) => $e->user_id !== null || $e->partner_id !== $partnerId)) {
                return response()->json(['message' => 'An employee with this phone number or email already exists.'], 422);
            }
            $reuse = $matches->first();
        }

        $user = DB::transaction(function () use ($data, $actor, $partnerId, $roles, $isEmployee, $reuse) {
            $user = User::create([
                'fullname' => $data['fullname'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null, 'password' => $data['password'],
                'partner_id' => $partnerId, 'created_by' => $actor->getKey(), 'email_verified_at' => now(),
            ]);
            $user->syncRoles($roles);

            if ($isEmployee) {
                $profile = [
                    'partner_id' => $partnerId, 'user_id' => $user->getKey(), 'name' => $data['fullname'],
                    'phone' => $data['phone'] ?? null, 'email' => $data['email'], 'status' => true,
                ] + collect($data)->only(['department_id', 'position_id', 'salary_type_id', 'salary_template_id', 'base_amount', 'hire_date'])->all();

                $reuse
                    ? $reuse->update($profile)
                    : Employee::withoutGlobalScopes()->create($profile + ['created_by' => $actor->getKey()]);
            }

            return $user;
        });

        return response()->json(['data' => $this->item($user->fresh('roles:id,name'), true)], 201);
    }

    // PUT|PATCH|POST /api/admin/users/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = $this->visible($actor)->find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->hasRole('super_admin') && ! $actor->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'fullname' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'phone' => 'sometimes|nullable|string|max:20',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'password' => 'sometimes|string|min:8|max:100',
            'roles' => 'sometimes|array|min:1|max:10',
            'roles.*' => 'string|max:255',
        ]);

        $roles = null;
        if (isset($data['roles'])) {
            $roles = $this->resolveRoles($actor, $data['roles']);
            if ($roles === null || $roles->isEmpty()) {
                return response()->json(['message' => 'One or more roles are not allowed.'], 422);
            }
        }

        DB::transaction(function () use ($user, $data, $roles) {
            $user->update(collect($data)->except('roles')->all());
            if ($roles) {
                $user->syncRoles($roles);
            }
            if ($employee = Employee::withoutGlobalScopes()->where('user_id', $user->getKey())->first()) {
                $employee->update(collect($data)->only(['phone', 'email'])->all() + (isset($data['fullname']) ? ['name' => $data['fullname']] : []));
            }
        });

        return response()->json(['data' => $this->item($user->fresh('roles:id,name'), true)]);
    }

    // DELETE /api/admin/users/{id}
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = $this->visible($actor)->find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->getKey() === $actor->getKey()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }
        if ($user->hasRole('super_admin')) {
            return response()->json(['message' => 'Super admin accounts cannot be deleted.'], 422);
        }

        try {
            DB::transaction(function () use ($user) {
                // Hồ sơ nhân sự được giữ lại (còn dùng cho lương đã chốt) nhưng ngừng làm việc và gỡ liên kết đăng nhập.
                Employee::withoutGlobalScopes()->where('user_id', $user->getKey())->update(['user_id' => null, 'status' => false]);
                $user->tokens()->delete();
                $user->syncRoles([]);
                $user->delete();
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') !== '23000') {
                throw $e;
            }

            return response()->json(['message' => 'This account still has related records and cannot be deleted.'], 422);
        }

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private function visible(User $actor)
    {
        $q = User::query();

        return $actor->isSuperAdmin() ? $q : $q->where('partner_id', $actor->partner_id);
    }

    /** Vai trò được phép gán theo tên; null nếu có tên không hợp lệ/không được phép (giống bộ lọc ở form Filament). */
    private function resolveRoles(User $actor, array $names)
    {
        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', array_unique($names))->whereNotIn('name', self::PROTECTED_ROLES)
            ->when(! $actor->isSuperAdmin(), fn ($q) => $q->where('created_by', $actor->getKey()))->get();

        return $roles->count() === count(array_unique($names)) ? $roles : null;
    }

    private function item(User $u, bool $detail = false): array
    {
        $row = [
            'id' => $u->getKey(), 'fullname' => $u->fullname, 'email' => $u->email, 'phone' => $u->phone, 'partner_id' => $u->partner_id,
            'date_of_birth' => $u->date_of_birth?->toDateString(), 'roles' => $u->roles->pluck('name')->values(), 'created_at' => $u->created_at?->toIso8601String(),
        ];
        if ($detail && ($e = Employee::withoutGlobalScopes()->where('user_id', $u->getKey())->first())) {
            $row['employee'] = ['id' => $e->id, 'employee_code' => $e->employee_code, 'department_id' => $e->department_id, 'position_id' => $e->position_id, 'status' => (bool) $e->status];
        }

        return $row;
    }
}
