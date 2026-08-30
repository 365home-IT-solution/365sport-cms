<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectionsConfiguration extends Model
{
    protected $fillable = [
        'enabled',
        'ors_api_key',
        'quota_limit',
        'quota_remaining',
        'quota_reset_at',
        'quota_checked_at',
    ];

    protected $casts = [
        'enabled'          => 'boolean',
        // Không lưu API key dạng plain text — cùng quy ước với GoogleOauthConfiguration::client_secret.
        'ors_api_key'       => 'encrypted',
        'quota_limit'       => 'integer',
        'quota_remaining'   => 'integer',
        'quota_reset_at'    => 'datetime',
        'quota_checked_at'  => 'datetime',
    ];
}
