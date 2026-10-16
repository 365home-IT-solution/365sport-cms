<?php

declare(strict_types=1);

namespace Modules\Book\App\Filament\Resources\PriceBoardResource\Forms;

use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Services\PriceBoardSyncService;
use Modules\Book\App\Filament\Traits\HasRoomPricingFormFields;
use Modules\Category\Entities\Category;
use Modules\DataPermission\Entities\UserBranchPermission;
use Modules\Product\App\Models\PriceBoard;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\TimeSlot;

/**
 * Port từ 365home-cms, RÚT GỌN — bỏ khối "Sửa giá hàng loạt theo vị trí/khung giờ across nhiều sân"
 * (phụ thuộc trait HasRoomPricingFormFields không tồn tại ở dự án này) và khối "Tổng quan lịch
 * trình" (ViewField trang trí, không phải chức năng cốt lõi). Cốt lõi "1 bảng áp cho NHIỀU sân
 * cùng lúc" vẫn đầy đủ qua CheckboxList chọn sân + Repeater 'items' — xem PriceBoardResource.php.
 * Field per-sân dùng ĐÚNG conventions đã có sẵn ở SettingBook.php (TableRepeater cho khung giờ) để
 * nhất quán với phần "Hệ thống giá" gốc.
 */
class PriceBoardForm
{
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Thông tin bảng giá')
                    ->schema([
                        TextInput::make('name')
                            ->label('Tên bảng giá')
                            ->placeholder('VD: Giá Tết 2026, Giá đối tác ABC')
                            ->required()
                            ->maxLength(255),

                        Select::make('category_id')
                            ->label('Chi nhánh')
                            ->options(fn () => static::branchOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                // Đổi chi nhánh thì danh sách sân cũ không còn hợp lệ.
                                $set('_room_checklist', []);
                                $set('product_ids', []);
                                $set('items', []);
                            })
                            ->helperText('Bảng giá chỉ áp cho sân thuộc chi nhánh này.'),

                        Toggle::make('is_active')
                            ->label('Kích hoạt')
                            ->helperText('Bật/tắt có hiệu lực NGAY LẬP TỨC cho các sân đã chọn, không cần chờ tới ngày.')
                            ->default(true)
                            ->inline(false),

                        DatePicker::make('start_date')
                            ->label('Ngày bắt đầu')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('end_date')
                            ->label('Ngày kết thúc')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('start_date'),

                        Select::make('pricing_mode')
                            ->label('Kiểu bảng giá')
                            ->options([
                                PriceBoard::MODE_OVERRIDE   => 'Thay thế giá cụ thể từng sân',
                                PriceBoard::MODE_ADJUSTMENT => 'Điều chỉnh % / số tiền trên giá gốc',
                            ])
                            ->default(PriceBoard::MODE_OVERRIDE)
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Group::make([
                            Select::make('adjustment_type')
                                ->label('Kiểu điều chỉnh')
                                ->options([
                                    'percentage' => 'Theo % giá gốc',
                                    'fixed'      => 'Số tiền cố định',
                                ])
                                ->default('percentage')
                                ->required(fn (Get $get) => $get('pricing_mode') === PriceBoard::MODE_ADJUSTMENT)
                                ->live(),

                            TextInput::make('adjustment_value')
                                ->label(fn (Get $get) => $get('adjustment_type') === 'fixed' ? 'Số tiền (+/- VNĐ)' : 'Phần trăm (+/- %)')
                                ->helperText('Nhập số âm để giảm giá. VD: -15 = giảm 15%, +20 = tăng 20%.')
                                ->numeric()
                                ->required(fn (Get $get) => $get('pricing_mode') === PriceBoard::MODE_ADJUSTMENT)
                                ->suffix(fn (Get $get) => $get('adjustment_type') === 'fixed' ? 'VNĐ' : '%'),
                        ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('pricing_mode') === PriceBoard::MODE_ADJUSTMENT),

                        Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Chọn sân áp dụng')
                    ->description('Tích chọn sân cần áp dụng bảng giá (gõ để tìm nhanh theo tên sân).')
                    ->schema([
                        CheckboxList::make('_room_checklist')
                            ->label('Sân')
                            ->options(fn (Get $get) => static::allRoomOptions($get('category_id')))
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT),

                        Select::make('_slot_source')
                            ->label('Cách tạo khung giờ')
                            ->options([
                                'random' => 'Random (nhiều khung nối tiếp, mỗi khung 1 tiếng)',
                                'template' => 'Chọn trong bảng giá mẫu có sẵn',
                            ])
                            ->default('random')
                            ->live()
                            ->dehydrated(false)
                            ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT),

                        Select::make('_template_id')
                            ->label('Bảng giá mẫu')
                            ->options(fn (Get $get) => static::templateOptions($get('category_id')))
                            ->helperText('Chọn bảng giá mẫu rồi bấm "Điền khung giờ + giá" — có thể sửa lại giá từng khung trước khi lưu.')
                            ->dehydrated(false)
                            ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT && $get('_slot_source') === 'template'),

                        TextInput::make('_random_count')
                            ->label('Số khung giờ ngẫu nhiên mỗi sân')
                            ->helperText('Dùng cho cách Random: các khung nối tiếp nhau, mỗi khung đúng 1 tiếng.')
                            ->numeric()
                            ->integer()
                            ->minValue(\App\Services\RoomSlotRandomizer::MIN_COUNT)
                            ->maxValue(\App\Services\RoomSlotRandomizer::MAX_COUNT)
                            ->default(\App\Services\RoomSlotRandomizer::DEFAULT_COUNT)
                            ->dehydrated(false)
                            ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT && ($get('_slot_source') ?? 'random') === 'random'),

                        Actions::make([
                            Action::make('apply_room_selection')
                                ->label('Tạo/cập nhật danh sách sân theo tích chọn ở trên')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->action(fn (Get $get, Set $set) => static::syncItemsFromChecklist($get('_room_checklist') ?? [], $get, $set)),
                            Action::make('random_slots')
                                ->label('Điền khung giờ + giá')
                                ->icon('heroicon-o-sparkles')
                                ->color('gray')
                                ->tooltip('Mỗi sân (theo khung giờ) được điền sẵn số khung giờ đã chọn, các khung nối tiếp mỗi khung 1 tiếng, kèm giá ngẫu nhiên — đỡ phải thêm và đặt giá từng khung. Kiểm tra lại rồi bấm Lưu.')
                                ->action(fn (Get $get, Set $set) => static::fillRandomSlots($get, $set)),
                        ])
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT),

                        CheckboxList::make('product_ids')
                            ->label('Sân')
                            ->options(fn (Get $get) => static::allRoomOptions($get('category_id')))
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('pricing_mode') === PriceBoard::MODE_ADJUSTMENT),
                    ])
                    ->columnSpanFull(),

                Section::make('Sửa giá hàng loạt')
                    ->description('Điền ô nào thì áp dụng cho loại sân đó (để trống = không đổi loại đó) — 1 lần bấm Áp dụng cho TẤT CẢ sân đang có trong bảng, không cần mở từng sân.')
                    ->schema([
                        Select::make('_bulk_mode')
                            ->label('Kiểu áp dụng')
                            ->options([
                                'price'   => 'Giá cụ thể (VNĐ)',
                                'percent' => 'Điều chỉnh % trên giá đang có',
                            ])
                            ->default('price')
                            ->live()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Section::make('Theo Ngày')
                            ->icon('heroicon-o-calendar-days')
                            ->compact()
                            ->schema([
                                TextInput::make('_bulk_day_value')
                                    ->label(fn (Get $get) => $get('_bulk_mode') === 'percent' ? 'Mức % (+/-)' : 'Giá/đêm mới (VNĐ)')
                                    ->helperText('Áp cho tất cả sân Theo Ngày đang có trong bảng. Để trống nếu không đổi.')
                                    ->numeric()
                                    ->dehydrated(false)
                                    ->columnSpan(1),
                            ])
                            // Chỉ hiện khi trong danh sách sân đã chọn có ÍT NHẤT 1 sân "Theo
                            // Ngày" — trước đây hiện cố định dù chọn toàn sân "Theo Khung Giờ",
                            // gây hiểu nhầm là áp được cho cả loại sân không có trong bảng.
                            ->visible(fn (Get $get) => static::itemsHaveStyle($get('items') ?? [], 2)),

                        Section::make('Theo Khung Giờ')
                            ->icon('heroicon-o-clock')
                            ->compact()
                            ->visible(fn (Get $get) => static::itemsHaveStyle($get('items') ?? [], 1))
                            ->schema([
                                Repeater::make('_bulk_slot_rules')
                                    ->label('Các khung giờ cần đổi giá')
                                    ->helperText('Thêm nhiều dòng để đổi giá nhiều khung giờ cùng lúc, VD: khung 1 → 200.000, khung 2 → 300.000... — 1 lần bấm "Áp dụng" bên dưới là áp hết cho mọi sân trong bảng.')
                                    ->schema([
                                        Select::make('pick_mode')
                                            ->label('Chọn khung giờ theo')
                                            ->options([
                                                'position' => 'Vị trí (đầu/cuối/thứ N)',
                                                'specific' => 'Khung giờ cụ thể',
                                            ])
                                            ->default('position')
                                            ->live()
                                            ->dehydrated(false)
                                            ->columnSpan(1),

                                        Select::make('timeslot_id')
                                            ->label('Khung giờ')
                                            ->options(fn () => TimeSlot::forCurrentAdmin()->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'date'))->pluck('label', 'id'))
                                            ->searchable()
                                            ->dehydrated(false)
                                            ->columnSpan(1)
                                            ->visible(fn (Get $get) => $get('pick_mode') === 'specific'),

                                        Select::make('position')
                                            ->label('Vị trí khung giờ')
                                            ->options([
                                                'first' => 'Khung đầu tiên',
                                                'last'  => 'Khung cuối cùng',
                                                'nth'   => 'Khung thứ N',
                                            ])
                                            ->default('first')
                                            ->live()
                                            ->dehydrated(false)
                                            ->columnSpan(1)
                                            ->visible(fn (Get $get) => $get('pick_mode') === 'position'),

                                        TextInput::make('position_n')
                                            ->label('N = ?')
                                            ->numeric()
                                            ->minValue(1)
                                            ->dehydrated(false)
                                            ->columnSpan(1)
                                            ->visible(fn (Get $get) => $get('pick_mode') === 'position' && $get('position') === 'nth'),

                                        TextInput::make('value')
                                            ->label(fn (Get $get) => $get('../../_bulk_mode') === 'percent' ? 'Mức % (+/-)' : 'Giá mới (VNĐ)')
                                            ->numeric()
                                            ->dehydrated(false)
                                            ->required()
                                            ->columnSpan(1),
                                    ])
                                    // Luôn 1 cột — lưới chia cột theo breakpoint MÀN HÌNH chứ không
                                    // theo bề rộng thật của khối cha (section này chỉ chiếm nửa trang
                                    // do nằm cạnh "Theo Ngày"), chia nhiều cột dễ bị bóp chật/cắt chữ dù
                                    // màn hình máy tính to. Mỗi field 1 dòng riêng cho chắc chắn không
                                    // lệch, kể cả trên mobile.
                                    ->columns(1)
                                    ->defaultItems(0)
                                    ->addActionLabel('Thêm khung giờ cần đổi')
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->description('Áp cho tất cả sân Theo Khung Giờ đang có trong bảng, tại đúng (các) khung giờ chọn bên dưới.'),

                        Actions::make([
                            Action::make('apply_bulk_price')
                                ->label('Áp dụng cho mọi sân đang có trong bảng')
                                ->icon('heroicon-o-bolt')
                                ->color('warning')
                                ->action(function (Get $get, Set $set, ?PriceBoard $record) {
                                    $mode      = $get('_bulk_mode');
                                    $dayValue  = $get('_bulk_day_value');
                                    $slotRules = collect($get('_bulk_slot_rules') ?? [])
                                        ->filter(fn ($rule) => ($rule['value'] ?? null) !== null && $rule['value'] !== '')
                                        ->values();

                                    if (($dayValue === null || $dayValue === '') && $slotRules->isEmpty()) {
                                        return;
                                    }

                                    $items = $get('items') ?? [];

                                    if ($dayValue !== null && $dayValue !== '') {
                                        $items = static::applyBulkDayPrice($items, $mode, (float) $dayValue);
                                    }

                                    // Áp TỪNG dòng 1 theo đúng thứ tự đã thêm — mỗi dòng chỉ đổi 1 vị
                                    // trí/khung giờ cụ thể nên không ghi đè lẫn nhau (khung 1→A xong mới
                                    // tới khung 2→B, không phải cả 2 cùng ghi vào 1 vị trí).
                                    foreach ($slotRules as $rule) {
                                        $items = ($rule['pick_mode'] ?? 'position') === 'position'
                                            ? static::applyBulkByPosition($items, $rule['position'] ?? 'first', (int) ($rule['position_n'] ?? 1), $mode, (float) $rule['value'])
                                            : static::applyBulkByTimeslot($items, $rule['timeslot_id'] ?? null, $mode, (float) $rule['value']);
                                    }

                                    $set('items', $items);

                                    static::persistBulkItems($record, $items, $set);
                                }),
                        ])->columnSpanFull(),
                    ])
                    // Luôn xếp dọc (1 cột) — "Theo Khung Giờ" giờ có thể nhiều dòng, để cạnh "Theo
                    // Ngày" sẽ bị ép chỉ còn nửa bề rộng trang, dễ bóp chật các field bên trong.
                    ->columns(1)
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT),

                Repeater::make('items')
                    ->label('Chi tiết giá từng sân đã chọn')
                    ->helperText('Đã điền sẵn giá/khung giờ hiện tại của từng sân — mở ra chỉ để sửa số cần đổi.')
                    ->schema([
                        Hidden::make('product_id'),

                        Group::make([
                            TableRepeater::make('roomTimeSlots')
                                ->label('Khung giờ')
                                ->headers([
                                    Header::make('timeslot_id')->label('Khung giờ')->width('200px'),
                                    Header::make('price')->label('Giá')->width('150px'),
                                    Header::make('over_night')->label('Qua đêm')->width('100px'),
                                ])
                                ->schema([
                                    Select::make('timeslot_id')
                                        ->label('Khung giờ')
                                        ->options(fn () => TimeSlot::where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'date'))->pluck('label', 'id'))
                                        ->searchable()
                                        ->required(),

                                    TextInput::make('price')
                                        ->label('Giá')
                                        ->required()
                                        ->suffix('VNĐ')
                                        ->extraInputAttributes(['inputmode' => 'numeric']),

                                    Toggle::make('over_night')
                                        ->label('Qua đêm')
                                        ->default(false),

                                    Hidden::make('status')->default('available'),
                                ])
                                ->defaultItems(0)
                                ->columns(3)
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ])->visible(fn (Get $get) => static::styleOf($get('product_id')) === 1),

                        Group::make(static::compactDaySchema())
                            ->visible(fn (Get $get) => static::styleOf($get('product_id')) === 2),
                    ])
                    ->itemLabel(fn (array $state) => Product::find($state['product_id'] ?? null)?->name ?? 'Sân')
                    ->collapsed()
                    ->collapsible()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    // Chặn lưu bảng rỗng khi admin đã tích chọn sân ở checklist phía trên nhưng
                    // QUÊN bấm nút "Tạo/cập nhật danh sách sân".
                    ->minItems(fn (Get $get) => $get('pricing_mode') === PriceBoard::MODE_ADJUSTMENT ? 0 : 1)
                    ->validationMessages([
                        'minItems' => 'Chưa có sân nào trong bảng giá — nếu bạn đã tích chọn sân ở trên, hãy bấm "Tạo/cập nhật danh sách sân theo tích chọn ở trên" trước khi lưu.',
                    ])
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT),
            ]);
    }

    /** Dữ liệu 1 dòng sân trong Repeater 'items', điền sẵn từ giá/cấu hình HIỆN TẠI của sân
     *  (products/room_time_slots) — để admin chỉ cần sửa số cần đổi thay vì phải thêm lại từ đầu
     *  từng khung giờ. */
    private static function defaultRowFor(Product $product): array
    {
        $row = ['product_id' => $product->id];

        if ((int) ($product->styles ?? 1) === 2) {
            $row['price']               = $product->price;
            $row['default_checkin']     = $product->default_checkin ?? '14:00';
            $row['default_checkout']    = $product->default_checkout ?? '12:00';
            $row['deposit_min_nights']  = $product->deposit_min_nights ?? 2;
            $row['deposit_multi_night'] = $product->deposit_multi_night ?? 50;

            return $row;
        }

        $row['roomTimeSlots'] = $product->roomTimeSlots()
            ->whereHas('timeSlot', fn ($q) => $q->where(fn ($q2) => $q2->whereNull('type')->orWhere('type', '!=', 'date')))
            ->with('timeSlot')
            ->get()
            ->sortBy(fn ($slot) => $slot->timeSlot?->start_time ?? '99:99:99')
            ->values()
            ->map(fn ($slot) => [
                'timeslot_id' => $slot->timeslot_id,
                'price'       => (int) $slot->price,
                'over_night'  => $slot->over_night,
                'status'      => $slot->status ?? 'available',
            ])
            ->toArray();

        return $row;
    }

    /** Form RÚT GỌN cho sân "Theo Ngày" trong bảng giá — chỉ nổi bật đúng 1 ô "Giá mỗi đêm" để đổi
     *  nhanh, các field còn lại gói trong khối "Cài đặt nâng cao" thu gọn. */
    private static function compactDaySchema(): array
    {
        return [
            TextInput::make('price')
                ->label('Giá mỗi đêm')
                ->placeholder('VD: 500000')
                ->numeric()
                ->suffix('VNĐ')
                ->extraInputAttributes(['inputmode' => 'numeric']),

            Section::make('Cài đặt nâng cao (giờ nhận/trả sân, cọc)')
                ->description('Chỉ mở nếu cần đổi thêm — để trống thì giữ nguyên như hiện tại.')
                ->schema([
                    TimePicker::make('default_checkin')
                        ->label('Giờ nhận sân mặc định')
                        ->seconds(false)
                        ->native(false)
                        ->locale('vi')
                        ->timezone('Asia/Ho_Chi_Minh'),

                    TimePicker::make('default_checkout')
                        ->label('Giờ trả sân mặc định')
                        ->seconds(false)
                        ->native(false)
                        ->locale('vi')
                        ->timezone('Asia/Ho_Chi_Minh'),

                    TextInput::make('deposit_min_nights')
                        ->label('Đặt từ X đêm mới cọc')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(30)
                        ->suffix('đêm')
                        ->extraInputAttributes(['inputmode' => 'numeric']),

                    TextInput::make('deposit_multi_night')
                        ->label('Phần trăm cọc (%)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('%')
                        ->extraInputAttributes(['inputmode' => 'numeric']),
                ])
                ->columns(2)
                ->collapsed()
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    /** Dựng lại state 'items' cho Repeater từ đúng dữ liệu ĐANG LƯU trong DB của $board — dùng khi
     *  mở trang Sửa (EditPriceBoard::mutateFormDataBeforeFill). */
    public static function buildItemsFromBoard(PriceBoard $board): array
    {
        return $board->items()->with(['timeSlots.timeslot', 'product'])->get()
            ->map(function ($item) {
                $style = (int) ($item->product->styles ?? 1);

                $row = ['product_id' => $item->product_id];

                if ($style === 2) {
                    $row['price']               = $item->price;
                    $row['default_checkin']     = $item->default_checkin;
                    $row['default_checkout']    = $item->default_checkout;
                    $row['deposit_min_nights']  = $item->deposit_min_nights;
                    $row['deposit_multi_night'] = $item->deposit_multi_night;

                    return $row;
                }

                $row['roomTimeSlots'] = $item->timeSlots
                    ->sortBy(fn ($slot) => $slot->timeslot?->start_time ?? '99:99:99')
                    ->values()
                    ->map(fn ($slot) => [
                        'timeslot_id' => $slot->timeslot_id,
                        'price'       => (int) $slot->price,
                        'over_night'  => $slot->over_night,
                        'status'      => $slot->status,
                    ])
                    ->toArray();

                return $row;
            })
            ->toArray();
    }

    private static function styleOf(?string $productId): int
    {
        if (! $productId) {
            return 1;
        }

        return (int) (Product::find($productId)?->styles ?? 1);
    }

    /** Toàn bộ sân đang hoạt động — dùng làm options() cho 2 CheckboxList chọn sân. Trang "Hệ
     *  thống giá" (SettingBook) đã có bộ lọc chi nhánh riêng ở cấp trang, nên ở đây không lọc lại
     *  theo chi nhánh/đối tác/loại sân nữa — chỉ cần danh sách sân để tích chọn (đã có tìm kiếm
     *  theo tên qua ->searchable() trên CheckboxList). */
    /** Chi nhánh (category gốc) của đối tác đang đăng nhập; super_admin chưa chọn đối tác thấy hết,
     *  nhân viên chỉ được giao vài chi nhánh thì chỉ những chi nhánh đó. */
    public static function branchOptions(): array
    {
        $user    = auth()->user();
        $allowed = $user?->allowedCategoryIds() ?? [];

        return Category::withoutGlobalScopes()
            ->whereNull('parent_id')
            ->where('category_type', 'product')
            ->when($user && ! $user->seesAllPartners(), fn ($q) => $q->where('partner_id', $user->partner_id))
            ->when(! empty($allowed), fn ($q) => $q->whereIn('id', $allowed))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /** Bảng giá mẫu dùng được cho chi nhánh đã chọn: mẫu hệ thống + mẫu riêng của đối tác chi nhánh đó
     *  (mẫu hệ thống đã tách bản riêng thì ẩn). */
    public static function templateOptions(mixed $branchId = null): array
    {
        $partnerId = filled($branchId)
            ? Category::withoutGlobalScopes()->whereKey($branchId)->value('partner_id')
            : auth()->user()?->partner_id;

        $forkedIds = $partnerId
            ? \Modules\Product\App\Models\PriceTemplate::where('partner_id', $partnerId)->whereNotNull('source_template_id')->pluck('source_template_id')
            : collect();

        return \Modules\Product\App\Models\PriceTemplate::usableBy($partnerId)
            ->where('is_active', true)
            ->whereNotIn('id', $forkedIds)
            ->orderByRaw('partner_id IS NULL DESC')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($t) => [$t->id => $t->name . ($t->partner_id ? ' (riêng)' : '')])
            ->toArray();
    }

    private static function allRoomOptions(mixed $branchId = null): array
    {
        if (blank($branchId)) {
            return [];
        }

        return Product::where('is_activated', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', PriceBoard::branchCategoryIds((int) $branchId)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /** Điền khung giờ + giá ngẫu nhiên vào các dòng sân theo khung giờ (styles=1) của $items.
     *  1 bộ khung giờ chung cho cả lần điền; giá từng sân lệch ±10% cho tự nhiên.
     *
     *  @return array{0: array, 1: int} [items đã điền, số sân đã điền] */
    public static function randomizeItems(array $items, string $partnerId, int $count, ?array $slots = null): array
    {
        $slots  ??= app(\App\Services\RoomSlotRandomizer::class)->pick($partnerId, $count)['slots'];
        $baseline = static::baselinePriceResolver();
        $filled   = 0;

        foreach ($items as $i => $item) {
            if (static::styleOf($item['product_id'] ?? null) !== 1) {
                continue;
            }

            $items[$i]['roomTimeSlots'] = collect($slots)
                ->map(fn (array $slot) => [
                    'timeslot_id'     => $slot['timeslot_id'],
                    'price'           => number_format(intdiv((int) round($slot['price'] * random_int(90, 110) / 100), 10000) * 10000, 0, ',', '.'),
                    '_baseline_price' => $baseline($item['product_id'], $slot['timeslot_id']),
                    'promotions'      => [],
                    'over_night'      => $slot['over_night'],
                    'status'          => 'available',
                ])->all();
            $filled++;
        }

        return [$items, $filled];
    }

    /** Điền Repeater 'items' bằng khung giờ + giá NGẪU NHIÊN lấy từ các khung giờ có sẵn của đối tác
     *  (App\Services\RoomSlotRandomizer) cho từng sân theo khung giờ — mỗi sân một bộ khác nhau.
     *  Chỉ điền vào form; đối tác kiểm tra/sửa rồi bấm Lưu mới ghi xuống hệ thống. */
    private static function fillRandomSlots(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];

        if (! $items) {
            Notification::make()
                ->title('Chưa có sân nào — tích chọn sân rồi bấm "Tạo/cập nhật danh sách sân" trước.')
                ->warning()
                ->send();

            return;
        }

        // Khung giờ random được tạo thành khung riêng của đối tác → phải biết chi nhánh (đối tác) trước.
        $branchId  = $get('category_id');
        $partnerId = $branchId
            ? Category::withoutGlobalScopes()->whereKey($branchId)->value('partner_id')
            : null;

        if (blank($partnerId)) {
            Notification::make()->title('Chọn chi nhánh trước khi điền khung giờ ngẫu nhiên.')->warning()->send();

            return;
        }

        $slots = null;

        if (($get('_slot_source') ?? 'random') === 'template') {
            $template = \Modules\Product\App\Models\PriceTemplate::usableBy((string) $partnerId)
                ->where('is_active', true)
                ->with('slots')
                ->find($get('_template_id'));

            if (! $template) {
                Notification::make()->title('Chọn bảng giá mẫu trước.')->warning()->send();

                return;
            }

            $slots = $template->slots
                ->filter(fn ($s) => $s->price !== null)
                ->map(fn ($s) => [
                    'timeslot_id' => (int) $s->timeslot_id,
                    'price'       => (int) $s->price,
                    'over_night'  => (bool) $s->over_night,
                ])->values()->all();
        }

        $count = (int) ($get('_random_count') ?: \App\Services\RoomSlotRandomizer::DEFAULT_COUNT);

        [$items, $filled] = static::randomizeItems($items, (string) $partnerId, $count, $slots);

        $set('items', $items);

        Notification::make()
            ->title($filled ? "Đã điền ngẫu nhiên khung giờ + giá cho {$filled} sân — kiểm tra rồi bấm Lưu." : 'Không có sân theo khung giờ nào để điền.')
            ->{$filled ? 'success' : 'warning'}()
            ->send();
    }

    /** Đồng bộ Repeater 'items' theo đúng tập sân đang tích trong CheckboxList — thêm dòng mới
     *  (điền sẵn giá hiện tại) cho sân vừa tích, gỡ dòng của sân vừa bỏ tích. Dòng đã có sẵn của
     *  sân vẫn đang tích giữ nguyên, không bị ghi đè lại (không mất dữ liệu đã tuỳ chỉnh). */
    private static function syncItemsFromChecklist(array $checkedIds, Get $get, Set $set): void
    {
        $checkedIds  = array_map('strval', $checkedIds);
        $items       = $get('items') ?? [];
        $existingIds = collect($items)->pluck('product_id')->filter()->map(fn ($v) => (string) $v)->all();

        foreach ($checkedIds as $id) {
            if (in_array($id, $existingIds, true)) {
                continue;
            }

            $product = Product::find($id);
            if ($product) {
                $items[] = static::defaultRowFor($product);
            }
        }

        $items = collect($items)
            ->filter(fn ($item) => in_array((string) ($item['product_id'] ?? ''), $checkedIds, true))
            ->values()
            ->all();

        $set('items', $items);
    }
}
