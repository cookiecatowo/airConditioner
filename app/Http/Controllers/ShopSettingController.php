<?php

namespace App\Http\Controllers;

use App\Models\ShopSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShopSettingController extends Controller
{
    /**
     * 顯示編輯頁面
     */
    public function edit()
    {
        // 讀取所有設定，轉換為 Key-Value 格式
        $settings = ShopSetting::all()->pluck('value', 'key');

        return Inertia::render('ShopSettings/Edit', [
            'settings' => $settings,
            'status' => session('status'),
        ]);
    }

    /**
     * 更新店家資訊
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'shop_name' => 'nullable|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'shop_address' => 'nullable|string|max:255',
            'shop_phone' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $value) {
            ShopSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->back()->with('status', '店家資訊已成功更新！');
    }
}