<?php

namespace Modules\Payment\Entities;

use App\Models\Concerns\BelongsToPartner;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Category\Entities\Category;
use Modules\Zns\App\Models\ZnsNotification;
use Illuminate\Support\Facades\DB;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Modules\Payment\App\Models\OrderService;
use Modules\Coach\App\Models\OrderCoach;
class Order extends Model implements Eventable
{
    use HasFactory, BelongsToPartner;

    protected $fillable = [
        'partner_id',
        'created_by',
        'order_code',
        'amount',
        'surcharge',
        'full_amount',
        'deposit_percent',
        'deposit_paid_amount',
        'coupon_code',
        'coupon_codes',
        // 'coupon_discount' TRƯỚC ĐÂY thiếu trong $fillable — Order::create(['coupon_discount' =>
        // ...]) ở ProductDetail.php ÂM THẦM bị Laravel bỏ qua field này (mass assignment), số tiền
        // giảm giá theo mã coupon chưa từng được lưu thật xuống DB dù code gọi đúng. Thêm cả
        // 'loyalty_discount' (mới, xem migration cùng ngày) — cần lưu riêng số tiền giảm giá khách
        // quen để admin đối chiếu lại đúng giá đã thấy lúc đặt (total-amount-card.blade.php).
        'coupon_discount',
        'loyalty_discount',
        'deposit_paid_at',
        'paid_at',
        'remaining_paid_at',
        'remaining_payment_method',
        'remaining_payos_code',
        'remaining_checkout_url',
        'current_payos_code',
        'description',
        'short_description',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'buyer_address',
        'ward_name',
        'district_name',
        'province_name',
        'payment_method',
        // Luồng "chuyển khoản ngân hàng thủ công" (payment_method='bank_transfer') — xem
        // ProductDetail::processBankTransferPayment() + OrderTable's "Xác nhận chuyển khoản".
        'payment_proof',
        'bank_transfer_status',
        'bank_transfer_confirmed_at',
        'bank_transfer_confirmed_by',
        'shipping_method',
        'is_freeship',
        'expired_at',
        'checkout_url',
        'status',
        'note_for_admin',
        'deposit_room',
        'cccd_front',
        'cccd_back',
        'cccd_front_2',
        'cccd_back_2',
        'cccd_data',
        'cccd_data_2',
        'buyer_phone_2',
        // Khách thứ 2 cùng lưu trú qua đêm (Luật Cư trú yêu cầu khai báo đủ từng người khi ở qua
        // đêm 2 khách) — xem CccdDeclarationService.
        'cccd_front_2',
        'cccd_back_2',
        'cccd_data_2',
        'buyer_phone_2',
        'guest_count',
        // Tên đội/nhóm — tuỳ chọn, dùng khi khách đặt sân theo dạng đội/nhóm (vd giải đấu nội bộ,
        // CLB) thay vì chỉ 1 khách lẻ. Không ảnh hưởng luồng đặt sân hiện có nếu để trống.
        'team_name',
        'category_id',
        'user_id',
        'money_deposit',
        'exclude_from_stats',
        'checked_in_at',
        'checked_out_at',
        'order_status',
        'customer_id',
        'qr_code',
        'remaining_qr_code',
        'device_token',
        // IP trình duyệt lúc tạo đơn — dùng để chặn phá hoại (đặt liên tục nhiều lần đè khung giờ),
        // xem App\Services\BookingRateLimiter. KHÔNG dùng buyer_phone làm khoá chặn vì SĐT ở luồng
        // đặt sân KHÔNG được xác minh OTP, đổi số vô hạn để né giới hạn.
        'creator_ip',
        'extra_charge_amount',
        'extra_charge_payos_code',
        'extra_charge_checkout_url',
        'extra_charge_qr_code',
        'extra_charge_payment_method',
        'extra_charge_paid_at',
        'extra_charge_expired_at',
        'extra_refund_amount',
        'extra_refund_method',
        'extra_refund_paid_at',
        'settled_adjustment_total',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        // Khách đã thanh toán (paid/deposit) nhưng KHÔNG đến nhận sân — xem migration
        // 2026_09_03_000004_add_no_show_to_orders_table + Order::markNoShow().
        'no_show_at',
        'no_show_marked_by',
        // Hoãn/hoàn tiền do thời tiết (sân ngoài trời) — xem migration
        // add_weather_policy_to_orders_table và App\Services\WeatherPolicyService.
        'weather_affected_at',
        'weather_resolution',
        'order_type',
        'package_plan_id',
        // Đơn thanh toán qua hạn mức tài khoản doanh nghiệp (App\Models\CorporateAccount) thay vì
        // khách tự trả — xem migration add_corporate_account_id_to_orders_table +
        // App\Http\Controllers\Api\Admin\BookingController::store().
        'corporate_account_id',
        // Đặt sân định kỳ (lặp lại N tuần) — xem migration add_recurring_group_to_orders_table +
        // App\Http\Controllers\Api\Admin\RecurringBookingController.
        'recurring_group_id',
        'recurring_occurrence_number',
    ];

    protected $casts = [
        'cccd_data'              => 'array',
        'cccd_data_2'            => 'array',
        'coupon_codes'           => 'array',
        'deposit_paid_at'        => 'datetime',
        'paid_at'                => 'datetime',
        'remaining_paid_at'      => 'datetime',
        'extra_charge_paid_at'    => 'datetime',
        'extra_charge_expired_at' => 'datetime',
        'extra_refund_paid_at'    => 'datetime',
        'exclude_from_stats'     => 'boolean',
        'checked_in_at'          => 'datetime',
        'checked_out_at'         => 'datetime',
        'cancelled_at'           => 'datetime',
        'no_show_at'             => 'datetime',
        'weather_affected_at'    => 'datetime',
        'bank_transfer_confirmed_at' => 'datetime',
    ];

    // % hoàn lại khi khách TỰ huỷ đơn online — áp trên số tiền ĐÃ THANH TOÁN thật (không phải tổng
    // giá trị đơn), khớp đúng câu chữ đã hiển thị cho khách lúc đặt sân (xem
    // Chính sách khách tự huỷ: hoàn đúng 70% số đã thanh toán; 30% là phí huỷ và xử lý.
    public const CUSTOMER_CANCEL_REFUND_RATE = 0.70;

    // Trạng thái đơn KHÔNG còn giữ sân/HLV/vật tư (huỷ QR, thanh toán thất bại, huỷ, hoàn tiền) —
    // khung giờ của các đơn này phải hiện "Trống" ở MỌI lưới lịch (client lẫn admin).
    public const SLOT_RELEASED_STATUSES = ['cancelled', 'cancelled_payment', 'failed', 'refunded'];

    // Đơn còn thực sự chiếm chỗ: không nằm trong SLOT_RELEASED_STATUSES và khách chưa tự huỷ.
    public function scopeHoldingSlot($query)
    {
        return $query->whereNotIn('status', self::SLOT_RELEASED_STATUSES)->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return ! is_null($this->cancelled_at);
    }

    // Đơn đã được xử lý hoãn/hoàn tiền do thời tiết (xem App\Services\WeatherPolicyService) —
    // weather_affected_at là NGUỒN SỰ THẬT DUY NHẤT, giống cách isCancelled() dùng cancelled_at.
    public function isWeatherAffected(): bool
    {
        return ! is_null($this->weather_affected_at);
    }

    public function isNoShow(): bool
    {
        return ! is_null($this->no_show_at);
    }

    /**
     * Đánh dấu khách ĐÃ ĐẶT SÂN (paid/deposit) nhưng KHÔNG đến nhận sân — KHÁC hẳn huỷ đơn
     * (cancelled_at/cancelled_by ở trên: khách/admin CHỦ ĐỘNG huỷ TRƯỚC giờ nhận, giải phóng khung
     * giờ cho người khác). No-show là đơn VẪN giữ chỗ nguyên vẹn tới hết giờ mà khách không xuất
     * hiện — không giải phóng khung giờ, không hoàn tiền, chỉ là NHÃN thống kê để nhận diện khách
     * hay bùng kèo. CỐ Ý không đổi cột `status` — cùng triết lý với cancelled_at, giữ nguyên
     * paid/deposit để báo cáo doanh thu không bị lệch.
     *
     * Logic gộp DUY NHẤT ở đây (không viết lại riêng cho Filament action lẫn API admin) — xem
     * Modules\Payment\App\Filament\Resources\OrderResource\Tables\OrderTable.php (action
     * 'mark_no_show') và App\Http\Controllers\Api\Admin\OrderController::markNoShow(), cả 2 chỉ tự
     * lo phần message/hiển thị theo ngôn ngữ riêng của từng nơi (Filament = tiếng Việt, JSON API =
     * tiếng Anh) dựa trên 'reason' trả về.
     *
     * Chỉ đơn ĐÃ THANH TOÁN thật (paid/deposit) mới đánh dấu được — đơn pending/cancelled/failed
     * chưa từng thật sự "giữ chỗ" nên no-show với nó vô nghĩa. Khoá đơn (lockForUpdate) + kiểm tra
     * lại no_show_at bên trong transaction để chặn double-click/2 tab cùng đánh dấu 1 đơn ra 2 lần
     * tăng đếm Customer.no_show_count.
     *
     * @param  User|string|null  $admin  Admin thao tác — null/'system' nếu về sau có job tự động
     *                                   quét đơn quá giờ nhận sân chưa check-in (CHƯA xây ở bản này).
     * @return array{ok: true}|array{ok: false, reason: 'already_marked'|'not_payable'}
     */
    public function markNoShow(User|string|null $admin = null): array
    {
        return DB::transaction(function () use ($admin): array {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if (! is_null($locked->no_show_at)) {
                return ['ok' => false, 'reason' => 'already_marked'];
            }

            if (! in_array($locked->status, ['paid', 'deposit'], true)) {
                return ['ok' => false, 'reason' => 'not_payable'];
            }

            $locked->update([
                'no_show_at'        => now(),
                'no_show_marked_by' => $admin instanceof User ? (string) $admin->id : ($admin ?: 'system'),
            ]);

            // Tăng đếm no-show của khách — best-effort, không chặn nếu đơn không gắn customer_id
            // (đơn vãng lai tạo tay không bắt buộc chọn khách có sẵn).
            if ($locked->customer_id) {
                Customer::where('id', $locked->customer_id)->increment('no_show_count');
            }

            $this->setRawAttributes($locked->getAttributes());

            return ['ok' => true];
        });
    }

    /**
     * Khách chỉ được TỰ huỷ khi: đơn đã thanh toán (cọc/đủ) — đơn 'pending' chưa thanh toán thì
     * chỉ cần bỏ qua QR/không thanh toán là tự hết hạn, không cần "huỷ"; CHƯA từng huỷ; và khung
     * giờ/ngày ĐẦU TIÊN trong đơn CHƯA bắt đầu (đã bắt đầu/đã qua thì huỷ không còn ý nghĩa, sân
     * đã/đang được dùng).
     */
    public function canBeCancelledByCustomer(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        if (! in_array($this->status, ['paid', 'deposit'], true)) {
            return false;
        }

        $earliestCheckin = $this->items->pluck('checkin_date')->filter()->min();

        return $earliestCheckin && $earliestCheckin->isFuture();
    }

    /**
     * Số tiền hoàn nếu khách huỷ NGAY BÂY GIỜ — 70% số tiền ĐÃ thanh toán thật (full_amount đã
     * phản ánh đúng số tiền thật đã thu, kể cả đơn deposit — xem cột full_amount comment trong
     * migration create_orders_table: "amount = tiền cọc cần thanh toán", full_amount = tổng giá
     * trị đơn; số ĐÃ THU thật với đơn deposit là amount, với đơn paid là full_amount).
     */
    public function computeCustomerCancelRefundAmount(): int
    {
        $paidSoFar = $this->status === 'deposit'
            ? (int) ($this->amount ?? 0)
            : (int) ($this->full_amount ?? $this->amount ?? 0);

        return (int) round($paidSoFar * self::CUSTOMER_CANCEL_REFUND_RATE);
    }
    protected $with = ['items'];
    protected $appends = ['payment_status'];

    // payment_status: trạng thái thanh toán (chưa trả/cọc/trả đủ/hoàn tiền), suy ra từ cột `status`.
    public const PAYMENT_STATUS_LABELS = [
        'unpaid'   => 'Chưa trả',
        'deposit'  => 'Đã đặt cọc',
        'paid'     => 'Đã trả đủ',
        'refunded' => 'Đã hoàn tiền',
    ];

    // order_status: vòng đời đơn (chờ → nhận sân → đang ở → trả sân).
    public const ORDER_STATUS_LABELS = [
        'pending'     => 'Chờ nhận sân',
        'checked_in'  => 'Đã nhận sân',
        'staying'     => 'Đang ở',
        'checked_out' => 'Đã trả sân',
    ];

    private const PAYMENT_STATUS_MAP = [
        'pending'           => 'unpaid',
        'failed'            => 'unpaid',
        'cancelled_payment' => 'unpaid',
        'deposit'           => 'deposit',
        'paid'              => 'paid',
        'refunded'          => 'refunded',
    ];

    public function getPaymentStatusAttribute(): string
    {
        return self::PAYMENT_STATUS_MAP[$this->attributes['status'] ?? 'pending'] ?? 'unpaid';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? self::PAYMENT_STATUS_LABELS['unpaid'];
    }

    public function getOrderStatusLabelAttribute(): string
    {
        return self::ORDER_STATUS_LABELS[$this->order_status ?? 'pending'] ?? self::ORDER_STATUS_LABELS['pending'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            // Sport bookings no longer collect identity-card data. Keep legacy
            // columns nullable during the transition, but never persist new data.
            foreach (['cccd_front', 'cccd_back', 'cccd_front_2', 'cccd_back_2', 'cccd_data', 'cccd_data_2', 'buyer_phone_2'] as $field) {
                $order->{$field} = null;
            }
        });

        static::creating(function (self $order): void {
            if (empty($order->order_code)) {
                do {
                    $code = intval(substr(strval(microtime(true) * 10000), -6));
                } while (self::where('order_code', $code)->exists());
                $order->order_code = (string) $code;
            }

            if (empty($order->user_id) && auth()->check()) {
                $order->user_id = (string) auth()->id();
            }
        });

        // order_items KHÔNG có FK cascade (chỉ unsignedBigInteger + index, không ràng buộc khoá
        // ngoại) — xoá Order trước đây chỉ xoá đúng 1 dòng orders, để lại order_items MỒ CÔI
        // trong DB. Vì khung giờ/ngày đã đặt của sân được TÍNH TỪ order_items còn tồn tại (không
        // phải từ 1 bảng "slot" riêng), các order_item mồ côi này vẫn khiến sân hiện "đã đặt",
        // không thể tạo lại đúng khung giờ đó dù đơn đã bị xoá. Phải tự tay dọn khi Order bị xoá.
        static::deleting(function (self $order): void {
            // Chụp lại (ngày, timeslot_id)/khoảng ngày của items TRƯỚC khi xoá — dùng để bắn
            // realtime "giải phóng" NGAY sau khi xoá xong, để khách đang mở book.blade.php/
            // product-detail.blade.php của đúng sân này thấy khung giờ trống lại tức thì thay
            // vì phải đợi tải lại trang (trước đây xoá đơn không bắn gì cả — ĐÚNG cách
            // App\Http\Controllers\Api\Admin\OrderController::updateOrder() đã làm cho luồng SỬA
            // đơn, áp dụng lại cho luồng XOÁ ở đây).
            $itemsByProduct = $order->items()->whereNotNull('product_id')->get()->groupBy('product_id');

            // Trả lại dụng cụ đang mượn (nếu có order_service nào gắn warehouse_item) TRƯỚC khi
            // xoá order_services — $order->services()->delete() bên dưới là DELETE hàng loạt qua
            // query builder (không phải Eloquent::delete() từng dòng), không tự bắn được sự kiện gì
            // để ta xử lý kho, nên phải gọi tay ở đây.
            app(\Modules\Warehouse\App\Services\WarehouseRentalService::class)->releaseForOrder($order);

            $order->items()->delete();
            $order->services()->delete();

            self::broadcastSlotsReleasedByProduct($itemsByProduct);
            self::notifyWaitlistByProduct($itemsByProduct);
        });

        // Thưởng coupon cho NGƯỜI GIỚI THIỆU khi khách được giới thiệu có đơn ĐẦU TIÊN chuyển
        // sang 'paid' — cố tình đợi tới lúc thanh toán thành công (không phải lúc đăng ký) để
        // chặn lạm dụng tạo tài khoản ảo lấy thưởng. Xem ZaloOtpController::register() (chỗ gán
        // referred_by_customer_id và cấp coupon NGAY cho người được giới thiệu).
        static::updated(function (self $order): void {
            if ($order->wasChanged('status') && $order->status === 'paid') {
                self::rewardReferrerIfEligible($order);
            }

            // Đơn chuyển sang trạng thái KHÔNG còn khả năng diễn ra (thất bại/huỷ/hoàn tiền) —
            // trả lại dụng cụ đang mượn (nếu có) về kho ngay, không đợi tới lượt dọn tự động.
            if ($order->wasChanged('status') && in_array($order->status, self::SLOT_RELEASED_STATUSES, true)) {
                app(\Modules\Warehouse\App\Services\WarehouseRentalService::class)->releaseForOrder($order);
            }
        });
    }

    private static function rewardReferrerIfEligible(self $order): void
    {
        if (empty($order->customer_id)) {
            return;
        }

        $settings = app(\App\Settings\GeneralSettings::class);
        if (! $settings->referral_enabled) {
            return;
        }

        $customer = Customer::find($order->customer_id);
        if (! $customer || empty($customer->referred_by_customer_id) || $customer->referrer_rewarded_at) {
            return;
        }

        $hasEarlierPaidOrder = self::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->where('id', '!=', $order->id)
            ->exists();
        if ($hasEarlierPaidOrder) {
            return;
        }

        $referrer = Customer::find($customer->referred_by_customer_id);
        if (! $referrer) {
            return;
        }

        \Modules\Promotion\App\Models\Coupon::create([
            'code'        => 'REF-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'name'        => 'Thưởng giới thiệu bạn bè',
            'description' => 'Thưởng dành cho khách đã giới thiệu bạn bè đặt sân thành công.',
            'type'        => 'percentage',
            'value'       => $settings->referral_reward_referrer_percent,
            'apply_type'  => 'all_rooms',
            'usage_limit' => 1,
            'used_count'  => 0,
            'start_at'    => now(),
            'end_at'      => now()->addDays($settings->referral_reward_expiry_days),
            'is_active'   => true,
            'customer_id' => $referrer->id,
        ]);

        $customer->update(['referrer_rewarded_at' => now()]);
    }

    /**
     * Bắn realtime "giải phóng" cho 1 tập order_items đã nhóm theo product_id — dùng chung cho
     * luồng XOÁ đơn (booted() ở trên, items sắp bị xoá) LẪN luồng khách TỰ HUỶ đơn
     * (OrderCancellationService::cancelByCustomer(), items vẫn còn nguyên trong DB, chỉ không còn
     * tính là "chiếm chỗ" nữa nhờ cancelled_at — xem applyActiveOrderConstraint() bên
     * OrderForm.php và các nơi lọc order tương tự khác).
     */
    public static function broadcastSlotsReleasedByProduct(\Illuminate\Support\Collection $itemsByProduct): void
    {
        if ($itemsByProduct->isEmpty()) {
            return;
        }

        $realtimeService = app(\App\Services\SlotRealtimeService::class);

        foreach ($itemsByProduct as $productId => $items) {
            $product = \Modules\Product\App\Models\Product::find($productId);
            if (! $product) {
                continue;
            }

            if ((int) $product->styles === 1) {
                // Sân "khung giờ" — order_items không lưu timeslot_id trực tiếp nên suy
                // ngược qua giờ bắt đầu (checkin_date) khớp với RoomTimeSlot lặp lại (date IS
                // NULL) của sân, best-effort cho mục đích hiển thị realtime.
                $recurringSlotsByStartTime = $product->roomTimeSlots()
                    ->whereNull('date')
                    ->with('timeSlot')
                    ->get()
                    ->filter(fn ($rts) => $rts->timeSlot)
                    ->keyBy(fn ($rts) => $rts->timeSlot->start_time);

                $items
                    ->filter(fn ($item) => $item->checkin_date)
                    ->map(function ($item) use ($recurringSlotsByStartTime) {
                        $rts = $recurringSlotsByStartTime->get($item->checkin_date->format('H:i:s'));

                        return $rts ? ['date' => $item->checkin_date->toDateString(), 'timeslot_id' => $rts->timeslot_id] : null;
                    })
                    ->filter()
                    ->groupBy('date')
                    ->each(fn ($slots, $date) => $realtimeService->broadcastSlotsAvailable(
                        (string) $productId,
                        $date,
                        collect($slots)->pluck('timeslot_id')->values()->toArray()
                    ));
            } else {
                // Sân "theo ngày" — giải phóng khoảng ngày rộng nhất (min checkin - max
                // checkout) trong số các item của sân này.
                $checkin  = $items->pluck('checkin_date')->filter()->min();
                $checkout = $items->pluck('checkout_date')->filter()->max();
                if ($checkin && $checkout) {
                    $realtimeService->broadcastDailyReleased((string) $productId, $checkin->toDateString(), $checkout->toDateString());
                }
            }
        }
    }

    /**
     * Báo cho khách đang chờ (App\Models\SlotWaitlist) khi khung/ngày họ chờ vừa trống — gọi
     * NGAY SAU broadcastSlotsReleasedByProduct() ở mọi nơi hủy/xoá đơn. Tái dùng đúng logic suy
     * ngược timeslot từ checkin_date ở method trên để 2 nơi luôn xác định "khung nào vừa trống"
     * giống hệt nhau.
     */
    public static function notifyWaitlistByProduct(\Illuminate\Support\Collection $itemsByProduct): void
    {
        if ($itemsByProduct->isEmpty()) {
            return;
        }

        $notifier = app(\App\Services\NotificationFcmService::class);

        foreach ($itemsByProduct as $productId => $items) {
            $product = \Modules\Product\App\Models\Product::find($productId);
            if (! $product) {
                continue;
            }

            if ((int) $product->styles === 1) {
                $recurringSlotsByStartTime = $product->roomTimeSlots()
                    ->whereNull('date')
                    ->with('timeSlot')
                    ->get()
                    ->filter(fn ($rts) => $rts->timeSlot)
                    ->keyBy(fn ($rts) => $rts->timeSlot->start_time);

                $releasedSlots = $items
                    ->filter(fn ($item) => $item->checkin_date)
                    ->map(function ($item) use ($recurringSlotsByStartTime) {
                        $rts = $recurringSlotsByStartTime->get($item->checkin_date->format('H:i:s'));

                        return $rts ? ['date' => $item->checkin_date->toDateString(), 'timeslot_id' => $rts->timeslot_id] : null;
                    })
                    ->filter()
                    ->unique(fn ($slot) => $slot['date'] . '-' . $slot['timeslot_id']);

                foreach ($releasedSlots as $slot) {
                    $waitlists = \App\Models\SlotWaitlist::where('product_id', $productId)
                        ->where('date', $slot['date'])
                        ->where('timeslot_id', $slot['timeslot_id'])
                        ->whereNull('notified_at')
                        ->with('customer')
                        ->get();

                    $dateLabel = \Carbon\Carbon::parse($slot['date'])->format('d/m/Y');

                    foreach ($waitlists as $waitlist) {
                        if (! $waitlist->customer) {
                            continue;
                        }

                        // Cột `type` trên notification_fcm là ENUM cố định ('manual'|'booking'|
                        // 'checkin_reminder'|'checkout_warning') — 'booking' là giá trị phù hợp
                        // nhất, phân biệt tiếp bằng 'subtype' trong data để app biết deep-link.
                        $notifier->sendToCustomer(
                            $waitlist->customer,
                            'Khung giờ đã trống!',
                            "{$product->name} - {$dateLabel} đã có khách huỷ, đặt ngay!",
                            'booking',
                            [
                                'subtype'     => 'slot_waitlist_available',
                                'product_id'  => (string) $productId,
                                'date'        => $slot['date'],
                                'timeslot_id' => (string) $slot['timeslot_id'],
                            ]
                        );

                        $waitlist->update(['notified_at' => now()]);
                    }
                }
            } else {
                $checkin  = $items->pluck('checkin_date')->filter()->min();
                $checkout = $items->pluck('checkout_date')->filter()->max();
                if (! $checkin || ! $checkout) {
                    continue;
                }

                $waitlists = \App\Models\SlotWaitlist::where('product_id', $productId)
                    ->whereNull('notified_at')
                    ->whereNotNull('checkin_date')
                    ->where('checkin_date', '<', $checkout)
                    ->where('checkout_date', '>', $checkin)
                    ->with('customer')
                    ->get();

                foreach ($waitlists as $waitlist) {
                    if (! $waitlist->customer) {
                        continue;
                    }

                    $notifier->sendToCustomer(
                        $waitlist->customer,
                        'Đã có chỗ trống!',
                        "{$product->name} vừa có khách huỷ, đặt ngay!",
                        'booking',
                        ['subtype' => 'slot_waitlist_available', 'product_id' => (string) $productId]
                    );

                    $waitlist->update(['notified_at' => now()]);
                }
            }
        }
    }

    // Số tiền THỰC TẾ cần thu ngay bây giờ (qua PayOS...) — 'full_amount' luôn là TỔNG GIÁ CỐ ĐỊNH
    // của đơn (không phải tiền cọc), nên số tiền cọc cần trả phải TÍNH LẠI từ full_amount *
    // deposit_percent mỗi lần cần, không đọc trực tiếp từ cột nào cả (tránh 2 cột amount/full_amount
    // bị dùng lẫn lộn giữa "tổng giá" và "tiền cần thu ngay" như trước đây).
    public function depositDueAmount(): int
    {
        $total = (int) ($this->full_amount ?? 0);

        if ($this->deposit_percent !== null && (int) $this->deposit_percent < 100) {
            return (int) ceil($total * (int) $this->deposit_percent / 100);
        }

        return $total;
    }

    // Số tiền cọc THỰC TẾ đã thu — mặc định bằng depositDueAmount() (đúng % đã áp dụng lúc đặt),
    // trừ khi admin đã tự điều chỉnh tay qua 'deposit_paid_amount' (form sửa đơn ở admin).
    public function depositPaidAmount(): int
    {
        return $this->deposit_paid_amount !== null
            ? (int) $this->deposit_paid_amount
            : $this->depositDueAmount();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Lượt đã trừ từ gói nhiều buổi cho đơn đặt sân này. Giá niêm yết vẫn được giữ trong amount
    // để đối soát, nhưng giao diện phải dựa vào quan hệ này để không hiểu nhầm là khách trả tiền.
    public function packageRedemptions()
    {
        return $this->hasMany(\App\Models\PackageRedemption::class);
    }

    public function activePackageRedemptions()
    {
        return $this->packageRedemptions()->whereNull('refunded_at');
    }

    // Khách thứ 2 trở đi (đặt qua đêm) — xem migration 2026_07_20_000001_create_order_guest_cccds_table.
    public function guestCccds()
    {
        return $this->hasMany(OrderGuestCccd::class)->orderBy('guest_index');
    }
    // Thêm accessor để đảm bảo items luôn trả về Collection
    public function getItemsAttribute($value)
    {
        if ($this->relationLoaded('items')) {
            return $this->getRelation('items');
        }

        // Nếu chưa load relation, load ngay
        return $this->items()->get();
    }

    // Thêm method helper để kiểm tra có items không
    public function hasItems()
    {
        return $this->items && $this->items->count() > 0;
    }

    // Method để get items với fallback
    public function getItemsSafe()
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }

        return $this->items ?? collect();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Chỉ có giá trị khi order_type='package_purchase' — gói khách đang mua qua đơn này (xem
    // Modules\Payment\App\Filament\Resources\PackageOrderResource).
    public function packagePlan()
    {
        return $this->belongsTo(\App\Models\PackagePlan::class, 'package_plan_id');
    }

    // Chỉ có giá trị khi đơn được thanh toán qua hạn mức tài khoản doanh nghiệp (xem migration
    // add_corporate_account_id_to_orders_table + App\Models\CorporateAccount).
    public function corporateAccount()
    {
        return $this->belongsTo(\App\Models\CorporateAccount::class);
    }

    // Nhân viên/tài khoản đã TẠO đơn này — dùng cho nhân viên đối tác nền tảng (vd 365home) xem
    // lại đúng những đơn CHÍNH HỌ đặt hộ cho đối tác khác (xem OrderResource::getEloquentQuery()).
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function znsNotifications()
    {
        return $this->hasMany(ZnsNotification::class, 'order_id');
    }

        public function toCalendarEvent(): CalendarEvent
    {
        return CalendarEvent::make($this)
            ->title($this->name);
    }

    public function services()
    {
        return $this->hasMany(OrderService::class);
    }

    public function coachBookings()
    {
        return $this->hasMany(OrderCoach::class);
    }

    // Trong số các additional_services.id truyền vào, id nào là "thuê vật tư" (gắn warehouse_item)
    // — order_services lưu chung cả dịch vụ lẫn vật tư, đây là cách DUY NHẤT để tách 2 nhóm.
    public static function equipmentServiceIds(iterable $serviceIds): array
    {
        $ids = collect($serviceIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return \Modules\BladeThemeV1\App\Models\AdditionService::query()
            ->whereIn('id', $ids)
            ->whereNotNull('warehouse_item_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Các khoản đi kèm tiền sân, tách thành 3 nhóm độc lập — dùng CHUNG cho mọi trang chi tiết
     * đơn (client + admin + API) để không nơi nào gộp lẫn/thiếu:
     *   services  = dịch vụ thuần, equipment = thuê vật tư (gắn kho), coaches = thuê HLV.
     */
    public function addonBreakdown(): array
    {
        $services = $this->relationLoaded('services') ? $this->services : $this->services()->get();
        $coaches  = ($this->relationLoaded('coachBookings') ? $this->coachBookings : $this->coachBookings()->get())
            ->sortBy('starts_at')->values();

        $equipmentIds = self::equipmentServiceIds($services->pluck('service_id'));
        [$equipment, $pureServices] = $services->partition(fn ($s) => in_array((int) $s->service_id, $equipmentIds, true));

        return [
            'services'        => $pureServices->values(),
            'equipment'       => $equipment->values(),
            'coaches'         => $coaches,
            'services_total'  => (int) $pureServices->sum('subtotal'),
            'equipment_total' => (int) $equipment->sum('subtotal'),
            'coaches_total'   => (int) $coaches->sum('subtotal'),
        ];
    }

    public function bankTransferConfirmedBy()
    {
        return $this->belongsTo(User::class, 'bank_transfer_confirmed_by');
    }

    // URL đầy đủ của ảnh biên lai chuyển khoản — payment_proof lưu PATH THUẦN (giống hệt cccd_front/
    // cccd_back trên bảng này, không dùng Spatie Media Library), disk mặc định 'public'
    // (FILESYSTEM_DISK trong .env) nên chỉ cần Storage::url(), không cần disk('public') riêng.
    public function getPaymentProofUrlAttribute(): ?string
    {
        return $this->payment_proof
            ? \Illuminate\Support\Facades\Storage::url($this->payment_proof)
            : null;
    }
}
