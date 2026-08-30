<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleOauthConfiguration extends Model
{
    protected $fillable = [
        'client_id',
        'client_secret',
        'enabled',
    ];

    protected $casts = [
        // Không bao giờ lưu Client Secret dạng plain text trong DB — cùng quy ước với
        // Partner::payos_api_key/payos_checksum_key (xem app/Models/Partner.php).
        'client_secret' => 'encrypted',
        'enabled'       => 'boolean',
    ];
}
