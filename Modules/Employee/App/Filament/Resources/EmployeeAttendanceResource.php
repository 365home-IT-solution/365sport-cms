<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use App\Filament\Support\PartnerTableHelpers;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Employee\App\Filament\Resources\EmployeeAttendanceResource\Pages;
use Modules\Employee\Entities\Employee;
use Modules\Employee\Entities\EmployeeAttendance;

// Chấm công nhân viên: mỗi dòng là một nhân viên trong một ngày. Quản lý nhập/sửa ở đây (hoặc nhân viên tự chấm công qua API
// /api/admin/attendance/check-in); số ngày đi làm / nghỉ không phép được PayrollService dùng khi chốt lương tháng.
class EmployeeAttendanceResource extends Resource
{
    use \App\Filament\Support\GatedByFeature;

    protected static string $featureFlag = 'attendance';

    protected static ?string $model = EmployeeAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Phân quyền';

    protected static ?string $navigationLabel = 'Chấm công';

    protected static ?string $modelLabel = 'Chấm công';

    protected static ?string $pluralModelLabel = 'Chấm công';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('employee_id')->label('Nhân viên')
                ->options(fn () => Employee::query()->where('status', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()->required()
                ->disabled(fn (?EmployeeAttendance $record) => $record !== null)->dehydrated(),
            DatePicker::make('work_date')->label('Ngày')->required()->default(today())
                ->disabled(fn (?EmployeeAttendance $record) => $record !== null)->dehydrated(),
            Select::make('status')->label('Trạng thái')->options(EmployeeAttendance::STATUSES)->default('present')->required(),
            DateTimePicker::make('check_in_at')->label('Giờ vào')->seconds(false),
            DateTimePicker::make('check_out_at')->label('Giờ ra')->seconds(false)->after('check_in_at'),
            Textarea::make('note')->label('Ghi chú')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_date')->label('Ngày')->date('d/m/Y')->sortable(),
                TextColumn::make('employee.name')->label('Nhân viên')->searchable(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => EmployeeAttendance::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'leave' => 'info', default => 'gray',
                    }),
                TextColumn::make('check_in_at')->label('Vào')->dateTime('H:i')->placeholder('—'),
                TextColumn::make('check_out_at')->label('Ra')->dateTime('H:i')->placeholder('—'),
                TextColumn::make('worked_minutes')->label('Giờ làm')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : floor($state / 60) . 'g' . str_pad((string) ($state % 60), 2, '0', STR_PAD_LEFT)),
                TextColumn::make('source')->label('Nguồn')->formatStateUsing(fn (string $state) => $state === 'self' ? 'Tự chấm công' : 'Quản lý nhập')->toggleable(isToggledHiddenByDefault: true),
                PartnerTableHelpers::column(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(EmployeeAttendance::STATUSES),
                SelectFilter::make('employee_id')->label('Nhân viên')->options(fn () => Employee::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
                Filter::make('month')->label('Tháng')
                    ->form([DatePicker::make('month')->label('Tháng')->displayFormat('m/Y')->native(false)])
                    ->query(fn (Builder $q, array $data) => $q->when($data['month'] ?? null, fn ($q, $d) => $q->whereYear('work_date', date('Y', strtotime($d)))->whereMonth('work_date', date('m', strtotime($d))))),
                PartnerTableHelpers::filter(),
            ])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([DeleteBulkAction::make()])
            ->defaultSort('work_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeAttendances::route('/'),
            'create' => Pages\CreateEmployeeAttendance::route('/create'),
            'edit' => Pages\EditEmployeeAttendance::route('/{record}/edit'),
        ];
    }
}
