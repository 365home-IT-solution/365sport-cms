<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 1 dòng = 1 lượt gán 1 Customer làm nhân viên của 1 CorporateAccount — xem ghi chú đầy đủ tại
// migration create_corporate_account_employees_table + App\Models\CorporateAccount. "Gỡ" nhân
// viên chỉ set removed_at (xem remove()), KHÔNG xoá dòng — giữ lịch sử ai từng dùng hạn mức công
// ty nào, đúng tinh thần audit của các thực thể billing khác trong dự án.
class CorporateAccountEmployee extends Model
{
    protected $fillable = [
        'corporate_account_id',
        'customer_id',
        'added_at',
        'removed_at',
    ];

    protected $casts = [
        'added_at'   => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function corporateAccount(): BelongsTo
    {
        return $this->belongsTo(CorporateAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isActive(): bool
    {
        return is_null($this->removed_at);
    }

    public function remove(): void
    {
        if ($this->isActive()) {
            $this->update(['removed_at' => now()]);
        }
    }
}
