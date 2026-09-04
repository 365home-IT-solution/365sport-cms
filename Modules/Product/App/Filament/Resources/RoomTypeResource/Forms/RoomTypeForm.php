<?php

declare(strict_types=1);

namespace Modules\Product\App\Filament\Resources\RoomTypeResource\Forms;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Illuminate\Support\Str;

class RoomTypeForm
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label(__('product::room_type.form.label.name'))
                ->placeholder(__('product::room_type.form.placeholder.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::snake(Str::ascii($state)))),

            TextInput::make('slug')
                ->label(__('product::room_type.form.label.slug'))
                ->placeholder(__('product::room_type.form.placeholder.slug'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            // Text tự do (KHÔNG phải enum cố định) — mỗi đối tác có thể có môn thể thao khác nhau.
            // Select chỉ gợi ý sẵn 1 số lựa chọn phổ biến; bấm "+" (createOptionForm) để tự nhập
            // giá trị khác không có trong danh sách — createOptionUsing() trả thẳng chuỗi vừa gõ
            // làm state (KHÔNG tạo bản ghi ở đâu cả, vì đây chỉ là 1 cột string trên room_types,
            // không phải quan hệ tới bảng riêng).
            Select::make('sport_type')
                ->label('Môn thể thao')
                ->placeholder('-- Chọn hoặc thêm môn thể thao --')
                ->options([
                    'pickleball'  => 'Pickleball',
                    'badminton'   => 'Cầu lông',
                    'football'    => 'Bóng đá',
                    'tennis'      => 'Tennis',
                    'basketball'  => 'Bóng rổ',
                    'volleyball'  => 'Bóng chuyền',
                    'futsal'      => 'Futsal',
                ])
                ->searchable()
                ->native(false)
                ->nullable()
                ->createOptionForm([
                    TextInput::make('value')
                        ->label('Môn thể thao khác')
                        ->required()
                        ->maxLength(100),
                ])
                ->createOptionUsing(fn (array $data) => $data['value'])
                // Giá trị đã lưu trước đó có thể KHÔNG nằm trong danh sách gợi ý (đã nhập tay qua
                // createOptionForm ở trên) — không có getOptionLabelUsing() thì Select sẽ hiện rỗng
                // khi mở form sửa dù cột DB vẫn có giá trị, hiển thị thẳng chuỗi đã lưu làm nhãn.
                ->getOptionLabelUsing(fn ($value) => $value),

            Select::make('surface_type')
                ->label('Loại mặt sân')
                ->placeholder('-- Chọn hoặc thêm loại mặt sân --')
                ->options([
                    'grass'         => 'Cỏ tự nhiên',
                    'synthetic_turf' => 'Cỏ nhân tạo',
                    'wood'          => 'Sàn gỗ',
                    'concrete'      => 'Bê tông',
                    'clay'          => 'Đất nện',
                    'rubber'        => 'Cao su/PU',
                ])
                ->searchable()
                ->native(false)
                ->nullable()
                ->createOptionForm([
                    TextInput::make('value')
                        ->label('Loại mặt sân khác')
                        ->required()
                        ->maxLength(100),
                ])
                ->createOptionUsing(fn (array $data) => $data['value'])
                ->getOptionLabelUsing(fn ($value) => $value),

            Toggle::make('is_outdoor')
                ->label('Sân ngoài trời')
                ->helperText('Bật nếu đây là sân ngoài trời, tắt nếu là sân trong nhà.')
                ->nullable(),

            TextInput::make('icon')
                ->label(__('product::room_type.form.label.icon'))
                ->placeholder(__('product::room_type.form.placeholder.icon'))
                ->helperText(__('product::room_type.form.helper_text.icon'))
                ->hintActions([
                    Action::make('browse_icons')
                        ->label('Xem danh sách icon')
                        ->url('https://icons.expo.fyi/Index')
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-arrow-top-right-on-square'),
                ])
                ->maxLength(150),

            TextInput::make('icon_url')
                ->label(__('product::room_type.form.label.icon_url'))
                ->placeholder(__('product::room_type.form.placeholder.icon_url'))
                ->url()
                ->maxLength(500),

            TextInput::make('sort_order')
                ->label(__('product::room_type.form.label.sort_order'))
                ->numeric()
                ->default(0)
                ->minValue(0),

            Toggle::make('is_active')
                ->label(__('product::room_type.form.label.is_active'))
                ->default(false),
        ]);
    }
}
