<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;

// Nhóm (đội) của khách hàng — xem ghi chú đầy đủ ở migration create_teams_tables. Trưởng nhóm ngầm định qua
// leader_customer_id (KHÔNG có dòng trong team_members), giống BookingFund. Vào nhóm bằng mã nhóm (xin tham
// gia -> trưởng nhóm duyệt) hoặc được trưởng nhóm mời theo SĐT (người được mời đồng ý).
class Team extends Model
{
    use LogsAuditTrail;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_DISBANDED = 'disbanded';

    protected $fillable = [
        'leader_customer_id',
        'code',
        'name',
        'description',
        'status',
        'disbanded_at',
    ];

    protected $casts = [
        'disbanded_at' => 'datetime',
    ];

    // Cùng lý do với BookingFund::$attributes: Eloquent không tự đọc lại DEFAULT của DB sau create().
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $team): void {
            if (empty($team->code)) {
                // Mã 8 ký tự, bỏ ký tự dễ nhầm (I, O, 0, 1) — cùng bộ ký tự với BookingFund::invite_code.
                do {
                    $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
                } while (self::where('code', $code)->exists());
                $team->code = $code;
            }
        });
    }

    public function leader()
    {
        return $this->belongsTo(Customer::class, 'leader_customer_id');
    }

    public function members()
    {
        return $this->hasMany(TeamMember::class);
    }

    public function approvedMembers()
    {
        return $this->members()->where('status', TeamMember::STATUS_APPROVED);
    }

    public function funds()
    {
        return $this->hasMany(BookingFund::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isLeader(?string $customerId): bool
    {
        return $customerId !== null && $this->leader_customer_id === $customerId;
    }

    // Trưởng nhóm hoặc thành viên đã được duyệt.
    public function hasMember(?string $customerId): bool
    {
        if ($customerId === null) {
            return false;
        }

        return $this->isLeader($customerId)
            || $this->members()->where('customer_id', $customerId)->where('status', TeamMember::STATUS_APPROVED)->exists();
    }

    // Số thành viên đang ở trong nhóm, tính cả trưởng nhóm.
    public function memberCount(): int
    {
        return $this->approvedMembers()->count() + 1;
    }
}
