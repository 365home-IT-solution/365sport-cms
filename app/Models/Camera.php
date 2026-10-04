<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToPartner;
use App\Services\Go2RtcClient;
use App\Support\CameraWsToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Category\Entities\Category;

// Camera an ninh — giám sát trộm/khu vực sân bãi (KHÁC hẳn camera phòng khách thuê ở home-cms).
// Gắn theo branch_id (chi nhánh), go2rtc/Frigate là phần mềm NGOÀI dự án, model này chỉ là client.
class Camera extends Model
{
    use BelongsToPartner, BelongsToBranch;

    protected $fillable = [
        'partner_id',
        'branch_id',
        'name',
        'stream_key',
        'frigate_camera_name',
        'rtsp_url',
        'note',
        'status',
        'alert_enabled',
        'alert_start_time',
        'alert_end_time',
        'alert_last_checked_at',
    ];

    protected $casts = [
        'status'                => 'boolean',
        'alert_enabled'         => 'boolean',
        'rtsp_url'              => 'encrypted',
        'alert_last_checked_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Xoá camera thì dọn luôn nguồn trên go2rtc NẾU camera có rtsp_url (tự khai báo nguồn) —
        // camera chỉ tham chiếu nguồn có sẵn trong Frigate (rtsp_url trống) thì không đụng go2rtc.
        static::deleted(function (self $camera) {
            if ($camera->rtsp_url) {
                app(Go2RtcClient::class)->deleteStream($camera->stream_key, $camera->partner_id);
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'branch_id');
    }

    // Global scope 'partner'/'branch' chỉ chạy trong admin panel Filament (xem BelongsToPartner) —
    // API admin phải tự lọc bằng scope này, nếu không đối tác A thao tác được camera của đối tác B.
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query
            ->where($this->getTable() . '.partner_id', $user->partner_id)
            ->whereIn($this->getTable() . '.branch_id', $user->rootProductCategoryIds());
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(CameraAlert::class);
    }

    public function frigateCameraName(): string
    {
        return $this->frigate_camera_name ?: $this->stream_key;
    }

    // URL WebSocket (qua Node proxy nội bộ) kèm token ký HMAC ngắn hạn — dùng cho <video-rtc> ở
    // trang CameraMonitor. TTL dài (12 giờ) vì client chỉ lấy URL 1 lần lúc load trang, tự
    // reconnect y nguyên URL cũ khi rớt mạng.
    public function wsProxyUrl(): ?string
    {
        $setting = CameraSetting::forPartner($this->partner_id);
        if (! $setting->isConfigured()) {
            return null;
        }

        $token = CameraWsToken::issue($this->stream_key, $setting->base_url, $this->partner_id, ttlSeconds: 43200);

        return rtrim((string) config('services.websocket.public_url'), '/') . '/camera-proxy?token=' . $token;
    }
}
