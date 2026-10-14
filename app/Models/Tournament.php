<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToPartner;
use Illuminate\Database\Eloquent\Model;

// Giải đấu của đối tác. Đội đăng ký qua tournament_teams (cần duyệt), trận đấu ở tournament_matches.
class Tournament extends Model
{
    use BelongsToPartner;

    public const STATUSES = ['draft' => 'Nháp', 'open' => 'Đang mở đăng ký', 'ongoing' => 'Đang diễn ra', 'finished' => 'Đã kết thúc', 'cancelled' => 'Đã huỷ'];

    protected $fillable = [
        'partner_id', 'title', 'description', 'location', 'banner', 'start_date', 'end_date', 'registration_deadline',
        'max_teams', 'entry_fee', 'prize_info', 'status',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'registration_deadline' => 'date',
        'max_teams' => 'integer', 'entry_fee' => 'integer',
    ];

    public function registrations()
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function approvedCount(): int
    {
        return $this->registrations()->where('status', 'approved')->count();
    }

    // Khách còn đăng ký được không: giải đang mở, chưa quá hạn đăng ký (hết ngày hạn) và còn chỗ (tính cả đội chờ duyệt để tránh nhận quá số đội).
    public function acceptsRegistration(): bool
    {
        return $this->status === 'open'
            && ! $this->registration_deadline->copy()->endOfDay()->isPast()
            && $this->registrations()->whereIn('status', ['pending', 'approved'])->count() < $this->max_teams;
    }
}
