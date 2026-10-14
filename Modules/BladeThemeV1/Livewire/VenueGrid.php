<?php

namespace Modules\BladeThemeV1\Livewire;

use App\Models\Province;
use App\Models\ProvinceBranch;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Product\App\Models\RoomType;

/**
 * Lưới thẻ sân/chi nhánh ở trang chủ (giao diện tham khảo ALO Booking) — mỗi thẻ là 1 chi nhánh
 * (Category có category_type liên kết qua ProvinceBranch, giống BranchSuggestion.php), lấy thêm
 * địa chỉ/toạ độ/đánh giá từ Product đại diện đầu tiên của chi nhánh đó (Category không tự có các
 * field này — xem plan). Không lọc theo khu vực đã chọn (khác BranchSuggestion): hiển thị tất cả
 * chi nhánh đang hoạt động, khoảng cách được Alpine tính ở phía client (xem card.blade.php).
 *
 * Hàng chip lọc theo môn thể thao (RoomType.slug, cùng khái niệm "type" dùng ở search-bar/
 * MapPage — xem $typeSlug trong MapPage::mount()) — bấm 1 chip lọc lại NGAY danh sách chi nhánh
 * hiện có ít nhất 1 sân đúng môn đó, không rời trang. "Tất cả" (activeType = null) luôn là lựa
 * chọn mặc định khi mở trang lần đầu.
 *
 * Hàng chip lọc theo TỈNH/THÀNH — chỉ gồm tỉnh có sân thật (Province::idsWithCourts(), cùng quy tắc với
 * bộ lọc Khu vực và API /provinces; tỉnh không có sân thì không hiện). Chọn tỉnh đồng bộ với phần còn
 * lại của trang chủ (Lịch đặt sân trực tuyến, gợi ý chi nhánh...) qua localStorage `home_province_id` +
 * sự kiện window 'province-selected' (xem venue-grid.blade.php); "Tất cả khu vực" chỉ bỏ lọc lưới này.
 */
class VenueGrid extends Component
{
    public array $venues = [];

    public array $roomTypes = [];

    public ?string $activeType = null;

    /** @var list<array{id:int,name:string,slug:string}> */
    public array $provinces = [];

    public ?int $activeProvinceId = null;

    public function mount(): void
    {
        $this->roomTypes = RoomType::orderBy('name')->get(['id', 'name', 'slug'])
            ->map(fn ($rt) => ['name' => $rt->name, 'slug' => $rt->slug])
            ->toArray();

        $this->provinces = Province::withCourts()
            ->orderByRaw('code IS NULL, code ASC')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug])
            ->toArray();

        $this->loadVenues();
    }

    // Chọn tỉnh (chip, hoặc sự kiện 'province-selected' từ nơi khác trên trang chủ) — null = "Tất cả
    // khu vực". Id không thuộc danh sách tỉnh có sân (vd localStorage cũ) thì bỏ qua, không đổi gì.
    public function setProvince(?int $id): void
    {
        if ($id !== null && ! in_array($id, array_column($this->provinces, 'id'), true)) {
            return;
        }
        if ($id === $this->activeProvinceId) {
            return;
        }

        $this->activeProvinceId = $id;
        $this->loadVenues();
        $this->dispatch('venues-updated');
    }

    // Gọi lại khi bấm 1 chip môn thể thao (wire:click="selectType('...')" ở venue-grid.blade.php)
    // — truyền null để quay lại "Tất cả".
    public function selectType(?string $slug): void
    {
        $this->activeType = $slug ?: null;
        $this->loadVenues();

        // Báo cho JS sắp xếp lại thẻ theo khoảng cách (venue-grid.blade.php) — cần dispatch rõ ràng
        // vì thẻ <script> trong view chỉ chạy đúng 1 lần lúc trình duyệt parse HTML ban đầu, Livewire
        // morph lại #venue-grid-track khi đổi chip KHÔNG làm script chạy lại (bug đã gặp: chỉ "Tất
        // cả" ở lần tải trang đầu tiên được sắp xếp gần-xa, các môn khác bấm vào thì không).
        $this->dispatch('venues-updated');
    }

    private function loadVenues(): void
    {
        $typeSlug = $this->activeType;

        $categories = ProvinceBranch::query()
            ->where('status', true)
            ->whereHas('category', fn ($q) => $q->where('status', true))
            ->when($this->activeProvinceId, fn ($q, $provinceId) => $q->where('province_id', $provinceId))
            // KHÔNG dùng ->limit(1) trong closure eager-load của quan hệ morphedByMany (products)
            // — xem giải thích chi tiết trong MapPage.php (bug: limit áp cho toàn bộ query, không
            // phải riêng từng category, khiến chỉ 1 category nhận được product).
            ->with(['category' => fn ($q) => $q->with(['products' => function ($pq) use ($typeSlug) {
                if ($typeSlug) {
                    $pq->whereHas('roomType', fn ($rq) => $rq->where('slug', $typeSlug));
                }
            }])])
            ->get()
            ->pluck('category')
            ->filter()
            // Chi nhánh không còn sân nào khớp môn đang lọc (products rỗng sau khi lọc theo
            // $typeSlug) thì loại hẳn khỏi danh sách — khác "Tất cả" (không lọc gì, luôn còn sân
            // đại diện vì query gốc KHÔNG áp whereHas('products') bắt buộc phải có sân).
            ->filter(fn ($category) => ! $typeSlug || $category->products->isNotEmpty())
            ->unique('id')
            ->sortBy('sort_order')
            ->take(24)
            ->values();

        // Chi nhánh có bán gói nhiều buổi → thẻ sân hiện icon hộp quà (xem venues/card.blade.php).
        $withPackages = array_flip(\App\Models\PackagePlan::categoryIdsWithActivePlans($categories));

        $this->venues = $categories
            ->map(function ($category) use ($withPackages) {
                $product = $category->products->first();

                return [
                    'name'       => $category->name,
                    'url'        => route('branch.booking', ['slug' => $category->slug]),
                    'image_url'  => $category->image
                        ? Storage::disk('public')->url($category->image)
                        : ($product?->getFirstMediaUrl('Ảnh bìa') ?: null),
                    'address'    => $product->address ?? null,
                    'lat'        => $product->latitude ?? null,
                    'lng'        => $product->longitude ?? null,
                    'rating'     => $product && $product->rating_score > 0 ? number_format((float) $product->rating_score, 1) : null,
                    'hours'      => $this->formatHours($category->checkin_time, $category->checkout_time),
                    // Sân đại diện của chi nhánh — dùng để gắn nút Yêu thích THẬT (API
                    // /api/wishlist/{id}/toggle vốn hoạt động ở cấp SÂN, không có khái niệm "yêu
                    // thích 1 chi nhánh" riêng — xem components/venues/card.blade.php).
                    'product_id' => $product->id ?? null,
                    'has_package' => isset($withPackages[$category->id]),
                ];
            })
            ->values()
            ->toArray();
    }

    private function formatHours(?string $checkin, ?string $checkout): ?string
    {
        if (! $checkin || ! $checkout) {
            return null;
        }

        try {
            return substr($checkin, 0, 5) . ' - ' . substr($checkout, 0, 5);
        } catch (\Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        return view('bladethemev1::livewire.venue-grid');
    }
}
