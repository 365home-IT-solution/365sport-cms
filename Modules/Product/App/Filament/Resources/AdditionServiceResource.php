<?php

declare(strict_types=1);

namespace Modules\Product\App\Filament\Resources;

use App\Filament\Support\PartnerTableHelpers;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\BladeThemeV1\App\Models\AdditionService;
use Modules\Product\App\Filament\Resources\AdditionServiceResource\Pages;

// Dịch vụ bổ sung được lọc theo partner_id qua BelongsToPartner (global scope trên
// AdditionService model) — mỗi đối tác tự tạo dịch vụ của riêng mình, không dùng chung nữa. Vẫn
// CỘNG THÊM lọc theo "Chuyển đổi chi nhánh" ở header vì 1 dịch vụ có thể gắn cho sân ở NHIỀU chi
// nhánh khác nhau của cùng đối tác (many-to-many qua products()).
class AdditionServiceResource extends Resource
{
    protected static ?string $model = AdditionService::class;

    public static function canViewAny(): bool
    {
        return parent::canViewAny() && (auth()->user()?->partnerHasFeature('addition_services') ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if (! $user) {
            return $query;
        }

        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            return $user->isSuperAdmin() ? $query : $query->whereRaw('1 = 0');
        }

        $categoryIds = $user->visibleProductCategoryIds($branchIds);

        if (empty($categoryIds)) {
            return $query->whereRaw('1 = 0');
        }

        // Dịch vụ chưa gán sân nào vẫn hiển thị bình thường — chỉ loại dịch vụ ĐÃ gắn sân nhưng
        // KHÔNG có sân nào thuộc chi nhánh đang chọn. QUAN TRỌNG: doesntHave('products') PHẢI bỏ
        // qua global scope của Product (BelongsToActiveBranchCategories) qua withoutGlobalScopes()
        // trong callback — nếu không, "not exists" cũng tự động bị lọc theo cùng $categoryIds,
        // khiến 1 dịch vụ ĐÃ gắn sân nhưng sân đó KHÔNG thuộc chi nhánh đang chọn bị hiểu nhầm
        // thành "chưa gắn sân nào" rồi vẫn lọt qua nhánh doesntHave — bug thật đã phát hiện khi
        // test qua tinker (baseline 6, chọn switcher vẫn ra 6, không hề thu hẹp).
        return $query->where(function (Builder $q) use ($categoryIds) {
            $q->doesntHave('products', callback: fn (Builder $q3) => $q3->withoutGlobalScopes())
                ->orWhereHas('products.categories', fn (Builder $q2) => $q2->whereIn('categories.id', $categoryIds));
        });
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-puzzle-piece';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getNavigationLabel(): string
    {
        return 'Dịch vụ Bổ sung';
    }

    public static function getModelLabel(): string
    {
        return 'Dịch vụ';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Dịch vụ Bổ sung';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('image')
                ->label('Ảnh dịch vụ')
                ->image()
                ->directory('addition-services')
                ->imageEditor()
                ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1'])
                ->imageCropAspectRatio('1:1')
                ->imageResizeMode('cover')
                ->imageResizeTargetWidth('800')
                ->imageResizeTargetHeight('800')
                ->columnSpanFull(),

            TextInput::make('name')
                ->label('Tên dịch vụ')
                ->required()
                ->maxLength(255),

            TextInput::make('price')
                ->label('Đơn giá')
                ->numeric()
                ->prefix('₫')
                ->required()
                ->minValue(0),

            Toggle::make('is_active')
                ->label('Kích hoạt')
                ->default(true)
                ->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width(60),

                ImageColumn::make('image')
                    ->label('Ảnh')
                    ->square()
                    ->size(60),

                TextColumn::make('name')
                    ->label('Tên dịch vụ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Đơn giá')
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('products_count')
                    ->label('Số sân')
                    ->counts('products')
                    ->badge()
                    ->color('info'),

                ToggleColumn::make('is_active')
                    ->label('Kích hoạt'),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                PartnerTableHelpers::column(),
            ])
            ->filters([
                PartnerTableHelpers::filter(),
            ])
            ->defaultSort('id')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdditionServices::route('/'),
            'create' => Pages\CreateAdditionService::route('/create'),
            'edit'   => Pages\EditAdditionService::route('/{record}/edit'),
        ];
    }
}
