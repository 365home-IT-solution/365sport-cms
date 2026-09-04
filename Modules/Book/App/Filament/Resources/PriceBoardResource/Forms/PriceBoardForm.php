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
                            ->options(fn () => static::allRoomOptions())
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT),

                        Actions::make([
                            Action::make('apply_room_selection')
                                ->label('Tạo/cập nhật danh sách sân theo tích chọn ở trên')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->action(fn (Get $get, Set $set) => static::syncItemsFromChecklist($get('_room_checklist') ?? [], $get, $set)),
                        ])
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('pricing_mode') !== PriceBoard::MODE_ADJUSTMENT),

                        CheckboxList::make('product_ids')
                            ->label('Sân')
                            ->options(fn () => static::allRoomOptions())
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('pricing_mode') === PriceBoard::MODE_ADJUSTMENT),
                    ])
                    ->columnSpanFull(),

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

    /** Toàn bộ sân đang hoạt động — dùng làm options() cho 2 CheckboxList chọn sân. */
    private static function allRoomOptions(): array
    {
        return Product::where('is_activated', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
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
