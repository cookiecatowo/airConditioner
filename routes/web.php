<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\BrandEquipmentController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopSettingController;
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

    // 報價單管理
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // 動態搜尋 API (回傳 JSON)
    Route::get('/api/search/customers', [OrderController::class, 'searchCustomers']);
    Route::get('/api/search/brands', [OrderController::class, 'searchBrands']);
    Route::get('/api/search/equipments', [OrderController::class, 'searchEquipments']);
    Route::get('/api/search/materials', [OrderController::class, 'searchMaterials']);
});

require __DIR__.'/auth.php';
