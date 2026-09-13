<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        $customer = Customer::create(['name' => '測試客戶', 'phone' => '0900000000']);

        return Order::create([
            'customer_id' => $customer->id,
            'date'        => '2026-09-14',
            'type'        => 'install',
        ]);
    }

    public function test_可以上傳多張照片(): void
    {
        Storage::fake('public');
        $order = $this->makeOrder();

        $response = $this->actingAs(User::factory()->create())
            ->post(route('orders.photos.store', $order), [
                'photos' => [
                    UploadedFile::fake()->image('site1.jpg'),
                    UploadedFile::fake()->image('site2.jpg'),
                ],
            ]);

        $response->assertRedirect();
        $this->assertSame(2, $order->photos()->count());

        foreach ($order->photos as $photo) {
            Storage::disk('public')->assertExists($photo->path);
            $this->assertStringStartsWith("order-photos/{$order->id}/", $photo->path);
            $this->assertStringContainsString('/storage/', $photo->url);
        }
    }

    public function test_非圖片檔會被擋下來(): void
    {
        Storage::fake('public');
        $order = $this->makeOrder();

        $this->actingAs(User::factory()->create())
            ->post(route('orders.photos.store', $order), [
                'photos' => [UploadedFile::fake()->create('報價單.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, $order->photos()->count());
    }

    public function test_可以編輯照片說明(): void
    {
        Storage::fake('public');
        $order = $this->makeOrder();
        $photo = $order->photos()->create(['path' => 'order-photos/1/a.jpg']);

        $this->actingAs(User::factory()->create())
            ->patch(route('orders.photos.update', $photo), ['caption' => '舊機位置'])
            ->assertRedirect();

        $this->assertSame('舊機位置', $photo->fresh()->caption);
    }

    public function test_刪除照片會連檔案一起刪除(): void
    {
        Storage::fake('public');
        $order = $this->makeOrder();
        $path = UploadedFile::fake()->image('x.jpg')->store("order-photos/{$order->id}", 'public');
        $photo = $order->photos()->create(['path' => $path]);

        $this->actingAs(User::factory()->create())
            ->delete(route('orders.photos.destroy', $photo))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(OrderPhoto::find($photo->id));
    }

    public function test_未登入不能上傳(): void
    {
        $order = $this->makeOrder();

        $this->post(route('orders.photos.store', $order), [
            'photos' => [UploadedFile::fake()->image('x.jpg')],
        ])->assertRedirect(route('login'));
    }

    public function test_刪除訂單會一併刪掉照片紀錄(): void
    {
        $order = $this->makeOrder();
        $order->photos()->create(['path' => 'order-photos/1/a.jpg']);

        $order->delete();

        $this->assertSame(0, OrderPhoto::count());
    }
}
