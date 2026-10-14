<?php

// Quy tắc điểm thưởng. Chỉnh bằng .env, không cần sửa code.
return [
    // Cứ chi bao nhiêu đồng (đơn đã thanh toán) thì được 1 điểm.
    'earn_vnd_per_point' => (int) env('LOYALTY_EARN_VND_PER_POINT', 10000),
    // 1 điểm đổi được bao nhiêu đồng giảm giá.
    'point_value_vnd' => (int) env('LOYALTY_POINT_VALUE_VND', 100),
    // Số điểm tối thiểu mỗi lần đổi.
    'min_redeem_points' => (int) env('LOYALTY_MIN_REDEEM_POINTS', 100),
    // Mã giảm giá sinh ra khi đổi điểm có hạn bao nhiêu ngày.
    'coupon_days' => (int) env('LOYALTY_COUPON_DAYS', 30),
];
