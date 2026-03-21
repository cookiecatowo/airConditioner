<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    /**
     * 儲存新材料
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'default_unit_price' => 'nullable|numeric|min:0',
        ]);

        Material::create($validated);

        return redirect()->back()->with('status', '材料已成功新增！');
    }

    /**
     * 更新材料
     */
    public function update(Request $request, Material $material)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'default_unit_price' => 'nullable|numeric|min:0',
        ]);

        $material->update($validated);

        return redirect()->back()->with('status', '材料已更新！');
    }

    /**
     * 刪除材料
     */
    public function destroy(Material $material)
    {
        $material->delete();
        return redirect()->back()->with('status', '材料已刪除！');
    }
}