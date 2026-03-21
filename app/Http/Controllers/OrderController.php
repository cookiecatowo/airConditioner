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
use PhpOffice\PhpWord\SimpleType\VerticalJc;

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

    /**
     * 儲存報價單 (核心邏輯)
     */
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
            // 1. 處理顧客
            $customer = Customer::updateOrCreate(
                ['name' => $request->customer_name],
                [
                    'phone' => $request->customer_phone,
                    'tax_id' => $request->customer_tax_id,
                    'address' => $request->address
                ]
            );

            // 2. 建立訂單
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

            $totalAmount = 0;

            // 3. 處理設備 (僅限安裝)
            if ($request->type === 'install' && !empty($request->equipments)) {
                foreach ($request->equipments as $item) {
                    if (empty($item['model_name']) && empty($item['is_adjustment'])) continue;

                    $equipmentId = null;
                    if (empty($item['is_adjustment'])) {
                        // 處理規格空值
                        $specs = !empty($item['specs']) ? $item['specs'] : null;
                        
                        // 查找或新增設備母表 (按型號與規格查找)
                        $masterEq = Equipment::firstOrCreate(
                            ['model_name' => $item['model_name'], 'specs' => $specs],
                            [
                                'brand_id' => $item['brand_id'] ?? null,
                                'default_cost_price' => $item['cost_price'] ?? 0,
                                'default_sale_price' => $item['sale_price'] ?? 0,
                            ]
                        );
                        $equipmentId = $masterEq->id;
                    }

                    $order->equipments()->attach($equipmentId, [
                        'cost_price' => $item['cost_price'] ?? 0,
                        'sale_price' => $item['sale_price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                        'is_adjustment' => !empty($item['is_adjustment']),
                        'item_note' => $item['item_note'] ?? '',
                    ]);

                    $totalAmount += ($item['sale_price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }

            // 4. 處理材料
            if (!empty($request->materials)) {
                foreach ($request->materials as $item) {
                    if (empty($item['name']) && empty($item['is_adjustment'])) continue;

                    $materialId = null;
                    if (empty($item['is_adjustment'])) {
                        // 處理規格空值
                        $specs = !empty($item['specs']) ? $item['specs'] : null;

                        // 查找或新增材料母表
                        $masterMat = Material::firstOrCreate(
                            ['name' => $item['name'], 'specs' => $specs],
                            [
                                'unit' => $item['unit'] ?? '組',
                                'default_unit_price' => $item['unit_price'] ?? 0,
                            ]
                        );
                        $materialId = $masterMat->id;
                    }

                    $order->materials()->attach($materialId, [
                        'unit_price' => $item['unit_price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                        'is_adjustment' => !empty($item['is_adjustment']),
                        'item_note' => $item['item_note'] ?? '',
                    ]);

                    $totalAmount += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }

            // 5. 更新總額
            $order->update(['total_amount' => $totalAmount]);

            // 6. 產生 Word 報表並導出
            return $this->generateWordReport($order);
        });
    }

    /**
     * 產生 Word 報價單
     */
    private function generateWordReport($order)
    {
        $shop = ShopSetting::first();
        $phpWord = new PhpWord();
        
        // 設定預設字體
        $phpWord->setDefaultFontName('Microsoft JhengHei');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1134, // 2cm
            'marginBottom' => 1134,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ]);

        // 頁首標題
        $section->addText($order->report_title, ['size' => 20, 'bold' => true], ['alignment' => 'center']);
        $section->addTextBreak(1);

        // 基本資訊 (兩欄)
        $tableInfo = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $tableInfo->addRow();
        $tableInfo->addCell(5000)->addText("顧客姓名：{$order->customer->name}");
        $tableInfo->addCell(5000)->addText("報價日期：{$order->date}", null, ['alignment' => 'right']);
        
        $tableInfo->addRow();
        $tableInfo->addCell(5000)->addText("聯絡電話：{$order->customer->phone}");
        $tableInfo->addCell(5000)->addText("施工地址：{$order->address}", null, ['alignment' => 'right']);

        if ($order->tax_id) {
            $tableInfo->addRow();
            $tableInfo->addCell(5000)->addText("統一編號：{$order->tax_id}");
            $tableInfo->addCell(5000);
        }
        $section->addTextBreak(1);

        // 主表格
        $styleTable = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
        $styleHeader = ['bold' => true, 'bgColor' => 'F2F2F2'];
        $phpWord->addTableStyle('MainTable', $styleTable);
        $table = $section->addTable('MainTable');

        // 表格標題
        $table->addRow();
        $table->addCell(800, $styleHeader)->addText('項目', null, ['alignment' => 'center']);
        $table->addCell(3000, $styleHeader)->addText('品名', null, ['alignment' => 'center']);
        $table->addCell(2000, $styleHeader)->addText('規格', null, ['alignment' => 'center']);
        $table->addCell(800, $styleHeader)->addText('數量', null, ['alignment' => 'center']);
        $table->addCell(1200, $styleHeader)->addText('單價', null, ['alignment' => 'center']);
        $table->addCell(1200, $styleHeader)->addText('金額', null, ['alignment' => 'center']);
        $table->addCell(2000, $styleHeader)->addText('備註', null, ['alignment' => 'center']);

        $itemIndex = 1;

        // 設備部分
        if ($order->type === 'install') {
            $equipments = $order->equipments()->withPivot('sale_price', 'quantity', 'item_note', 'is_adjustment')->get();
            $subtotal = 0;
            foreach ($equipments as $eq) {
                $table->addRow();
                $table->addCell(800)->addText($itemIndex++, null, ['alignment' => 'center']);
                
                if ($eq->pivot->is_adjustment) {
                    $table->addCell(3000)->addText($eq->model_name, null, ['alignment' => 'center']);
                    $table->addCell(2000)->addText('', null, ['alignment' => 'center']);
                    $table->addCell(800)->addText('', null, ['alignment' => 'center']);
                    $table->addCell(1200)->addText('', null, ['alignment' => 'center']);
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
            // 小計
            $table->addRow();
            $table->addCell(6600, ['gridSpan' => 4])->addText('小計', ['bold' => true], ['alignment' => 'center']);
            $table->addCell(2400, ['gridSpan' => 2])->addText(number_format($subtotal), ['bold' => true], ['alignment' => 'right']);
            $table->addCell(2000);
        }

        // 材料部分
        $materials = $order->materials()->withPivot('unit_price', 'quantity', 'item_note', 'is_adjustment')->get();
        if ($materials->count() > 0) {
            $subtotal = 0;
            foreach ($materials as $mat) {
                $table->addRow();
                $table->addCell(800)->addText($itemIndex++, null, ['alignment' => 'center']);
                
                if ($mat->pivot->is_adjustment) {
                    $table->addCell(3000)->addText($mat->name, null, ['alignment' => 'center']);
                    $table->addCell(2000)->addText('', null, ['alignment' => 'center']);
                    $table->addCell(800)->addText('', null, ['alignment' => 'center']);
                    $table->addCell(1200)->addText('', null, ['alignment' => 'center']);
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
            // 小計
            $table->addRow();
            $table->addCell(6600, ['gridSpan' => 4])->addText('小計', ['bold' => true], ['alignment' => 'center']);
            $table->addCell(2400, ['gridSpan' => 2])->addText(number_format($subtotal), ['bold' => true], ['alignment' => 'right']);
            $table->addCell(2000);
        }

        // 總計
        $table->addRow();
        $table->addCell(7800, ['gridSpan' => 5])->addText('總計', ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        $table->addCell(3200, ['gridSpan' => 2])->addText('$' . number_format($order->total_amount), ['bold' => true, 'size' => 14, 'color' => '0000FF'], ['alignment' => 'right']);

        $section->addTextBreak(1);
        if ($order->public_notes) {
            $section->addText("備註：{$order->public_notes}", ['bold' => true]);
        }

        // 頁尾店家資訊
        $section->addTextBreak(2);
        if ($shop) {
            $footerTable = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
            $footerTable->addRow();
            $footerTable->addCell(5000)->addText("服務單位：{$shop->shop_name}");
            $footerTable->addCell(5000)->addText("負責人：{$shop->owner_name}");
            
            $footerTable->addRow();
            $footerTable->addCell(5000)->addText("聯絡電話：{$shop->phone}");
            $footerTable->addCell(5000)->addText("公司地址：{$shop->address}");
            
            $footerTable->addRow();
            $footerTable->addCell(5000)->addText("匯款銀行：{$shop->bank_name}");
            $footerTable->addCell(5000)->addText("帳號：{$shop->bank_account}");
        }

        $filename = "{$order->date}-{$order->customer->name}-報價單.docx";
        $tempFile = tempnam(sys_get_temp_dir(), 'phpword');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    public function searchCustomers(Request $request)
    {
        return Customer::where('name', 'like', "%{$request->q}%")
            ->orWhere('phone', 'like', "%{$request->q}%")
            ->limit(10)->get();
    }

    public function searchBrands(Request $request)
    {
        return Brand::where('name', 'like', "%{$request->q}%")->limit(10)->get();
    }

    public function searchEquipments(Request $request)
    {
        $query = Equipment::with('brand');
        
        if ($request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->q) {
            $keywords = explode(' ', $request->q);
            foreach ($keywords as $keyword) {
                if (empty($keyword)) continue;
                $query->where(function($q) use ($keyword) {
                    $q->where('model_name', 'like', "%{$keyword}%")
                      ->orWhere('specs', 'like', "%{$keyword}%");
                });
            }
        }

        return $query->limit(20)->get();
    }

    public function searchMaterials(Request $request)
    {
        $query = Material::query();
        
        if ($request->q) {
            $keywords = explode(' ', $request->q);
            foreach ($keywords as $keyword) {
                if (empty($keyword)) continue;
                $query->where(function($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                      ->orWhere('specs', 'like', "%{$keyword}%");
                });
            }
        }

        return $query->limit(20)->get();
    }
}