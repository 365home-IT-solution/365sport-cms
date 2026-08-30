<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\AllowanceTypeResource\Forms;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Str;

class AllowanceTypeForm
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            self::partnerInput(),
            TextInput::make('name')
                ->label('Tên loại phụ cấp')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, callable $set) => $set('slug', Str::slug($get('name')))),
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255),
            Select::make('calc_type')
                ->label('Cách tính')
                ->options(['fixed' => 'Số tiền cố định', 'percentage' => 'Theo % lương'])
                ->default('fixed')
                ->required(),
            TextInput::make('default_amount')
                ->label('Số tiền mặc định')
                ->numeric()
                ->minValue(0)
                ->suffix('VNĐ'),
            Textarea::make('description')
                ->label('Mô tả')
                ->maxLength(1000)
                ->columnSpanFull(),
            Toggle::make('status')
                ->label('Đang hoạt động')
                ->default(true),
        ]);
    }

    private static function partnerInput(): Select
    {
        return Select::make('partner_id')
            ->label('Đối tác sở hữu')
            ->options(fn () => \App\Models\Partner::withTrashed()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn ($partner) => [
                    $partner->id => $partner->name . ($partner->trashed() ? ' (đã xoá)' : ''),
                ])
                ->all())
            ->getOptionLabelUsing(fn ($value) => $value
                ? \App\Models\Partner::withTrashed()->find($value)?->name
                : null)
            ->dehydrated()
            ->searchable()
            ->preload()
            ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)
            ->required(fn () => auth()->user()?->isSuperAdmin() ?? false);
    }
}
