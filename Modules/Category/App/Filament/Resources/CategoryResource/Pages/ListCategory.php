<?php

declare(strict_types=1);

namespace Modules\Category\App\Filament\Resources\CategoryResource\Pages;

use App\Models\ProvinceBranch;
use Modules\Category\App\Filament\Resources\CategoryResource;
use Modules\Category\Entities\Category;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;

class ListCategory extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalWidth(MaxWidth::Full)
                // Tài khoản đối tác/nhân viên tạo chi nhánh: partner_id LUÔN lấy theo tài khoản
                // đang đăng nhập (ghi đè giá trị từ form nếu có) — field "Đối tác sở hữu" trên
                // form chỉ hiện cho super_admin nên user thường không tự chọn được.
                ->mutateFormDataUsing(function (array $data): array {
                    $user = auth()->user();

                    if ($user && ! $user->isSuperAdmin()) {
                        $data['partner_id'] = $user->partner_id;
                    }

                    return $data;
                })
                // Tự lưu Tỉnh/Thành + Phường/Xã (chọn ở form CategoryForm::locationSection()) vào
                // ProvinceBranch — 2 field đó KHÔNG phải cột thật trên bảng categories nên
                // Category::create() mặc định bỏ qua, phải tách riêng ra lưu thủ công. Dùng
                // ->using() để tự kiểm soát toàn bộ bước tạo record thay vì để Filament tự
                // $record->fill($data) (province_id/ward_code trong $data sẽ bị Eloquent lặng lẽ
                // bỏ qua vì không nằm trong $fillable, không lỗi nhưng cũng không lưu được).
                ->using(function (array $data): Category {
                    // Giới hạn số chi nhánh theo gói dịch vụ đối tác đang dùng (Partner::
                    // branchLimit(), null = không giới hạn) — CHỈ áp dụng cho chi nhánh GỐC
                    // (category_type=product, parent_id null); "khu vực con" bên trong 1 chi
                    // nhánh không tính vào giới hạn này.
                    $isRootBranch = ($data['category_type'] ?? null) === 'product' && empty($data['parent_id']);
                    if ($isRootBranch) {
                        $user = auth()->user();
                        $partner = $user && ! $user->isSuperAdmin() ? $user->partner : null;
                        $limit = $partner?->branchLimit();

                        if ($limit !== null) {
                            $currentCount = Category::where('partner_id', $partner->id)
                                ->where('category_type', 'product')
                                ->whereNull('parent_id')
                                ->count();

                            if ($currentCount >= $limit) {
                                Notification::make()
                                    ->title('Đã đạt giới hạn số chi nhánh')
                                    ->body("Gói dịch vụ hiện tại của bạn chỉ cho phép tối đa {$limit} chi nhánh. Vui lòng nâng cấp gói để tạo thêm chi nhánh mới.")
                                    ->danger()
                                    ->send();

                                throw new Halt();
                            }
                        }
                    }

                    $provinceId = $data['province_id'] ?? null;
                    $wardCode   = $data['ward_code'] ?? null;
                    unset($data['province_id'], $data['ward_code']);

                    $record = Category::create($data);

                    if (($data['category_type'] ?? null) === 'product') {
                        ProvinceBranch::updateOrCreate(
                            ['categorie_id' => $record->id],
                            ['province_id' => $provinceId, 'ward_code' => $wardCode, 'status' => true]
                        );
                    }

                    return $record;
                }),
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = static::getResource()::getEloquentQuery();

        return [
            'product' => Tab::make('Sân')
                ->icon('heroicon-o-home')
                ->badge($baseQuery->clone()->where('category_type', 'product')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category_type', 'product')),

            'post' => Tab::make('Bài viết')
                ->icon('heroicon-o-document-text')
                ->badge($baseQuery->clone()->where('category_type', 'post')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category_type', 'post')),
        ];
    }
}
