<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Forms;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Str;
use Modules\Employee\Entities\SalaryType;

class SalaryTemplateForm
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            self::partnerInput(),
            TextInput::make('name')
                ->label('Tên mẫu lương')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, callable $set) => $set('slug', Str::slug($get('name')))),
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255),
            Select::make('salary_type_id')
                ->label('Loại lương')
                ->options(fn (Get $get) => SalaryType::where('partner_id', self::resolvePartnerId($get))->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('base_amount')
                ->label('Lương cơ bản (đ)')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Textarea::make('description')
                ->label('Mô tả')
                ->maxLength(1000)
                ->columnSpanFull(),
            Toggle::make('status')
                ->label('Đang hoạt động')
                ->default(true),
        ]);
    }

    private static function resolvePartnerId(Get $get): ?string
    {
        return $get('partner_id') ?? auth()->user()?->partner_id;
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
            ->live()
            ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)
            ->required(fn () => auth()->user()?->isSuperAdmin() ?? false);
    }
}
