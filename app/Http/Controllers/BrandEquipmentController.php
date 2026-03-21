<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Equipment;
use App\Models\Material;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandEquipmentController extends Controller
{
    /**
     * 顯示所有設備與材料的整合管理頁面
     */
    public function index()
    {
        return Inertia::render('Settings/EquipMaterials', [
            'brands' => Brand::with('equipments')->orderBy('id', 'desc')->get(),
            'materials' => Material::orderBy('name', 'asc')->orderBy('specs', 'asc')->get(),
        ]);
    }

    /**
     * 儲存品牌
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        Brand::create($validated);

        return redirect()->back()->with('status', '品牌已成功新增！');
    }

    /**
     * 更新品牌
     */
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $brand->update($validated);

        return redirect()->back()->with('status', '品牌已更新！');
    }

    /**
     * 刪除品牌
     */
    public function destroy(Brand $brand)
    {
        $brand->delete();
        return redirect()->back()->with('status', '品牌已刪除！');
    }

    /**
     * 儲存設備 (型號)
     */
    public function storeEquipment(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'model_name' => 'required|string|max:255',
            'specs' => 'nullable|string|max:255',
            'default_cost_price' => 'nullable|numeric|min:0',
            'default_sale_price' => 'nullable|numeric|min:0',
        ]);

        Equipment::create($validated);

        return redirect()->back()->with('status', '設備已成功新增！');
    }

    /**
     * 更新設備
     */
    public function updateEquipment(Request $request, Equipment $equipment)
    {
        $validated = $request->validate([
            'model_name' => 'required|string|max:255',
            'specs' => 'nullable|string|max:255',
            'default_cost_price' => 'nullable|numeric|min:0',
            'default_sale_price' => 'nullable|numeric|min:0',
        ]);

        $equipment->update($validated);

        return redirect()->back()->with('status', '設備已更新！');
    }

    /**
     * 刪除設備
     */
    public function destroyEquipment(Equipment $equipment)
    {
        $equipment->delete();
        return redirect()->back()->with('status', '設備已刪除！');
    }
}