<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PackagePlanResource\Pages;
use App\Models\CustomerPackage;
use App\Models\PackagePlan;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Product\App\Models\Product;

// Gói nhiều buổi / thẻ trả trước — theo yêu cầu rà soát nghiệp vụ đặt sân (khách mua trước N buổi
// cho 1 sân cụ thể, giá ưu đãi hơn mua lẻ). Đây là nơi ADMIN cấu hình các GÓI (PackagePlan) bán ra
// — khách đã MUA (CustomerPackage) xem/quản lý riêng ở tab "Đã bán" trong action "Bán gói" bên
// dưới, không tách Resource riêng (số lượng bản ghi CustomerPackage không cần trang danh sách đầy
// đủ như Order).
//
// LƯU Ý: canViewAny() dùng auth()->check() đơn giản (KHÔNG qua permission slug riêng như các
// resource khác, vd 'view_any_coupon') — resource mới, chưa đăng ký permission qua Shield, mọi
// admin đã đăng nhập đều thấy được. Nếu cần phân quyền chi tiết hơn (theo đối tác/role), chạy
// `php artisan shield:generate` để Shield tự phát hiện resource này rồi đổi lại thành
// auth()->user()?->can('view_any_package::plan') giống các resource khác.
class PackagePlanResource extends Resource
{
    protected static ?string $model = PackagePlan::class;

    protected static ?string $navigationIcon   = 'heroicon-o-ticket';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Gói nhiều buổi';
    protected static ?string $modelLabel       = 'Gói nhiều buổi';
    protected static ?string $pluralModelLabel = 'Gói nhiều buổi';

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Thông tin gói')->schema([
                Select::make('product_id')
                    ->label('Sân áp dụng')
                    ->placeholder('Tìm theo tên sân')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Product::where('name', 'like', "%{$search}%")
                        ->limit(20)->pluck('name', 'id'))
                    ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                    ->helperText('Gói CHỈ dùng được cho đúng sân này khi đặt sân (không dùng chéo sân khác).'),

                TextInput::make('name')
                    ->label('Tên gói')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('VD: Gói 10 buổi'),

                Grid::make(3)->schema([
                    TextInput::make('sessions_count')
                        ->label('Số buổi')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('buổi')
                        ->helperText('1 buổi = 1 khung giờ, bất kể dài/ngắn khác nhau.'),

                    TextInput::make('price')
                        ->label('Giá trọn gói')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('đ'),

                    TextInput::make('validity_days')
                        ->label('Hết hạn sau')
                        ->numeric()
                        ->minValue(1)
                        ->suffix('ngày')
                        ->placeholder('Không hết hạn')
                        ->helperText('Tính từ lúc mua. Để trống = không hết hạn.'),
                ]),

                Toggle::make('is_active')
                    ->label('Đang bán (hiện cho khách mua trên trang sân)')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Sân')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Tên gói')
                    ->searchable(),

                TextColumn::make('sessions_count')
                    ->label('Số buổi')
                    ->suffix(' buổi')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Giá gói')
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('validity_days')
                    ->label('Hết hạn')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} ngày" : 'Không hết hạn'),

                TextColumn::make('customer_packages_count')
                    ->label('Đã bán')
                    ->counts('customerPackages')
                    ->suffix(' gói')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Đang bán')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                // Bán gói tay cho khách walk-in — thu tiền NGOÀI hệ thống (tiền mặt/chuyển khoản
                // trực tiếp), chỉ ghi nhận lại. Khác luồng khách tự mua online qua PayOS (xem
                // ProductDetail::buyPackage() + PaymentController order_type=package_purchase).
                Action::make('sell')
                    ->label('Bán gói')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->form([
                        // Dùng SỐ ĐIỆN THOẠI làm khoá chính (KHÔNG bắt buộc chọn tài khoản
                        // Customer) — dự án này gần như không có khách đăng ký tài khoản, mọi đơn
                        // đều định danh qua buyer_phone (xem ghi chú tại
                        // CustomerPackage::findValidForPhoneAndProduct() + migration
                        // make_customer_id_nullable...). Nếu SAU NÀY khách đăng nhập bằng đúng SĐT
                        // này, ProductDetail vẫn tra được gói bình thường vì tra theo phone chứ
                        // không theo customer_id.
                        TextInput::make('buyer_name')
                            ->label('Tên khách hàng')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('buyer_phone')
                            ->label('Số điện thoại')
                            ->required()
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Dùng số này để khách xem/dùng gói khi đặt sân (giống cách tra cứu đơn hàng hiện tại).'),
                    ])
                    ->action(function (PackagePlan $record, array $data): void {
                        CustomerPackage::create([
                            'package_plan_id'    => $record->id,
                            'buyer_phone'        => $data['buyer_phone'],
                            'buyer_name'         => $data['buyer_name'],
                            'product_id'         => $record->product_id,
                            'name'               => $record->name,
                            'sessions_total'     => $record->sessions_count,
                            'sessions_remaining' => $record->sessions_count,
                            'price_paid'         => $record->price,
                            'purchased_at'       => now(),
                            'expires_at'         => $record->validity_days ? now()->addDays($record->validity_days) : null,
                            'sale_channel'       => 'admin',
                            'created_by'         => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Đã bán gói — nhớ thu tiền khách (ngoài hệ thống)')
                            ->success()
                            ->send();
                    }),

                EditAction::make()->label('Sửa'),
                DeleteAction::make()->label('Xoá'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPackagePlans::route('/'),
            'create' => Pages\CreatePackagePlan::route('/create'),
            'edit'   => Pages\EditPackagePlan::route('/{record}/edit'),
        ];
    }
}
