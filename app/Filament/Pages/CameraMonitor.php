<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Camera;
use Filament\Pages\Page;

// Lưới xem trực tiếp camera an ninh — <video-rtc> (client chính thức go2rtc, public/vendor/go2rtc/
// video-rtc.js) kết nối qua Node camera-proxy (websocket/server.js) để tự gắn cookie Frigate thay
// người xem. CHỈ hiển thị được khi đối tác đã cấu hình go2rtc/Frigate thật (Setting > Cấu hình
// Camera) — nếu chưa, ô camera hiện thông báo "chưa cấu hình" thay vì video trống/lỗi.
class CameraMonitor extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-video-camera';
    protected static ?string $navigationGroup = 'Quản lý';
    protected static ?string $navigationLabel = 'Xem Camera';
    protected static ?string $title           = 'Xem Camera trực tiếp';
    protected static ?int    $navigationSort  = 12;
    protected static string $view = 'filament.pages.camera-monitor';

    public static function canAccess(): bool
    {
        return auth()->user()?->partnerHasFeature('camera') ?? false;
    }

    public function getCameras()
    {
        $query = Camera::query()->where('status', true)->with('branch:id,name');

        $user = auth()->user();
        if ($user && ! $user->isSuperAdmin()) {
            $query->whereIn('branch_id', $user->rootProductCategoryIds() ?? []);
        }

        return $query->orderBy('name')->get()->map(fn (Camera $camera) => [
            'id'         => $camera->id,
            'name'       => $camera->name,
            'branch'     => $camera->branch?->name,
            'ws_url'     => $camera->wsProxyUrl(),
            'configured' => $camera->wsProxyUrl() !== null,
        ]);
    }
}
