<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Bảng đối soát hoa hồng theo tháng giữa nền tảng và một đối tác — xem migration create_partner_settlements_table và
// App\Services\PartnerSettlementService.
class PartnerSettlement extends Model
{
    use LogsAuditTrail;

    protected $fillable = [
        'partner_id', 'month', 'order_count', 'gross_revenue', 'refunds', 'net_revenue', 'commission_rate', 'commission_amount',
        'adjustment_amount', 'adjustment_note', 'amount_due', 'status', 'paid_at', 'payment_method', 'payment_reference', 'created_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'commission_rate' => 'float',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
