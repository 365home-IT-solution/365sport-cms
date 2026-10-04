<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Một khách trong quan hệ với 1 nhóm: xin tham gia (pending), được mời (invited), đã là thành viên (approved)
// hoặc đã bị từ chối (rejected/declined). Trưởng nhóm KHÔNG có dòng ở đây — xem Team.
class TeamMember extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_INVITED  = 'invited';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'team_id',
        'customer_id',
        'status',
        'invited_by_customer_id',
        'requested_at',
        'decided_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at'   => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function inviter()
    {
        return $this->belongsTo(Customer::class, 'invited_by_customer_id');
    }
}
