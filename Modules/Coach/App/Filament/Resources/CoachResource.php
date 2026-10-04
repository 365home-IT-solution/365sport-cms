<?php

namespace Modules\Coach\App\Filament\Resources;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Category\Entities\Category;
use Modules\Coach\App\Filament\Resources\CoachResource\Pages;
use Modules\Coach\App\Models\Coach;

class CoachResource extends Resource
{
    protected static ?string $model = Coach::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Quản lý';
    protected static ?string $navigationLabel = 'Huấn luyện viên';
    protected static ?int $navigationSort = 12;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $branchIds = auth()->user()?->effectiveBranchIds() ?? [];

        return $branchIds ? $query->whereIn('coaches.category_id', $branchIds) : $query;
    }

    public static function getModelLabel(): string { return 'Huấn luyện viên'; }
    public static function getPluralModelLabel(): string { return 'Huấn luyện viên'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('category_id')
                ->label('Chi nhánh')
                ->options(fn () => Category::query()->where('category_type', 'product')->where('status', true)
                    ->when(! auth()->user()?->isSuperAdmin(), fn ($q) => $q->where('partner_id', auth()->user()?->partner_id))
                    ->orderBy('name')->pluck('name', 'id'))
                ->searchable()->required(),
            TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
            TextInput::make('hourly_rate')->label('Giá thuê mỗi giờ')->numeric()->minValue(0)->prefix('₫')->required(),
            TextInput::make('phone')->label('Số điện thoại')->tel()->maxLength(30),
            TextInput::make('email')->label('Email')->email(),
            FileUpload::make('photo')->label('Ảnh đại diện')->image()->disk('public')->directory('coaches'),
            TextInput::make('specialties')->label('Chuyên môn')->maxLength(255),
            TextInput::make('years_experience')->label('Số năm kinh nghiệm')->numeric()->minValue(0)->maxValue(80)->default(0),
            Textarea::make('certifications')->label('Chứng chỉ')->rows(3),
            Textarea::make('bio')->label('Giới thiệu')->rows(4),
            Toggle::make('is_active')->label('Đang nhận lịch')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('photo')->label('Ảnh')->disk('public')->circular(),
            TextColumn::make('name')->label('Họ tên')->searchable()->sortable(),
            TextColumn::make('branch.name')->label('Chi nhánh')->searchable()->sortable(),
            TextColumn::make('specialties')->label('Chuyên môn')->limit(40),
            TextColumn::make('hourly_rate')->label('Giá/giờ')->money('VND')->sortable(),
            IconColumn::make('is_active')->label('Hoạt động')->boolean(),
        ])->filters([
            SelectFilter::make('category_id')->label('Chi nhánh')->relationship('branch', 'name'),
        ])->actions([
            \Filament\Tables\Actions\EditAction::make(),
            \Filament\Tables\Actions\DeleteAction::make()->before(function (Coach $record) {
                if ($record->bookings()->exists()) throw new \RuntimeException('HLV đã phát sinh booking; chỉ được ngừng hoạt động.');
            }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoaches::route('/'),
            'create' => Pages\CreateCoach::route('/create'),
            'edit' => Pages\EditCoach::route('/{record}/edit'),
        ];
    }
}
