<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PriceBoardSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Product\App\Models\PriceBoard;
use Modules\Product\App\Models\PriceBoardPriceLog;
use Modules\Product\App\Models\Product;

/**
 * "Bảng giá" đặt tên, có thời hạn (Tết, khuyến mãi, đối tác...) — Admin API tương đương
 * Modules\Book\App\Filament\Resources\PriceBoardResource. Đây là lớp quản trị NGỒI TRÊN
 * products/room_time_slots (nguồn duy nhất pipeline đặt sân đọc giá thật) — ghi đè theo lịch
 * (start_date/end_date) rồi tự khôi phục khi hết hạn, xem toàn bộ cơ chế ở
 * App\Services\PriceBoardSyncService (dùng lại NGUYÊN service này, không viết lại logic đồng bộ).
 *
 * CHỈ super_admin — cùng mức hạn chế với PriceBoardResource::canViewAny()/canCreate()/canEdit()/
 * canDelete() (ghi thẳng giá xuống hệ thống ngay khi lưu, không xem trước, cùng rủi ro với "Sửa giá
 * hàng loạt" ở BookResource). "Bảng giá mặc định" (is_default=true, quản lý qua trang "Hệ thống
 * giá"/GET-PATCH /api/admin/rooms/{id}/pricing) KHÔNG hiển thị/sửa được ở đây — cùng quy ước với
 * PriceBoardResource::getEloquentQuery().
 */
class PriceBoardController extends Controller
{
    /**
     * GET /api/admin/price-boards
     * Danh sách bảng giá đặt tên (is_default=false). Không lọc theo partner_id trực tiếp (PriceBoard
     * gắn sân qua many-to-many price_board_items, không có category_id/partner_id riêng) — dùng
     * ?branch_id= (category gốc) để lọc theo bảng giá có ÍT NHẤT 1 sân thuộc chi nhánh đó, cùng cách
     * PriceBoardResource::getEloquentQuery() lọc qua whereHas('products.categories').
     * ?search= : tìm theo tên bảng giá.
     * per_page (mặc định 10).
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->ensureSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $query = PriceBoard::query()->where('is_default', false)->withCount('items');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('branch_id')) {
            $branchId = (int) $request->input('branch_id');
            $query->whereHas('products.categories', function ($q) use ($branchId) {
                $q->where('categories.id', $branchId)->orWhere('categories.parent_id', $branchId);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $boards = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 10));

        $boards->getCollection()->transform(fn (PriceBoard $board) => $this->toListItem($board));

        return response()->json($boards);
    }

    /**
     * GET /api/admin/price-boards/{id}
     * Chi tiết 1 bảng giá kèm toàn bộ items/khung giờ đã cấu hình cho từng sân — đủ dữ liệu để FE
     * dựng lại form sửa mà không cần gọi thêm API nào khác. Kèm 'recent_price_logs' (tối đa 50 dòng
     * gần nhất, PriceBoardPriceLog) — tương đương action "Lịch sử" ở EditPriceBoard.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (! $this->ensureSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $board = PriceBoard::where('is_default', false)
            ->with(['items.product:id,name,styles', 'items.timeSlots.timeslot:id,label,start_time,end_time'])
            ->find($id);

        if (! $board) {
            return response()->json(['message' => 'Price board not found.'], 404);
        }

        return response()->json(['price_board' => $this->toDetailItem($board)]);
    }

    /**
     * POST /api/admin/price-boards
     * Tạo bảng giá mới — dùng LẠI NGUYÊN quy trình Filament CreatePriceBoard::handleRecordCreation():
     * kiểm tra chồng lấn ngày hiệu lực (PriceBoardSyncService::assertNoOverlap()) TRƯỚC khi tạo, ghi
     * items/product_ids qua saveItems(), rồi áp giá NGAY LẬP TỨC nếu bảng đang active và trong
     * khoảng hiệu lực (resyncBoardProducts() — không cần chờ job `price-boards:sync-due` chạy đêm).
     *
     *   - name              : bắt buộc, tên bảng giá (vd "Giá Tết 2026")
     *   - is_active         : bool, mặc định true — bật/tắt có hiệu lực
     *   - start_date/end_date : ngày hiệu lực (nullable — để trống = không giới hạn 1 đầu tương ứng)
     *   - pricing_mode      : 'override' (thay giá cụ thể từng sân) | 'adjustment' (điều chỉnh %/số
     *     tiền trên giá gốc "Bảng giá mặc định")
     *   - adjustment_type/adjustment_value : bắt buộc khi pricing_mode='adjustment'
     *     ('percentage'|'fixed', số âm = giảm)
     *   - note              : ghi chú tuỳ chọn
     *   - product_ids[]     : CHỈ dùng khi pricing_mode='adjustment' — danh sách sân áp dụng
     *   - items[]           : CHỈ dùng khi pricing_mode='override' — mỗi phần tử:
     *       product_id (bắt buộc), full_booking_discount, bulk_discount_rules[{slots,discount}],
     *       room_config_max_free_guests, room_config_extra_guest_fee,
     *       roomTimeSlots[{timeslot_id,price,over_night,status}] (sân "Theo Khung Giờ", styles=1),
     *       price/default_checkin/default_checkout/deposit_min_nights/deposit_multi_night (sân
     *       "Theo Ngày", styles=2) — field nào không gửi thì KHÔNG đổi giá tương ứng của sân đó khi
     *       bảng này áp dụng (coi như null/mặc định, xem PriceBoardSyncService::saveOverrideItems()).
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->ensureSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $data = $this->validatePayload($request);

        $isAdjustment = $data['pricing_mode'] === PriceBoard::MODE_ADJUSTMENT;
        $items        = $data['items'] ?? [];
        $productIds   = $isAdjustment ? collect($data['product_ids'] ?? []) : collect($items)->pluck('product_id');

        $service = app(PriceBoardSyncService::class);

        try {
            $service->assertNoOverlap(new PriceBoard(), $productIds->filter());
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'A conflicting active price board already covers the same effective date range for at least one selected room.',
            ], 422);
        }

        $boardData = collect($data)->only([
            'name', 'note', 'start_date', 'end_date', 'is_active',
            'pricing_mode', 'adjustment_type', 'adjustment_value',
        ])->toArray();

        $board = DB::transaction(function () use ($boardData, $isAdjustment, $items, $data, $service) {
            $board = PriceBoard::create($boardData);

            $service->saveItems($board, $isAdjustment
                ? ['product_ids' => $data['product_ids'] ?? []]
                : ['items' => $items]);

            $service->resyncBoardProducts($board);

            return $board;
        });

        $board->load(['items.product:id,name,styles', 'items.timeSlots.timeslot:id,label,start_time,end_time']);

        return response()->json(['price_board' => $this->toDetailItem($board)], 201);
    }

    /**
     * PUT|POST /api/admin/price-boards/{id}
     * Sửa bảng giá — cùng ruleset với store(), thay TOÀN BỘ items/product_ids hiện có bằng dữ liệu
     * gửi lên (giống hệt Filament EditPriceBoard: mọi sân KHÔNG có mặt trong payload mới sẽ bị GỠ
     * khỏi bảng này). Áp lại giá NGAY sau khi lưu (resyncBoardProducts()).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $this->ensureSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $board = PriceBoard::where('is_default', false)->find($id);

        if (! $board) {
            return response()->json(['message' => 'Price board not found.'], 404);
        }

        $data = $this->validatePayload($request);

        $isAdjustment = $data['pricing_mode'] === PriceBoard::MODE_ADJUSTMENT;
        $items        = $data['items'] ?? [];
        $productIds   = $isAdjustment ? collect($data['product_ids'] ?? []) : collect($items)->pluck('product_id');

        $service = app(PriceBoardSyncService::class);

        try {
            $service->assertNoOverlap($board, $productIds->filter());
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'A conflicting active price board already covers the same effective date range for at least one selected room.',
            ], 422);
        }

        $boardData = collect($data)->only([
            'name', 'note', 'start_date', 'end_date', 'is_active',
            'pricing_mode', 'adjustment_type', 'adjustment_value',
        ])->toArray();

        DB::transaction(function () use ($board, $boardData, $isAdjustment, $items, $data, $service) {
            $board->update($boardData);

            $service->saveItems($board, $isAdjustment
                ? ['product_ids' => $data['product_ids'] ?? []]
                : ['items' => $items]);

            $service->resyncBoardProducts($board);
        });

        $board->load(['items.product:id,name,styles', 'items.timeSlots.timeslot:id,label,start_time,end_time']);

        return response()->json(['price_board' => $this->toDetailItem($board->fresh([
            'items.product:id,name,styles', 'items.timeSlots.timeslot:id,label,start_time,end_time',
        ]))]);
    }

    /**
     * DELETE /api/admin/price-boards/{id}
     * Xoá bảng giá — cascade xoá luôn PriceBoardItem/PriceBoardTimeSlot của nó. Sau khi xoá, TÍNH LẠI
     * giá đúng cho từng sân từng gắn với bảng này (bảng khác đang active vẫn thắng nếu có, không thì
     * khôi phục baseline/"Bảng giá mặc định") — cùng quy trình EditPriceBoard::getHeaderActions()
     * (DeleteAction before()/after()), chụp danh sách sân TRƯỚC khi xoá vì xoá xong sẽ mất liên kết.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->ensureSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $board = PriceBoard::where('is_default', false)->find($id);

        if (! $board) {
            return response()->json(['message' => 'Price board not found.'], 404);
        }

        $service           = app(PriceBoardSyncService::class);
        $affectedProducts  = $service->productsAffectedByBoard($board);

        $board->delete();

        $service->resyncProductsAfterBoardDeleted($affectedProducts);

        return response()->json(['message' => 'Price board deleted successfully.']);
    }

    /**
     * POST /api/admin/price-boards/{id}/apply-now
     * Áp NGAY giá của bảng này xuống các sân đã gắn, BẤT KỂ ngày hiệu lực/is_active — tương đương
     * EditPriceBoard action "Áp dụng ngay" (PriceBoardSyncService::applyBoard()). Dùng khi đối tác
     * cần đổi giá tức thời dù chưa tới ngày bắt đầu đã cấu hình.
     */
    public function applyNow(Request $request, int $id): JsonResponse
    {
        if (! $this->ensureSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $board = PriceBoard::where('is_default', false)->find($id);

        if (! $board) {
            return response()->json(['message' => 'Price board not found.'], 404);
        }

        app(PriceBoardSyncService::class)->applyBoard($board);

        return response()->json(['message' => 'Price board applied successfully.']);
    }

    private function ensureSuperAdmin(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();

        return (bool) ($user?->isSuperAdmin());
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json(['message' => 'Only super admins can manage price boards.'], 403);
    }

    private function validatePayload(Request $request): array
    {
        $rules = [
            'name'              => 'required|string|max:255',
            'note'              => 'sometimes|nullable|string|max:1000',
            'is_active'         => 'sometimes|boolean',
            'start_date'        => 'sometimes|nullable|date',
            'end_date'          => 'sometimes|nullable|date|after_or_equal:start_date',
            'pricing_mode'      => 'required|in:' . PriceBoard::MODE_OVERRIDE . ',' . PriceBoard::MODE_ADJUSTMENT,
            'adjustment_type'   => 'required_if:pricing_mode,' . PriceBoard::MODE_ADJUSTMENT . '|in:percentage,fixed',
            'adjustment_value'  => 'required_if:pricing_mode,' . PriceBoard::MODE_ADJUSTMENT . '|numeric',
        ];

        if ($request->input('pricing_mode') === PriceBoard::MODE_ADJUSTMENT) {
            $rules['product_ids']   = 'required|array|min:1';
            $rules['product_ids.*'] = 'string|exists:products,id';
        } else {
            $rules['items']                   = 'required|array|min:1';
            $rules['items.*.product_id']      = 'required|string|exists:products,id';
            $rules['items.*.full_booking_discount']       = 'sometimes|nullable|string|max:50';
            $rules['items.*.bulk_discount_rules']         = 'sometimes|nullable|array';
            $rules['items.*.bulk_discount_rules.*.slots']    = 'required_with:items.*.bulk_discount_rules|integer|min:2';
            $rules['items.*.bulk_discount_rules.*.discount'] = 'required_with:items.*.bulk_discount_rules|numeric|min:0|max:100';
            $rules['items.*.room_config_max_free_guests'] = 'sometimes|nullable|integer|min:0';
            $rules['items.*.room_config_extra_guest_fee']  = 'sometimes|nullable|numeric|min:0';
            $rules['items.*.roomTimeSlots']                     = 'sometimes|array';
            $rules['items.*.roomTimeSlots.*.timeslot_id']       = 'required_with:items.*.roomTimeSlots|integer';
            $rules['items.*.roomTimeSlots.*.price']             = 'required_with:items.*.roomTimeSlots|numeric|min:0';
            $rules['items.*.roomTimeSlots.*.over_night']        = 'sometimes|boolean';
            $rules['items.*.roomTimeSlots.*.status']            = 'sometimes|string';
            $rules['items.*.price']               = 'sometimes|nullable|numeric|min:0';
            $rules['items.*.default_checkin']     = 'sometimes|nullable|string';
            $rules['items.*.default_checkout']    = 'sometimes|nullable|string';
            $rules['items.*.deposit_min_nights']  = 'sometimes|nullable|integer|min:0';
            $rules['items.*.deposit_multi_night'] = 'sometimes|nullable|integer|min:1|max:100';
        }

        return $request->validate($rules);
    }

    private function toListItem(PriceBoard $board): array
    {
        return [
            'id'               => $board->id,
            'name'             => $board->name,
            'note'             => $board->note,
            'is_active'        => (bool) $board->is_active,
            'pricing_mode'     => $board->pricing_mode,
            'adjustment_type'  => $board->adjustment_type,
            'adjustment_value' => $board->adjustment_value !== null ? (float) $board->adjustment_value : null,
            'start_date'       => $board->start_date?->toDateString(),
            'end_date'         => $board->end_date?->toDateString(),
            'covers_today'     => $board->coversDate(),
            'rooms_count'      => $board->items_count ?? $board->items()->count(),
            'created_at'       => $board->created_at,
        ];
    }

    private function toDetailItem(PriceBoard $board): array
    {
        return $this->toListItem($board) + [
            'items' => $board->items->map(function ($item) {
                $style = (int) ($item->product?->styles ?? 1);

                $row = [
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product?->name,
                    'style'        => $style === 2 ? 'daily' : 'slot',
                    'full_booking_discount' => $item->full_booking_discount,
                    'bulk_discount_rules'   => $item->bulk_discount_rules,
                    'room_config'           => $item->room_config,
                ];

                if ($style === 2) {
                    $row['price']               = $item->price !== null ? (int) $item->price : null;
                    $row['default_checkin']     = $item->default_checkin;
                    $row['default_checkout']    = $item->default_checkout;
                    $row['deposit_min_nights']  = $item->deposit_min_nights;
                    $row['deposit_multi_night'] = $item->deposit_multi_night;
                } else {
                    $row['room_time_slots'] = $item->timeSlots->map(fn ($slot) => [
                        'timeslot_id' => $slot->timeslot_id,
                        'label'       => $slot->timeslot?->label,
                        'price'       => (int) $slot->price,
                        'over_night'  => (bool) $slot->over_night,
                        'status'      => $slot->status,
                    ])->values();
                }

                return $row;
            })->values(),
            'recent_price_logs' => PriceBoardPriceLog::where('price_board_id', $board->id)
                ->with('product:id,name')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($log) => [
                    'product'    => $log->product?->name,
                    'old_price'  => $log->old_price,
                    'new_price'  => $log->new_price,
                    'created_at' => $log->created_at,
                ])->values(),
        ];
    }
}
