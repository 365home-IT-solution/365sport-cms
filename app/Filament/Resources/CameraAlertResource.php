<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CameraAlertResource\Pages;
use App\Models\CameraAlert;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Employee\Entities\Employee;

// Lịch sử cảnh báo phát hiện người ngoài giờ mở cửa — do CameraAlertPollingService tạo tự động,
// KHÔNG có form tạo/sửa thủ công ở đây.
class CameraAlertResource extends Resource
{
    protected static ?string $model = CameraAlert::class;

    protected static ?string $navigationIcon   = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Cảnh báo Camera';
    protected static ?string $modelLabel       = 'Cảnh báo';
    protected static ?string $pluralModelLabel = 'Cảnh báo Camera';
    protected static ?int    $navigationSort   = 13;

    public static function canViewAny(): bool
    {
        return auth()->user()?->partnerHasFeature('camera') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->whereNull('acknowledged_at')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('camera.name')
                    ->label('Camera')
                    ->searchable(),

                TextColumn::make('camera.branch.name')
                    ->label('Chi nhánh')
                    ->placeholder('—'),

                TextColumn::make('detected_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Loại')
                    ->badge(),

                TextColumn::make('acknowledgedBy.name')
                    ->label('Người xử lý')
                    ->placeholder('Chưa xử lý'),

                TextColumn::make('acknowledged_at')
                    ->label('Xử lý lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('detected_at', 'desc')
            ->filters([
                TernaryFilter::make('acknowledged')
                    ->label('Đã xử lý')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('acknowledged_at'),
                        false: fn ($query) => $query->whereNull('acknowledged_at'),
                    ),
            ])
            ->actions([
                Action::make('acknowledge')
                    ->label('Xác nhận đã kiểm tra')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CameraAlert $record) => ! $record->isAcknowledged())
                    ->action(function (CameraAlert $record): void {
                        $employee = Employee::where('user_id', auth()->id())->first();

                        $record->update([
                            'acknowledged_by' => $employee?->id,
                            'acknowledged_at' => now(),
                        ]);

                        Notification::make()->title('Đã xác nhận xử lý cảnh báo.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCameraAlerts::route('/'),
        ];
    }
}
