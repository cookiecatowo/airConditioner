<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Equipment;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DaikinProductsImport
{
    private int $daikinBrandId;
    private int $importCount = 0;

    /**
     * 匯入進入點，由 Controller 直接呼叫
     */
    public function import(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $this->daikinBrandId = Brand::firstOrCreate(['name' => 'Daikin 大金'])->id;

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (str_contains($sheetName, '多聯')) {
                $this->processMultiSheet($sheet);
            } else {
                $this->processSingleSheet($sheet);
            }
        }

        return $this->importCount;
    }

    /**
     * 處理一對一系列 (橫綱Y/Z、大關Z、豪菁Z...)
     * 沒有外內機個別發票價的工作表 (舊R系列) 直接跳過
     */
    private function processSingleSheet($sheet): void
    {
        $maxRow = $sheet->getHighestRow();

        // 取標題：通常在 R1 的第 4 欄
        $title = trim($sheet->getCellByColumnAndRow(4, 1)->getValue());
        if (empty($title)) return;

        // 找表頭列（含「號」字的列）並確認 外內機發票價 欄位位置
        $headerRow = null;
        $invoiceColNum = null;

        for ($r = 1; $r <= 4; $r++) {
            $col2Val = $sheet->getCellByColumnAndRow(2, $r)->getValue();
            if (!str_contains((string) $col2Val, '號')) continue;

            // 找含「發票」的欄位（只看個別外內機那欄，不要取到「發票價」整組欄）
            for ($c = 10; $c <= 14; $c++) {
                $hVal = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                if (str_contains((string) $hVal, '外內') || str_contains((string) $hVal, '發票')) {
                    // 優先取最後找到的（外內機發票價 > 發票價）
                    $invoiceColNum = $c;
                }
            }
            $headerRow = $r;
            break;
        }

        // 沒有外內機發票價欄 → 舊R系列，跳過
        if ($headerRow === null || $invoiceColNum === null) return;

        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $modelNumber = trim($sheet->getCellByColumnAndRow(2, $r)->getFormattedValue());
            if (empty($modelNumber)) continue;

            $invoicePrice = $this->parsePrice(
                $sheet->getCellByColumnAndRow($invoiceColNum, $r)->getFormattedValue()
            );
            if ($invoicePrice <= 0) continue;

            $typeCell = trim($sheet->getCellByColumnAndRow(1, $r)->getFormattedValue());
            $unitType = $this->getUnitType($modelNumber, $typeCell);

            $this->upsert($title . ' ' . $unitType, $modelNumber, $invoicePrice);
        }
    }

    /**
     * 處理一對多聯工作表
     * 型式欄 (col1) 會標示「室外機」或「壁掛型室內機」等，空白時沿用上一個型式
     */
    private function processMultiSheet($sheet): void
    {
        $maxRow = $sheet->getHighestRow();
        $mainTitle = trim($sheet->getCellByColumnAndRow(1, 1)->getFormattedValue());
        $currentType = '';
        $invoiceColNum = null;

        for ($r = 1; $r <= $maxRow; $r++) {
            $col0 = trim($sheet->getCellByColumnAndRow(1, $r)->getFormattedValue());
            $col1 = trim($sheet->getCellByColumnAndRow(2, $r)->getFormattedValue());

            // 偵測表頭列（col1 含「號」）→ 找發票價欄位
            if (str_contains($col1, '號')) {
                for ($c = 8; $c <= 12; $c++) {
                    $hVal = $sheet->getCellByColumnAndRow($c, $r)->getFormattedValue();
                    if (str_contains((string) $hVal, '發票')) {
                        $invoiceColNum = $c;
                    }
                }
                $currentType = '';
                continue;
            }

            // 偵測型式變更（室外機 / 壁掛型室內機 / 吊隱型室內機...）
            if (!empty($col0) && empty($col1)) continue; // 系列名稱列，跳過
            if (!empty($col0) && !is_numeric($col0)) {
                if (str_contains($col0, '室外')) $currentType = '室外機';
                elseif (str_contains($col0, '室內') || str_contains($col0, '壁掛') || str_contains($col0, '吊隱')) $currentType = '室內機';
            }

            // 資料列
            if (empty($col1) || $invoiceColNum === null) continue;

            $invoicePrice = $this->parsePrice(
                $sheet->getCellByColumnAndRow($invoiceColNum, $r)->getFormattedValue()
            );
            if ($invoicePrice <= 0) continue;

            // 若型式欄仍為空，從型號前綴判斷
            if (empty($currentType)) {
                $currentType = $this->getUnitType($col1);
            }

            $this->upsert($mainTitle . ' ' . $currentType, $col1, $invoicePrice);
        }
    }

    /**
     * 判斷室外機或室內機
     * F 開頭 → 室內機；其餘 (R 開頭或數字開頭) → 室外機
     */
    private function getUnitType(string $modelNumber, string $typeCell = ''): string
    {
        if (!empty($typeCell)) {
            if (str_contains($typeCell, '室外')) return '室外機';
            if (str_contains($typeCell, '室內') || str_contains($typeCell, '壁掛') || str_contains($typeCell, '吊隱')) return '室內機';
        }
        return strtoupper($modelNumber[0]) === 'F' ? '室內機' : '室外機';
    }

    private function parsePrice(string $value): int
    {
        return (int) str_replace([',', ' ', ' '], '', $value);
    }

    /**
     * 寫入 equipments 表
     * model_name = 系列名稱 (如 "R32 橫綱 Y系列 變頻一對一冷暖壁掛 室外機")
     * specs      = 機型編號 (如 "RXM22YVLT")
     */
    private function upsert(string $seriesName, string $modelNumber, int $invoicePrice): void
    {
        Equipment::updateOrCreate(
            [
                'brand_id'   => $this->daikinBrandId,
                'model_name' => $seriesName,
                'specs'      => $modelNumber,
            ],
            [
                'default_cost_price' => round($invoicePrice * 0.88),
                'default_sale_price' => $invoicePrice,
            ]
        );
        $this->importCount++;
    }
}
