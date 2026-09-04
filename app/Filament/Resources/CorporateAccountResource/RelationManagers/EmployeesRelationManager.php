<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorporateAccountResource\RelationManagers;

use App\Models\Customer;
use App\Models\CorporateAccount;
use App\Models\CorporateAccountEmployee;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Nhân viên được PHÉP dùng hạn mức của công ty này — mỗi dòng là 1 App\Models\CorporateAccountEmployee
// liên kết tới 1 Customer đã có sẵn trên hệ thống (KHÔNG tạo Customer mới ở đây — nhân viên phải tự
// đăng ký/đã có tài khoản trước, resource này chỉ CHỌN và LIÊN KẾT). "Gỡ" dùng removed_at (soft-remove,
// xem CorporateAccountEmployee::remove()) thay vì xoá bản ghi — giữ lịch sử cho việc tra cứu sau.
class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Nhân viên được dùng hạn mức';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('customer_id')
                ->label('Khách hàng (nhân viên)')
                ->relationship('customer', 'fullname')
                ->getOptionLabelFromRecordUsing(fn (Customer $record) => "{$record->fullname} — {$record->phone}")
                ->searchable(['fullname', 'phone'])
                ->preload()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('customer.fullname')
                    ->label('Tên nhân viên')
                    ->searchable(),

                TextColumn::make('customer.phone')
                    ->label('SĐT')
                    ->searchable(),

                TextColumn::make('added_at')
                    ->label('Ngày thêm')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Đang hoạt động')
                    ->boolean()
                    ->getStateUsing(fn (CorporateAccountEmployee $record) => $record->isActive()),
            ])
            ->defaultSort('added_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Thêm nhân viên')
                    // ->using() tự tay tạo bản ghi (thay vì để Filament tự Model::create($data) mặc
                    // định) — cần chặn TRÙNG (1 nhân viên đang hoạt động không được gán 2 lần vào
                    // cùng 1 công ty) VÀ tự set added_at, không có hook "before" nào of CreateAction
                    // nhận được $data theo tài liệu Filament v3, nên gộp cả 2 việc vào đây cho chắc
                    // (xem PriceBoardResource/Pages/CreatePriceBoard.php dùng cùng cách ném Halt sau
                    // khi bắn Notification lỗi).
                    ->using(function (array $data, string $model) {
                        /** @var CorporateAccount $account */
                        $account = $this->getOwnerRecord();

                        if ($account->hasActiveEmployee($data['customer_id'])) {
                            Notification::make()
                                ->title('Khách hàng này đã là nhân viên đang hoạt động của công ty.')
                                ->danger()
                                ->send();

                            throw new Halt();
                        }

                        return $model::create([
                            'corporate_account_id' => $account->id,
                            'customer_id'           => $data['customer_id'],
                            'added_at'              => now(),
                        ]);
                    }),
            ])
            ->actions([
                Action::make('remove')
                    ->label('Gỡ')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CorporateAccountEmployee $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalDescription('Gỡ nhân viên này khỏi tài khoản doanh nghiệp? Lịch sử đặt sân trước đó vẫn được giữ nguyên.')
                    ->action(fn (CorporateAccountEmployee $record) => $record->remove()),
            ])
            ->bulkActions([]);
    }
}
