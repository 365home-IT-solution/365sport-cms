<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;
use Modules\Dashboard\Http\Controllers\ReportController;

Route::middleware(['auth:sanctum', 'admin.api'])
    ->prefix('admin/dashboard')
    ->group(function () {
        Route::get('/overview', [DashboardController::class, 'overview']);
        Route::get('/kpi-stats', [DashboardController::class, 'kpiStats']);
        Route::get('/rankings', [DashboardController::class, 'rankings']);
        Route::get('/occupancy', [DashboardController::class, 'occupancy']);
    });

// "Tất cả báo cáo" — 7 màn hình báo cáo cho admin/nhân viên (xem ReportController docblock).
Route::middleware(['auth:sanctum', 'admin.api'])
    ->prefix('admin/reports')
    ->group(function () {
        Route::get('/receptionist', [ReportController::class, 'receptionist']);
        Route::get('/end-of-day', [ReportController::class, 'endOfDay']);
        Route::get('/booking', [ReportController::class, 'booking']);
        Route::get('/revenue', [ReportController::class, 'revenue']);
        Route::get('/room', [ReportController::class, 'room']);
        Route::get('/customer', [ReportController::class, 'customer']);
        Route::get('/financial', [ReportController::class, 'financial']);
    });
