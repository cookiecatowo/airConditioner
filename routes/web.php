<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopSettingController;
use App\Http\Controllers\BrandEquipmentController;
use App\Http\Controllers\MaterialController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 店家資訊管理
    Route::get('/shop-settings', [ShopSettingController::class, 'edit'])->name('shop-settings.edit');
    Route::patch('/shop-settings', [ShopSettingController::class, 'update'])->name('shop-settings.update');

    // 品牌與設備管理
    Route::resource('brands', BrandEquipmentController::class);
    Route::post('/equipments', [BrandEquipmentController::class, 'storeEquipment'])->name('equipments.store');
    Route::patch('/equipments/{equipment}', [BrandEquipmentController::class, 'updateEquipment'])->name('equipments.update');
    Route::delete('/equipments/{equipment}', [BrandEquipmentController::class, 'destroyEquipment'])->name('equipments.destroy');

    // 材料管理
    Route::resource('materials', MaterialController::class);
});

require __DIR__.'/auth.php';
