<?php

use Illuminate\Support\Facades\Route;
use Modules\Coach\App\Http\Controllers\CoachController;
use Modules\Coach\App\Http\Controllers\AdminCoachController;

Route::middleware('throttle:public-api')->prefix('v1/branches/{branch}/coaches')->group(function () {
    Route::get('/', [CoachController::class, 'index'])->name('api.v1.branches.coaches.index');
    Route::get('{coach}', [CoachController::class, 'show'])->name('api.v1.branches.coaches.show');
});

Route::middleware(['auth:sanctum', 'admin.api'])->prefix('admin/coaches')->group(function () {
    Route::get('/', [AdminCoachController::class, 'index']);
    Route::post('/', [AdminCoachController::class, 'store']);
    Route::get('{coach}', [AdminCoachController::class, 'show']);
    Route::put('{coach}', [AdminCoachController::class, 'update']);
    Route::delete('{coach}', [AdminCoachController::class, 'destroy']);
});
