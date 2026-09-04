<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorporateAccountResource\Pages;

use App\Filament\Resources\CorporateAccountResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateAccount extends ViewRecord
{
    protected static string $resource = CorporateAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Sửa'),
        ];
    }
}
