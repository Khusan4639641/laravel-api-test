<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemLabelLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_returns_code_and_localized_label(): void
    {
        $statuses = collect($this->getJson('/api/public/statuses', ['Accept-Language' => 'ru'])
            ->assertOk()
            ->json('statuses'))
            ->keyBy('code');

        $this->assertSame('gold_director', $statuses['gold_director']['code']);
        $this->assertSame('Золотой директор', $statuses['gold_director']['label']);
        $this->assertSame('Золотой директор', $statuses['gold_director']['name']);

        $englishStatuses = collect($this->getJson('/api/public/statuses', ['Accept-Language' => 'en'])
            ->assertOk()
            ->json('statuses'))
            ->keyBy('code');

        $this->assertSame('Gold Director', $englishStatuses['gold_director']['label']);
    }

    public function test_product_status_returns_inactive_ru_label_without_changing_code(): void
    {
        Product::query()->create([
            'name' => 'Inactive product',
            'sku' => 'INACTIVE-LABEL-001',
            'description' => 'Inactive product',
            'price' => 1000,
            'pv' => 10,
            'stock_quantity' => 5,
            'status' => 'inactive',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson('/api/admin/products', ['Accept-Language' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'inactive')
            ->assertJsonPath('data.0.status_label', 'Неактивно');
    }

    public function test_order_status_returns_code_and_localized_label(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-LABEL-001',
            'status' => 'completed',
            'payment_status' => 'pending',
            'subtotal_amount' => 1000,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'total_pv' => 10,
            'recipient_name' => 'Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson("/api/admin/orders/{$order->id}", ['Accept-Language' => 'ru'])
            ->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.status_label', 'Выполнен')
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.payment_status_label', 'Ожидает оплаты');
    }

    public function test_package_code_is_never_translated(): void
    {
        $this->seed(PackageSeeder::class);

        $packages = collect($this->getJson('/api/public/packages', ['Accept-Language' => 'ru'])
            ->assertOk()
            ->json('packages'))
            ->keyBy('code');

        $this->assertSame('START', $packages['START']['code']);
        $this->assertSame('START', $packages['START']['code_label']);
        $this->assertSame('START', $packages['START']['label']);
        $this->assertSame('Активен', $packages['START']['status_label']);
    }
}
