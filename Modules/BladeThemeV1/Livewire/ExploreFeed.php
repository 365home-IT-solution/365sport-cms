<?php

namespace Modules\BladeThemeV1\Livewire;

use App\Settings\GeneralSettings;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Post\Entities\Post;
use Modules\Promotion\App\Models\Promotion;

/**
 * Trang /kham-pha (giao diện tham khảo ALO Booking) — CHỈ giao diện, theo quyết định đã chốt với
 * người dùng: hệ thống chưa có model Event/Ticket/Pass (đếm ngược, mua vé, số người tham gia thật)
 * nên feed ở đây ghép từ 2 nguồn dữ liệu THẬT đang có: Bài viết (Post, status=published) và
 * Promotion thật (Modules\Promotion\App\Models\Promotion, is_active + còn hạn) — TRƯỚC ĐÂY tab
 * "Ưu đãi" dùng mock cứng 3 khuyến mãi giả (bug đã gặp: nút "Xem chi tiết" trỏ tới trang tìm kiếm
 * chung chung /s, không phải trang khuyến mãi thật, vì dữ liệu vốn không thật nên không có đích
 * đến rõ ràng). Không có nút "Mua vé"/đếm ngược/tiến trình người tham gia vì chưa có dữ liệu thật
 * cho các phần đó.
 */
class ExploreFeed extends Component
{
    public array $items = [];
    public string $activeTab = 'all';

    public array $tabs = [
        'all'   => 'Tất cả',
        'post'  => 'Thông báo',
        'promo' => 'Ưu đãi',
    ];

    public function mount(): void
    {
        $logo = (new GeneralSettings())->brand_logo ?? null;
        $siteName = config('app.name', '365 Home');

        $posts = Post::query()
            ->where('status', 'published')
            ->latest('published_at')
            ->limit(12)
            ->get()
            ->map(fn (Post $post) => [
                'type'      => 'post',
                'title'     => $post->title,
                'summary'   => $post->summary,
                'image_url' => $post->getFirstMediaUrl() ?: null,
                'author'    => $siteName,
                'logo'      => $logo,
                'date'      => optional($post->published_at)->format('H:i - d/m/Y'),
                'url'       => route('post.detail', ['slug' => $post->slug]),
            ]);

        $promos = Promotion::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
            // CHỈ hiện khuyến mãi ĐÃ được gắn vào ít nhất 1 khung giờ thật (gắn ở trang admin "Hệ
            // thống giá" /admin/books, KHÔNG gắn được ngay trong form Ưu đãi) — khuyến mãi chưa gắn
            // gì thì không có sân/chi nhánh nào để trỏ tới, hiển thị ra chỉ gây hiểu nhầm.
            ->whereHas('roomTimeSlots')
            ->with('roomTimeSlots.room.partner.plan')
            ->latest('created_at')
            ->limit(12)
            ->get()
            // Ẩn khuyến mãi nếu KHÔNG CÒN sân/khung giờ nào gắn thuộc đối tác đang có tính năng
            // "promotions" theo gói dịch vụ hiện tại (Partner::hasFeature()) — vd đối tác đã hạ
            // xuống gói thấp hơn, khuyến mãi cũ vẫn còn liên kết trong DB nhưng không nên hiện ra
            // trang Khám phá chung nữa.
            ->filter(fn (Promotion $promo) => $promo->roomTimeSlots->contains(
                fn ($rts) => ($rts->room?->partner?->hasFeature('promotions') ?? true) !== false
            ))
            ->map(function (Promotion $promo) use ($siteName, $logo) {
                return [
                    'type'      => 'promo',
                    'title'     => $promo->name,
                    'summary'   => $promo->description ?: ($promo->type === 'discount' && $promo->value
                        ? 'Giảm ' . rtrim(rtrim(number_format((float) $promo->value, 2), '0'), '.') . '%'
                        : null),
                    'image_url' => $promo->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($promo->image) : null,
                    'author'    => $siteName,
                    'logo'      => $logo,
                    'date'      => null,
                    'emoji'     => '🎁',
                    // 1 khuyến mãi có thể gắn khung giờ ở NHIỀU chi nhánh khác nhau — thay vì đoán
                    // 1 chi nhánh đại diện, đưa thẳng sang bản đồ kèm ?promotion=id để
                    // MapPage::mount() tự lọc đúng TẤT CẢ chi nhánh đang áp dụng khuyến mãi này.
                    'url'       => route('map.page', ['promotion' => $promo->id]),
                ];
            });

        // Ưu đãi lên đầu feed khi ở tab "Tất cả" (theo yêu cầu người dùng) — bài viết xếp sau.
        $this->items = $promos->concat($posts)->values()->toArray();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getFilteredItemsProperty(): array
    {
        if ($this->activeTab === 'all') {
            return $this->items;
        }

        return array_values(array_filter($this->items, fn ($item) => $item['type'] === $this->activeTab));
    }

    public function render(): View
    {
        return view('bladethemev1::livewire.explore-feed');
    }
}
