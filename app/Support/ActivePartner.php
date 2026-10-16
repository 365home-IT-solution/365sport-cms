<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Partner;
use App\Models\User;

// "Đối tác đang chọn" của super_admin: API nhận qua header X-Partner-Id. Chỉ super_admin mới có tác
// dụng — mọi tài khoản khác luôn bị khoá theo đối tác của chính mình. Sau khi áp dụng,
// $user->partner_id trả về đối tác đang chọn (xem User::getPartnerIdAttribute) nên toàn bộ logic lọc
// theo đối tác tự chạy đúng phạm vi.
class ActivePartner
{
    public const HEADER = 'X-Partner-Id';

    // Trả về false khi super_admin gửi id không tồn tại (đối tác đã xoá) — caller trả lỗi.
    public static function apply(User $user, ?string $partnerId): bool
    {
        if (! $user->isSuperAdmin() || blank($partnerId)) {
            return true;
        }

        if (! Partner::query()->whereKey($partnerId)->exists()) {
            return false;
        }

        $user->actAsPartner($partnerId);

        return true;
    }
}
