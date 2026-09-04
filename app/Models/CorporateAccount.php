<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToPartner;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\Payment\Entities\Order;

// Tài khoản khách hàng doanh nghiệp (B2B) — 1 công ty đăng ký với 1 đối tác (partner_id), được cấp
// 1 hạn mức GIỜ SÂN/tháng (monthly_hour_quota) dùng chung cho nhiều nhân viên (employees, mỗi
// người là 1 Customer bình thường được LIÊN KẾT vào đây qua CorporateAccountEmployee, KHÔNG phải 1
// loại tài khoản khác). Nhân viên đặt sân qua Admin\BookingController::store() với
// corporate_account_id -> trừ thẳng vào hours_used_current_cycle, đơn đánh dấu 'paid' ngay (không
// thu tiền per-booking) — công ty trả tiền 1 lần/kỳ qua hoá đơn tổng hợp (xem
// App\Filament\Resources\CorporateAccountResource, action "Xuất hoá đơn"/"Chốt kỳ").
//
// QUYẾT ĐỊNH THIẾT KẾ (xem thêm ghi chú tại consumeHours()): KHÔNG tái dùng App\Models\
// PackageRedemption/CustomerPackage cho cơ chế trừ hạn mức — PackageRedemption gắn CỨNG vào 1
// customer_package_id (1-1 với 1 khách) và luôn trừ NGUYÊN 1 buổi (sessions_remaining->decrement(),
// đơn vị đếm, không phải giờ phân số), trong khi CorporateAccount cần: (1) NHIỀU Customer khác
// nhau cùng rút từ 1 hồ chứa chung, và (2) đơn vị GIỜ THẬP PHÂN (1 khung giờ 1.5h, thuê ngày cả
// chục giờ...), không phải "1 buổi = 1 đơn vị". Ép PackageRedemption gánh thêm 2 khác biệt này sẽ
// phải sửa lại field/logic lõi của nó (rủi ro hồi quy cho toàn bộ luồng "Dùng gói" hiện có) — chọn
// cách viết cơ chế trừ RIÊNG (hours_used_current_cycle + hasQuotaRemaining()/consumeHours() ở
// dưới) nhưng vẫn COPY NGUYÊN tinh thần khoá/transaction đã được kiểm chứng ở CustomerPackage::
// redeem() (lockForUpdate() bên trong DB::transaction() do CALLER mở, tăng/giảm bằng increment()
// nguyên tử thay vì đọc-sửa-ghi). MVP này chưa có bảng "nhật ký redemption" riêng cho corporate
// (khác PackageRedemption có bảng log) — Order::corporate_account_id + Order.created_at đã đủ để
// liệt kê "đơn nào đã trừ vào kỳ nào" cho nhu cầu xuất hoá đơn hiện tại; nếu sau này cần hoàn giờ
// khi huỷ đơn corporate, nên bổ sung bảng log tương tự PackageRedemption lúc đó.
class CorporateAccount extends Model
{
    use BelongsToPartner, LogsAuditTrail, SoftDeletes;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'partner_id',
        'company_name',
        'tax_code',
        'contact_name',
        'contact_phone',
        'contact_email',
        'billing_address',
        'monthly_hour_quota',
        'hours_used_current_cycle',
        'cycle_start_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'monthly_hour_quota'       => 'decimal:2',
        'hours_used_current_cycle' => 'decimal:2',
        'cycle_start_date'         => 'date',
    ];

    protected function auditLabel(): string
    {
        return $this->company_name ?: ('#' . $this->getKey());
    }

    public function employees(): HasMany
    {
        return $this->hasMany(CorporateAccountEmployee::class);
    }

    // Nhân viên ĐANG hoạt động (chưa bị gỡ) — xem migration create_corporate_account_employees_table.
    public function activeEmployees(): HasMany
    {
        return $this->employees()->whereNull('removed_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // 1 Customer (theo customer_id) có đang là nhân viên HOẠT ĐỘNG của công ty này không — dùng ở
    // Admin\BookingController::store() trước khi cho phép trừ vào hạn mức công ty (không cho nhân
    // viên đã nghỉ/không thuộc công ty này dùng ké hạn mức của công ty khác).
    public function hasActiveEmployee(string $customerId): bool
    {
        return $this->activeEmployees()->where('customer_id', $customerId)->exists();
    }

    public function remainingHours(): float
    {
        return max(0.0, (float) $this->monthly_hour_quota - (float) $this->hours_used_current_cycle);
    }

    public function hasQuotaRemaining(float $hoursNeeded): bool
    {
        return $this->isActive() && $this->remainingHours() + 0.0001 >= $hoursNeeded;
    }

    /**
     * Trừ $hours vào hạn mức kỳ hiện tại — PHẢI gọi bên trong 1 DB::transaction() đã lockForUpdate()
     * bản ghi này từ TRƯỚC (giống hợp đồng của CustomerPackage::redeem() — caller chịu trách nhiệm
     * khoá + mở transaction, method này chỉ làm đúng 1 việc: tăng số đã dùng). increment() ở đây là
     * 1 câu UPDATE nguyên tử (không đọc-sửa-ghi lại giá trị PHP đang giữ), an toàn dù có lockForUpdate
     * hay không, nhưng lockForUpdate() bên ngoài vẫn cần thiết để hasQuotaRemaining() (đọc TRƯỚC khi
     * gọi hàm này) không bị 2 request cùng lúc đọc thấy còn hạn mức rồi cùng trừ vượt quá.
     */
    public function consumeHours(float $hours): void
    {
        $this->increment('hours_used_current_cycle', $hours);
    }

    /**
     * "Chốt kỳ" — reset số giờ đã dùng về 0 và đẩy cycle_start_date sang kỳ kế tiếp (đúng 1 tháng
     * kể từ ngày bắt đầu kỳ hiện tại, KHÔNG phải từ hôm nay — tránh trôi ngày chốt kỳ nếu admin bấm
     * trễ vài hôm). Hành động CÓ CHỦ Ý của con người (xem CorporateAccountResource action "Chốt
     * kỳ") — không tự động chạy theo cron, vì admin cần xuất hoá đơn trước khi số liệu bị reset.
     */
    public function closeCycle(): void
    {
        DB::transaction(function () {
            $locked = self::query()->lockForUpdate()->findOrFail($this->getKey());

            $locked->update([
                'hours_used_current_cycle' => 0,
                'cycle_start_date'         => $locked->cycle_start_date->copy()->addMonthNoOverflow(),
            ]);

            $this->setRawAttributes($locked->getAttributes());
        });
    }
}
