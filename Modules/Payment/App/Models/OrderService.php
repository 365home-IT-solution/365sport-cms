<?php

namespace Modules\Payment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Payment\Entities\Order;
use Modules\Warehouse\App\Services\WarehouseRentalService;

class OrderService extends Model
{
    use HasFactory;

    protected $table = 'order_services';

    // Dịch vụ bổ sung gắn 1 warehouse_item (thuê dụng cụ, vd vợt/giày) thì mỗi lần tạo dòng
    // order_service tự động "mượn" (tăng quantity_in_use) — xem WarehouseRentalService::rent().
    // Dùng created() (không phải creating()) vì cần $orderService->id đã có để ghi WarehouseItemRental.
    protected static function booted(): void
    {
        static::created(function (self $orderService): void {
            app(WarehouseRentalService::class)->rent($orderService);
        });
    }

    protected $fillable = [
        'order_id',
        'service_id',
        'service_name',
        'price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'price'    => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}