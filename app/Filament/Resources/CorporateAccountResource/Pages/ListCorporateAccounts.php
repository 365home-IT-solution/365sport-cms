<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorporateAccountResource\Pages;

use App\Filament\Resources\CorporateAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateAccounts extends ListRecords
{
    protected static string $resource = CorporateAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Thêm công ty'),
        ];
    }
}
