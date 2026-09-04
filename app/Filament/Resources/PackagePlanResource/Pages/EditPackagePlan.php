<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackagePlanResource\Pages;

use App\Filament\Resources\PackagePlanResource;
use App\Filament\Resources\PackagePlanResource\Concerns\EnforcesPackagePlanScope;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPackagePlan extends EditRecord
{
    use EnforcesPackagePlanScope;

    protected static string $resource = PackagePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Xoá'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->enforceScopeExclusivity($data);
    }
}
