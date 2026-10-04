<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CameraResource\Pages;
use App\Models\Camera;
use App\Services\Go2RtcClient;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Modules\Category\Entities\Category;

// Camera an ninh (giám sát trộm/khu vực sân bãi) — xem ghi chú nghiệp vụ đầy đủ ở migration
// create_cameras_table và App\Models\Camera.
class CameraResource extends Resource
{
    protected static ?string $model = Camera::class;

    protected static ?string $navigationIcon   = 'heroicon-o-video-camera';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Camera an ninh';
    protected static ?string $modelLabel       = 'Camera';
    protected static ?string $pluralModelLabel = 'Camera an ninh';
    protected static ?int    $navigationSort   = 12;

    public static function canViewAny(): bool
    {
        return auth()->user()?->partnerHasFeature('camera') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Tên camera')
                ->required()
                ->maxLength(255),

            Select::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn () => Category::whereIn('id', auth()->user()?->rootProductCategoryIds() ?? [])
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required()
                ->helperText('Camera giám sát theo cơ sở — gắn theo chi nhánh, không theo từng sân riêng lẻ.'),

            TextInput::make('stream_key')
                ->label('Tên nguồn (go2rtc)')
                ->required()
                ->maxLength(255)
                ->helperText('Tên nguồn stream trong go2rtc — phải khớp với camera đã khai báo trên server go2rtc/Frigate.'),

            TextInput::make('frigate_camera_name')
                ->label('Tên camera trong Frigate')
                ->maxLength(255)
                ->helperText('Để trống nếu trùng với "Tên nguồn" ở trên — chỉ cần điền khi tên trong Frigate lệch chữ hoa/thường hoặc gạch nối.'),

            TextInput::make('rtsp_url')
                ->label('RTSP URL')
                ->password()
                ->revealable()
                ->helperText('CHỈ điền khi camera CHƯA có sẵn trong Frigate — hệ thống tự khai báo nguồn này sang go2rtc khi lưu. Nếu camera đã có sẵn trong Frigate/go2rtc, để trống.'),

            Textarea::make('note')
                ->label('Ghi chú')
                ->rows(2),

            Toggle::make('status')
                ->label('Đang hoạt động')
                ->default(true),

            Toggle::make('alert_enabled')
                ->label('Bật cảnh báo phát hiện người ngoài giờ')
                ->live()
                ->helperText('Quét định kỳ phát hiện người trong khung giờ dưới đây, báo cho nhân viên phụ trách chi nhánh.'),

            TimePicker::make('alert_start_time')
                ->label('Bắt đầu khung giờ cảnh báo')
                ->seconds(false)
                ->visible(fn ($get) => $get('alert_enabled')),

            TimePicker::make('alert_end_time')
                ->label('Kết thúc khung giờ cảnh báo')
                ->seconds(false)
                ->visible(fn ($get) => $get('alert_enabled')),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên camera')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->placeholder('—'),

                TextColumn::make('stream_key')
                    ->label('Nguồn')
                    ->fontFamily('mono')
                    ->searchable(),

                ToggleColumn::make('status')
                    ->label('Hoạt động'),

                ToggleColumn::make('alert_enabled')
                    ->label('Cảnh báo'),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCameras::route('/'),
            'create' => Pages\CreateCamera::route('/create'),
            'edit'   => Pages\EditCamera::route('/{record}/edit'),
        ];
    }

    // Đồng bộ nguồn go2rtc sau khi lưu — dùng chung cho cả Create/Edit page (xem
    // Pages\CreateCamera::afterCreate() / Pages\EditCamera::afterSave()).
    public static function syncGo2Rtc(Camera $camera): void
    {
        if ($camera->rtsp_url) {
            app(Go2RtcClient::class)->addStream($camera->stream_key, $camera->rtsp_url, $camera->partner_id);
        }
    }
}
