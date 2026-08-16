<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;

// Nhật ký 1 lượt trừ buổi (App\Models\CustomerPackage::redeem()) — xem ghi chú đầy đủ tại
// migration create_package_redemptions_table.
class PackageRedemption extends Model
{
    protected $fillable = [
        'customer_package_id',
        'order_item_id',
        'order_id',
        'redeemed_at',
        'refunded_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function customerPackage()
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
