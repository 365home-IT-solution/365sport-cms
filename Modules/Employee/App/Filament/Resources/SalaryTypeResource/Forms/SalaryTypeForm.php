<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTypeResource\Forms;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Str;

class SalaryTypeForm
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            self::partnerInput(),
            TextInput::make('name')
                ->label('Tên loại lương')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, callable $set) => $set('slug', Str::slug($get('name')))),
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255),
            Select::make('calc_type')
                ->label('Cách tính lương')
                ->options([
                    'fixed'      => 'Lương cố định',
                    'piece_rate' => 'Lương theo lượt (vd Lễ tân: theo lượt dọn sân/tư vấn)',
                ])
                ->default('fixed')
                ->required()
                ->live(),
            TextInput::make('room_cleaning_rate')
                ->label('Đơn giá / lượt dọn sân (đ)')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->visible(fn (Get $get) => $get('calc_type') === 'piece_rate'),
            TextInput::make('consultation_rate')
                ->label('Đơn giá / lượt tư vấn (đ)')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->visible(fn (Get $get) => $get('calc_type') === 'piece_rate'),
            Textarea::make('description')
                ->label('Mô tả')
                ->maxLength(1000)
                ->columnSpanFull(),
            Toggle::make('status')
                ->label('Đang hoạt động')
                ->default(true),
        ]);
    }

    // Chỉ super_admin thấy field này — tài khoản đối tác tạo thì partner_id tự động lấy theo tài
    // khoản đang đăng nhập (BelongsToPartner::creating()), không cần tự chọn.
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
