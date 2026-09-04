<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\BuildsRoomBooking;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\CccdDeclarationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Category\Entities\Category;
use Modules\Payment\App\Services\CccdScannerService;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;
use Modules\Product\App\Models\Product;

class BookingController extends Controller
{
    use BuildsRoomBooking;

    /**
     * POST /api/admin/orders
     * Admin/lễ tân tạo đơn đặt sân hộ khách — dùng chung 1 API cho cả 2 trường hợp:
     *   - Khách vãng lai: gửi buyer_name + buyer_phone, không có customer_id/customer_phone.
     *   - Khách đã là thành viên: gửi customer_id (hoặc customer_phone để tự tra), đơn được
     *     gắn customer_id như đơn khách tự đặt qua app.
     *
     * Dùng lại NGUYÊN engine tính giá (slot/daily/monthly, khuyến mãi, giảm giá bulk/full-booking,
     * đặt cọc) của app khách hàng — xem BuildsRoomBooking — để giá tạo ra ở đây không lệch với giá
     * app hiển thị. KHÔNG xử lý mã giảm giá (coupon_codes) — admin tạo đơn nền, không cần áp coupon.
     *
     * CCCD (mặt trước/sau) là TÙY CHỌN (khác app khách/guest luôn bắt buộc) — lễ tân đã xác minh
     * giấy tờ trực tiếp tại quầy nên không bắt phải chụp ảnh ngay bước này; nếu có gửi kèm thì hệ
     * thống vẫn tự quét QR và lưu vào "Khai báo lưu trú" (Luật Cư trú) giống hệt luồng CMS admin —
     * xem CccdDeclarationService. Khung giờ qua đêm (over_night) với guest_count > 1 thì cho gửi
     * thêm CCCD của khách đi cùng qua guests[{guest_index}][front]/[back] (guest_index từ 2), cũng
     * không bắt buộc — chỉ những khách nào có gửi ảnh mới được quét/lưu.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        // ── 1. Validate ──────────────────────────────────────────────────────
        $baseRules = [
            'type'                    => 'required|in:slot,monthly,daily',
            'room_id'                 => 'required|string',
            'guest_count'             => 'required|integer|min:1',
            // Tên đội/nhóm — tuỳ chọn, dùng khi khách đặt sân theo dạng đội/nhóm (vd giải đấu nội
            // bộ, CLB) thay vì chỉ 1 khách lẻ.
            'team_name'               => 'sometimes|nullable|string|max:255',
            // customers.id là UUID (char 36), KHÔNG phải số nguyên — xem migration create_customers_table.
            'customer_id'             => 'sometimes|nullable|string|size:36|exists:customers,id',
            'customer_phone'          => 'sometimes|nullable|string|max:20',
            'buyer_name'              => 'sometimes|nullable|string|max:100',
            'buyer_phone'             => 'sometimes|nullable|string|max:20',
            'payment_method'          => 'sometimes|in:PayOS,cod',
            'payment_type'            => 'sometimes|in:full,deposit',
            'status'                  => 'sometimes|in:pending,paid,deposit,failed,cancelled_payment,refunded',
            // Đặt hộ NHÂN VIÊN CÔNG TY — trừ vào hạn mức giờ của tài khoản doanh nghiệp thay vì thu
            // tiền per-booking (xem App\Models\CorporateAccount). Chỉ hỗ trợ ở luồng admin/lễ tân
            // đặt hộ (KHÔNG có ở app khách tự đặt — xem giải thích tại khối xử lý bên dưới).
            'corporate_account_id'    => 'sometimes|nullable|integer|exists:corporate_accounts,id',
            'services'                => 'sometimes|nullable|array',
            'services.*.service_id'   => 'required_with:services|integer',
            'services.*.quantity'     => 'required_with:services|integer|min:1',
            'note_for_admin'          => 'sometimes|nullable|string|max:500',
            'description'             => 'sometimes|nullable|string|max:500',
            'short_description'       => 'sometimes|nullable|string|max:255',
            'return_url'              => 'sometimes|nullable|string|max:500',
            'cancel_url'              => 'sometimes|nullable|string|max:500',
            // CCCD khách chính — không bắt buộc (xem docblock). Chỉ validate ĐỊNH DẠNG nếu có gửi.
            'cccd_front'              => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
            'cccd_back'               => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
            // CCCD khách đi cùng (khung giờ qua đêm) — guests[{guest_index}][front|back], guest_index
            // từ 2. Cũng không bắt buộc, chỉ validate định dạng nếu có gửi.
            'guests'                  => 'sometimes|array',
            'guests.*.front'          => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
            'guests.*.back'           => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
        ];

        if ($request->input('type') === 'slot') {
            $baseRules['date']                = 'sometimes|date_format:Y-m-d|after_or_equal:today';
            $baseRules['slots']               = 'required|array|min:1';
            $baseRules['slots.*.timeslot_id'] = 'required|integer';
            $baseRules['slots.*.date']        = 'sometimes|date_format:Y-m-d|after_or_equal:today';
        } else {
            $baseRules['checkin_date']  = 'required|date|after_or_equal:today';
            $baseRules['checkout_date'] = 'required|date|after:checkin_date';
        }

        if ($request->input('type') === 'daily') {
            $baseRules['checkin_date']  = 'required|date_format:Y-m-d|after_or_equal:today';
            $baseRules['checkout_date'] = 'required|date_format:Y-m-d|after:checkin_date';
        }

        $request->validate($baseRules);

        // ── 2. Xác định khách: thành viên có sẵn hay khách vãng lai ────────────
        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::find($request->input('customer_id'));
        } elseif ($request->filled('customer_phone')) {
            $customer = Customer::where('phone', trim($request->input('customer_phone')))->first();
        }

        if ($customer) {
            $buyerName  = $request->filled('buyer_name')  ? trim($request->input('buyer_name'))  : $customer->fullname;
            $buyerPhone = $request->filled('buyer_phone') ? trim($request->input('buyer_phone')) : $customer->phone;
        } else {
            if (! $request->filled('buyer_name') || ! $request->filled('buyer_phone')) {
                throw ValidationException::withMessages([
                    'buyer_name' => ['Khách vãng lai cần buyer_name + buyer_phone. Nếu là khách đã đăng ký thành viên, gửi customer_id hoặc customer_phone.'],
                ]);
            }
            $buyerName  = trim($request->input('buyer_name'));
            $buyerPhone = trim($request->input('buyer_phone'));
        }

        // ── 3. Load sân + kiểm tra quyền theo đối tác ────────────────────────
        $room = Product::where('id', $request->input('room_id'))
            ->where('is_activated', true)
            ->with([
                'roomType',
                'additionalServices',
                'roomTimeSlots.timeSlot',
                'roomTimeSlots.promotions' => fn ($q) => $q->where('is_active', true),
            ])
            ->first();

        if (! $room) {
            return response()->json(['message' => 'Sân không tồn tại hoặc đã ngừng hoạt động.'], 404);
        }

        // Nhân viên đối tác nền tảng (vd 365home) được đặt hộ cho MỌI đối tác — xem
        // User::belongsToPlatformPartner(). Nhân viên đối tác thường chỉ được đặt sân
        // thuộc chính đối tác mình.
        if (! $admin->isSuperAdmin() && ! $admin->belongsToPlatformPartner() && $room->partner_id !== $admin->partner_id) {
            return response()->json(['message' => 'Bạn không có quyền đặt sân của đối tác khác.'], 403);
        }

        // ── 4. Xây dựng items đặt sân ──────────────────────────────────────
        $rtsCollection = collect();
        $slotSummary   = [];

        if ($request->input('type') === 'slot') {
            [$basePrice, $summaryName, $itemsData, $rtsCollection, $slotSummary] = $this->buildSlotItems($request, $room);
        } elseif ($request->input('type') === 'daily') {
            [$basePrice, $summaryName, $itemsData, $rtsCollection, $slotSummary] = $this->buildDailyItems($request, $room);
        } else {
            [$basePrice, $summaryName, $itemsData] = $this->buildMonthlyItem($request, $room);
        }

        // ── 4.4 Tài khoản doanh nghiệp (B2B) — trừ hạn mức giờ thay vì thu tiền per-booking ────
        // CHỈ hỗ trợ ở API admin/lễ tân này (KHÔNG có ở app khách tự đặt): thực tế vận hành, việc
        // "công ty ký hợp đồng, nhân viên tới quầy đặt sân" luôn qua LỄ TÂN xác minh nhân viên đúng
        // công ty rồi mới trừ hạn mức — cho khách tự chọn "trả bằng tài khoản công ty" trên app sẽ
        // phải tự expose danh sách nhân viên/hạn mức còn lại cho khách xem trước (rò rỉ thông tin
        // nội bộ công ty ra app công khai) và mất luôn bước xác minh con người. Nếu sau này có nhu
        // cầu tự đặt (vd nhân viên có tài khoản riêng, công ty chấp nhận rủi ro), nên làm ở luồng
        // khách hàng như 1 tính năng riêng, không mở rộng thẳng từ đây.
        $corporateAccount    = null;
        $corporateHoursNeeded = 0.0;

        if ($request->filled('corporate_account_id')) {
            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Đặt sân qua tài khoản doanh nghiệp bắt buộc chọn khách hàng đã là thành viên (customer_id/customer_phone) — khách vãng lai không thể là nhân viên công ty.'],
                ]);
            }

            // Hạn mức tài khoản doanh nghiệp tính theo GIỜ trong tháng — chỉ hợp lý cho đặt theo
            // khung giờ (type=slot). Đặt theo ngày/tháng (type=daily/monthly) có thể tự chiếm 8-22h+
            // chỉ trong 1 đơn, "ăn" gần hết hạn mức tháng ngay lập tức — sai với ý nghĩa "hạn mức giờ
            // chơi định kỳ" ban đầu. Nếu công ty cần đặt nguyên ngày/tháng, dùng luồng thanh toán
            // thường (không qua corporate_account_id).
            if ($request->input('type') !== 'slot') {
                throw ValidationException::withMessages([
                    'corporate_account_id' => ['Tài khoản doanh nghiệp chỉ áp dụng cho đặt sân theo khung giờ (type=slot), không áp dụng cho đặt theo ngày/tháng.'],
                ]);
            }

            $corporateAccount = \App\Models\CorporateAccount::find($request->input('corporate_account_id'));

            if (! $corporateAccount || ! $corporateAccount->isActive()) {
                throw ValidationException::withMessages([
                    'corporate_account_id' => ['Tài khoản doanh nghiệp không tồn tại hoặc đã bị tạm ngưng.'],
                ]);
            }

            if ($corporateAccount->partner_id !== $room->partner_id) {
                throw ValidationException::withMessages([
                    'corporate_account_id' => ['Tài khoản doanh nghiệp này không thuộc đối tác sở hữu sân đang đặt.'],
                ]);
            }

            if (! $corporateAccount->hasActiveEmployee($customer->id)) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Khách hàng này không phải là nhân viên đang hoạt động của tài khoản doanh nghiệp đã chọn.'],
                ]);
            }

            if ($request->input('payment_type') === 'deposit') {
                throw ValidationException::withMessages([
                    'payment_type' => ['Đặt cọc không áp dụng cho đơn thanh toán qua tài khoản doanh nghiệp — hạn mức được trừ đủ ngay lúc đặt.'],
                ]);
            }

            $corporateHoursNeeded = $this->computeItemsHours($itemsData);

            if (! $corporateAccount->hasQuotaRemaining($corporateHoursNeeded)) {
                throw ValidationException::withMessages([
                    'corporate_account_id' => ['Tài khoản doanh nghiệp không còn đủ hạn mức giờ trong kỳ này (còn lại ' . $corporateAccount->remainingHours() . 'h, cần ' . $corporateHoursNeeded . 'h).'],
                ]);
            }
        }

        // ── 4.5 CCCD (tùy chọn) — khách chính + khách đi cùng nếu có khung giờ qua đêm ─────────
        // type != 'slot' (đặt theo ngày) LUÔN là qua đêm — xem over_night => true hardcode trong
        // buildDailyItems(). $rtsCollection ở đó chỉ chứa RoomTimeSlot có type 'date' nên có thể
        // rỗng dù đơn thực chất qua đêm — phải check type riêng (giống BookingController khách hàng).
        $hasOvernight = $request->input('type') === 'daily'
            || $rtsCollection->contains(fn ($rts) => (bool) $rts->over_night);

        $cccdFront = null;
        $cccdBack  = null;
        $cccdData  = null;

        if ($request->hasFile('cccd_front') && $request->hasFile('cccd_back')) {
            $cccdFront = $request->file('cccd_front')->store('cccd', 'public');
            $cccdBack  = $request->file('cccd_back')->store('cccd', 'public');

            try {
                $tempOrder = new Order(['cccd_front' => $cccdFront, 'cccd_back' => $cccdBack]);
                $cccdData  = app(CccdScannerService::class)->scanOrder($tempOrder);
            } catch (\Throwable $e) {
                $cccdData = null;
                Log::warning('Admin API: quét CCCD khách chính thất bại', ['error' => $e->getMessage()]);
            }
            // KHÔNG bắt buộc đọc được QR — khác app khách/guest (BookingController/GuestBookingController)
            // luôn chặn đơn nếu quét lỗi. Lễ tân đã xác minh giấy tờ trực tiếp, ảnh chỉ để lưu hồ sơ/
            // khai báo lưu trú — quét lỗi thì vẫn tạo đơn bình thường, cccd_data để trống, sửa tay sau.
        }

        // Chặn dùng chung 1 CCCD cho nhiều người trong cùng đơn (kể cả khách chính) — cùng nguyên
        // tắc seenCccds đã dùng ở luồng khách hàng tự đặt (ProductDetail::confirmBooking()). Khác
        // với quét lỗi (KHÔNG chặn, xem ghi chú trên), đây là dữ liệu ĐÃ đọc được nhưng phát hiện
        // trùng — 1 người thật không thể vừa là khách chính vừa là khách đi cùng của chính họ.
        $seenCccds = array_filter([$cccdData['cccd'] ?? null]);

        // guest_index bắt đầu từ 2 (1 = khách chính) — mỗi phần tử LUÔN có mặt trong mảng trả về dù
        // chưa gửi ảnh (front/back/data = null), để FE biết cần hiển thị đủ (guest_count - 1) ô nhập.
        $guestCccdRows = [];

        if ($hasOvernight) {
            $companionCount = max(0, (int) $request->input('guest_count') - 1);

            for ($i = 0; $i < $companionCount; $i++) {
                $guestIndex = $i + 2;
                $frontKey   = "guests.{$guestIndex}.front";
                $backKey    = "guests.{$guestIndex}.back";

                $row = ['guest_index' => $guestIndex, 'front' => null, 'back' => null, 'data' => null];

                if ($request->hasFile($frontKey) && $request->hasFile($backKey)) {
                    $row['front'] = $request->file($frontKey)->store('cccd', 'public');
                    $row['back']  = $request->file($backKey)->store('cccd', 'public');

                    try {
                        $row['data'] = app(CccdScannerService::class)->scanPaths($row['front'], $row['back']);
                    } catch (\Throwable $e) {
                        $row['data'] = null;
                        Log::warning('Admin API: quét CCCD khách đi cùng thất bại', [
                            'guest_index' => $guestIndex,
                            'error'       => $e->getMessage(),
                        ]);
                    }

                    $rowCccd = $row['data']['cccd'] ?? null;
                    if (! empty($rowCccd)) {
                        if (in_array($rowCccd, $seenCccds, true)) {
                            throw ValidationException::withMessages([
                                "guests.{$guestIndex}.front" => ["CCCD của người đi cùng thứ {$guestIndex} bị trùng với một CCCD khác trong đơn. Vui lòng upload CCCD của một người khác."],
                            ]);
                        }
                        $seenCccds[] = $rowCccd;
                    }
                }

                $guestCccdRows[] = $row;
            }
        }

        // ── 5. Dịch vụ bổ sung ───────────────────────────────────────────────
        [$servicesTotal, $servicesData] = $this->buildServices($request, $room);

        // ── 5.5 Phụ thu số lượng người ─────────────────────────────────────────
        [$guestSurcharge, $guestSurchargeInfo] = $this->buildGuestSurcharge($request, $room, $slotSummary);

        // ── 6. Áp dụng discount theo thứ tự ưu tiên (giống app khách) ─────────
        $appliedPromotions     = [];
        $promotionDiscount     = 0;
        $appliedSystemDiscount = null;
        $systemDiscount        = 0;

        $hasFullBooking = ! empty($slotSummary) && $this->checkFullDayBooking($slotSummary, $room);

        if ($hasFullBooking) {
            [$systemDiscount, $appliedSystemDiscount] = $this->applyFullBookingDiscount($basePrice, $room);
        } else {
            if ($rtsCollection->isNotEmpty()) {
                if ($request->input('type') === 'daily') {
                    [$promotionDiscount, $appliedPromotions] = $this->applyDailyPromotions($rtsCollection, $slotSummary);
                } else {
                    [$promotionDiscount, $appliedPromotions] = $this->applyPromotions($rtsCollection, $slotSummary);
                }
            }

            if (! empty($slotSummary)) {
                [$systemDiscount, $appliedSystemDiscount] = $this->applyBulkDiscount(
                    count($slotSummary),
                    $room,
                    $basePrice - $promotionDiscount
                );
            }
        }

        $discountAmount = $promotionDiscount + $systemDiscount;
        $slotFinalPrice = max(0, $basePrice - $discountAmount);
        $finalAmount    = $slotFinalPrice + $servicesTotal + $guestSurcharge;

        // ── Deposit (chỉ daily) ───────────────────────────────────────────────
        // Đơn corporate LUÔN dùng payment_method='corporate_account' — bỏ qua lựa chọn PayOS/cod
        // của request (nếu có gửi), vì đơn không thu tiền qua cổng thanh toán nào cả, chỉ trừ hạn
        // mức. Cột payment_method là string thường (không phải DB enum, xem migration
        // create_orders_table), nên thêm giá trị mới ở đây không cần migration đổi schema.
        $paymentMethod = $corporateAccount ? 'corporate_account' : $request->input('payment_method', 'cod');
        $amountDue     = $finalAmount;
        $depositInfo   = null;

        if ($request->input('type') === 'daily') {
            $depositMin  = (int) ($room->deposit_min_nights  ?? 0);
            $depositPct  = (int) ($room->deposit_multi_night ?? 50);
            $paymentType = $request->input('payment_type', 'full');

            if ($paymentType === 'deposit') {
                if ($paymentMethod === 'cod') {
                    throw ValidationException::withMessages([
                        'payment_type' => ['Đặt cọc không áp dụng cho phương thức thanh toán tiền mặt.'],
                    ]);
                }

                $nights = count($slotSummary);
                if ($depositMin > 0 && $nights >= $depositMin && $depositPct < 100) {
                    $amountDue   = (int) ceil($finalAmount * $depositPct / 100);
                    $depositInfo = [
                        'type'             => 'deposit',
                        'percentage'       => $depositPct,
                        'deposit_amount'   => $amountDue,
                        'remaining_amount' => $finalAmount - $amountDue,
                    ];
                } else {
                    throw ValidationException::withMessages([
                        'payment_type' => [
                            'Đặt cọc không áp dụng' . ($depositMin > 0 ? " (cần tối thiểu {$depositMin} ngày)" : '') . '.',
                        ],
                    ]);
                }
            }
        }

        // Leo lên đúng cấp chi nhánh thật (parent_id NULL) — xem giải thích chi tiết ở
        // BookingController/GuestBookingController::store().
        $category = $room->categories()->first();
        for ($i = 0; $i < 5 && $category && $category->parent_id; $i++) {
            $parent = Category::find($category->parent_id);
            if (! $parent) {
                break;
            }
            $category = $parent;
        }

        $depositPercentToSave = $depositInfo !== null ? (int) ($depositInfo['percentage']) : null;

        // Đơn PayOS LUÔN bắt đầu ở "pending" (đang xử lý) — trạng thái thật chỉ được cập nhật qua
        // webhook PayOS khi khách thanh toán (xem OrderController::paymentStatus/webhook), nên bỏ
        // qua status admin gửi lên nếu có. Đơn tiền mặt (cod) không qua cổng thanh toán nên admin có
        // thể chỉnh trạng thái ngay lúc tạo (vd đã thu tiền mặt tại quầy → 'paid' luôn).
        // Đơn corporate được xem là "đã thanh toán" NGAY LÚC ĐẶT — không có khái niệm chờ xác nhận
        // (khác cod, nơi admin CÓ THỂ vẫn để 'pending' nếu chưa thu tiền mặt): công ty đã cam kết
        // trả qua hợp đồng/hoá đơn cuối kỳ, hạn mức bị trừ ngay nên đơn phải phản ánh đúng đã
        // "trả" — mirror đúng cách processPackagePayment() ở ProductDetail.php đánh dấu 'paid' ngay
        // khi redeem gói thành công (không qua PayOS/QR).
        $initialStatus = $corporateAccount
            ? 'paid'
            : ($paymentMethod === 'PayOS' ? 'pending' : $request->input('status', 'pending'));

        // ── 7. Tạo đơn + items + services trong transaction ──────────────────
        $order = DB::transaction(function () use (
            $room, $finalAmount, $buyerName, $buyerPhone,
            $customer, $category, $itemsData, $servicesData,
            $paymentMethod, $request, $depositPercentToSave,
            $admin, $initialStatus, $cccdFront, $cccdBack, $cccdData, $guestCccdRows,
            $corporateAccount, $corporateHoursNeeded
        ) {
            Product::where('id', $room->id)->lockForUpdate()->first();

            // Khoá + kiểm tra lại hạn mức NGAY TRONG transaction (không tin kết quả hasQuotaRemaining()
            // đã tính TRƯỚC KHI vào transaction) — cùng nguyên tắc "re-check sau lockForUpdate()" đã
            // dùng ở CustomerPackage::redeem()/ProductDetail::processPackagePayment(): 2 lễ tân ở 2 chi
            // nhánh/2 tab cùng đặt hộ cho công ty này gần như đồng thời có thể cùng đọc thấy còn hạn
            // mức trước khi cả 2 cùng trừ, vượt quá monthly_hour_quota nếu không khoá lại ở đây.
            if ($corporateAccount) {
                $lockedCorporate = \App\Models\CorporateAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($corporateAccount->id);

                if (! $lockedCorporate->hasQuotaRemaining($corporateHoursNeeded)) {
                    throw ValidationException::withMessages([
                        'corporate_account_id' => ['Tài khoản doanh nghiệp không còn đủ hạn mức giờ trong kỳ này (vừa bị dùng hết bởi 1 đơn khác).'],
                    ]);
                }
            }

            foreach ($itemsData as $itemData) {
                if (empty($itemData['checkin_date'])) {
                    continue;
                }
                $conflict = OrderItem::where('product_id', $room->id)
                    ->whereNotNull('checkin_date')
                    ->whereNotNull('checkout_date')
                    ->where('checkin_date', '<', $itemData['checkout_date'])
                    ->where('checkout_date', '>', $itemData['checkin_date'])
                    ->whereHas('order', fn ($q) => $q->whereIn('status', ['pending', 'paid', 'deposit', 'shipped']))
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'slots' => ['Khung giờ vừa được đặt (hoặc do người khác). Vui lòng chọn khung giờ khác.'],
                    ]);
                }
            }

            $order = Order::create([
                'amount'          => $finalAmount,
                'full_amount'     => $finalAmount,
                'deposit_percent' => $depositPercentToSave,
                // Admin có thể tự nhập mô tả; không gửi thì fallback về mô tả tự sinh như trước.
                'description'       => $request->input('description') ?: ('Đặt sân (admin) - ' . $room->name),
                'short_description' => $request->input('short_description'),
                'buyer_name'      => $buyerName,
                'buyer_phone'     => $buyerPhone,
                'payment_method'  => $paymentMethod,
                'status'          => $initialStatus,
                'guest_count'     => $request->guest_count,
                'team_name'       => $request->input('team_name'),
                'note_for_admin'  => $request->input('note_for_admin'),
                'category_id'     => $category?->id,
                // Đơn gán theo đúng partner_id của SÂN đang đặt (không phải của admin đang tạo) —
                // nhân viên đối tác nền tảng có thể đặt hộ cho đối tác khác (đã check quyền ở bước 3),
                // nên partner_id phải phản ánh đúng chủ sở hữu sân.
                'partner_id'      => $room->partner_id,
                'customer_id'     => $customer?->id,
                'corporate_account_id' => $corporateAccount?->id,
                // Ghi nhận admin/lễ tân đã tạo đơn này — dùng để tra lại đơn đặt hộ (xem Order::creator()).
                'created_by'      => $admin->id,
                // Ưu tiên ảnh CCCD admin vừa upload trong request này (tùy chọn); nếu không gửi thì
                // dùng lại CCCD đã lưu sẵn trong hồ sơ khách thành viên (hành vi cũ, không đổi).
                'cccd_front'      => $cccdFront ?? $customer?->cccd_front,
                'cccd_back'       => $cccdBack  ?? $customer?->cccd_back,
                'cccd_data'       => $cccdData  ?? $customer?->cccd_data,
            ]);

            // CCCD khách đi cùng (khung giờ qua đêm) — chỉ lưu những khách ĐÃ gửi ảnh, các ô còn
            // trống (chưa upload) không tạo bản ghi rỗng.
            foreach ($guestCccdRows as $guestRow) {
                if (! $guestRow['front'] || ! $guestRow['back']) {
                    continue;
                }

                $order->guestCccds()->create([
                    'guest_index' => $guestRow['guest_index'],
                    'cccd_front'  => $guestRow['front'],
                    'cccd_back'   => $guestRow['back'],
                    'cccd_data'   => $guestRow['data'],
                ]);
            }

            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            foreach ($servicesData as $svc) {
                $order->services()->create($svc);
            }

            // Trừ hạn mức giờ NGAY TRONG transaction (cùng transaction với tạo Order/items) — nếu
            // bất kỳ bước nào bên trên throw (vd trùng khung giờ), toàn bộ rollback lại, hạn mức
            // KHÔNG bị trừ nhầm cho 1 đơn không thực sự được tạo.
            if ($corporateAccount) {
                $lockedCorporate->consumeHours($corporateHoursNeeded);
            }

            return $order;
        });

        // ── 8. Tạo link PayOS (nếu chọn chuyển khoản thay vì tiền mặt) ────────
        if ($paymentMethod === 'PayOS' && $amountDue >= 2000) {
            $this->createPayOSLink($order, $summaryName, $request->input('return_url'), $request->input('cancel_url'));
        }

        // Đơn tạo qua API admin (lễ tân lên đơn hộ) cũng có nghĩa vụ "Khai báo lưu trú" y hệt đơn
        // khách tự đặt — gọi lại đúng service dùng chung với CMS/app khách (xem CccdDeclarationService)
        // để không bỏ sót, dù CCCD lúc tạo có thể còn thiếu (guest_index=1 vẫn được khai báo với dữ
        // liệu rỗng, nhân viên bổ sung tay sau ở trang "Khai báo lưu trú").
        app(CccdDeclarationService::class)->upsertFromOrder($order->load('items'));

        // ── 9. Realtime: cập nhật trạng thái slot ────────────────────────────
        $realtimeService = app(\App\Services\SlotRealtimeService::class);

        if ($request->input('type') === 'daily' && ! empty($slotSummary)) {
            $realtimeService->broadcastDailyBooked($room->id, $request->input('checkin_date'), $request->input('checkout_date'));
        } elseif (! empty($slotSummary)) {
            $byDate = collect($slotSummary)->groupBy('date');
            foreach ($byDate as $date => $slots) {
                $realtimeService->broadcastBooked(
                    $room->id,
                    $date,
                    $slots->pluck('timeslot_id')->values()->toArray()
                );
            }
        }

        $order->refresh();

        return response()->json([
            'order' => [
                'id'             => $order->id,
                'order_code'     => $order->order_code,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'order_status'   => $order->order_status,
                'payment_method' => $order->payment_method,
                'qr_code'        => $order->qr_code,
                'expired_at'     => $order->expired_at,
                'buyer_name'     => $order->buyer_name,
                'buyer_phone'    => $order->buyer_phone,
                'team_name'      => $order->team_name,
                'customer_id'    => $order->customer_id,
                'corporate_account_id' => $order->corporate_account_id,
                'description'       => $order->description,
                'short_description' => $order->short_description,
                'created_by'     => $admin->fullname,
                'cccd_front'     => $order->cccd_front ? Storage::disk('public')->url($order->cccd_front) : null,
                'cccd_back'      => $order->cccd_back  ? Storage::disk('public')->url($order->cccd_back)  : null,
                'cccd_data'      => $order->cccd_data,
            ],
            'room' => [
                'id'   => $room->id,
                'name' => $room->name,
            ],
            // Qua đêm (over_night) + guest_count > 1 → luôn có đúng (guest_count - 1) phần tử, kể cả
            // khách chưa gửi ảnh (cccd_front/back/data = null) — FE dựa vào đây để hiển thị đủ số ô
            // nhập CCCD cần khai báo theo Luật Cư trú, không bắt buộc điền ngay lúc tạo đơn.
            'overnight'        => $hasOvernight,
            'guests'           => collect($guestCccdRows)->map(fn ($row) => [
                'guest_index' => $row['guest_index'],
                'cccd_front'  => $row['front'] ? Storage::disk('public')->url($row['front']) : null,
                'cccd_back'   => $row['back']  ? Storage::disk('public')->url($row['back'])  : null,
                'cccd_data'   => $row['data'],
            ])->values(),
            'slots'            => $slotSummary,
            'services'         => $servicesData,
            'guest_surcharge'  => $guestSurchargeInfo,
            'promotions'       => $appliedPromotions,
            'system_discount'  => $appliedSystemDiscount,
            'deposit'          => $depositInfo,
            'summary' => [
                'slots_total'          => $basePrice,
                'promotion_discount'   => $promotionDiscount,
                'system_discount'      => $systemDiscount,
                'discount_amount'      => $discountAmount,
                'slots_final'          => $slotFinalPrice,
                'guest_surcharge'      => $guestSurcharge,
                'services_total'       => $servicesTotal,
                'total_after_discount' => (int) $finalAmount,
                'final_amount'         => (int) $order->full_amount,
            ],
            'corporate_account' => $corporateAccount ? [
                'id'               => $corporateAccount->id,
                'company_name'     => $corporateAccount->company_name,
                'hours_charged'    => $corporateHoursNeeded,
                'remaining_hours'  => $corporateAccount->fresh()->remainingHours(),
            ] : null,
        ], 201);
    }

    /**
     * POST /api/admin/orders/preview
     * Tính giá cho 1 đơn MỚI (chưa tạo) — body GIỐNG HỆT POST /api/admin/orders (type, room_id,
     * guest_count, slots[]/checkin_date+checkout_date, services[], payment_type) nhưng KHÔNG tạo
     * đơn, KHÔNG giữ chỗ (không ghi order/order_items nào) — dùng cho FE hiển thị giá trước khi bấm
     * "Xác nhận đặt". Gọi lại được liên tục, kể cả khung giờ/dữ liệu không đổi (idempotent, không có
     * tác dụng phụ) — dùng lại NGUYÊN engine tính giá của store() (xem BuildsRoomBooking) nên số
     * luôn khớp với đơn thật nếu submit ngay sau đó (trừ khi khung giờ vừa bị người khác đặt mất
     * giữa 2 lần gọi — vẫn còn tự kiểm tra trùng lịch bên trong buildSlotItems/buildDailyItems nên
     * trả lỗi 422 thay vì giá sai, không phải lỗi của preview).
     *
     * Response CHỈ gồm các khối đã có sẵn ở response tạo đơn — không có 'order'/'room'/CCCD (không
     * có đơn nào được tạo để trả về các field đó):
     *   summary { slots_total, promotion_discount, system_discount, discount_amount, slots_final,
     *             guest_surcharge, services_total, total_after_discount, final_amount }
     *   guest_surcharge, system_discount, deposit — cùng cấu trúc với response tạo đơn.
     */
    public function preview(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $rules = [
            'type'                   => 'required|in:slot,monthly,daily',
            'room_id'                => 'required|string',
            'guest_count'            => 'required|integer|min:1',
            'payment_method'         => 'sometimes|in:PayOS,cod',
            'payment_type'           => 'sometimes|in:full,deposit',
            'services'               => 'sometimes|nullable|array',
            'services.*.service_id'  => 'required_with:services|integer',
            'services.*.quantity'    => 'required_with:services|integer|min:1',
        ];

        if ($request->input('type') === 'slot') {
            $rules['date']                = 'sometimes|date_format:Y-m-d|after_or_equal:today';
            $rules['slots']               = 'required|array|min:1';
            $rules['slots.*.timeslot_id'] = 'required|integer';
            $rules['slots.*.date']        = 'sometimes|date_format:Y-m-d|after_or_equal:today';
        } else {
            $rules['checkin_date']  = 'required|date|after_or_equal:today';
            $rules['checkout_date'] = 'required|date|after:checkin_date';
        }

        if ($request->input('type') === 'daily') {
            $rules['checkin_date']  = 'required|date_format:Y-m-d|after_or_equal:today';
            $rules['checkout_date'] = 'required|date_format:Y-m-d|after:checkin_date';
        }

        $request->validate($rules);

        $room = Product::where('id', $request->input('room_id'))
            ->where('is_activated', true)
            ->with([
                'roomType',
                'additionalServices',
                'roomTimeSlots.timeSlot',
                'roomTimeSlots.promotions' => fn ($q) => $q->where('is_active', true),
            ])
            ->first();

        if (! $room) {
            return response()->json(['message' => 'Sân không tồn tại hoặc đã ngừng hoạt động.'], 404);
        }

        if (! $admin->isSuperAdmin() && ! $admin->belongsToPlatformPartner() && $room->partner_id !== $admin->partner_id) {
            return response()->json(['message' => 'Bạn không có quyền đặt sân của đối tác khác.'], 403);
        }

        return response()->json($this->computeBookingPreview($request, $room));
    }

    /**
     * Tổng số GIỜ của 1 đơn — dùng để trừ hạn mức tài khoản doanh nghiệp (xem khối "4.4" ở store()).
     * Cộng dồn (checkout_date - checkin_date) của TỪNG item, tính theo PHÚT rồi chia 60 để giữ được
     * phần lẻ (vd khung giờ 90 phút = 1.5h) thay vì làm tròn — hạn mức công ty là số thập phân
     * (monthly_hour_quota decimal), làm tròn sớm ở đây sẽ làm sai lệch dần qua nhiều lần đặt.
     *
     * GIẢ ĐỊNH NGHIỆP VỤ CẦN NGƯỜI XÁC NHẬN LẠI: sân thuê theo ngày/tháng (type=daily/monthly) quy
     * đổi NGUYÊN các khung giờ nhận-trả thành "giờ" (vd thuê 1 ngày qua đêm 14h hôm nay -> 12h hôm
     * sau = 22h) — hợp lý về mặt số học (đúng số giờ sân bị chiếm dụng) nhưng CÓ THỂ không phải điều
     * chủ đối tác muốn tính vào hạn mức B2B (thường hạn mức công ty nhắm tới đặt sân THEO KHUNG GIỜ
     * ngắn cho nhân viên chơi giao lưu, không phải thuê nguyên ngày/tháng). MVP này áp dụng CÙNG 1
     * công thức cho mọi loại (đơn giản, nhất quán) — nếu thực tế công ty chỉ nên dùng hạn mức cho
     * type=slot, cần chặn thêm ở khối "4.4" (validate $request->input('type') === 'slot').
     */
    private function computeItemsHours(array $itemsData): float
    {
        $totalMinutes = 0;

        foreach ($itemsData as $item) {
            $checkin  = $item['checkin_date']  ?? null;
            $checkout = $item['checkout_date'] ?? null;

            if (! $checkin || ! $checkout) {
                continue;
            }

            $totalMinutes += $checkin->diffInMinutes($checkout);
        }

        return round($totalMinutes / 60, 2);
    }
}
