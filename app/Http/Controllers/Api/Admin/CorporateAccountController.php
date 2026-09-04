<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CorporateAccount;
use App\Models\CorporateAccountEmployee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// CRUD tài khoản khách hàng doanh nghiệp (B2B) + quản lý danh sách nhân viên được phép dùng hạn
// mức — xem App\Models\CorporateAccount cho toàn bộ nghiệp vụ. BelongsToPartner đã gắn trên model
// nên global scope 'partner' tự lọc (super_admin xem toàn bộ, đối tác khác chỉ xem tài khoản B2B
// CỦA MÌNH) — CHỈ áp dụng khi request chạy trong context admin panel (AdminPanelContext::isActive()),
// nên ở ĐÂY (context API thường, KHÔNG phải Filament) global scope đó KHÔNG tự áp dụng — phải tự
// lọc partner_id thủ công trong mọi query bên dưới, cùng cách EmployeeController/CategoryController
// (các API admin khác dùng model có BelongsToPartner) đã làm.
//
// Message JSON tiếng Anh (theo quy ước bắt buộc cho API — xem CouponController làm ví dụ), khác
// các controller admin cũ hơn còn dùng tiếng Việt.
class CorporateAccountController extends Controller
{
    // GET /api/admin/corporate-accounts
    public function index(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $accounts = $this->scopedQuery($admin)
            ->withCount(['activeEmployees'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q2) use ($request) {
                $search = $request->string('search');
                $q2->where('company_name', 'like', "%{$search}%")
                    ->orWhere('tax_code', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($accounts);
    }

    // GET /api/admin/corporate-accounts/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $account = $this->scopedQuery($admin)->find($id);

        if (! $account) {
            return response()->json(['message' => 'Corporate account not found.'], 404);
        }

        return response()->json(['corporate_account' => $account]);
    }

    // POST /api/admin/corporate-accounts
    public function store(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $data = $request->validate($this->rules($request));

        // partner_id: user thường luôn tạo cho đúng đối tác của mình (không cho tự chọn tuỳ ý qua
        // API — khác nhân viên nền tảng/super_admin, những người CÓ THỂ quản lý nhiều đối tác nên
        // bắt buộc phải gửi rõ partner_id). Cùng nguyên tắc CategoryController::store() áp dụng cho
        // 'partner_id bắt buộc với chi nhánh gốc'.
        if (! $admin->isSuperAdmin() && ! $admin->belongsToPlatformPartner()) {
            if (! $admin->partner_id) {
                return response()->json(['message' => 'Your account does not belong to any partner, cannot create a corporate account.'], 403);
            }
            $data['partner_id'] = $admin->partner_id;
        } elseif (empty($data['partner_id'])) {
            return response()->json(['message' => 'partner_id is required.'], 422);
        }

        $data['hours_used_current_cycle'] = 0;
        $data['cycle_start_date']         = $data['cycle_start_date'] ?? now()->format('Y-m-d');
        $data['status']                   = $data['status'] ?? CorporateAccount::STATUS_ACTIVE;

        $account = CorporateAccount::create($data);

        return response()->json(['corporate_account' => $account], 201);
    }

    // PUT/POST /api/admin/corporate-accounts/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $account = $this->scopedQuery($admin)->find($id);

        if (! $account) {
            return response()->json(['message' => 'Corporate account not found.'], 404);
        }

        $data = $request->validate($this->rules($request, true));

        // Không cho đổi partner_id qua update — tài khoản B2B chuyển sang đối tác khác là 1 nghiệp
        // vụ khác hẳn (thực chất phải tất toán kỳ cũ + tạo mới), không phải sửa thông tin thông thường.
        unset($data['partner_id']);

        $account->update($data);

        return response()->json(['corporate_account' => $account->fresh()]);
    }

    // DELETE /api/admin/corporate-accounts/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $account = $this->scopedQuery($admin)->find($id);

        if (! $account) {
            return response()->json(['message' => 'Corporate account not found.'], 404);
        }

        $account->delete();

        return response()->json(['message' => 'Corporate account deleted.']);
    }

    // GET /api/admin/corporate-accounts/{id}/employees
    public function employees(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $account = $this->scopedQuery($admin)->find($id);

        if (! $account) {
            return response()->json(['message' => 'Corporate account not found.'], 404);
        }

        $employees = $account->employees()
            ->with('customer:id,fullname,phone,email')
            ->when(! $request->boolean('include_removed'), fn ($q) => $q->whereNull('removed_at'))
            ->orderByDesc('added_at')
            ->get();

        return response()->json(['employees' => $employees]);
    }

    // POST /api/admin/corporate-accounts/{id}/employees
    public function addEmployee(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $account = $this->scopedQuery($admin)->find($id);

        if (! $account) {
            return response()->json(['message' => 'Corporate account not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_id'    => 'required_without:customer_phone|nullable|string|size:36|exists:customers,id',
            'customer_phone' => 'required_without:customer_id|nullable|string|max:20',
        ]);
        $validator->validate();

        $customer = $request->filled('customer_id')
            ? Customer::find($request->input('customer_id'))
            : Customer::where('phone', trim((string) $request->input('customer_phone')))->first();

        if (! $customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        if ($account->hasActiveEmployee($customer->id)) {
            return response()->json(['message' => 'This customer is already an active employee of this corporate account.'], 422);
        }

        $employee = $account->employees()->create([
            'customer_id' => $customer->id,
            'added_at'    => now(),
        ]);

        return response()->json(['employee' => $employee->load('customer:id,fullname,phone,email')], 201);
    }

    // DELETE /api/admin/corporate-accounts/{id}/employees/{employeeId}
    // "Gỡ" (soft-remove qua removed_at, xem CorporateAccountEmployee::remove()) — KHÔNG xoá bản
    // ghi, giữ lịch sử ai từng thuộc công ty nào (đơn cũ order.corporate_account_id vẫn tra được
    // đúng nhân viên đã đặt dù người đó đã nghỉ việc/bị gỡ sau này).
    public function removeEmployee(Request $request, int $id, int $employeeId): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $account = $this->scopedQuery($admin)->find($id);

        if (! $account) {
            return response()->json(['message' => 'Corporate account not found.'], 404);
        }

        $employee = CorporateAccountEmployee::where('corporate_account_id', $account->id)->find($employeeId);

        if (! $employee) {
            return response()->json(['message' => 'Employee link not found.'], 404);
        }

        $employee->remove();

        return response()->json(['message' => 'Employee removed from corporate account.']);
    }

    // super_admin/nhân viên nền tảng thấy MỌI đối tác; user thường chỉ thấy tài khoản B2B thuộc
    // đúng partner_id của mình — xem ghi chú ở đầu class (global scope BelongsToPartner không tự
    // chạy ở context API thường, chỉ trong Filament admin panel).
    private function scopedQuery(User $admin)
    {
        $query = CorporateAccount::query();

        if ($admin->isSuperAdmin() || $admin->belongsToPlatformPartner()) {
            return $query;
        }

        return $query->where('partner_id', $admin->partner_id);
    }

    private function rules(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'partner_id'          => 'sometimes|nullable|string|size:36|exists:partners,id',
            'company_name'        => "{$required}|string|max:255",
            'tax_code'            => 'sometimes|nullable|string|max:50',
            'contact_name'        => "{$required}|string|max:255",
            'contact_phone'       => "{$required}|string|max:20",
            'contact_email'       => 'sometimes|nullable|email|max:255',
            'billing_address'     => 'sometimes|nullable|string|max:1000',
            'monthly_hour_quota'  => "{$required}|numeric|min:0",
            'cycle_start_date'    => 'sometimes|nullable|date_format:Y-m-d',
            'status'              => 'sometimes|in:active,suspended',
            'notes'               => 'sometimes|nullable|string|max:2000',
        ];
    }
}
