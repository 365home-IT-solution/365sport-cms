<?php

use App\Http\Controllers\Api\Admin\GenericCrudController;
use App\Support\AdminCrud\CrudRegistry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API — CRUD sinh từ App\Support\AdminCrud\CrudRegistry
|--------------------------------------------------------------------------
| Mỗi nhóm trong registry mở các route sau (chỉ những thao tác khai báo trong 'ops'):
|   GET    /api/admin/{nhóm}            danh sách phân trang (?search=, ?per_page=, ?sort=&dir=, các bộ lọc riêng)
|   GET    /api/admin/{nhóm}/{id}       xem một bản ghi
|   POST   /api/admin/{nhóm}            tạo mới
|   PUT|PATCH|POST /api/admin/{nhóm}/{id}   cập nhật (POST dùng khi gửi kèm file multipart)
|   DELETE /api/admin/{nhóm}/{id}       xoá
| Quyền do middleware admin.api quyết định theo CrudRegistry::permission(); permission = null nghĩa là chỉ super_admin.
*/
foreach (array_keys(CrudRegistry::all()) as $slug) {
    // Khai báo lưu trú (CCCD) là nghiệp vụ homestay — không mở API khi cờ features.lodging tắt.
    if ($slug === 'cccd-declarations' && ! config('features.lodging')) {
        continue;
    }

    $ops = CrudRegistry::ops($slug);

    Route::middleware(['auth:sanctum', 'admin.api'])->prefix("admin/{$slug}")->group(function () use ($slug, $ops) {
        $c = GenericCrudController::class;

        if (in_array('index', $ops, true)) {
            Route::get('/', [$c, 'index'])->defaults('resource', $slug)->name("api.admin.crud.{$slug}.index");
        }
        if (in_array('store', $ops, true)) {
            Route::post('/', [$c, 'store'])->defaults('resource', $slug)->name("api.admin.crud.{$slug}.store");
        }
        if (in_array('show', $ops, true)) {
            Route::get('{id}', [$c, 'show'])->defaults('resource', $slug)->name("api.admin.crud.{$slug}.show");
        }
        if (in_array('update', $ops, true)) {
            Route::match(['PUT', 'PATCH', 'POST'], '{id}', [$c, 'update'])->defaults('resource', $slug)->name("api.admin.crud.{$slug}.update");
        }
        if (in_array('destroy', $ops, true)) {
            Route::delete('{id}', [$c, 'destroy'])->defaults('resource', $slug)->name("api.admin.crud.{$slug}.destroy");
        }
    });
}
