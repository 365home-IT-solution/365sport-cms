<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoyaltyTransactionResource\Pages;

use App\Filament\Resources\LoyaltyTransactionResource;
use App\Models\Customer;
use App\Services\LoyaltyService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyTransactions extends ListRecords
{
    protected static string $resource = LoyaltyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjust')->label('Điều chỉnh điểm')->icon('heroicon-o-adjustments-horizontal')
                ->visible(fn () => (bool) auth()->user()?->can('create_loyalty::transaction'))
                ->form([
                    Select::make('customer_id')->label('Khách hàng')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Customer::query()->where(fn ($q) => $q->where('fullname', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))->limit(20)->get()->mapWithKeys(fn ($c) => [$c->id => "{$c->fullname} — {$c->phone}"])->all())
                        ->getOptionLabelUsing(fn ($value) => ($c = Customer::find($value)) ? "{$c->fullname} — {$c->phone}" : $value),
                    TextInput::make('points')->label('Số điểm (âm = trừ)')->numeric()->integer()->required()->rules(['not_in:0']),
                    TextInput::make('note')->label('Lý do')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $result = app(LoyaltyService::class)->adjust(Customer::findOrFail($data['customer_id']), (int) $data['points'], $data['note'], auth()->user());
                    $result['ok']
                        ? Notification::make()->success()->title('Đã điều chỉnh điểm')->send()
                        : Notification::make()->danger()->title('Không thể điều chỉnh')->body('Số điểm trừ vượt quá số dư của khách.')->send();
                }),
        ];
    }
}
