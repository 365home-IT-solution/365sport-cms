<?php

namespace Modules\BladeThemeV1\Livewire;

use App\Models\DirectionsConfiguration;
use App\Models\ProvinceBranch;
use App\Models\RoomRating;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Promotion\App\Models\Promotion;

/**
 * Trang /ban-do (giao diện tham khảo ALO Booking): danh sách chi nhánh bên trái + bản đồ Leaflet/
 * OpenStreetMap bên phải, marker lấy toạ độ từ Product đại diện của mỗi chi nhánh (Category không
 * tự có lat/lng — xem VenueGrid.php cho cùng cách lấy dữ liệu). Bấm vào 1 sân (marker hoặc dòng
 * trong danh sách) mở panel chi tiết (ảnh, đánh giá, địa chỉ/giờ/hotline, tab Thông tin/Hình
 * ảnh/Đánh giá..., kèm bảng khung giờ HÔM NAY chỉ để xem — không đặt trực tiếp ở đây, xem
 * map-page.blade.php và today_availability bên dưới).
 *
 * Khác ảnh mẫu: ảnh mẫu có hàng pill lọc theo MÔN THỂ THAO (Pickleball, Cầu lông...), nhưng hệ
 * thống hiện tại không có taxonomy "môn thể thao" riêng (Category.category_type chỉ phân biệt
 * product/post — xem Modules/BladeThemeV1/Livewire/Product.php) — nên hàng pill ở đây liệt kê
 * TÊN CHI NHÁNH thực tế để bấm nhanh tới (thay vì lọc theo môn thể thao chưa tồn tại).
 */
class MapPage extends Component
{
    public array $venues = [];

    // Toạ độ "Tìm sân gần đây" gửi từ trang khác (components/home/search-bar.blade.php,
    // findNearby() không tìm thấy window.__panToMyLocation vì chưa ở sẵn trang bản đồ nên điều
    // hướng kèm query lat/lng) — map-page.blade.php tự pan tới đây sau khi khởi tạo xong.
    public ?float $initialLat = null;
    public ?float $initialLng = null;

    // Dẫn đường qua OpenRouteService (tránh Lào/Campuchia) hay OSRM công cộng như trước đây — nhập
    // ở admin (App\Filament\Pages\DirectionsSettings), xem map-page.blade.php nhánh drawRoute().
    public bool $directionsOrsEnabled = false;

    // Nền bản đồ CARTO Voyager — xem ghi chú đầy đủ tại config('services.carto') + .env.example
    // (CARTO_BASEMAPS_API_KEY). Rỗng thì tile CARTO chỉ hiện chữ đè "API KEY REQUIRED" (bug thật đã
    // gặp, KHÔNG phải lỗi code — CARTO đổi chính sách bắt buộc key, trước đây thật sự miễn phí).
    public ?string $cartoBasemapsKey = null;

    // Đoán "môn thể thao" hiển thị dạng badge từ tên chi nhánh — không có taxonomy riêng (xem
    // comment ở SportVenueDemoSeeder::coverImageFor(), cùng logic đoán từ khoá).
    private const SPORT_KEYWORDS = [
        'pickleball' => 'Pickleball',
        'tennis'     => 'Tennis',
        'cầu lông'   => 'Cầu lông',
        'bóng đá'    => 'Bóng đá',
        'bóng rổ'    => 'Bóng rổ',
    ];

    public function mount(): void
    {
        // Lọc từ popup lọc dùng chung (components/home/search-bar.blade.php) — ĐÂY LÀ NƠI LỌC DUY
        // NHẤT của toàn site (trang danh sách /s/{province} không còn nhận lọc từ popup nữa, xem
        // comment ở đầu search-bar.blade.php). type = RoomType.slug (đã backfill room_type_id đầy
        // đủ cho toàn bộ sân), province = Province.slug, ward_code = cột thật trên ProvinceBranch
        // (Ward.code), q = tìm theo tên chi nhánh (ô tìm kiếm chính của thanh search-bar).
        $typeSlug     = request()->query('type');
        $provinceSlug = request()->query('province');
        $wardCode     = request()->query('ward_code');
        $keyword      = request()->query('q');
        // Từ nút "Xem chi tiết" ở tab Ưu đãi trang Khám phá (ExploreFeed.php) — 1 khuyến mãi có thể
        // áp dụng cho khung giờ ở NHIỀU chi nhánh khác nhau, nên lọc ra TẤT CẢ chi nhánh có ít nhất
        // 1 sân có khung giờ gắn đúng khuyến mãi này, thay vì chỉ trỏ tới 1 chi nhánh đại diện.
        $promotionId  = request()->query('promotion');
        $this->initialLat = request()->query('lat') ? (float) request()->query('lat') : null;
        $this->initialLng = request()->query('lng') ? (float) request()->query('lng') : null;

        $directionsConfig = DirectionsConfiguration::first();
        $this->directionsOrsEnabled = (bool) ($directionsConfig?->enabled && $directionsConfig?->ors_api_key);
        $this->cartoBasemapsKey = config('services.carto.basemaps_key');

        $this->venues = ProvinceBranch::query()
            ->where('status', true)
            ->whereHas('category', fn ($q) => $q->where('status', true)
                ->when($keyword, fn ($sub) => $sub->where('name', 'like', '%' . $keyword . '%'))
                ->when($promotionId, fn ($sub) => $sub->whereHas(
                    'products.roomTimeSlots.promotions',
                    fn ($pp) => $pp->where('promotions.id', $promotionId)
                )))
            ->when($provinceSlug, fn ($q) => $q->whereHas('province', fn ($sub) => $sub->where('slug', $provinceSlug)))
            ->when($wardCode, fn ($q) => $q->where('ward_code', $wardCode))
            // QUAN TRỌNG: KHÔNG dùng ->limit(1) bên trong closure eager-load của quan hệ
            // morphedByMany (products) — với BelongsToMany/morphToMany, Laravel áp LIMIT đó cho
            // TOÀN BỘ câu query 1 lần (không phải limit riêng cho từng category cha), nên chỉ 1
            // category duy nhất trong cả danh sách nhận được product, tất cả category khác nhận
            // collection rỗng -> $product = null -> bị lọc mất khỏi bản đồ (bug đã gặp: bản đồ
            // chỉ hiện đúng 1 marker). Lấy hết rồi ->first() ở PHP bên dưới thay vì limit trong SQL.
            ->with(['category' => fn ($q) => $q->with(['products' => function ($pq) use ($typeSlug) {
                $pq->whereNotNull('latitude')->whereNotNull('longitude')->with('roomType');
                if ($typeSlug) {
                    $pq->whereHas('roomType', fn ($rq) => $rq->where('slug', $typeSlug));
                }
            }])])
            ->get()
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->map(function ($category) {
                $products = $category->products;
                $product  = $products->first();

                if (! $product) {
                    return null;
                }

                $ratingCount = RoomRating::whereIn('room_id', $products->pluck('id'))->count();
                $ratingAvg   = $ratingCount > 0 ? RoomRating::whereIn('room_id', $products->pluck('id'))->avg('star') : null;

                // Giá "Từ ..." hiển thị trên nhãn giá ở bản đồ + thẻ khi rê chuột (giống homestay): giá khung giờ
                // thấp nhất còn mở của các sân thuộc chi nhánh; chưa cấu hình khung giờ thì lấy giá sân thấp nhất.
                $priceFrom = RoomTimeSlot::whereIn('room_id', $products->pluck('id'))
                    ->where('status', 'available')
                    ->where('price', '>', 0)
                    ->min('price');
                $priceFrom = $priceFrom ?: $products->where('price', '>', 0)->min('price');

                $gallery = $products
                    ->flatMap(fn ($p) => $p->getMedia('Thư viện'))
                    ->map(fn ($media) => $media->getUrl())
                    ->values();

                $coverUrl = $category->image
                    ? Storage::disk('public')->url($category->image)
                    : ($product->getFirstMediaUrl('Ảnh bìa') ?: null);

                // Ưu đãi THẬT đang áp dụng cho chi nhánh này — hiện ở tab "Thông tin" panel chi
                // tiết (map-page.blade.php), cùng nguồn dữ liệu với tab "Ưu đãi" ở trang Khám phá
                // (ExploreFeed.php): Promotion đang bật + còn hạn + đã gắn ít nhất 1 khung giờ của
                // sân thuộc chi nhánh này. Rỗng thẳng nếu đối tác sở hữu chi nhánh không còn tính
                // năng "promotions" theo gói dịch vụ hiện tại (Partner::hasFeature()) — khách hàng
                // không nên thấy ưu đãi của những gói đối tác đã hạ cấp/không dùng nữa.
                $promotions = $category->partner?->hasFeature('promotions') === false
                    ? collect()
                    : Promotion::query()
                        ->where('is_active', true)
                        ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
                        ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
                        ->whereHas('roomTimeSlots', fn ($q) => $q->whereIn('room_id', $products->pluck('id')))
                        ->get(['id', 'name', 'description', 'type', 'value'])
                        ->map(fn (Promotion $p) => [
                            'name'    => $p->name,
                            'summary' => $p->description ?: ($p->type === 'discount' && $p->value
                                ? 'Giảm ' . rtrim(rtrim(number_format((float) $p->value, 2), '0'), '.') . '%'
                                : null),
                        ])
                        ->values();

                return [
                    'name'         => $category->name,
                    'slug'         => $category->slug,
                    'url'          => route('branch.booking', ['slug' => $category->slug]),
                    'address'      => $product->address,
                    'price_from'   => $priceFrom ? (int) $priceFrom : null,
                    'lat'          => (float) $product->latitude,
                    'lng'          => (float) $product->longitude,
                    'image_url'    => $coverUrl,
                    'gallery'      => $gallery,
                    'hours'        => $this->formatHours($category->checkin_time, $category->checkout_time),
                    'promotions'   => $promotions,
                    'hotline'      => $product->hotline ?: null,
                    'description'  => $category->description ?: $product->description,
                    // Ưu tiên môn thể thao THẬT của $product (chính là sân đại diện, xem comment
                    // "->first()" ở trên) qua RoomType — QUAN TRỌNG khi đang lọc theo $typeSlug:
                    // $products lúc đó đã bị lọc CHỈ CÒN đúng loại sân khớp filter (whereHas
                    // 'roomType' ở trên), nên $product->roomType->name luôn đúng khớp với môn đang
                    // tìm — 1 chi nhánh vừa có sân cầu lông vừa có sân bóng đá sẽ tự hiện nhãn
                    // "Cầu lông" khi lọc cầu lông, đổi thành "Bóng đá" khi lọc bóng đá, thay vì
                    // luôn hiện cố định 1 nhãn đoán theo TÊN chi nhánh như trước (bug đã gặp — nhãn
                    // sai môn khi chi nhánh có nhiều loại sân khác nhau).
                    'sport_label'  => optional($product->roomType)->name ?? $this->guessSportLabel($category->name),
                    'court_count'  => $products->count(),
                    'rating_avg'   => $ratingAvg ? round((float) $ratingAvg, 1) : null,
                    'rating_count' => $ratingCount,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Bảng khung giờ HÔM NAY (chỉ xem, không đặt được ở đây — trạng thái "đã đặt" tính real-time
     * từ orderItems giống hệt cách book/_slot-cell.blade.php tính, nhưng KHÔNG kèm giá/khuyến mãi/
     * giữ chỗ vì đây chỉ là bảng xem trước gọn trong panel bản đồ). Gọi qua $wire.call() khi mở
     * panel chi tiết 1 sân (map-page.blade.php) thay vì nhúng sẵn cho TẤT CẢ chi nhánh lúc mount()
     * (tốn kém không cần thiết cho những chi nhánh người dùng không xem tới).
     */
    public function getTodayAvailability(string $categorySlug): array
    {
        $today = Carbon::today();

        $products = Product::whereHas('categories', fn ($q) => $q->where('categories.slug', $categorySlug))
            ->where('is_activated', true)
            ->where(fn ($q) => $q->where('styles', 1)->orWhereNull('styles'))
            ->with([
                'roomTimeSlots' => fn ($q) => $q->join('time_slots', 'room_time_slots.timeslot_id', '=', 'time_slots.id')
                    ->select('room_time_slots.*')
                    ->orderBy('time_slots.start_time'),
                'roomTimeSlots.timeSlot',
                'orderItems' => fn ($q) => $q->where('checkout_date', '>', $today->copy()->startOfDay())
                    ->where('checkin_date', '<=', $today->copy()->endOfDay())
                    ->whereHas('order', fn ($sub) => $sub->whereIn('status', ['pending', 'paid', 'shipped', 'confirmed'])->whereNull('cancelled_at')),
            ])
            ->get();

        if ($products->isEmpty()) {
            return ['courts' => [], 'times' => []];
        }

        $times = $products
            ->flatMap(fn ($p) => $p->roomTimeSlots->pluck('timeSlot.start_time'))
            ->unique()
            ->sort()
            ->values();

        $courts = $products->map(function (Product $product) use ($times, $today) {
            $slotsByTime = $product->roomTimeSlots->keyBy(fn ($rts) => $rts->timeSlot->start_time);

            $cells = $times->map(function ($time) use ($slotsByTime, $product, $today) {
                if (! $slotsByTime->has($time)) {
                    return ['label' => substr($time, 0, 5), 'status' => 'locked'];
                }

                $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' ' . $time);
                $isBooked  = $product->orderItems->contains(function ($item) use ($slotStart) {
                    return $slotStart->between(Carbon::parse($item->checkin_date), Carbon::parse($item->checkout_date));
                });

                return ['label' => substr($time, 0, 5), 'status' => $isBooked ? 'booked' : 'free'];
            })->values();

            return ['name' => $product->name, 'cells' => $cells];
        })->values();

        return [
            'courts' => $courts,
            'times'  => $times->map(fn ($t) => substr($t, 0, 5))->values(),
        ];
    }

    /**
     * Tab "Dịch vụ" của panel bản đồ: bảng giá theo khung giờ (từ RoomTimeSlot thật, KHÔNG có 2
     * mức "Cố định/Vãng lai" như ảnh tham khảo — hệ thống hiện chỉ có 1 giá/khung giờ, xem
     * Modules/Product/App/Models/RoomTimeSlot.php) + danh sách dịch vụ thuê thêm thật (RoomService
     * gắn theo từng sân, AdditionService gắn theo đối tác qua bảng room_additional_service_assigns
     * — xem database/seeders/PartnerSeeder.php).
     *
     * Gộp gọn 2 lớp (giống yêu cầu "giá giống nhau thì gom lại", KHÔNG gộp nhầm sân có giá/khung
     * giờ khác nhau): (1) nhiều khung giờ liên tiếp CÙNG giá trên 1 sân -> gộp thành 1 dòng khoảng
     * giờ; (2) nhóm các sân có ĐÚNG CÙNG 1 bảng giá (sau khi gộp) lại thành 1 bảng DÙNG CHUNG, tên
     * ghép tất cả sân trong nhóm (vd "Sân 1, Sân 2") — sân nào có bảng giá khác (vd 1 sân cao cấp
     * đắt hơn) thì tự thành 1 nhóm/bảng riêng, không bị gộp lẫn vào nhóm khác.
     */
    public function getServicesAndPricing(string $categorySlug): array
    {
        $products = Product::whereHas('categories', fn ($q) => $q->where('categories.slug', $categorySlug))
            ->where('is_activated', true)
            ->with(['roomTimeSlots.timeSlot', 'services', 'additionalServices'])
            ->get();

        $branchName = optional($products->first()?->categories->firstWhere('slug', $categorySlug))->name;

        $perCourt = $products->map(function (Product $product) use ($branchName) {
            $rows = $this->collapsePriceRanges($product->roomTimeSlots->sortBy(fn ($rts) => $rts->timeSlot->start_time));

            // Bỏ tiền tố trùng tên chi nhánh (vd "Catchy Pickleball Club - Sân 1" -> "Sân 1") để
            // khi gộp nhiều sân cùng giá, tên không lặp lại tên chi nhánh 3 lần liền nhau.
            $shortName = $branchName ? preg_replace('/^' . preg_quote($branchName, '/') . '\s*-\s*/u', '', $product->name) : $product->name;

            return ['name' => $shortName, 'rows' => $rows, 'signature' => json_encode($rows->toArray())];
        })->filter(fn ($p) => $p['rows']->isNotEmpty())->values();

        // Nhóm các sân theo CHỮ KÝ bảng giá (giữ thứ tự xuất hiện đầu tiên của mỗi nhóm) — chỉ gộp
        // tên + dùng 1 bảng rows chung cho các sân CÙNG nhóm, không đụng tới nhóm khác.
        $pricing = $perCourt
            ->groupBy('signature')
            ->map(fn ($group) => [
                'name' => $group->pluck('name')->implode(', '),
                'rows' => $group->first()['rows'],
            ])
            ->values();

        $services = $products
            ->flatMap(fn ($p) => $p->services)
            ->unique('id')
            ->map(fn ($s) => ['name' => $s->name, 'price' => number_format((float) $s->price, 0, ',', '.') . ' đ' . ($s->unit ? ' / ' . $s->unit : '')])
            ->concat(
                $products->flatMap(fn ($p) => $p->additionalServices)
                    ->unique('id')
                    ->map(fn ($s) => ['name' => $s->name, 'price' => number_format((float) $s->price, 0, ',', '.') . ' đ'])
            )
            ->values();

        return ['pricing' => $pricing, 'services' => $services];
    }

    /**
     * Gộp các RoomTimeSlot LIÊN TIẾP có CÙNG giá thành 1 dòng "05:00 - 22:00: 120.000 đ" thay vì
     * liệt kê từng khung 1 tiếng riêng lẻ dù giá giống hệt nhau suốt cả ngày.
     *
     * @param \Illuminate\Support\Collection $sortedSlots RoomTimeSlot đã sắp theo giờ bắt đầu.
     */
    private function collapsePriceRanges($sortedSlots): \Illuminate\Support\Collection
    {
        $rows = collect();
        $rangeStart = null;
        $rangeEnd = null;
        $rangePrice = null;

        foreach ($sortedSlots as $rts) {
            $price = (float) $rts->price;
            $start = substr($rts->timeSlot->start_time, 0, 5);
            $end   = substr($rts->timeSlot->end_time, 0, 5);

            if ($rangePrice !== null && $price === $rangePrice && $rangeEnd === $start) {
                // Liên tiếp + cùng giá -> nối dài khoảng hiện tại.
                $rangeEnd = $end;
                continue;
            }

            if ($rangePrice !== null) {
                $rows->push([
                    'range' => "{$rangeStart} - {$rangeEnd}",
                    'price' => number_format($rangePrice, 0, ',', '.') . ' đ',
                ]);
            }

            $rangeStart = $start;
            $rangeEnd   = $end;
            $rangePrice = $price;
        }

        if ($rangePrice !== null) {
            $rows->push([
                'range' => "{$rangeStart} - {$rangeEnd}",
                'price' => number_format($rangePrice, 0, ',', '.') . ' đ',
            ]);
        }

        return $rows;
    }

    private function formatHours(?string $checkin, ?string $checkout): ?string
    {
        if (! $checkin || ! $checkout) {
            return null;
        }

        return substr($checkin, 0, 5) . ' - ' . substr($checkout, 0, 5);
    }

    private function guessSportLabel(string $name): ?string
    {
        $lower = Str::lower($name);

        foreach (self::SPORT_KEYWORDS as $keyword => $label) {
            if (Str::contains($lower, $keyword)) {
                return $label;
            }
        }

        return null;
    }

    public function render(): View
    {
        return view('bladethemev1::livewire.map-page');
    }
}
