<?php

namespace App\Services;

use App\Models\ShopSetting;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/** 產生報價單 Word 檔。$order 需先經過 OrderController::loadOrderFullDetails 載入明細。 */
class QuotationWordWriter
{
    public function write($order, string $path): void
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
        $section->addText($order->report_title ?? '估價單', ['size' => 26, 'bold' => true, 'spacing' => 480], ['alignment' => 'center', 'spaceAfter' => 0]);
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
        $planSubtotals = [];
        $materialsSubtotal = 0;

        // 安裝設備部分
        if ($order->type === 'install' && count($order->equipments) > 0) {
            // 依方案分組（保持原順序），未填方案的歸同一組
            $groups = [];
            foreach ($order->equipments as $eq) {
                $groups[trim($eq->pivot->plan_group ?? '')][] = $eq;
            }

            foreach ($groups as $planName => $items) {
            $subtotal = 0;
            foreach ($items as $i => $eq) {
                $table->addRow();
                if ($i === 0) {
                    $table->addCell(800, ['vMerge' => 'restart', 'bgColor' => 'F9F9F9', 'valign' => 'center'])->addText((string) $planName, ['size' => $tableFontSize], $pStyleCentered);
                } else {
                    $table->addCell(800, ['vMerge' => 'continue']);
                }

                if ($eq->pivot->is_adjustment) {
                    $table->addCell(5000, ['gridSpan' => 2, 'valign' => 'center'])->addText($eq->pivot->custom_model_name, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(3400, ['gridSpan' => 3, 'valign' => 'center'])->addText(number_format($eq->pivot->sale_price), ['size' => $tableFontSize], $pStyleCentered);
                } else {
                    // 品名＝品牌＋系列，但系列名稱已含品牌時不重複加
                    $brandName   = $eq->brand->name ?? '';
                    $productName = ($brandName !== '' && ! str_contains((string) $eq->model_name, $brandName))
                        ? trim($brandName . ' ' . $eq->model_name)
                        : (string) $eq->model_name;
                    $table->addCell(3000, $cellStyleCentered)->addText($productName, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(2000, $cellStyleCentered)->addText($eq->specs, ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1000, $cellStyleCentered)->addText($eq->pivot->quantity . ' ' . ($eq->pivot->unit ?? '台'), ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1200, $cellStyleCentered)->addText(number_format($eq->pivot->sale_price), ['size' => $tableFontSize], $pStyleCentered);
                    $table->addCell(1200, $cellStyleCentered)->addText(number_format($eq->pivot->sale_price * $eq->pivot->quantity), ['size' => $tableFontSize], $pStyleCentered);
                }
                $table->addCell(1800, $cellStyleCentered)->addText($eq->pivot->item_note, ['size' => $tableFontSize], $pStyleLeft);
                $subtotal += $eq->pivot->sale_price * $eq->pivot->quantity;
            }
            $table->addRow();
            $table->addCell(800, ['vMerge' => 'continue']);
            $table->addCell(5000, ['gridSpan' => 2, 'bgColor' => 'F2F2F2', 'valign' => 'center'])->addText('小計', ['size' => $tableFontSize], $pStyleCentered);
            $table->addCell(5200, ['gridSpan' => 4, 'bgColor' => 'F2F2F2', 'valign' => 'center'])->addText(number_format($subtotal), ['size' => $tableFontSize], $pStyleCentered);
            $planSubtotals[(string) $planName] = $subtotal;
            }
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
            $table->addCell(5200, ['gridSpan' => 4, 'bgColor' => 'F2F2F2', 'valign' => 'center'])->addText(number_format($subtotal), ['size' => $tableFontSize], $pStyleCentered);
            $materialsSubtotal = $subtotal;
        }

        // 總計列：有兩個以上具名方案時，逐一列出各方案總價
        $named = array_filter($planSubtotals, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY);
        $base  = $materialsSubtotal + ($planSubtotals[''] ?? 0);

        if (count($named) >= 2) {
            foreach ($named as $planName => $amount) {
                $table->addRow();
                $table->addCell(5800, ['gridSpan' => 3, 'valign' => 'center'])->addText("選「{$planName}」總計", ['size' => 14], $pStyleCentered);
                $table->addCell(5200, ['gridSpan' => 4, 'valign' => 'center'])->addText(number_format($base + $amount), ['size' => 14], $pStyleCentered);
            }
        } else {
            $table->addRow();
            $table->addCell(5800, ['gridSpan' => 3, 'valign' => 'center'])->addText('總計', ['size' => 14], $pStyleCentered);
            $table->addCell(5200, ['gridSpan' => 4, 'valign' => 'center'])->addText(number_format($order->total_amount), ['size' => 14], $pStyleCentered);
        }

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


        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    }
}
