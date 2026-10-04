<?php

namespace Modules\BladeThemeV1\App\Models;

use App\Models\Concerns\BelongsToPartner;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Warehouse\App\Models\WarehouseItem;


class AdditionService extends Model
{
    use HasFactory, BelongsToPartner, LogsAuditTrail;

    protected $table = 'additional_services';

    protected $fillable = [
        'partner_id',
        'name',
        'price',
        'warehouse_item_id',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'integer',
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(
            \Modules\Product\App\Models\Product::class,
            'room_additional_service_assigns',
            'additional_service_id',
            'room_id'
        );
    }

    // Chỉ gán khi dịch vụ này là CHO THUÊ dụng cụ (vợt, giày, bóng...) — có warehouse_item_id thì
    // mỗi lần khách chọn dịch vụ này khi đặt sân sẽ tự "mượn" từ kho (xem WarehouseRentalService),
    // khác với dịch vụ tính tiền thuần (nước uống...) không gắn vật tư nào.
    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }
}