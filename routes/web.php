<?php

use App\Http\Controllers\QuotationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\BrandEquipmentController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopSettingController;
use App\Http\Controllers\ImportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 1. 自動跳轉 (進門直接看儀表板)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// 2. 儀表板 (手機連進來的主畫面)
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// 3. 登入後權限區 (只有龍哥能進來)
Route::middleware('auth')->group(function () {
    
    // 個人帳號與店家管理
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/shop-settings', [ShopSettingController::class, 'edit'])->name('shop-settings.edit');
    Route::patch('/shop-settings', [ShopSettingController::class, 'update'])->name('shop-settings.update');

    // ★ 報價單管理核心 (正式版) ★
    Route::resource('orders', OrderController::class);
    Route::get('/orders/{order}/export', [OrderController::class, 'export'])->name('orders.export');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');

    // ★ 今天要做的：手機報價單快速產生器 ★
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');

    // 品牌/設備/材料管理 (Excel 匯入與手動調整)
    Route::get('/import-daikin', [ImportController::class, 'index'])->name('import.daikin.index');
    Route::post('/import-daikin', [ImportController::class, 'import'])->name('import.daikin.store');
    Route::resource('brands', BrandEquipmentController::class);
    Route::resource('materials', MaterialController::class);

    // API 搜尋功能 (供手機介面動態抓資料)
    Route::get('/api/search/customers', [OrderController::class, 'searchCustomers']);
    Route::get('/api/search/brands', [OrderController::class, 'searchBrands']);
    Route::get('/api/search/equipments', [OrderController::class, 'searchEquipments']);
    Route::get('/api/search/materials', [OrderController::class, 'searchMaterials']);
});

require __DIR__.'/auth.php';