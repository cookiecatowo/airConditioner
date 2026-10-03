<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Order;
use App\Models\ShopSetting;
use App\Services\QuotationWordWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('customer')->orderByDesc('date')->orderByDesc('id');

        // 關鍵字搜尋
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('address', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // 處理狀況篩選
        if ($request->filled('ps')) {
            $ps = array_filter(explode(',', $request->ps), 'is_numeric');
            if (!empty($ps)) $query->whereIn('processing_status', $ps);
        }

        // 收款狀況篩選
        if ($request->filled('pmt')) {
            $pmt = array_filter(explode(',', $request->pmt), 'is_numeric');
            if (!empty($pmt)) $query->whereIn('payment_status', $pmt);
        }

        // 訂單類型篩選
        if ($request->filled('tp')) {
            $tp = array_filter(explode(',', $request->tp));
            if (!empty($tp)) $query->whereIn('type', $tp);
        }

        // 業務分類篩選 (包含 null，用 none 代表)
        if ($request->filled('wc')) {
            $wc = explode(',', $request->wc);
            $query->where(function ($q) use ($wc) {
                if (in_array('none', $wc)) $q->orWhereNull('work_category');
                $actual = array_filter($wc, fn($v) => $v !== 'none');
                if (!empty($actual)) $q->orWhereIn('work_category', $actual);
            });
        }

        return Inertia::render('Orders/Index', [
            'orders'  => $query->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $request->search,
                'ps'     => $request->ps,
                'pmt'    => $request->pmt,
                'tp'     => $request->tp,
                'wc'     => $request->wc,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Orders/Create', [
            'brands'  => Brand::all(),
            'catalog'  => $this->equipmentCatalog(),
            'materialCatalog' => $this->materialCatalog(),
        ]);
    }

    public function show(Order $order)
    {
        $order = $this->loadOrderFullDetails($order);
        $shop = ShopSetting::all()->pluck('value', 'key');

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'shop' => $shop
        ]);
    }

    public function edit(Order $order)
    {
        $order = $this->loadOrderFullDetails($order);

        return Inertia::render('Orders/Edit', [
            'order'   => $order,
            'brands'  => Brand::all(),
            'catalog'  => $this->equipmentCatalog(),
            'materialCatalog' => $this->materialCatalog(),
        ]);
    }

    /** 設備選單用：品牌 → 系列 → 規格 的完整清單 */
    private function equipmentCatalog()
    {
        return Equipment::orderBy('model_name')->orderBy('specs')
            ->get(['id', 'brand_id', 'model_name', 'specs', 'default_cost_price', 'default_sale_price']);
    }

    /** 材料選單用：讓使用者直接挑一筆加入，不必打字 */
    private function materialCatalog()
    {
        return Material::orderBy('name')->orderBy('specs')
            ->get(['id', 'name', 'specs', 'unit', 'default_unit_price']);
    }

    /** 供歸檔服務取得含完整明細的訂單 */
    public function fullDetails(Order $order)
    {
        return $this->loadOrderFullDetails($order);
    }

    private function loadOrderFullDetails(Order $order)
    {
        // 載入基本關聯
        $order->load(['customer', 'photos']);

        // 手動載入設備細項 (包含調整項)
        $equipments = DB::table('order_equipment')
            ->leftJoin('equipments', 'order_equipment.equipment_id', '=', 'equipments.id')
            ->leftJoin('brands', 'equipments.brand_id', '=', 'brands.id')
            ->where('order_equipment.order_id', $order->id)
            ->select(
                'order_equipment.*',
                'equipments.model_name as master_model_name',
                'equipments.specs as master_specs',
                'equipments.brand_id',
                'brands.name as brand_name'
            )
            ->get()
            ->map(function($item) {
                // 模擬 Eloquent Model 結構供前端與後續邏輯使用
                $obj = new \stdClass();
                $obj->id = $item->equipment_id;
                $obj->brand_id = $item->brand_id;
                $obj->model_name = $item->is_adjustment ? $item->custom_model_name : $item->master_model_name;
                $obj->specs = $item->is_adjustment ? '' : $item->master_specs;
                $obj->pivot = (object)[
                    'cost_price' => $item->cost_price,
                    'sale_price' => $item->sale_price,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit ?? '台',
                    'is_adjustment' => $item->is_adjustment,
                    'item_note' => $item->item_note,
                    'custom_model_name' => $item->custom_model_name,
                    'plan_group' => $item->plan_group,
                ];
                $obj->brand = $item->brand_id ? (object)['id' => $item->brand_id, 'name' => $item->brand_name] : null;
                return $obj;
            });

        // 手動載入材料細項 (包含調整項)
        $materials = DB::table('order_material')
            ->leftJoin('materials', 'order_material.material_id', '=', 'materials.id')
            ->where('order_material.order_id', $order->id)
            ->select(
                'order_material.*',
                'materials.name as master_name',
                'materials.specs as master_specs',
                'materials.unit as master_unit'
            )
            ->get()
            ->map(function($item) {
                $obj = new \stdClass();
                $obj->id = $item->material_id;
                $obj->name = $item->is_adjustment ? $item->custom_name : $item->master_name;
                $obj->specs = $item->is_adjustment ? '' : $item->master_specs;
                $obj->unit = $item->is_adjustment ? '' : $item->master_unit;
                $obj->pivot = (object)[
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'is_adjustment' => $item->is_adjustment,
                    'item_note' => $item->item_note,
                    'custom_name' => $item->custom_name,
                ];
                return $obj;
            });

        $order->setRelation('equipments', $equipments);
        $order->setRelation('materials', $materials);

        return $order;
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'address' => 'required|string',
            'date' => 'required|date',
            'type' => 'required|in:install,repair,maintenance',
            'work_category' => 'nullable|in:ac,surveillance,other',
            'equipments' => 'array',
            'materials' => 'array',
        ]);

        return DB::transaction(function () use ($request) {
            $customer = Customer::updateOrCreate(
                ['name' => $request->customer_name],
                ['phone' => $request->customer_phone, 'tax_id' => $request->customer_tax_id]
            );

            $order = Order::create([
                'customer_id' => $customer->id,
                'address' => $request->address,
                'tax_id' => $request->customer_tax_id,
                'public_notes' => $request->public_notes,
                'date' => $request->date,
                'type' => $request->type,
                'work_category' => $request->work_category,
                'report_title' => $request->report_title ?? '估價單',
                'notes' => $request->notes,
                'total_amount' => 0,
            ]);

            $this->syncOrderItems($order, $request);

            return redirect()->route('orders.index');
        });
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'address' => 'required|string',
            'date' => 'required|date',
            'type' => 'required|in:install,repair,maintenance',
            'work_category' => 'nullable|in:ac,surveillance,other',
            'equipments' => 'array',
            'materials' => 'array',
        ]);

        return DB::transaction(function () use ($request, $order) {
            $customer = Customer::updateOrCreate(
                ['name' => $request->customer_name],
                ['phone' => $request->customer_phone, 'tax_id' => $request->customer_tax_id]
            );

            $order->update([
                'customer_id' => $customer->id,
                'address' => $request->address,
                'tax_id' => $request->customer_tax_id,
                'public_notes' => $request->public_notes,
                'date' => $request->date,
                'type' => $request->type,
                'work_category' => $request->work_category,
                'report_title' => $request->report_title ?? '估價單',
                'notes' => $request->notes,
            ]);

            // 清理舊細項
            DB::table('order_equipment')->where('order_id', $order->id)->delete();
            DB::table('order_material')->where('order_id', $order->id)->delete();

            $this->syncOrderItems($order, $request);

            return redirect()->route('orders.index');
        });
    }

    private function syncOrderItems($order, $request)
    {
        $totalAmount = 0;
        // 設備依「方案」分組小計，'' 代表未分組（一律計入）
        $planTotals = [];

        // 設備
        if ($request->type === 'install' && !empty($request->equipments)) {
            foreach ($request->equipments as $item) {
                if (empty($item['model_name']) && empty($item['is_adjustment'])) continue;

                if (!empty($item['is_adjustment'])) {
                    DB::table('order_equipment')->insert([
                        'order_id' => $order->id,
                        'equipment_id' => null,
                        'custom_model_name' => $item['model_name'],
                        'sale_price' => $item['sale_price'] ?? 0,
                        'quantity' => 1,
                        'unit' => $item['unit'] ?? '台',
                        'is_adjustment' => true,
                        'item_note' => $item['item_note'] ?? '',
                        'plan_group' => $item['plan_group'] ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $group = trim($item['plan_group'] ?? '');
                    $planTotals[$group] = ($planTotals[$group] ?? 0) + ($item['sale_price'] ?? 0);
                } else {
                    $specs = !empty($item['specs']) ? $item['specs'] : null;
                    
                    // 處理品牌：如果 brand_id 是字串（新品牌名稱），則建立它
                    $brandId = $item['brand_id'] ?? null;
                    if (!is_numeric($brandId) && !empty($brandId)) {
                        $brand = \App\Models\Brand::firstOrCreate(['name' => $brandId]);
                        $brandId = $brand->id;
                    }

                    $masterEq = Equipment::firstOrCreate(
                        ['model_name' => $item['model_name'], 'specs' => $specs, 'brand_id' => $brandId],
                        ['default_cost_price' => $item['cost_price'] ?? 0, 'default_sale_price' => $item['sale_price'] ?? 0]
                    );
                    DB::table('order_equipment')->insert([
                        'order_id' => $order->id,
                        'equipment_id' => $masterEq->id,
                        'cost_price' => $item['cost_price'] ?? 0,
                        'sale_price' => $item['sale_price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                        'unit' => $item['unit'] ?? '台',
                        'is_adjustment' => false,
                        'item_note' => $item['item_note'] ?? '',
                        'plan_group' => $item['plan_group'] ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $group = trim($item['plan_group'] ?? '');
                    $planTotals[$group] = ($planTotals[$group] ?? 0)
                        + ($item['sale_price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }
        }

        // 材料
        if (!empty($request->materials)) {
            foreach ($request->materials as $item) {
                if (empty($item['name']) && empty($item['is_adjustment'])) continue;

                if (!empty($item['is_adjustment'])) {
                    DB::table('order_material')->insert([
                        'order_id' => $order->id,
                        'material_id' => null,
                        'custom_name' => $item['name'],
                        'unit_price' => $item['unit_price'] ?? 0,
                        'quantity' => 1,
                        'is_adjustment' => true,
                        'item_note' => $item['item_note'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $totalAmount += ($item['unit_price'] ?? 0);
                } else {
                    $specs = !empty($item['specs']) ? $item['specs'] : null;
                    $masterMat = Material::firstOrCreate(
                        ['name' => $item['name'], 'specs' => $specs],
                        ['unit' => $item['unit'] ?? '組', 'default_unit_price' => $item['unit_price'] ?? 0]
                    );
                    DB::table('order_material')->insert([
                        'order_id' => $order->id,
                        'material_id' => $masterMat->id,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                        'is_adjustment' => false,
                        'item_note' => $item['item_note'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $totalAmount += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }
        }

        // $totalAmount 此時只含材料；設備依方案另計
        $named = array_filter($planTotals, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY);
        $totalAmount += $planTotals[''] ?? 0;

        // 兩個以上方案代表客戶還沒選，金額未定
        $undecided = count($named) >= 2;
        $order->update([
            'total_amount'   => $undecided ? 0 : $totalAmount + array_sum($named),
            'plan_undecided' => $undecided,
        ]);
    }

    public function quickEdit(Request $request, Order $order)
    {
        $request->validate([
            'notes'        => 'nullable|string',
            'report_title' => 'nullable|string|max:50',
        ]);

        $order->update($request->only(['notes', 'report_title']));

        return back()->with('success', '已儲存');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'processing_status' => 'sometimes|integer|in:1,2,3',
            'payment_status'    => 'sometimes|integer|in:1,2,3',
        ]);

        // 未結清不能設為已完成
        $newProcessing = $request->input('processing_status', $order->processing_status);
        $newPayment    = $request->input('payment_status',    $order->payment_status);

        if ($newProcessing == 2 && $newPayment != 3) {
            return back()->withErrors(['status' => '需先將收款狀態設為「已結清」才能標記已完成']);
        }

        $order->update([
            'processing_status' => $newProcessing,
            'payment_status'    => $newPayment,
        ]);

        return back();
    }

    public function export(Order $order)
    {
        $order = $this->loadOrderFullDetails($order);

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) { mkdir($tempDir, 0755, true); }
        $tempFile = $tempDir . '/' . uniqid('phpword_') . '.docx';

        app(QuotationWordWriter::class)->write($order, $tempFile);

        return response()->download($tempFile, "{$order->date}-{$order->customer->name}-報價單.docx")
            ->deleteFileAfterSend(true);
    }


    public function searchCustomers(Request $request)
    {
        return Customer::where('name', 'like', "%{$request->q}%")
            ->orWhere('phone', 'like', "%{$request->q}%")
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'phone'     => $c->phone,
                'tax_id'    => $c->tax_id,
                'addresses' => $c->knownAddresses(),
            ]);
    }
    public function searchBrands(Request $request) { return Brand::where('name', 'like', "%{$request->q}%")->limit(10)->get(); }
    public function searchEquipments(Request $request) {
        // 下拉選單需求：選了品牌就算沒打關鍵字也要列出該品牌全部
        if (! $request->brand_id && ! $request->q) return [];

        $query = Equipment::with('brand');
        if ($request->brand_id) $query->where('brand_id', $request->brand_id);
        if ($request->q) {
            foreach (explode(' ', $request->q) as $kw) {
                if (empty($kw)) continue;
                $query->where(function($q) use ($kw) { $q->where('model_name', 'like', "%{$kw}%")->orWhere('specs', 'like', "%{$kw}%"); });
            }
        }
        return $query->orderBy('specs')->limit(200)->get();
    }
    public function searchMaterials(Request $request) {
        $query = Material::query();
        if ($request->q) {
            foreach (explode(' ', $request->q) as $kw) {
                if (empty($kw)) continue;
                $query->where(function($q) use ($kw) { $q->where('name', 'like', "%{$request->q}%")->orWhere('specs', 'like', "%{$request->q}%"); });
            }
        }
        return $query->limit(20)->get();
    }
}
