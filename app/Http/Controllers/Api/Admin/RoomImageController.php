<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomImage;

/**
 * Quản lý ẢNH SÂN (bảng `room_images`, room_id) — xem docblock
 * Modules\Product\App\Filament\Resources\RoomImageResource + RoomImageForm. Cột `path` là JSON,
 * hình dạng phụ thuộc `type`:
 *  - type=main    : mảng phẳng 1 phần tử đường dẫn ảnh, vd ["room-images/abc.jpg"] (xem
 *                    RoomImage::firstImagePath() — "Flat array: return $data[0] ?? ''").
 *  - type=gallery : mảng các "khu vực", mỗi phần tử {title, description, images: [đường dẫn,...]}
 *                    (khớp Repeater 'gallery_path' ở RoomImageForm + RoomImage::getSectionsAttribute()).
 * File LƯU thẳng qua Storage::disk($disk) (KHÔNG dùng Spatie MediaLibrary như Product::image_main —
 * RoomImage tự quản lý disk/path riêng, xem cột `disk` trong RoomImageForm: public/s3/r2).
 * RoomImage KHÔNG có cột partner_id riêng — phạm vi hiển thị/sửa lọc GIÁN TIẾP qua partner_id của
 * Product cha (room_id), cùng nguyên tắc RoomServiceController/RoomSpecialController.
 */
class RoomImageController extends Controller
{
    private const IMAGE_RULE = 'file|mimes:jpg,jpeg,png,webp,avif,gif|max:5120';

    private const ALLOWED_DISKS = ['public', 's3', 'r2'];

    /**
     * GET /api/admin/products/{id}/images
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $images = $product->roomImages()->get();

        return response()->json(['data' => $images->map(fn (RoomImage $i) => $this->toItem($i))->values()]);
    }

    /**
     * POST /api/admin/products/{id}/images (multipart/form-data)
     * Body chung: type (required|main|gallery), disk (nullable, default public), alt, title, sort_order.
     * Nếu type=main   : image (required, 1 file).
     * Nếu type=gallery: sections[{title?, description?, images[] (required, >=1 file mỗi khu vực)}].
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $data = $request->validate($this->rules());

        $disk = $data['disk'] ?? 'public';
        $path = $data['type'] === 'main'
            ? $this->storeMain($request, $disk)
            : $this->storeGallerySections($request, $data['sections'] ?? [], $disk);

        $image = RoomImage::create([
            'room_id'    => $product->id,
            'type'       => $data['type'],
            'disk'       => $disk,
            'path'       => $path,
            'alt'        => $data['alt'] ?? null,
            'title'      => $data['title'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $this->toItem($image)], 201);
    }

    /**
     * PUT|POST /api/admin/products/{id}/images/{imageId} (multipart/form-data khi cần thay ảnh)
     * Chỉ field có mặt trong request mới bị ghi đè. Gửi kèm 'image' (type=main) hoặc 'sections'
     * (type=gallery) sẽ THAY THẾ TOÀN BỘ ảnh cũ (xoá file cũ khỏi disk trước khi lưu file mới) —
     * không hỗ trợ sửa từng khu vực/ảnh lẻ trong 1 khu vực, giữ đúng mức đơn giản của form Filament
     * (mutateFormDataBeforeSave() cũng ghi đè nguyên cột `path`, không merge từng phần).
     */
    public function update(Request $request, string $id, string $imageId): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $image = RoomImage::where('id', $imageId)->where('room_id', $product->id)->first();

        if (! $image) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $data = $request->validate($this->rules(isUpdate: true, currentType: $image->type));

        $type = $data['type'] ?? $image->type;
        $disk = $data['disk'] ?? $image->disk;

        $update = collect($data)->only(['alt', 'title', 'sort_order'])->toArray();
        $update['type'] = $type;
        $update['disk'] = $disk;

        $replacingMain    = $type === 'main' && $request->hasFile('image');
        $replacingGallery = $type === 'gallery' && array_key_exists('sections', $data);

        if ($replacingMain || $replacingGallery) {
            $this->deleteStoredFiles($image);
            $update['path'] = $replacingMain
                ? $this->storeMain($request, $disk)
                : $this->storeGallerySections($request, $data['sections'] ?? [], $disk);
        }

        $image->update($update);

        return response()->json(['data' => $this->toItem($image)]);
    }

    /**
     * DELETE /api/admin/products/{id}/images/{imageId}
     * Xoá luôn file vật lý trên disk (khác Product::image_main dùng MediaLibrary tự dọn khi
     * clearMediaCollection() — RoomImage tự quản lý file thủ công nên phải tự xoá ở đây).
     */
    public function destroy(Request $request, string $id, string $imageId): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $image = RoomImage::where('id', $imageId)->where('room_id', $product->id)->first();

        if (! $image) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $this->deleteStoredFiles($image);
        $image->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function rules(bool $isUpdate = false, ?string $currentType = null): array
    {
        $type = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'type'                     => "{$type}|in:main,gallery",
            'disk'                     => ['nullable', Rule::in(self::ALLOWED_DISKS)],
            'alt'                      => 'nullable|string|max:255',
            'title'                    => 'nullable|string|max:255',
            'sort_order'               => 'nullable|integer|min:0',

            'image'                    => ($isUpdate ? 'sometimes|' : 'required_if:type,main|') . self::IMAGE_RULE,

            'sections'                 => $isUpdate ? 'sometimes|array|min:1' : 'required_if:type,gallery|array|min:1',
            'sections.*.title'         => 'nullable|string|max:100',
            'sections.*.description'   => 'nullable|string',
            'sections.*.images'        => 'required_with:sections|array|min:1',
            'sections.*.images.*'      => self::IMAGE_RULE,
        ];
    }

    private function storeMain(Request $request, string $disk): array
    {
        /** @var UploadedFile $file */
        $file = $request->file('image');

        return [$file->store('room-images', $disk)];
    }

    private function storeGallerySections(Request $request, array $sections, string $disk): array
    {
        $result = [];

        foreach ($sections as $index => $section) {
            $files = $request->file("sections.{$index}.images", []);

            $paths = collect($files)->map(fn (UploadedFile $file) => $file->store('room-images', $disk))->values()->all();

            $result[] = [
                'title'       => $section['title'] ?? null,
                'description' => $section['description'] ?? null,
                'images'      => $paths,
            ];
        }

        return $result;
    }

    private function deleteStoredFiles(RoomImage $image): void
    {
        $storage = Storage::disk($image->disk);
        $data    = $image->path;

        if (! is_array($data)) {
            return;
        }

        if ($image->type === 'gallery') {
            foreach ($data as $section) {
                foreach ($section['images'] ?? [] as $path) {
                    $storage->delete($path);
                }
            }

            return;
        }

        foreach ($data as $path) {
            $storage->delete($path);
        }
    }

    private function visibleProduct(User $user, string $id): ?Product
    {
        $query = Product::query();

        if (! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }

        return $query->find($id);
    }

    private function toItem(RoomImage $image): array
    {
        return [
            'id'         => $image->id,
            'room_id'    => $image->room_id,
            'type'       => $image->type,
            'disk'       => $image->disk,
            'alt'        => $image->alt,
            'title'      => $image->title,
            'sort_order' => $image->sort_order,
            'url'        => $image->type === 'main' ? ($image->url ?: null) : null,
            'sections'   => $image->type === 'gallery' ? $image->sections : null,
        ];
    }
}
