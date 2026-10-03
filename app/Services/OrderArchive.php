<?php

namespace App\Services;

use App\Http\Controllers\OrderController;
use App\Models\Order;

/**
 * 把報價單同步存成 Word 檔，放到主機看得到的資料夾，
 * 讓使用者不開系統也能用檔名查詢。
 */
class OrderArchive
{
    public const ROOT = '/var/www/html/exports';

    private const FOLDERS = [
        1 => '處理中',
        2 => '已完成',
    ];

    private const TYPES = [
        'install'     => '新品',
        'repair'      => '維修',
        'maintenance' => '保養',
    ];

    private const CATEGORIES = [
        'ac'           => '空調',
        'surveillance' => '監視',
        'other'        => '其他',
    ];

    public function __construct(private QuotationWordWriter $writer) {}

    /** 重新產生這張單的檔案；垃圾桶的單只刪不產生。 */
    public function sync(Order $order): ?string
    {
        $this->forget($order);

        $folder = self::FOLDERS[$order->processing_status] ?? null;
        if ($folder === null) {
            return null;
        }

        $dir = self::ROOT . '/' . $folder;
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . '/' . $this->fileName($order);

        $full = app(OrderController::class)->fullDetails($order);
        $this->writer->write($full, $path);

        Order::withoutEvents(fn () => $order->forceFill(['export_path' => $path])->saveQuietly());

        return $path;
    }

    /** 刪掉這張單先前產生的檔案。 */
    public function forget(Order $order): void
    {
        if ($order->export_path && is_file($order->export_path)) {
            @unlink($order->export_path);
        }
    }

    /** 日期-顧客-業務分類-類型-電話-地址.docx，缺的欄位直接略過 */
    public function fileName(Order $order): string
    {
        $parts = array_filter([
            $order->date,
            $order->customer->name ?? '未填顧客',
            self::CATEGORIES[$order->work_category] ?? null,
            self::TYPES[$order->type] ?? null,
            $this->clean($order->customer->phone ?? ''),
            $this->addressOf($order),
        ], fn ($v) => $v !== null && $v !== '');

        $name = $this->clean(implode('-', $parts));

        // Windows 檔名上限 255 字元，留空間給副檔名與重複後綴
        return mb_substr($name, 0, 200) . '.docx';
    }

    private function addressOf(Order $order): string
    {
        $a = trim((string) $order->address);

        return ($a === '' || $a === '未填寫') ? '' : $a;
    }

    /** 去掉 Windows 檔名不接受的字元 */
    private function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(
            ['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\n", "\r"],
            ' ',
            $s
        )));
    }
}
