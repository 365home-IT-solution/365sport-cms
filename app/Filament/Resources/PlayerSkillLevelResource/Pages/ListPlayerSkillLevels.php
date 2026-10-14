<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlayerSkillLevelResource\Pages;

use App\Filament\Resources\PlayerSkillLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlayerSkillLevels extends ListRecords
{
    protected static string $resource = PlayerSkillLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Đánh giá trình độ cho khách')];
    }
}
