<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerResource\Tables;

use App\Models\Partner;
use App\Services\PartnerOnboardingService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartnerTable
{
    private const STATUS_LABELS = [
        'pending'   => 'Chờ phê duyệt',
        'approved'  => 'Đang hoạt động',
        'suspended' => 'Ngừng hoạt động',
        'rejected'  => 'Từ chối',
    ];

    private const STATUS_COLORS = [
        'pending'   => 'warning',
        'approved'  => 'success',
        'suspended' => 'gray',
        'rejected'  => 'danger',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên đối tác')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('id')
                    ->label('Mã đối tác')
                    ->formatStateUsing(fn (string $state): string => 'PART-' . strtoupper(substr($state, 0, 6)))
                    ->copyable(),

                TextColumn::make('representative_name')
                    ->label('Người liên hệ')
                    ->placeholder('—')
                    ->description(fn (Partner $record) => $record->email, position: 'below'),

                TextColumn::make('verification_status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::STATUS_COLORS[$state] ?? 'gray')
                    ->sortable(),

                TextColumn::make('categories_count')
                    ->label('Số cơ sở')
                    ->counts('categories')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Ngày đăng ký')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('verification_status')
                    ->label('Trạng thái')
                    ->options(self::STATUS_LABELS),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                // Đăng ký mua gói trên website đang chờ duyệt: duyệt = tặng dùng thử + kích hoạt + tạo tài khoản + gửi email.
                Action::make('approveSignup')->label('Duyệt đăng ký')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (Partner $r) => app(PartnerOnboardingService::class)->awaitingSignupApproval($r))
                    ->requiresConfirmation()
                    ->modalDescription('Tặng dùng thử, kích hoạt đối tác, tự tạo tài khoản chủ đối tác và gửi email đăng nhập.')
                    ->action(function (Partner $record) {
                        try {
                            $res = app(PartnerOnboardingService::class)->approveSignup($record, auth()->user());
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            Notification::make()->title('Không duyệt được')->body(collect($e->errors())->flatten()->first())->danger()->send();

                            return;
                        }
                        $n = Notification::make()->title($res['created'] ? 'Đã duyệt và tạo tài khoản' : 'Đã duyệt nhưng chưa tạo được tài khoản')->body($res['reason'] ?? ($res['mail_sent'] ? 'Đã gửi email đăng nhập tới ' . $res['email'] : 'Gửi email thất bại — dùng "Gửi lại tài khoản".'));
                        ($res['created'] && $res['mail_sent'] ? $n->success() : $n->warning())->send();
                    }),
                Action::make('rejectSignup')->label('Từ chối đăng ký')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Partner $r) => app(PartnerOnboardingService::class)->awaitingSignupApproval($r))
                    ->form([Textarea::make('reason')->label('Lý do từ chối')->required()->maxLength(1000)])
                    ->action(function (Partner $record, array $data) {
                        app(PartnerOnboardingService::class)->rejectSignup($record, $data['reason'], auth()->user());
                        Notification::make()->title('Đã từ chối đăng ký')->success()->send();
                    }),
                Action::make('resendCredentials')->label('Gửi lại tài khoản')->icon('heroicon-o-envelope')->color('gray')
                    ->visible(fn (Partner $r) => filled($r->onboarding_token) && $r->status)
                    ->requiresConfirmation()
                    ->modalDescription('Đặt mật khẩu mới cho tài khoản chủ đối tác và gửi email đăng nhập.')
                    ->action(function (Partner $record) {
                        $res = app(PartnerOnboardingService::class)->resendCredentials($record);
                        Notification::make()->title($res['mail_sent'] ? 'Đã gửi lại thông tin đăng nhập' : 'Gửi email thất bại')->body($res['reason'] ?? $res['email'])->{$res['mail_sent'] ? 'success' : 'danger'}()->send();
                    }),
                ViewAction::make()->label('Xem'),
                EditAction::make()->label('Sửa'),
                DeleteAction::make()->label('Xóa'),
            ]);
    }
}
