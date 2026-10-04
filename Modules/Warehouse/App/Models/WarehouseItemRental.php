<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payment\App\Models\OrderService;

class WarehouseItemRental extends Model
{
    public const STATUS_RENTED = 'rented';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'order_service_id',
        'warehouse_item_id',
        'quantity',
        'rented_at',
        'returned_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'quantity'    => 'float',
        'rented_at'   => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class);
    }

    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_RENTED;
    }
}
