<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PackagePlanResource\Pages;
use App\Models\CustomerPackage;
use App\Models\PackagePlan;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Modules\Product\App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
    protected static ?int    $navigationSort   = 6;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    // GHI CHÚ (không tự ý sửa trong lần này, ngoài phạm vi yêu cầu): PackagePlan KHÔNG có
    // BelongsToPartner dù có cột partner_id — resource này hiện lộ gói của MỌI đối tác cho bất kỳ
    // admin nào đã đăng nhập (canViewAny() chỉ auth()->check()). Lọc chi nhánh dưới đây (dựa trên
    // effectiveBranchIds()/visibleProductCategoryIds(), vốn đã tự giới hạn theo partner_id của
    // user thường qua User::rootProductCategoryIds()) tình cờ thu hẹp luôn phần lớn rò rỉ này cho
    // user KHÔNG PHẢI super_admin, nhưng KHÔNG thay thế cho việc thêm BelongsToPartner đúng nghĩa.
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

        // Đúng 1 trong 2 phạm vi (xem PackagePlan::isBranchWide()): category_id (cả chi nhánh)
        // HOẶC product_id (1 sân cụ thể, lọc qua Product::categories()). Phạm vi thứ 3 "một số sân
        // trong chi nhánh" LUÔN có category_id đi kèm (xem PackagePlan::scopeType()) nên tự động
        // khớp nhánh category_id, không cần thêm điều kiện riêng.
        return $query->where(function (Builder $q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds)
                ->orWhereHas('product.categories', fn (Builder $q2) => $q2->whereIn('categories.id', $categoryIds));
        });
    }

    // Tập category_id đang hiệu lực theo "Chuyển đổi chi nhánh" ở header (session active_branch_ids
    // qua User::effectiveBranchIds()) — DÙNG CHUNG cho cả getEloquentQuery() (lọc DANH SÁCH gói) LẪN
    // các Select trong form() (lọc SÂN/CHI NHÁNH được phép chọn khi Tạo/Sửa gói). TRƯỚC ĐÂY 2 field
    // Select 'product_id'/'category_id' trong form hoàn toàn không lọc gì — chọn switcher xong danh
    // sách gói đã lọc đúng, nhưng form Tạo/Sửa vẫn hiện tràn lan MỌI chi nhánh/sân của MỌI đối tác
    // (bug thật đã gặp).
    private static function scopedCategoryIds(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            return [];
        }

        return $user->visibleProductCategoryIds($branchIds);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Thông tin gói')->schema([
                // Radio thuần UI (dehydrated: false — KHÔNG lưu xuống DB) để chọn ĐÚNG 1 TRONG 2
                // Select bên dưới hiện ra — xem PackagePlan::isBranchWide()/migration
                // add_category_scope_to_package_tables. afterStateHydrated tự suy giá trị ban đầu
                // khi SỬA gói có sẵn (category_id có giá trị → đang là gói cả chi nhánh).
                Radio::make('scope_type')
                    ->label('Phạm vi áp dụng')
                    ->options([
                        'product'  => '1 sân cụ thể',
                        'selected' => 'Một số sân trong chi nhánh',
                        'category' => 'Toàn bộ chi nhánh (mọi sân)',
                    ])
                    ->default('product')
                    ->inline()
                    ->live()
                    // LUÔN dehydrate (KHÔNG còn ->dehydrated(false)) — 'scope_type' không map cột
                    // nào của PackagePlan nên Eloquent tự bỏ qua nếu lọt vào update()/create(), NHƯNG
                    // bắt buộc phải có mặt trong $data để 2 trang Create/Edit tự XỬ LÝ TẬP TRUNG việc
                    // ép loại trừ lẫn nhau giữa product_id/category_id/product_ids (xem
                    // enforceScopeExclusivity() ở Pages/CreatePackagePlan.php + EditPackagePlan.php).
                    // TRƯỚC ĐÂY mỗi field tự ->dehydrateStateUsing() dựa vào Get('scope_type') riêng
                    // lẻ — với 3 field loại trừ nhau (từ khi thêm phạm vi "Một số sân trong chi
                    // nhánh"), cách này KHÔNG ĐÁNG TIN CẬY: đã gặp thật product_id cũ (từ trước khi
                    // đổi qua "Một số sân") không được xoá dù category_id/product_ids MỚI đã lưu
                    // đúng, khiến scopeType() vẫn đọc nhầm thành 'product' (còn sót giá trị cũ). Tập
                    // trung về 1 nơi DUY NHẤT xử lý sau khi form đã dehydrate xong toàn bộ, không phụ
                    // thuộc thứ tự chạy giữa các field độc lập.
                    ->afterStateHydrated(function ($component, $record) {
                        $component->state(match (true) {
                            $record?->product_id !== null => 'product',
                            ! empty($record?->product_ids) => 'selected',
                            $record?->category_id !== null => 'category',
                            default => 'product',
                        });
                    }),

                Select::make('product_id')
                    ->label('Sân áp dụng')
                    ->placeholder('Tìm theo tên sân')
                    ->visible(fn (Get $get) => $get('scope_type') === 'product')
                    ->required(fn (Get $get) => $get('scope_type') === 'product')
                    // KHÔNG tự ->dehydrateStateUsing() ép null ở field nữa — việc ép loại trừ lẫn
                    // nhau giữa product_id/category_id/product_ids giờ xử lý TẬP TRUNG ở
                    // enforceScopeExclusivity() (xem ghi chú dài tại field 'scope_type' phía trên
                    // giải thích lý do đổi cách làm).
                    // Dùng thẳng ->relationship() (PackagePlan::product(), chuẩn Filament) thay vì
                    // ->getSearchResultsUsing()/->getOptionLabelUsing() tự viết tay — select trước
                    // đây kẹt mãi ở "Đang tải..." không load được sân nào (bug đã gặp). Không dựng
                    // lại được lỗi qua tinker (câu query gốc chạy đúng, trả về kết quả bình
                    // thường) nên đổi sang cách làm chuẩn, đã chạy ổn ở nhiều select khác trong hệ
                    // thống, để loại trừ rủi ro lỗi ở tầng Livewire/JS khó dò khi không debug được
                    // trực tiếp trên trình duyệt.
                    // Lọc theo đúng chi nhánh đang hiệu lực (switcher header) — TRƯỚC ĐÂY không lọc
                    // gì cả, hiện ĐỦ sân của MỌI đối tác trong dropdown này (bug thật đã gặp: chọn
                    // switcher xong list "Gói Nhiều Buổi" đã lọc đúng, nhưng form Tạo/Sửa vẫn hiện
                    // tràn lan mọi chi nhánh). Cùng nguồn effectiveBranchIds()/visibleProductCategoryIds()
                    // dùng ở getEloquentQuery() phía trên, xem scopedCategoryIds().
                    ->relationship('product', 'name', fn ($query) => $query->whereHas(
                        'categories',
                        fn ($q) => $q->whereIn('categories.id', static::scopedCategoryIds())
                    ))
                    ->searchable()
                    ->preload()
                    ->helperText('Gói CHỈ dùng được cho đúng sân này khi đặt sân (không dùng chéo sân khác).'),

                Select::make('category_id')
                    ->label('Chi nhánh áp dụng')
                    ->placeholder('Tìm theo tên chi nhánh')
                    // Cần cho CẢ 2 phạm vi "Toàn bộ chi nhánh" LẪN "Một số sân trong chi nhánh" —
                    // phạm vi sau vẫn phải biết đang thuộc chi nhánh nào để lọc options() của
                    // CheckboxList 'product_ids_input' bên dưới VÀ để CustomerPackage::
                    // findValidForPhoneAndProduct() khớp đúng theo category_id + product_ids.
                    ->visible(fn (Get $get) => in_array($get('scope_type'), ['category', 'selected'], true))
                    ->required(fn (Get $get) => in_array($get('scope_type'), ['category', 'selected'], true))
                    ->live()
                    // Lọc theo đúng chi nhánh đang hiệu lực (switcher header) — cùng lý do với
                    // product_id ở trên, xem scopedCategoryIds().
                    ->relationship('category', 'name', fn ($query) => $query->where('category_type', 'product')
                        ->whereIn('id', static::scopedCategoryIds()))
                    ->searchable()
                    ->preload()
                    ->helperText(fn (Get $get) => $get('scope_type') === 'selected'
                        ? 'Chọn chi nhánh trước để hiện danh sách sân bên dưới.'
                        : 'Gói dùng được cho MỌI sân thuộc chi nhánh này, không riêng 1 sân.'),

                CheckboxList::make('product_ids')
                    ->label('Sân áp dụng')
                    ->visible(fn (Get $get) => $get('scope_type') === 'selected')
                    ->required(fn (Get $get) => $get('scope_type') === 'selected')
                    ->options(fn (Get $get) => $get('category_id')
                        ? Product::whereHas('categories', fn ($q) => $q->where('categories.id', $get('category_id')))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                        : [])
                    ->disabled(fn (Get $get) => blank($get('category_id')))
                    ->columns(2)
                    ->bulkToggleable()
                    // Tên field khớp THẲNG cột JSON thật 'product_ids' (cast 'array' ở PackagePlan
                    // model) — không tự ->dehydrateStateUsing() ép null nữa, xử lý tập trung ở
                    // enforceScopeExclusivity() (xem ghi chú tại field 'scope_type').
                    ->helperText('Gói CHỈ dùng được cho các sân đã chọn — sân khác trong cùng chi nhánh không dùng được.'),

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
                    ->label('Phạm vi')
                    ->formatStateUsing(function ($record) {
                        return match ($record->scopeType()) {
                            'product'           => $record->product?->name ?? '—',
                            'selected_products'  => count($record->product_ids ?? []) . ' sân — ' . ($record->category?->name ?? '—'),
                            'branch'            => 'Cả chi nhánh: ' . ($record->category?->name ?? '—'),
                        };
                    })
                    ->searchable(['product.name'])
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
                            'partner_id'         => $record->partner_id,
                            'buyer_phone'        => $data['buyer_phone'],
                            'buyer_name'         => $data['buyer_name'],
                            'product_id'         => $record->product_id,
                            'category_id'        => $record->category_id,
                            'product_ids'        => $record->product_ids,
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
