<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Mail\LockNotificationMail;
use App\Models\Partner;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

// Thông báo dùng chung cho luồng gói/đăng ký đối tác: chuông trong trang quản trị (Filament database notification) và email.
// Mọi lỗi gửi đều chỉ ghi log, không được làm hỏng luồng nghiệp vụ chính (thanh toán, duyệt đăng ký...).
trait NotifiesUsers
{
    private function notifyUsers($users, string $title, string $body, string $color = 'info', string $icon = 'heroicon-o-bell'): void
    {
        try {
            $notification = Notification::make()->title($title)->body($body)->icon($icon);
            match ($color) {
                'success' => $notification->success(),
                'danger'  => $notification->danger(),
                'warning' => $notification->warning(),
                default   => $notification->info(),
            };

            foreach ($users as $user) {
                $notification->sendToDatabase($user);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifySuperAdmins(string $title, string $body, string $color = 'info', string $icon = 'heroicon-o-credit-card'): void
    {
        $this->notifyUsers(User::role(config('filament-shield.super_admin.name', 'super_admin'))->get(), $title, $body, $color, $icon);
    }

    private function notifyPartnerUsers(?Partner $partner, string $title, string $body, string $color = 'info'): void
    {
        if ($partner) {
            $this->notifyUsers(User::query()->where('partner_id', $partner->id)->get(), $title, $body, $color, 'heroicon-o-credit-card');
        }
    }

    /** Gửi email HTML cho đối tác đăng ký; trả về true nếu gửi được. */
    private function mailPartner(Partner $partner, string $subject, string $html): bool
    {
        if (blank($partner->email)) {
            return false;
        }

        try {
            Mail::to($partner->email)->send(new LockNotificationMail($subject, $html));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function partnerName(?Partner $partner): string
    {
        return $partner?->legal_name ?: ($partner?->name ?? 'Đối tác');
    }
}
