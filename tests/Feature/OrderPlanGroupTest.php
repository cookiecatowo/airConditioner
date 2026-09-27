<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPlanGroupTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $equipments, array $materials = []): void
    {
        $this->actingAs(User::factory()->create())->post(route('orders.store'), [
            'customer_name' => '吳小姐',
            'address'       => '台中市北區中清路一段1號12樓之2',
            'date'          => '2026-09-23',
            'type'          => 'install',
            'work_category' => 'ac',
            'equipments'    => $equipments,
            'materials'     => $materials,
        ]);
    }

    private function eq(string $name, float $price, ?string $plan, int $qty = 1): array
    {
        return ['id' => null, 'brand_id' => '測試牌', 'model_name' => $name, 'specs' => '',
                'cost_price' => 0, 'sale_price' => $price, 'quantity' => $qty, 'unit' => '台',
                'item_note' => '', 'is_adjustment' => false, 'plan_group' => $plan];
    }

    private function mat(string $name, float $price): array
    {
        return ['id' => null, 'name' => $name, 'specs' => '', 'unit' => '式',
                'unit_price' => $price, 'quantity' => 1, 'item_note' => '', 'is_adjustment' => false];
    }

    public function test_兩個方案時金額未定(): void
    {
        $this->makeOrder([
            $this->eq('大金一對二', 155260, '大金'),
            $this->eq('禾聯一對二', 109700, '禾聯'),
        ], [$this->mat('工程材料', 51750)]);

        $o = Order::latest('id')->first();
        $this->assertTrue((bool) $o->plan_undecided);
        $this->assertEquals(0, $o->total_amount);
    }

    public function test_只剩一個方案時金額恢復正常(): void
    {
        $this->makeOrder([
            $this->eq('大金一對二', 155260, '大金'),
        ], [$this->mat('工程材料', 51750)]);

        $o = Order::latest('id')->first();
        $this->assertFalse((bool) $o->plan_undecided);
        $this->assertEquals(207010, $o->total_amount);
    }

    public function test_沒有分方案時照舊加總(): void
    {
        $this->makeOrder([
            $this->eq('冷氣A', 30000, null),
            $this->eq('冷氣B', 20000, ''),
        ], [$this->mat('工資', 3500)]);

        $o = Order::latest('id')->first();
        $this->assertFalse((bool) $o->plan_undecided);
        $this->assertEquals(53500, $o->total_amount);
    }

    public function test_未分組設備與單一方案會相加(): void
    {
        $this->makeOrder([
            $this->eq('共用配件', 5000, null),
            $this->eq('大金一對二', 100000, '大金'),
        ], [$this->mat('工資', 3500)]);

        $o = Order::latest('id')->first();
        $this->assertEquals(108500, $o->total_amount);
    }

    public function test_方案分組會存進明細(): void
    {
        $this->makeOrder([
            $this->eq('大金外機', 33480, '大金'),
            $this->eq('大金內機', 13130, '大金'),
            $this->eq('禾聯外機', 43900, '禾聯'),
        ]);

        $o = Order::latest('id')->first();
        $groups = \DB::table('order_equipment')->where('order_id', $o->id)->pluck('plan_group')->all();
        $this->assertSame(['大金', '大金', '禾聯'], $groups);
    }

    public function test_數量會乘進方案小計(): void
    {
        $this->makeOrder([
            $this->eq('大金內機', 10000, '大金', 3),
            $this->eq('禾聯內機', 9000, '禾聯', 2),
        ]);

        $o = Order::latest('id')->first();
        $this->assertTrue((bool) $o->plan_undecided);

        // 刪掉禾聯後應為 30000
        \DB::table('order_equipment')->where('order_id', $o->id)->where('plan_group', '禾聯')->delete();
        $this->assertSame(30000.0, (float) \DB::table('order_equipment')
            ->where('order_id', $o->id)->selectRaw('sum(sale_price * quantity) t')->value('t'));
    }
}
