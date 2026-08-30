<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerLeadResource\Pages;

use App\Filament\Resources\PartnerLeadResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnerLead extends EditRecord
{
    protected static string $resource = PartnerLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['viewed_by'] = auth()->id();
        $data['viewed_at'] = Carbon::now();

        return $data;
    }
}
