<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerPlanResource\Pages;
use App\Models\PartnerPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// Quản lý các gói dịch vụ bán cho đối tác (Cơ bản/Chuyên nghiệp/Doanh nghiệp...) — admin tự thêm/
// sửa gói, giá, số chi nhánh tối đa và tính năng mở khoá ở đây mà KHÔNG cần đổi code. Gán gói cho
// từng đối tác cụ thể thực hiện ở PartnerResource (tab "Hợp đồng") — resource này chỉ định nghĩa
// CÁC GÓI CÓ SẴN, không gán trực tiếp cho đối tác nào. CHỈ super_admin quản lý (đây là nghiệp vụ
// giá bán, cùng mức nhạy cảm với PartnerResource).
class PartnerPlanResource extends Resource
{
    protected static ?string $model = PartnerPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Gói đối tác';

    protected static ?string $modelLabel = 'Gói đối tác';

    protected static ?string $pluralModelLabel = 'Gói đối tác';

    protected static ?string $slug = 'partner-plans';

    protected static ?int $navigationSort = 1;

    // Danh sách MODULE CHÍNH mà 1 tài khoản đối tác có thể được cấp quyền (khớp đúng
    // PartnerRolePermissionsSeeder::PARTNER_RESOURCES — toàn bộ resource Shield thật sự cấp cho
    // role 'partner'/'employee', trừ 'room::image'/'room::housekeeping' vì 2 resource đó hard-code
    // canViewAny() chỉ cho super_admin, không dựa vào permission nên gán/gỡ ở đây vô tác dụng).
    // Admin tick/bỏ tick ở trang "Tính năng theo gói" (PartnerFeatureMatrix). Enforcement: MỖI
    // resource tương ứng tự thêm điều kiện `partnerHasFeature($key)` vào canViewAny() của nó (đã
    // thử cách tập trung 1 chỗ qua Gate::before() nhưng KHÔNG đáng tin cậy trong môi trường thực
    // tế — bỏ hẳn, xem lịch sử sửa lỗi). Thêm khoá mới vào đây CHƯA đủ để tính năng bị giới hạn —
    // phải tự thêm check vào canViewAny() của resource tương ứng nữa.
    public const FEATURE_OPTIONS = [
        'branches'           => 'Chi nhánh',
        'products'           => 'Sân',
        'room_types'         => 'Danh mục sân',
        'room_amenities'     => 'Tiện ích sân',
        'room_services'      => 'Dịch vụ sân',
        'room_specials'      => 'Điểm đặc biệt của sân',
        'coupons'            => 'Mã giảm giá',
        'promotions'         => 'Quản lý khuyến mãi',
        'orders'             => 'Đơn hàng',
        'bookings'           => 'Đặt lịch / Hệ thống giá',
        'access_codes'       => 'Mã truy cập cửa',
        'ttlock_accounts'    => 'Tài khoản khoá TTLock',
        'lock_passwords'     => 'Mật khẩu khoá thủ công',
        'addition_services'  => 'Dịch vụ cộng thêm',
        'tags'               => 'Thẻ (Tag)',
        'posts'              => 'Bài viết',
        'audit_logs'         => 'Nhật ký hoạt động',
        'staff_accounts'     => 'Tài khoản nhân viên',
        'staff_permissions'  => 'Phân quyền chi nhánh cho nhân viên',
        'staff_roles'        => 'Vai trò cho nhân viên',
        'warehouse'          => 'Quản lý kho',
        // NGOẠI LỆ: không gắn với resource Filament nào (không có trong FEATURE_RESOURCE_SLUGS bên
        // dưới) — kiểm tra trực tiếp bằng Partner::hasFeature('landing_page') ở
        // BladeThemeV1Controller::partnerLandingPage() (route theo subdomain, phía client, không
        // phải admin panel).
        'landing_page'       => 'Trang landing riêng (subdomain)',
        // NGOẠI LỆ (giống landing_page): không gắn resource Filament nào — kiểm tra trực tiếp bằng
        // Partner::hasFeature('sepay_auto_confirm') ở SePayWebhookController TRƯỚC KHI tự xác nhận
        // đơn (không phải "hiện/ẩn 1 form kết nối" — chỉ cần đối tác đã điền STK ở tab Tài chính là
        // dùng được ngay, KHÔNG có bước "liên kết" riêng nào cả, xem giải thích trong
        // PartnerForm::financialTab()). Giới hạn theo GÓI (thay vì mở cho mọi đối tác) vì tài khoản
        // SePay nền tảng chỉ hỗ trợ SỐ LƯỢNG GIAO DỊCH/THÁNG có hạn (xem trao đổi với người dùng) —
        // để mọi đối tác tự dùng sẽ vượt quá hạn mức đã mua ngoài kiểm soát.
        'sepay_auto_confirm' => 'Xác nhận chuyển khoản tự động (SePay)',
    ];

    // Khoá tính năng => (các) slug resource Shield tương ứng trong tên permission (vd
    // "view_any_promotion" khớp slug "promotion") — RoleResource::getAllowedPermissions() dùng
    // bảng tra này để lọc checkbox quyền khi đối tác tự tạo Vai trò cho nhân viên. Khớp CHÍNH XÁC
    // theo hậu tố tên quyền (str_ends_with '_' . slug), không phải chứa chuỗi con — tránh khoá
    // nhầm (vd slug "user" không được khoá nhầm luôn "user::branch::permission").
    public const FEATURE_RESOURCE_SLUGS = [
        'branches'           => ['category'],
        'products'           => ['product'],
        'room_types'         => ['room::type'],
        'room_amenities'     => ['room::amenity', 'room::amenity::assign'],
        'room_services'      => ['room::service'],
        'room_specials'      => ['room::special'],
        'coupons'            => ['coupon'],
        'promotions'         => ['promotion'],
        'orders'             => ['order'],
        'bookings'           => ['book'],
        'access_codes'       => ['access::code'],
        'ttlock_accounts'    => ['ttlock::account'],
        'lock_passwords'     => ['manual::lock::password'],
        'addition_services'  => ['addition::service'],
        'tags'               => ['tag'],
        'posts'              => ['post'],
        'audit_logs'         => ['audit::log'],
        'staff_accounts'     => ['user'],
        'staff_permissions'  => ['user::branch::permission'],
        'staff_roles'        => ['shield::role'],
        'warehouse'          => ['warehouse::category', 'warehouse::unit', 'warehouse::item', 'warehouse::stock::in', 'warehouse::stock::out', 'warehouse::stock::check'],
    ];

    private static function isSuperAdmin(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên gói')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),

                Forms\Components\TextInput::make('description')
                    ->label('Mô tả ngắn')
                    ->maxLength(150)
                    ->columnSpanFull()
                    ->helperText('Hiện ngay dưới tên gói ở trang quảng cáo /lam-doi-tac (vd "Phù hợp sân mới bắt đầu chuyển đổi số.").'),

                Forms\Components\TextInput::make('price')
                    ->label('Giá / tháng (đ)')
                    ->required()
                    ->numeric()
                    ->minValue(0),

                Forms\Components\TextInput::make('max_branches')
                    ->label('Số chi nhánh tối đa')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Để trống = không giới hạn số chi nhánh.'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Thứ tự hiển thị')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Đang bán')
                    ->default(true)
                    ->helperText('Tắt = ẩn hẳn khỏi trang quảng cáo /lam-doi-tac.'),

                Forms\Components\Toggle::make('is_highlighted')
                    ->label('Nổi bật ("Phổ biến nhất")')
                    ->helperText('Chỉ nên bật cho 1 gói duy nhất — hiện badge cam nổi bật ở trang quảng cáo.'),
            ]),

            Forms\Components\CheckboxList::make('features')
                ->label('Tính năng mở khoá (module thật trong hệ thống)')
                ->options(self::FEATURE_OPTIONS)
                ->columns(2),

            Forms\Components\TagsInput::make('extra_features')
                ->label('Tính năng quảng cáo thêm (tự gõ, không gắn module nào)')
                ->helperText('Gõ xong 1 dòng, nhấn Enter để thêm — hiện thêm ở cuối danh sách tính năng trên trang /lam-doi-tac, không khoá/mở tính năng thật nào (vd "Tư vấn 1-1", "Xây dựng website riêng theo yêu cầu").')
                ->placeholder('Nhập rồi nhấn Enter...')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Tên gói')->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Giá / tháng')
                    ->formatStateUsing(fn (int $state) => number_format($state, 0, ',', '.') . 'đ'),
                Tables\Columns\TextColumn::make('max_branches')
                    ->label('Số chi nhánh')
                    ->formatStateUsing(fn (?int $state) => $state ?? 'Không giới hạn'),
                // 'features' cast là array -> Filament TextColumn tự coi đây là DANH SÁCH NHIỀU
                // GIÁ TRỊ, gọi formatStateUsing cho TỪNG PHẦN TỬ riêng lẻ (1 feature key/lần),
                // KHÔNG phải cả mảng 1 lần -- tham số phải nhận string, không phải ?array (bug đã
                // gặp: TypeError khi vào danh sách gói).
                Tables\Columns\TextColumn::make('features')
                    ->label('Tính năng')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (self::FEATURE_OPTIONS[$state] ?? $state) : '—')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('Đang bán')->boolean(),
                Tables\Columns\TextColumn::make('partners_count')
                    ->label('Số đối tác đang dùng')
                    ->counts('partners'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPartnerPlans::route('/'),
            'create' => Pages\CreatePartnerPlan::route('/create'),
            'edit'   => Pages\EditPartnerPlan::route('/{record}/edit'),
        ];
    }
}
