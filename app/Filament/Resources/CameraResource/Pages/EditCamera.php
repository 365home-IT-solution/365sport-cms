<?php

declare(strict_types=1);

namespace App\Filament\Resources\CameraResource\Pages;

use App\Filament\Resources\CameraResource;
use App\Models\Camera;
use App\Services\Go2RtcClient;
use Filament\Resources\Pages\EditRecord;

class EditCamera extends EditRecord
{
    protected static string $resource = CameraResource::class;

    private ?string $originalStreamKey = null;

    protected function beforeSave(): void
    {
        /** @var Camera $camera */
        $camera = $this->record;
        $this->originalStreamKey = $camera->getOriginal('stream_key');
    }

    protected function afterSave(): void
    {
        /** @var Camera $camera */
        $camera = $this->record;

        // Đổi stream_key thì xoá nguồn cũ trên go2rtc trước khi khai báo nguồn mới — tránh rác
        // nguồn cũ không ai dùng nữa còn tồn tại trên server go2rtc.
        if ($this->originalStreamKey && $this->originalStreamKey !== $camera->stream_key) {
            app(Go2RtcClient::class)->deleteStream($this->originalStreamKey, $camera->partner_id);
        }

        CameraResource::syncGo2Rtc($camera);
    }
}
