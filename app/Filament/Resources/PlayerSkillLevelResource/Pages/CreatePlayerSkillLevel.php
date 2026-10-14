<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlayerSkillLevelResource\Pages;

use App\Filament\Resources\PlayerSkillLevelResource;
use App\Models\Customer;
use App\Models\PlayerSkillLevel;
use App\Services\PlayerSkillService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlayerSkillLevel extends CreateRecord
{
    protected static string $resource = PlayerSkillLevelResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Ghi qua service (xác nhận bởi nhân viên + lịch sử + đồng bộ hồ sơ khách), không tạo bản ghi trực tiếp.
        return app(PlayerSkillService::class)->set(
            Customer::findOrFail($data['customer_id']),
            (string) ($data['sport_type'] ?? 'general'),
            $data['level'],
            PlayerSkillLevel::SOURCE_STAFF,
            (string) auth()->id(),
            $data['note'] ?? null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
