<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Entities\OrderItem;
use Modules\Product\App\Models\Product;

/**
 * Trích xuất từ BookingController::buildSlotItems()/buildDailyItems() (không sửa file gốc —
 * xem plan "Cho phép đặt thêm khung giờ trên đơn đã thành công"). Dùng cho luồng khách tự
 * đặt thêm khung giờ/ngày trên đơn ĐÃ THÀNH CÔNG (OrderExtraBookingService), tách khỏi
 * Request để tái sử dụng được ngoài context tạo đơn mới.
 */
class RoomItemBuilder
{
    /**
     * @param  array{timeslot_id:int, date?:string}[]  $slots
     * @return array{0:int, 1:string, 2:array, 3:\Illuminate\Support\Collection, 4:array}
     */
    public function buildSlotItems(array $slots, ?string $defaultDate, Product $room, int $guestCount): array
    {
        $totalPrice    = 0;
        $itemsData     = [];
        $slotSummary   = [];
        $rtsCollection = collect();

        foreach ($slots as $index => $slot) {
            $timeslotId = (int) $slot['timeslot_id'];
            $dateStr    = $slot['date'] ?? $defaultDate;

            if (! $dateStr) {
                throw ValidationException::withMessages([
                    "slots.{$index}.date" => ['Vui lòng cung cấp ngày đặt sân.'],
                ]);
            }

            $rts = $room->roomTimeSlots
                ->filter(fn ($s) => is_null($s->date))
                ->where('timeslot_id', $timeslotId)
                ->first();

            if (! $rts || ! $rts->timeSlot) {
                throw ValidationException::withMessages([
                    "slots.{$index}.timeslot_id" => ['Khung giờ không tồn tại cho sân này.'],
                ]);
            }

            if ($rts->isBlockedOn($dateStr)) {
                throw ValidationException::withMessages([
                    "slots.{$index}.date" => ['Khung giờ này đã bị chặn vào ngày bạn chọn.'],
                ]);
            }

            $timeSlot = $rts->timeSlot;
            $checkin  = Carbon::parse("{$dateStr} {$timeSlot->start_time}");
            $checkout = Carbon::parse("{$dateStr} {$timeSlot->end_time}");
            if ($checkout->lte($checkin)) {
                $checkout->addDay();
            }

            $conflict = OrderItem::where('product_id', $room->id)
                ->whereNotNull('checkin_date')
                ->whereNotNull('checkout_date')
                ->where('checkin_date', '<', $checkout)
                ->where('checkout_date', '>', $checkin)
                ->whereHas('order', fn ($q) => $q->whereIn('status', ['pending', 'paid', 'deposit', 'shipped']))
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    "slots.{$index}.timeslot_id" => ['Khung giờ này đã được đặt rồi.'],
                ]);
            }

            $startLabel  = substr($timeSlot->start_time, 0, 5);
            $endLabel    = substr($timeSlot->end_time, 0, 5);
            $isOvernight = (bool) $rts->over_night;
            $label       = $startLabel . ' - ' . $endLabel . ($isOvernight ? ' (Qua đêm)' : '');
            $price       = (int) ($rts->priceOn($checkin) ?? $rts->price);

            $totalPrice  += $price;
            $itemsData[]  = [
                'product_id'    => $room->id,
                'name'          => $room->name . ' - ' . $label,
                'price'         => $price,
                'quantity'      => 1,
                'is_shipped'    => true,
                'checkin_date'  => $checkin,
                'checkout_date' => $checkout,
                'extra_fee'     => 0,
                'guest_count'   => $guestCount,
                'over_night'    => $isOvernight,
            ];

            $slotSummary[] = [
                'timeslot_id' => $timeslotId,
                'date'        => $dateStr,
                'label'       => $label,
                'price'       => $price,
            ];

            $rtsCollection->push($rts);
        }

        $slotCount   = count($slots);
        $summaryName = $slotCount === 1
            ? $itemsData[0]['name']
            : $room->name . ' - ' . $slotCount . ' khung giờ';

        return [$totalPrice, $summaryName, $itemsData, $rtsCollection, $slotSummary];
    }

    /**
     * @return array{0:int, 1:string, 2:array, 3:\Illuminate\Support\Collection, 4:array}
     */
    public function buildDailyItems(string $checkinDate, string $checkoutDate, Product $room, int $guestCount): array
    {
        $checkin  = Carbon::parse($checkinDate)->startOfDay();
        $checkout = Carbon::parse($checkoutDate)->startOfDay();
        $days     = (int) $checkin->diffInDays($checkout);

        if ($days < 1) {
            throw ValidationException::withMessages([
                'checkout_date' => ['Phải đặt tối thiểu 1 ngày.'],
            ]);
        }

        $conflict = OrderItem::where('product_id', $room->id)
            ->whereNotNull('checkin_date')
            ->whereNotNull('checkout_date')
            ->where('checkin_date', '<', $checkout)
            ->where('checkout_date', '>', $checkin)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['pending', 'paid', 'deposit', 'shipped', 'confirmed']))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'checkin_date' => ['Sân đã được đặt trong khoảng thời gian này.'],
            ]);
        }

        $slotsByDate = $room->roomTimeSlots
            ->filter(fn ($rts) => $rts->timeSlot?->type === 'date')
            ->keyBy(fn ($rts) => $rts->timeSlot?->label);

        $basePrice = (float) $room->price;
        // Giờ nhận/trả mặc định khi sân không tự đặt riêng (default_checkin/checkout) VÀ ngày đó
        // cũng không có override theo room_time_slots: ưu tiên đọc giờ mở/đóng cửa THẬT của chi
        // nhánh (categories.checkin_time/checkout_time, xem CategoryForm::openingHoursInputs()) —
        // sân thể thao thuê cả ngày không có khái niệm "trả phòng 12:00 trưa" kiểu khách sạn. Chỉ
        // rơi về 14:00/12:00 khi chi nhánh CHƯA cấu hình giờ mở cửa (dữ liệu cũ).
        $branchCategory = $room->categories()
            ->whereNull('parent_id')
            ->where('category_type', 'product')
            ->first();
        $defCheckin  = $room->default_checkin  ?? $branchCategory?->checkin_time  ?? '14:00';
        $defCheckout = $room->default_checkout ?? $branchCategory?->checkout_time ?? '12:00';

        $totalPrice    = 0;
        $itemsData     = [];
        $daySummary    = [];
        $rtsCollection = collect();

        $current = $checkin->copy();
        while ($current->lt($checkout)) {
            $dateStr = $current->format('Y-m-d');
            $rts     = $slotsByDate->get($dateStr);

            $dayPrice   = $rts?->price !== null
                ? (float) $rts->price
                : app(\App\Services\PriceBoards\StayPriceResolver::class)->nightPrice($room, $dateStr, $basePrice);
            $checkinTime  = $rts?->checkin  ?? $defCheckin;
            $checkoutTime = $rts?->checkout ?? $defCheckout;
            $nextDate     = $current->copy()->addDay()->format('Y-m-d');
            $checkinDt    = Carbon::parse("{$dateStr} {$checkinTime}");
            $checkoutDt   = Carbon::parse("{$nextDate} {$checkoutTime}");

            $totalPrice += $dayPrice;

            $itemsData[] = [
                'product_id'    => $room->id,
                'name'          => $room->name . ' - ' . $current->format('d/m/Y'),
                'price'         => (int) $dayPrice,
                'quantity'      => 1,
                'is_shipped'    => true,
                'checkin_date'  => $checkinDt,
                'checkout_date' => $checkoutDt,
                'extra_fee'     => 0,
                'guest_count'   => $guestCount,
                'over_night'    => true,
            ];

            $daySummary[] = [
                'date'  => $dateStr,
                'price' => (int) $dayPrice,
            ];

            if ($rts) {
                $rtsCollection->push($rts);
            }

            $current->addDay();
        }

        $summaryName = $room->name . ' - ' . $days . ' ngày ('
            . $checkin->format('d/m') . ' → ' . $checkout->format('d/m/Y') . ')';

        return [(int) $totalPrice, $summaryName, $itemsData, $rtsCollection, $daySummary];
    }
}
