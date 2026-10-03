<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs = [], array $customer = []): Order
    {
        $c = Customer::create($customer + ['name' => '吳小姐', 'phone' => '0952108025']);

        return Order::make($attrs + [
            'customer_id'       => $c->id,
            'date'              => '2026-09-23',
            'type'              => 'install',
            'work_category'     => 'ac',
            'address'           => '台中市北區中清路一段1號12樓之2',
            'total_amount'      => 1000,
            'processing_status' => 2,
        ])->setRelation('customer', $c);
    }

    private function archive(): OrderArchive
    {
        return app(OrderArchive::class);
    }

    public function test_檔名依序為日期顧客分類類型電話地址(): void
    {
        $this->assertSame(
            '2026-09-23-吳小姐-空調-新品-0952108025-台中市北區中清路一段1號12樓之2.docx',
            $this->archive()->fileName($this->order())
        );
    }

    public function test_三種類型分別寫成新品維修保養(): void
    {
        foreach (['install' => '新品', 'repair' => '維修', 'maintenance' => '保養'] as $type => $label) {
            $this->assertStringContainsString("-{$label}-", $this->archive()->fileName($this->order(['type' => $type])));
        }
    }

    public function test_業務分類會轉成中文(): void
    {
        $this->assertStringContainsString('-監視-', $this->archive()->fileName($this->order(['work_category' => 'surveillance'])));
        $this->assertStringContainsString('-其他-', $this->archive()->fileName($this->order(['work_category' => 'other'])));
    }

    public function test_沒有電話時不留空欄(): void
    {
        $name = $this->archive()->fileName($this->order([], ['phone' => '']));

        $this->assertStringNotContainsString('--', $name);
        $this->assertSame('2026-09-23-吳小姐-空調-新品-台中市北區中清路一段1號12樓之2.docx', $name);
    }

    public function test_地址未填寫時會略過(): void
    {
        $name = $this->archive()->fileName($this->order(['address' => '未填寫']));

        $this->assertStringNotContainsString('未填寫', $name);
        $this->assertSame('2026-09-23-吳小姐-空調-新品-0952108025.docx', $name);
    }

    public function test_檔名不含windows禁用字元(): void
    {
        $name = $this->archive()->fileName($this->order(
            ['address' => '台中市<test>/路:1號'],
            ['name' => '王*小姐?']
        ));

        foreach ([chr(92), '/', ':', '*', '?', '"', '<', '>', '|'] as $bad) {
            $this->assertStringNotContainsString($bad, $name);
        }
    }

    public function test_檔名過長會截斷(): void
    {
        $name = $this->archive()->fileName($this->order(['address' => str_repeat('台中市中清路', 60)]));

        $this->assertLessThanOrEqual(205, mb_strlen($name));
        $this->assertStringEndsWith('.docx', $name);
    }
}
