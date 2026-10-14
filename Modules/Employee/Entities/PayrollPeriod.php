<?php

declare(strict_types=1);

namespace Modules\Employee\Entities;

use App\Models\Concerns\BelongsToPartner;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Kỳ lương theo tháng của một đối tác. Chốt kỳ (closed) tạo ảnh chụp phiếu lương không đổi — xem App\Services\PayrollService::close().
class PayrollPeriod extends Model
{
    use BelongsToPartner, LogsAuditTrail;

    protected $fillable = ['partner_id', 'month', 'status', 'employee_count', 'total_amount', 'closed_at', 'closed_by'];

    protected $casts = [
        'closed_at' => 'datetime',
        'employee_count' => 'integer',
        'total_amount' => 'integer',
    ];

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
