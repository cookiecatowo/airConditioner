<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Order;
use App\Models\ShopSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('customer')->latest();

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

        return Inertia::render('Orders/Index', [
            'orders' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $request->search
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Orders/Create', [
            'brands' => Brand::all()
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
            'order' => $order,
            'brands' => Brand::all()
        ]);
    }

    private function loadOrderFullDetails(Order $order)
    {
        // 載入基本關聯
        $order->load(['customer']);

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
                    'is_adjustment' => $item->is_adjustment,
                    'item_note' => $item->item_note,
                    'custom_model_name' => $item->custom_model_name,
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
            'type' => 'required|in:install,repair',
            'equipments' => 'array',
            'materials' => 'array',
        ]);

        return DB::transaction(function () use ($request) {
            $customer = Customer::updateOrCreate(
                ['name' => $request->customer_name],
                ['phone' => $request->customer_phone, 'tax_id' => $request->customer_tax_id, 'address' => $request->address]
            );

            $order = Order::create([
                'customer_id' => $customer->id,
                'address' => $request->address,
                'tax_id' => $request->customer_tax_id,
                'public_notes' => $request->public_notes,
                'date' => $request->date,
                'type' => $request->type,
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
            'type' => 'required|in:install,repair',
            'equipments' => 'array',
            'materials' => 'array',
        ]);

        return DB::transaction(function () use ($request, $order) {
            $customer = Customer::updateOrCreate(
                ['name' => $request->customer_name],
                ['phone' => $request->customer_phone, 'tax_id' => $request->customer_tax_id, 'address' => $request->address]
            );

            $order->update([
                'customer_id' => $customer->id,
                'address' => $request->address,
                'tax_id' => $request->customer_tax_id,
                'public_notes' => $request->public_notes,
                'date' => $request->date,
                'type' => $request->type,
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
                        'is_adjustment' => true,
                        'item_note' => $item['item_note'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $totalAmount += ($item['sale_price'] ?? 0);
                } else {
                    $specs = !empty($item['specs']) ? $item['specs'] : null;
                    $masterEq = Equipment::firstOrCreate(
                        ['model_name' => $item['model_name'], 'specs' => $specs],
                        ['brand_id' => $item['brand_id'] ?? null, 'default_cost_price' => $item['cost_price'] ?? 0, 'default_sale_price' => $item['sale_price'] ?? 0]
                    );
                    DB::table('order_equipment')->insert([
                        'order_id' => $order->id,
                        'equipment_id' => $masterEq->id,
                        'cost_price' => $item['cost_price'] ?? 0,
                        'sale_price' => $item['sale_price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                        'is_adjustment' => false,
                        'item_note' => $item['item_note'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $totalAmount += ($item['sale_price'] ?? 0) * ($item['quantity'] ?? 1);
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

        $order->update(['total_amount' => $totalAmount]);
    }

    public function export(Order $order)
    {
        return $this->generateWordReport($this->loadOrderFullDetails($order));
    }

    private function generateWordReport($order)
    {
        $shop = ShopSetting::all()->pluck('value', 'key');
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Microsoft JhengHei');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection(['marginTop' => 1134, 'marginBottom' => 1134, 'marginLeft' => 1134, 'marginRight' => 1134]);
        $section->addText($order->report_title, ['size' => 20, 'bold' => true], ['alignment' => 'center']);
        // 標題下方的雙線 (滿版)
        $styleTableTitle = ['borderBottomSize' => 18, 'borderBottomColor' => '000000', 'borderBottomStyle' => 'double'];
        $titleLineTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $titleLineTable->addRow();
        $titleLineTable->addCell(10000, $styleTableTitle);
        $section->addTextBreak(1);

        $tableInfo = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $tableInfo->addRow();
        
        // 左欄 (60%)
        $leftHeaderCell = $tableInfo->addCell(6000, ['valign' => 'bottom']);
        // 第一行：姓名 (左) 與 台照 (右)
        $innerTable = $leftHeaderCell->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $innerTable->addRow();
        $innerTable->addCell(4000)->addText($order->customer->name, ['size' => 14, 'bold' => true], ['borderBottomSize' => 6]);
        $innerTable->addCell(1000)->addText("台照", ['size' => 11], ['alignment' => 'right']);
        $leftHeaderCell->addTextBreak(1);
        // 第二行：日期
        $leftHeaderCell->addText("建單日期：{$order->date}", ['size' => 11]);

        // 右欄 (40%)
        $rightHeaderCell = $tableInfo->addCell(4000, ['valign' => 'top']);
        $rightHeaderCell->addText("電話：{$order->customer->phone}", ['size' => 10]);
        $rightHeaderCell->addText("地址：{$order->address}", ['size' => 10]);
        $rightHeaderCell->addText("備註：{$order->public_notes}", ['size' => 10]);
        $rightHeaderCell->addText("統編：{$order->tax_id}", ['size' => 10]);
        
        $section->addTextBreak(1);

        $styleTable = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
        $styleHeader = ['bold' => true, 'bgColor' => 'F2F2F2'];
        $phpWord->addTableStyle('MainTable', $styleTable);
        $table = $section->addTable('MainTable');

        $table->addRow();
        foreach (['項目', '品名', '規格', '數量', '單價', '金額', '備註'] as $h) $table->addCell(1000, $styleHeader)->addText($h, null, ['alignment' => 'center']);

        $itemIndex = 1;
        if ($order->type === 'install') {
            $subtotal = 0;
            foreach ($order->equipments as $eq) {
                $table->addRow();
                $table->addCell(800)->addText($itemIndex++, null, ['alignment' => 'center']);
                if ($eq->pivot->is_adjustment) {
                    $table->addCell(3000)->addText($eq->pivot->custom_model_name, null, ['alignment' => 'center']);
                    for ($j=0;$j<3;$j++) $table->addCell(800);
                    $table->addCell(1200)->addText(number_format($eq->pivot->sale_price), ['bold' => true], ['alignment' => 'right']);
                } else {
                    $table->addCell(3000)->addText($eq->model_name, null, ['alignment' => 'center']);
                    $table->addCell(2000)->addText($eq->specs, null, ['alignment' => 'center']);
                    $table->addCell(800)->addText($eq->pivot->quantity . ' 台', null, ['alignment' => 'center']);
                    $table->addCell(1200)->addText(number_format($eq->pivot->sale_price), null, ['alignment' => 'right']);
                    $table->addCell(1200)->addText(number_format($eq->pivot->sale_price * $eq->pivot->quantity), null, ['alignment' => 'right']);
                }
                $table->addCell(2000)->addText($eq->pivot->item_note, null, ['alignment' => 'center']);
                $subtotal += $eq->pivot->sale_price * $eq->pivot->quantity;
            }
            $table->addRow();
            $table->addCell(6600, ['gridSpan' => 4])->addText('小計', ['bold' => true], ['alignment' => 'center']);
            $table->addCell(2400, ['gridSpan' => 2])->addText(number_format($subtotal), ['bold' => true], ['alignment' => 'right']);
            $table->addCell(2000);
        }

        $materials = $order->materials;
        if ($materials->count() > 0) {
            $subtotal = 0;
            foreach ($materials as $mat) {
                $table->addRow();
                $table->addCell(800)->addText($itemIndex++, null, ['alignment' => 'center']);
                if ($mat->pivot->is_adjustment) {
                    $table->addCell(3000)->addText($mat->pivot->custom_name, null, ['alignment' => 'center']);
                    for ($j=0;$j<3;$j++) $table->addCell(800);
                    $table->addCell(1200)->addText(number_format($mat->pivot->unit_price), ['bold' => true], ['alignment' => 'right']);
                } else {
                    $table->addCell(3000)->addText($mat->name, null, ['alignment' => 'center']);
                    $table->addCell(2000)->addText($mat->specs, null, ['alignment' => 'center']);
                    $table->addCell(800)->addText($mat->pivot->quantity . ' ' . $mat->unit, null, ['alignment' => 'center']);
                    $table->addCell(1200)->addText(number_format($mat->pivot->unit_price), null, ['alignment' => 'right']);
                    $table->addCell(1200)->addText(number_format($mat->pivot->unit_price * $mat->pivot->quantity), null, ['alignment' => 'right']);
                }
                $table->addCell(2000)->addText($mat->pivot->item_note, null, ['alignment' => 'center']);
                $subtotal += $mat->pivot->unit_price * $mat->pivot->quantity;
            }
            $table->addRow();
            $table->addCell(6600, ['gridSpan' => 4])->addText('小計', ['bold' => true], ['alignment' => 'center']);
            $table->addCell(2400, ['gridSpan' => 2])->addText(number_format($subtotal), ['bold' => true], ['alignment' => 'right']);
            $table->addCell(2000);
        }

        $table->addRow();
        $table->addCell(7800, ['gridSpan' => 5])->addText('總計', ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        $table->addCell(3200, ['gridSpan' => 2])->addText('$' . number_format($order->total_amount), ['bold' => true, 'size' => 14, 'color' => '0000FF'], ['alignment' => 'right']);

        if ($order->public_notes) { $section->addTextBreak(1); $section->addText("備註：{$order->public_notes}", ['bold' => true]); }
        $section->addTextBreak(1);

        if ($shop->isNotEmpty()) {
            // 稅務與匯款資訊
            $section->addText("1. 本報價單不含5%營業稅", ['size' => 10]);
            $bankInfo = "匯款帳戶: " . ($shop['bank_name'] ?? '') . " " . ($shop['bank_account_name'] ?? '') . " " . ($shop['bank_account'] ?? '');
            $section->addText($bankInfo, ['size' => 10]);
            $section->addTextBreak(1);

            // 簽章與店家資訊 (兩行上下排列，皆靠右對齊)
            $section->addText("客戶簽章：____________________        客戶簽章：____________________", ['size' => 11], ['alignment' => 'right']);
            
            $shopInfo = ($shop['shop_name'] ?? '') . "  TEL:" . ($shop['shop_phone'] ?? '') . "  " . ($shop['owner_name'] ?? '') . "  " . ($shop['shop_address'] ?? '');
            $section->addText($shopInfo, ['size' => 11], ['alignment' => 'right']);
        }

        $filename = "{$order->date}-{$order->customer->name}-報價單.docx";
        $tempFile = tempnam(sys_get_temp_dir(), 'phpword');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);
        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    public function searchCustomers(Request $request) { return Customer::where('name', 'like', "%{$request->q}%")->orWhere('phone', 'like', "%{$request->q}%")->limit(10)->get(); }
    public function searchBrands(Request $request) { return Brand::where('name', 'like', "%{$request->q}%")->limit(10)->get(); }
    public function searchEquipments(Request $request) {
        $query = Equipment::with('brand');
        if ($request->brand_id) $query->where('brand_id', $request->brand_id);
        if ($request->q) {
            foreach (explode(' ', $request->q) as $kw) {
                if (empty($kw)) continue;
                $query->where(function($q) use ($kw) { $q->where('model_name', 'like', "%{$kw}%")->orWhere('specs', 'like', "%{$kw}%"); });
            }
        }
        return $query->limit(20)->get();
    }
    public function searchMaterials(Request $request) {
        $query = Material::query();
        if ($request->q) {
            foreach (explode(' ', $request->q) as $kw) {
                if (empty($kw)) continue;
                $query->where(function($q) use ($kw) { $q->where('name', 'like', "%{$kw}%")->orWhere('specs', 'like', "%{$kw}%"); });
            }
        }
        return $query->limit(20)->get();
    }
}