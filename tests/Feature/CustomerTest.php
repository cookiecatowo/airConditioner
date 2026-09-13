<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private function order(Customer $c, string $date, string $address, float $amount = 1000): Order
    {
        return Order::create([
            'customer_id' => $c->id, 'date' => $date, 'type' => 'install',
            'address' => $address, 'total_amount' => $amount, 'tax_id' => $c->tax_id,
        ]);
    }

    public function test_顧客列表顯示單數與累計金額(): void
    {
        $c = Customer::create(['name' => '老順安']);
        $this->order($c, '2026-01-01', '台中市', 3000);
        $this->order($c, '2026-02-01', '台中市', 2000);

        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertInertia(fn ($p) => $p
                ->component('Customers/Index')
                ->where('customers.data.0.orders_count', 2)
                ->where('customers.data.0.orders_sum_total_amount', 5000));
    }

    public function test_改統編會同步到該顧客所有報價單(): void
    {
        $c = Customer::create(['name' => '福星牙醫', 'tax_id' => '11111111']);
        $o1 = $this->order($c, '2026-01-01', '台中市');
        $o2 = $this->order($c, '2026-02-01', '台北市');

        $this->actingAs(User::factory()->create())
            ->patch(route('customers.update', $c), ['name' => '福星牙醫', 'tax_id' => '22222222'])
            ->assertRedirect();

        $this->assertSame('22222222', $c->fresh()->tax_id);
        $this->assertSame('22222222', $o1->fresh()->tax_id);
        $this->assertSame('22222222', $o2->fresh()->tax_id);
    }

    public function test_顧客名稱不可與他人重複(): void
    {
        Customer::create(['name' => '老順安']);
        $b = Customer::create(['name' => '老順安大藥局']);

        $this->actingAs(User::factory()->create())
            ->patch(route('customers.update', $b), ['name' => '老順安'])
            ->assertSessionHasErrors('name');

        $this->assertSame('老順安大藥局', $b->fresh()->name);
    }

    public function test_合併會轉移訂單並刪除來源顧客(): void
    {
        $from = Customer::create(['name' => '老順安大藥局', 'phone' => '0400000000']);
        $to   = Customer::create(['name' => '老順安', 'tax_id' => '99999999']);
        $o1 = $this->order($from, '2026-01-01', '台中市');
        $o2 = $this->order($from, '2026-02-01', '台中市');

        $this->actingAs(User::factory()->create())
            ->post(route('customers.merge', $from), ['target_id' => $to->id])
            ->assertRedirect(route('customers.show', $to));

        $this->assertNull(Customer::find($from->id));
        $this->assertSame($to->id, $o1->fresh()->customer_id);
        $this->assertSame($to->id, $o2->fresh()->customer_id);
        // 目標缺的欄位由來源補上，並同步統編到訂單
        $this->assertSame('0400000000', $to->fresh()->phone);
        $this->assertSame('99999999', $o1->fresh()->tax_id);
    }

    public function test_不能把顧客合併到自己(): void
    {
        $c = Customer::create(['name' => '協新木器']);

        $this->actingAs(User::factory()->create())
            ->post(route('customers.merge', $c), ['target_id' => $c->id])
            ->assertSessionHasErrors('target_id');

        $this->assertNotNull(Customer::find($c->id));
    }

    public function test_還有報價單的顧客不能刪除(): void
    {
        $c = Customer::create(['name' => '天元']);
        $this->order($c, '2026-01-01', '台中市');

        $this->actingAs(User::factory()->create())
            ->delete(route('customers.destroy', $c))
            ->assertSessionHasErrors('delete');

        $this->assertNotNull(Customer::find($c->id));
    }

    public function test_顧客頁列出用過的地址且不重複(): void
    {
        $c = Customer::create(['name' => '陳金門']);
        $this->order($c, '2026-01-01', '北平路三段131號');
        $this->order($c, '2026-02-01', '北平路四段');
        $this->order($c, '2026-03-01', '北平路三段131號');
        $this->order($c, '2026-04-01', '未填寫');

        $this->actingAs(User::factory()->create())
            ->get(route('customers.show', $c))
            ->assertInertia(fn ($p) => $p
                ->component('Customers/Show')
                ->where('addresses', ['北平路三段131號', '北平路四段'])
                ->has('orders', 4));
    }
}
