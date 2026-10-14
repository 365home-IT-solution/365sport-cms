<?php

use App\Http\Controllers\Api\Admin\AdminActionsController as C;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API — hành động trên màn quản trị (huỷ đơn, thu tiền, xuất Excel, gán...)
|--------------------------------------------------------------------------
| File này được nạp TRƯỚC routes/api_admin.php và CRUD để các đường dẫn cố định (vd orders/export) không bị route {id} / {order_code} nuốt.
| Quyền do middleware admin.api quyết định theo nhóm đường dẫn (POST kiểm tra quyền "tạo", nếu không có thì quyền "sửa").
*/
Route::middleware(['auth:sanctum', 'admin.api'])->prefix('admin')->name('api.admin.actions.')->group(function () {
    // Đơn hàng
    Route::get('orders/export',                            [C::class, 'exportOrders'])->name('orders.export');
    Route::post('orders/{order_code}/cancel',              [C::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('orders/{order_code}/collect-extra-cash',  [C::class, 'collectExtraCash'])->name('orders.collect-extra-cash');

    // Thiết bị nhận thông báo đẩy của quản trị viên (tương đương POST /admin/fcm-token trên web)
    Route::post('device-token',                            [C::class, 'saveDeviceToken'])->name('device-token.store');
    Route::delete('device-token',                          [C::class, 'deleteDeviceToken'])->name('device-token.destroy');

    // Đối tác (chỉ super admin)
    Route::post('partners/{id}/status',                    [C::class, 'partnerStatus'])->name('partners.status');
    Route::get('partners/{id}/contract-versions',          [C::class, 'contractVersions'])->name('partners.contract-versions');
    Route::post('partners/{id}/contract-versions',         [C::class, 'addContractVersion'])->name('partners.contract-versions.store');
    Route::post('partners/{id}/contract/renew',            [C::class, 'renewContract'])->name('partners.contract.renew');
    Route::post('partners/{id}/contract/terminate',        [C::class, 'terminateContract'])->name('partners.contract.terminate');

    // Khách doanh nghiệp, ZNS
    Route::post('corporate-accounts/{id}/close-cycle',     [C::class, 'closeCorporateCycle'])->whereNumber('id')->name('corporate.close-cycle');
    Route::post('zns-notifications/{id}/retry',            [C::class, 'retryZns'])->whereNumber('id')->name('zns.retry');

    // Kho
    Route::post('warehouse/stock-checks/{id}/confirm-handover', [C::class, 'confirmHandover'])->whereNumber('id')->name('warehouse.stock-checks.confirm-handover');
    Route::get('warehouse/{kind}/export',                  [C::class, 'exportWarehouse'])->whereIn('kind', ['items', 'stock-ins', 'stock-outs', 'stock-checks'])->name('warehouse.export');

    // Gán dữ liệu
    Route::post('customers/assign-coupons',                [C::class, 'assignCoupons'])->name('customers.assign-coupons');
    Route::post('customers/{id}/assign-tier',              [C::class, 'assignTier'])->name('customers.assign-tier');
    Route::put('room-types/{id}/products',                 [C::class, 'assignRoomTypeProducts'])->whereNumber('id')->name('room-types.products');
    Route::post('coupons/bulk-status',                     [C::class, 'couponsBulkStatus'])->name('coupons.bulk-status');
});
