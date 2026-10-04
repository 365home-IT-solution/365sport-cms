<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Cấu hình server go2rtc/Frigate theo TỪNG ĐỐI TÁC (1-1 partner_id) — go2rtc/Frigate là phần mềm
// NGOÀI dự án, đối tác tự dựng/vận hành, chỗ này chỉ lưu thông tin để CLIENT kết nối tới đó.
class CameraSetting extends Model
{
    protected $primaryKey = 'partner_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'partner_id',
        'base_url',
        'api_key',
        'username',
        'password',
    ];

    protected $casts = [
        'api_key'  => 'encrypted',
        'username' => 'encrypted',
        'password' => 'encrypted',
    ];

    // get-or-new — KHÔNG throw khi đối tác chưa từng cấu hình, để isConfigured()/
    // hasFrigateCredentials() trả false an toàn thay vì lỗi.
    public static function forPartner(?string $partnerId): self
    {
        if (! $partnerId) {
            return new self();
        }

        return static::find($partnerId) ?? new self(['partner_id' => $partnerId]);
    }

    public function isConfigured(): bool
    {
        return filled($this->base_url);
    }

    public function hasFrigateCredentials(): bool
    {
        return filled($this->username) && filled($this->password);
    }
}
