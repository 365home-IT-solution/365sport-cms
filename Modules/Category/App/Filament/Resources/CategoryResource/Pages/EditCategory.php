<?php

declare(strict_types=1);

namespace Modules\Category\App\Filament\Resources\CategoryResource\Pages;

use App\Models\ProvinceBranch;
use Modules\Category\App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    // Tỉnh/Thành + Phường/Xã (CategoryForm::locationSection()) không phải cột thật trên bảng
    // categories (nằm ở ProvinceBranch riêng) — tách ra ở đây để lưu tay trong afterSave(),
    // tránh $data['province_id']/['ward_code'] lọt vào $this->record->update() (Eloquent lặng lẽ
    // bỏ qua vì không có trong $fillable, không lỗi nhưng cũng mất giá trị nếu không tách riêng).
    // KHÔNG khai kiểu int/string cụ thể — giá trị 2 field Select này tới từ Livewire dạng gì (int
    // hay chuỗi số) không đảm bảo cố định, khai kiểu chặt kèm strict_types=1 ở đầu file (dòng 3)
    // dễ vỡ TypeError ngay khi lưu nếu Livewire gửi lên sai kiểu mong đợi.
    private int|string|null $pendingProvinceId = null;
    private int|string|null $pendingWardCode = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingProvinceId = $data['province_id'] ?? null;
        $this->pendingWardCode   = $data['ward_code'] ?? null;
        unset($data['province_id'], $data['ward_code']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->category_type !== 'product') {
            return;
        }

        ProvinceBranch::updateOrCreate(
            ['categorie_id' => $this->record->id],
            ['province_id' => $this->pendingProvinceId, 'ward_code' => $this->pendingWardCode, 'status' => true]
        );
    }
}