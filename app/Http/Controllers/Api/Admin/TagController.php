<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\Tags\Tag;

/**
 * Danh sách TIỆN ÍCH sân — dùng bảng `tags` (Spatie\Tags\Tag, field `tags` trên ProductForm, label
 * hiển thị "Tiện ích" — xem Modules/Product/lang/vi/product.php). KHÔNG phải RoomAmenity (module
 * riêng "room_amenities", dùng cho 1 màn quản lý khác - RoomAmenityAssignResource).
 *
 * name/slug là field translatable (spatie/laravel-translatable) nên đọc thẳng $tag->name là ra
 * chuỗi theo locale hiện tại. Riêng `image` KHÔNG được khai báo translatable trên model Tag của
 * package, nhưng TagForm (Filament) vẫn cố tình lưu dạng JSON {"vi": "path"} giống name/slug (xem
 * TagForm::imagesCard dehydrateStateUsing) — nên phải tự giải mã ở đây thay vì đọc thẳng.
 */
class TagController extends Controller
{
    // Map giá trị ?type= thân thiện (product/post) sang morph class thật lưu ở taggables.taggable_type.
    private const TYPE_MORPH_MAP = [
        'product' => \Modules\Product\App\Models\Product::class,
        'post'    => \Modules\Post\Entities\Post::class,
    ];

    /**
     * GET /api/admin/tags
     * Query params:
     *  - search : lọc theo tên
     *  - type   : 'product' (tiện ích dành cho sân) hoặc 'post' (thẻ dành cho bài viết) — bảng
     *             `tags` dùng CHUNG cho cả Post lẫn Product nên cần lọc mới biết tag nào thuộc bên nào.
     *             Xét theo 2 nguồn: (1) cột tags.type nếu có set đúng giá trị 'product'/'post' —
     *             đây là quy ước gốc của package spatie/laravel-tags nhưng TagForm hiện KHÔNG có
     *             field này nên trong dữ liệu thực tế cột này luôn rỗng; (2) tag ĐÃ được gắn thực
     *             tế vào ít nhất 1 bản ghi Post/Product (bảng taggables.taggable_type) — cùng cách
     *             Modules\Tag\...\TagTable dùng để hiển thị "Bài viết"/"Sân sử dụng" ở trang
     *             admin. Với dữ liệu hiện tại, (2) là tín hiệu DUY NHẤT thực sự hoạt động; giữ thêm
     *             (1) để không vỡ nếu sau này TagForm được bổ sung field type.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query();

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('type')) {
            $type = (string) $request->string('type');

            if (! array_key_exists($type, self::TYPE_MORPH_MAP)) {
                return response()->json([
                    'message' => 'type không hợp lệ. Chỉ nhận: ' . implode(', ', array_keys(self::TYPE_MORPH_MAP)) . '.',
                ], 422);
            }

            $morphClass = self::TYPE_MORPH_MAP[$type];

            $query->where(function ($q) use ($type, $morphClass) {
                $q->where('type', $type)
                    ->orWhereExists(function ($sub) use ($morphClass) {
                        $sub->selectRaw('1')
                            ->from('taggables')
                            ->whereColumn('taggables.tag_id', 'tags.id')
                            ->where('taggables.taggable_type', $morphClass);
                    });
            });
        }

        $tags = $query->orderBy('order_column')->orderBy('id')->get();

        $data = $tags->map(fn (Tag $tag) => [
            'id'        => $tag->id,
            'name'      => $tag->name,
            'type'      => $tag->type,
            'image'     => $image = $this->extractImage($tag),
            'image_url' => $image ? Storage::disk('public')->url($image) : null,
        ])->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/admin/tags/{id}
     */
    public function show(string $id): JsonResponse
    {
        $tag = Tag::find($id);

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        return response()->json(['data' => $this->toItem($tag)]);
    }

    /**
     * POST /api/admin/tags — body: name (bắt buộc), type (tuỳ chọn), image (file, tuỳ chọn).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'type'  => 'nullable|string|max:50',
            'image' => 'sometimes|nullable|file|image|max:4096',
        ]);

        $tag = Tag::findOrCreate($data['name'], $data['type'] ?? null);
        $this->saveImage($request, $tag);

        return response()->json(['data' => $this->toItem($tag->fresh())], 201);
    }

    /**
     * PUT|POST /api/admin/tags/{id} — đổi tên / ảnh.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tag = Tag::find($id);

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        $data = $request->validate([
            'name'  => 'sometimes|required|string|max:100',
            'image' => 'sometimes|nullable|file|image|max:4096',
        ]);

        if (isset($data['name'])) {
            $tag->setTranslation('name', app()->getLocale(), $data['name']);
            $tag->setTranslation('slug', app()->getLocale(), \Illuminate\Support\Str::slug($data['name']));
            $tag->save();
        }
        $this->saveImage($request, $tag);

        return response()->json(['data' => $this->toItem($tag->fresh())]);
    }

    /**
     * DELETE /api/admin/tags/{id} — gỡ thẻ khỏi mọi bài viết/sân rồi xoá.
     */
    public function destroy(string $id): JsonResponse
    {
        $tag = Tag::find($id);

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        \Illuminate\Support\Facades\DB::table('taggables')->where('tag_id', $tag->id)->delete();
        $tag->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // Ảnh thẻ lưu dạng JSON theo locale giống TagForm (Filament): {"vi": "path"}.
    private function saveImage(Request $request, Tag $tag): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $path = $request->file('image')->store('tags', 'public');
        $tag->image = json_encode([app()->getLocale() => $path]);
        $tag->save();
    }

    private function toItem(Tag $tag): array
    {
        $image = $this->extractImage($tag);

        return [
            'id'        => $tag->id,
            'name'      => $tag->name,
            'type'      => $tag->type,
            'image'     => $image,
            'image_url' => $image ? Storage::disk('public')->url($image) : null,
        ];
    }

    private function extractImage(Tag $tag): ?string
    {
        $raw = $tag->getRawOriginal('image');

        if (empty($raw)) {
            return null;
        }

        if (is_string($raw) && str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded[app()->getLocale()] ?? collect($decoded)->first();
            }
        }

        return $raw;
    }
}
