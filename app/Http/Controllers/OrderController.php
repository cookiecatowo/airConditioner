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
        
        // 設定預設字體為「新細明體」(PMingLiU)
        $phpWord->setDefaultFontName('PMingLiU');
        $phpWord->setDefaultFontSize(4);

        // 設定頁面邊距
        $section = $phpWord->addSection([
            'marginTop' => 850, 
            'marginBottom' => 850, 
            'marginLeft' => 850, 
            'marginRight' => 850
        ]);

        // 1. 報單標題 + 全寬雙底線 (增加字距 spacing)
        $section->addText($order->report_title, ['size' => 26, 'bold' => true, 'spacing' => 480], ['alignment' => 'center', 'spaceAfter' => 0]);
        $styleTitleTable = ['borderBottomSize' => 18, 'borderBottomColor' => '000000', 'borderBottomStyle' => 'double'];
        $titleLineTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $titleLineTable->addRow();
        $titleLineTable->addCell(10000, $styleTitleTable);
        
        $section->addTextBreak(1); // 距離上方雙底線一點距離

        // 2. 抬頭資訊 (左:中:右 = 45%:10%:45% 佈局以產生中間空隙)
        $headerTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $headerTable->addRow();
        
        // 左欄
        $leftCell = $headerTable->addCell(4500, ['valign' => 'bottom']);
        $nameTable = $leftCell->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $nameTable->addRow();
        $nameTable->addCell(4000, ['borderBottomSize' => 6])->addText($order->customer->name, ['size' => 16], ['spaceAfter' => 0]);
        $nameTable->addCell(1000)->addText("台照", ['size' => 16], ['alignment' => 'right', 'spaceAfter' => 0]);
        
        $leftCell->addTextBreak(1); // 客戶名稱和日期之間空一行
        $leftCell->addText("建單日期：{$order->date}", ['size' => 14], ['spaceAfter' => 0]);

        // 中間空隙
        $headerTable->addCell(1000);

        // 右欄
        $rightCell = $headerTable->addCell(4500, ['valign' => 'top']);
        $rightStyle = ['spaceAfter' => 0, 'lineHeight' => 1.1];
        $rightCell->addText("電話：{$order->customer->phone}", ['size' => 11], $rightStyle);
        $rightCell->addText("地址：{$order->address}", ['size' => 11], $rightStyle);
        $rightCell->addText("備註：{$order->public_notes}", ['size' => 11], $rightStyle);
        $rightCell->addText("統編：{$order->tax_id}", ['size' => 11], $rightStyle);
        
        // 3. 主明細表格 (寬度縮小至 95% 並置中，適度間距)
        $styleTable = [
            'borderSize' => 6, 
            'borderColor' => '000000', 
            'cellMarginTop' => 40,
            'cellMarginBottom' => 40,
            'cellMarginLeft' => 80,
            'cellMarginRight' => 80,
            'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
        ];
        $styleHeader = ['bgColor' => 'F2F2F2'];
        $phpWord->addTableStyle('MainTable', $styleTable);
        $table = $section->addTable('MainTable');

        // 表格內通用文字與段落樣式 (統一 12pt, 移除粗體)
        $tableFontSize = 12;
        $pStyleCentered = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15, 'alignment' => 'center'];
        $pStyleLeft = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15, 'alignment' => 'left'];

        // 表頭 (全部置中)
        $table->addRow();
        $headers = ['項目', '品名', '規格', '數量', '單價', '金額', '備註'];
        $widths = [800, 3000, 2000, 1000, 1200, 1200, 1800];
        foreach ($headers as $i => $h) {
            $table->addCell($widths[$i], array_merge($styleHeader, ['valign' => 'center']))->addText($h, ['size' => $tableFontSize], $pStyleCentered);
        }

        $cellStyleCentered = ['valign' => 'center'];

        // 安裝設備部分
        if ($order->type === 'install' && count($order->equipments) > 0) {
            $subtotal = 0;
            foreach ($order->equipments as $i => $eq) {
                $table->addRow();
                if ($i === 0) {
                    $table->addCell(800, ['vMerge' => 'restart', 'bgColor' => 'F9F9F9', 'valign' => 'center'])->addText('', ['size' => $tableFontSize], $pStyleCentered);
                } else {
                    $table->addCell(800, ['vMerge' => 'continue']);
                }

                if ($eq->pivot->is_adjustment) {
                    $table->addCell(5000, ['gridSpan' => 2, 'valign' => 'center'])->addText($eq->pivot->custom_model_name, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(3400, ['gridSpan' => 3, 'valign' => 'center'])->addText(number_format($eq->pivot->sale_price), ['size' => $tableFontSize], $pStyleCentered);
                } else {
                    $table->addCell(3000, $cellStyleCentered)->addText($eq->model_name, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(2000, $cellStyleCentered)->addText($eq->specs, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1000, $cellStyleCentered)->addText($eq->pivot->quantity . ' 台', ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1200, $cellStyleCentered)->addText(number_format($eq->pivot->sale_price), ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1200, $cellStyleCentered)->addText(number_format($eq->pivot->sale_price * $eq->pivot->quantity), ['size' => $tableFontSize], $pStyleCentered);
                }
                $table->addCell(1800, $cellStyleCentered)->addText($eq->pivot->item_note, ['size' => $tableFontSize], $pStyleLeft);
                $subtotal += $eq->pivot->sale_price * $eq->pivot->quantity;
            }
            $table->addRow();
            $table->addCell(800, ['vMerge' => 'continue']);
            $table->addCell(5000, ['gridSpan' => 2, 'bgColor' => 'F2F2F2', 'valign' => 'center'])->addText('小計', ['size' => $tableFontSize], $pStyleCentered);
            $table->addCell(3400, ['gridSpan' => 4, 'bgColor' => 'F2F2F2', 'valign' => 'center'])->addText(number_format($subtotal), ['size' => $tableFontSize], $pStyleCentered);
        }

        // 材料與工資部分
        if (count($order->materials) > 0) {
            $subtotal = 0;
            foreach ($order->materials as $i => $mat) {
                $table->addRow();
                if ($i === 0) {
                    $table->addCell(800, ['vMerge' => 'restart', 'bgColor' => 'F9F9F9', 'valign' => 'center'])->addText('', ['size' => $tableFontSize], $pStyleCentered);
                } else {
                    $table->addCell(800, ['vMerge' => 'continue']);
                }

                if ($mat->pivot->is_adjustment) {
                    $table->addCell(5000, ['gridSpan' => 2, 'valign' => 'center'])->addText($mat->pivot->custom_name, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(3400, ['gridSpan' => 3, 'valign' => 'center'])->addText(number_format($mat->pivot->unit_price), ['size' => $tableFontSize], $pStyleCentered);
                } else {
                    $table->addCell(3000, $cellStyleCentered)->addText($mat->name, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(2000, $cellStyleCentered)->addText($mat->specs, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1000, $cellStyleCentered)->addText($mat->pivot->quantity . ' ' . ($mat->unit ?? '組'), ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1200, $cellStyleCentered)->addText(number_format($mat->pivot->unit_price), ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1200, $cellStyleCentered)->addText(number_format($mat->pivot->unit_price * $mat->pivot->quantity), ['size' => $tableFontSize], $pStyleCentered);
                }
                $table->addCell(1800, $cellStyleCentered)->addText($mat->pivot->item_note, ['size' => $tableFontSize], $pStyleLeft);
                $subtotal += $mat->pivot->unit_price * $mat->pivot->quantity;
            }
            $table->addRow();
            $table->addCell(800, ['vMerge' => 'continue']);
            $table->addCell(5000, ['gridSpan' => 2, 'bgColor' => 'F2F2F2', 'valign' => 'center'])->addText('小計', ['size' => $tableFontSize], $pStyleCentered);
            $table->addCell(3400, ['gridSpan' => 4, 'bgColor' => 'F2F2F2', 'valign' => 'center'])->addText(number_format($subtotal), ['size' => $tableFontSize], $pStyleCentered);
        }

        // 總計列
        $table->addRow();
        $table->addCell(5800, ['gridSpan' => 3, 'valign' => 'center'])->addText('總計', ['size' => 14], $pStyleCentered);
        $table->addCell(5200, ['gridSpan' => 4, 'valign' => 'center'])->addText(number_format($order->total_amount), ['size' => 14], $pStyleCentered);

        // 4. 頁尾資訊
        $section->addText("1. 本報價單不含5%營業稅", ['size' => 12], ['spaceBefore' => 120]);
        $bankInfo = "匯款帳戶: " . ($shop['bank_name'] ?? '') . " " . ($shop['bank_account_name'] ?? '') . " " . ($shop['bank_account'] ?? '');
        $section->addText($bankInfo, ['size' => 12], ['spaceAfter' => 0]);

        // 簽章區 (靠右)
        $section->addText("客戶簽章：____________________", ['size' => 14], ['alignment' => 'right', 'spaceBefore' => 120]);

        // 店家資訊 (店名與資訊放在同一行，靠右)
        if ($shop->isNotEmpty()) {
            $shopName = ($shop['shop_name'] ?? '');
            $contactInfo = "  TEL:" . ($shop['shop_phone'] ?? '') . "  " . ($shop['owner_name'] ?? '') . "  " . ($shop['shop_address'] ?? '');

            $section->addText($shopName . $contactInfo, ['size' => 11], ['alignment' => 'right']);
        }

        // 導出檔案
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
                $query->where(function($q) use ($kw) { $q->where('name', 'like', "%{$request->q}%")->orWhere('specs', 'like', "%{$request->q}%"); });
            }
        }
        return $query->limit(20)->get();
    }
}