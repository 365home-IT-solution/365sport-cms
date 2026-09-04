<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorporateAccountResource\Pages;

use App\Filament\Resources\CorporateAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCorporateAccount extends EditRecord
{
    protected static string $resource = CorporateAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Xoá'),
        ];
    }
}
