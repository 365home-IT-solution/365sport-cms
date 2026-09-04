<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerPackageResource\Pages;
use App\Filament\Resources\CustomerPackageResource\RelationManagers\RedemptionsRelationManager;
use App\Models\CustomerPackage;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Quản lý KHÁCH ĐÃ MUA gói nhiều buổi (App\Models\CustomerPackage) — khác PackagePlanResource
// (nơi admin CẤU HÌNH các gói bán ra). Dùng CHUNG App\Models\Concerns\BelongsToPartner (đã gắn ở
// CustomerPackage model) để tự lọc: super_admin xem TOÀN BỘ gói của mọi đối tác, đối tác khác chỉ
// xem đúng khách đã mua gói CỦA MÌNH — không cần viết thêm getEloquentQuery() ở đây, global scope
// 'partner' tự áp dụng cho mọi query trong panel admin (xem BelongsToPartner::bootBelongsToPartner()).
// KHÔNG có action "Tạo mới" — gói chỉ phát sinh qua khách tự mua (ProductDetail::buyPackage()/
// Book::buyPackage()) hoặc admin "Bán gói" tại PackagePlanResource, tránh trùng logic tạo bản ghi ở
// 2 nơi. Resource này chỉ để XEM + SỬA (điều chỉnh buổi còn lại/hạn dùng khi cần, vd bù buổi cho
// khách do lỗi hệ thống) + xem lịch sử dùng (RedemptionsRelationManager).
class CustomerPackageResource extends Resource
{
    protected static ?string $model = CustomerPackage::class;

    protected static ?string $navigationIcon   = 'heroicon-o-ticket';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Khách đã mua gói';
    protected static ?string $modelLabel       = 'Gói đã bán';
    protected static ?string $pluralModelLabel = 'Khách đã mua gói';
    protected static ?int    $navigationSort   = 7;

    // Cùng cách canViewAny() đơn giản như PackagePlanResource (chưa đăng ký permission qua Shield).
    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    // CustomerPackage đã lọc theo partner_id qua BelongsToPartner (global scope 'partner') — CỘNG
    // THÊM (AND) lọc chi nhánh theo "Chuyển đổi chi nhánh" ở header. Snapshot ĐÚNG 1 TRONG 2 phạm
    // vi (xem comment đầu class): category_id (gói cả chi nhánh) HOẶC product_id (gói riêng 1 sân)
    // — viết OR giữa 2 điều kiện, cùng cách PackagePlan::isBranchWide() phân biệt.
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

        return $query->where(function (Builder $q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds)
                ->orWhereHas('product.categories', fn (Builder $q2) => $q2->whereIn('categories.id', $categoryIds));
        });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Thông tin gói đã bán')
                ->schema([
                    TextInput::make('name')
                        ->label('Tên gói')
                        ->disabled(),

                    TextInput::make('buyer_name')
                        ->label('Tên khách hàng')
                        ->required()
                        ->maxLength(150),

                    TextInput::make('buyer_phone')
                        ->label('Số điện thoại')
                        ->required()
                        ->tel()
                        ->maxLength(20),

                    TextInput::make('sessions_total')
                        ->label('Tổng số buổi')
                        ->numeric()
                        ->disabled(),

                    // CHO SỬA — vd cộng bù buổi cho khách khi hệ thống lỗi trừ nhầm, hoặc trừ tay
                    // khi khách dùng ngoài hệ thống (walk-in không qua đặt sân online).
                    TextInput::make('sessions_remaining')
                        ->label('Số buổi còn lại')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->helperText('Sửa tay khi cần bù/trừ buổi thủ công — mọi thay đổi qua đặt sân bình thường KHÔNG cần sửa ở đây.'),

                    DateTimePicker::make('expires_at')
                        ->label('Hạn dùng')
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->placeholder('Không hết hạn'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('buyer_name')
                    ->label('Khách hàng')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('buyer_phone')
                    ->label('Số điện thoại')
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Tên gói')
                    ->searchable(),

                TextColumn::make('scope_label')
                    ->label('Phạm vi')
                    ->getStateUsing(fn (CustomerPackage $record) => $record->category_id
                        ? 'Cả chi nhánh: ' . ($record->category?->name ?? '—')
                        : ($record->product?->name ?? '—')),

                TextColumn::make('partner.name')
                    ->label('Đối tác')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sessions_remaining')
                    ->label('Còn lại')
                    ->suffix(fn (CustomerPackage $record) => '/' . $record->sessions_total . ' buổi')
                    ->sortable()
                    ->badge()
                    ->color(fn (CustomerPackage $record) => $record->isValid() ? 'success' : 'gray'),

                TextColumn::make('sale_channel')
                    ->label('Kênh bán')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'online' => 'Khách tự mua',
                        'admin'  => 'Admin bán tay',
                        default  => $state ?? '—',
                    })
                    ->color(fn (?string $state) => $state === 'online' ? 'info' : 'warning'),

                TextColumn::make('purchased_at')
                    ->label('Ngày mua')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Hạn dùng')
                    ->dateTime('d/m/Y')
                    ->placeholder('Không hết hạn')
                    ->sortable(),
            ])
            ->defaultSort('purchased_at', 'desc')
            ->filters([
                SelectFilter::make('sale_channel')
                    ->label('Kênh bán')
                    ->options([
                        'online' => 'Khách tự mua',
                        'admin'  => 'Admin bán tay',
                    ]),
                TernaryFilter::make('is_valid')
                    ->label('Còn dùng được')
                    ->queries(
                        true: fn ($query) => $query->where('sessions_remaining', '>', 0)
                            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
                        false: fn ($query) => $query->where('sessions_remaining', '<=', 0)
                            ->orWhere(fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', now())),
                    ),
            ])
            ->actions([
                ViewAction::make()->label('Chi tiết'),
                EditAction::make()->label('Sửa'),
            ])
            ->bulkActions([]);
    }

    public static function getRelationManagers(): array
    {
        return [
            RedemptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerPackages::route('/'),
            'edit'  => Pages\EditCustomerPackage::route('/{record}/edit'),
            'view'  => Pages\ViewCustomerPackage::route('/{record}'),
        ];
    }
}
