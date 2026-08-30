<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\DepartmentResource\Forms;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Str;

class DepartmentForm
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Tên phòng ban')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, callable $set) => $set('slug', Str::slug($get('name')))),
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->label('Mô tả')
                ->maxLength(1000)
                ->columnSpanFull(),
            Toggle::make('status')
                ->label('Đang hoạt động')
                ->default(true),
        ]);
    }
}
