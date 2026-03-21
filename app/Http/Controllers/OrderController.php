<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * 報價單列表
     */
    public function index(Request $request)
    {
        $query = Order::with('customer')->orderBy('date', 'desc')->orderBy('id', 'desc');

        if ($request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->whereHas('customer', function($cq) use ($s) {
                    $cq->where('name', 'like', "%{$s}%")
                       ->orWhere('phone', 'like', "%{$s}%");
                })->orWhere('address', 'like', "%{$s}%");
            });
        }

        return Inertia::render('Orders/Index', [
            'orders' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * 顯示新增報價單頁面
     */
    public function create()
    {
        return Inertia::render('Orders/Create', [
            'brands' => Brand::all(), // 用於選擇品牌
        ]);
    }

    /**
     * 儲存報價單 (核心邏輯)
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'customer_phone' => 'nullable|string',
            'customer_tax_id' => 'nullable|string',
            'address' => 'required|string',
            'public_notes' => 'nullable|string',
            'date' => 'required|date',
            'type' => 'required|in:install,repair',
            'equipments' => 'array',
            'materials' => 'array',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            // 1. 處理顧客 (不存在則新增，若已存在則更新電話/統編)
            $customer = Customer::updateOrCreate(
                ['name' => $request->customer_name],
                [
                    'phone' => $request->customer_phone,
                    'tax_id' => $request->customer_tax_id,
                    'address' => $request->address,
                    'created_date' => $request->date
                ]
            );

            // 2. 建立訂單主檔 (Snapshot 當時的地址、統編、備註)
            $order = Order::create([
                'customer_id' => $customer->id,
                'address' => $request->address,
                'tax_id' => $request->customer_tax_id,
                'public_notes' => $request->public_notes,
                'date' => $request->date,
                'type' => $request->type,
                'notes' => $request->notes,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            // 3. 處理設備 (僅限安裝)
            if ($request->type === 'install' && !empty($request->equipments)) {
                foreach ($request->equipments as $item) {
                    // 如果是手動調整項 (is_adjustment)，直接記錄到 pivot
                    if (!empty($item['is_adjustment'])) {
                        $order->equipments()->attach(null, [
                            'custom_name' => $item['model_name'],
                            'sale_price' => $item['sale_price'],
                            'quantity' => $item['quantity'] ?? 1,
                        ]);
                        $totalAmount += $item['sale_price'] * ($item['quantity'] ?? 1);
                        continue;
                    }

                    // 原有的設備處理邏輯...
                    $equipmentId = $item['id'] ?? null;
                    if (!$equipmentId && !empty($item['model_name'])) {
                        $brandId = $item['brand_id'] ?? null;
                        if (!$brandId) {
                            $brand = Brand::firstOrCreate(['name' => '其他']);
                            $brandId = $brand->id;
                        }
                        $newEquip = Equipment::firstOrCreate([
                            'brand_id' => $brandId,
                            'model_name' => $item['model_name'],
                            'specs' => $item['specs'] ?? '',
                        ], [
                            'default_sale_price' => $item['sale_price'],
                            'default_cost_price' => $item['cost_price'] ?? 0,
                        ]);
                        $equipmentId = $newEquip->id;
                    }

                    if ($equipmentId) {
                        $order->equipments()->attach($equipmentId, [
                            'cost_price' => $item['cost_price'] ?? 0,
                            'sale_price' => $item['sale_price'],
                            'quantity' => $item['quantity'],
                        ]);
                        $totalAmount += $item['sale_price'] * $item['quantity'];
                    }
                }
            }

            // 4. 處理材料
            if (!empty($request->materials)) {
                foreach ($request->materials as $item) {
                    // 如果是手動調整項 (is_adjustment)
                    if (!empty($item['is_adjustment'])) {
                        $order->materials()->attach(null, [
                            'custom_name' => $item['name'],
                            'unit_price' => $item['unit_price'],
                            'quantity' => $item['quantity'] ?? 1,
                        ]);
                        $totalAmount += $item['unit_price'] * ($item['quantity'] ?? 1);
                        continue;
                    }

                    $materialId = $item['id'] ?? null;
                    if (!$materialId && !empty($item['name'])) {
                        $newMat = Material::firstOrCreate([
                            'name' => $item['name'],
                            'specs' => $item['specs'] ?? '',
                        ], [
                            'default_unit_price' => $item['unit_price'],
                            'unit' => $item['unit'] ?? '個'
                        ]);
                        $materialId = $newMat->id;
                    }

                    if ($materialId) {
                        $order->materials()->attach($materialId, [
                            'unit_price' => $item['unit_price'],
                            'quantity' => $item['quantity'],
                        ]);
                        $totalAmount += $item['unit_price'] * $item['quantity'];
                    }
                }
            }

            // 更新總金額
            $order->update(['total_amount' => $totalAmount]);

            return redirect()->route('orders.index')->with('status', '報價單已成功建立！');
        });
    }

    /**
     * 搜尋 API
     */
    public function searchCustomers(Request $request)
    {
        return Customer::where('name', 'like', "%{$request->q}%")
            ->orWhere('phone', 'like', "%{$request->q}%")
            ->limit(10)->get();
    }

    public function searchBrands(Request $request)
    {
        return Brand::where('name', 'like', "%{$request->q}%")
            ->limit(10)->get();
    }

    public function searchEquipments(Request $request)
    {
        $query = Equipment::with('brand');
        
        if ($request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }

        return $query->where(function($q) use ($request) {
                $q->where('model_name', 'like', "%{$request->q}%")
                  ->orWhere('specs', 'like', "%{$request->q}%");
            })
            ->limit(20)->get();
    }

    public function searchMaterials(Request $request)
    {
        return Material::where('name', 'like', "%{$request->q}%")
            ->orWhere('specs', 'like', "%{$request->q}%")
            ->limit(20)->get();
    }
}