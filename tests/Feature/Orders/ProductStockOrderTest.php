<?php

namespace Tests\Feature\Orders;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductStockOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_items_table_has_product_snapshot_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('order_items', [
            'product_name',
            'unit_price',
            'unit_pv',
            'total_price',
            'total_pv',
            'item_snapshot',
        ]));
    }

    public function test_user_can_create_order_with_available_stock(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(stock: 10);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.total_amount', '36000.00')
            ->assertJsonPath('order.total_pv', '60.00')
            ->assertJsonCount(1, 'order.items');

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_user_cannot_order_more_than_stock(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(stock: 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(1, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_user_cannot_order_inactive_product(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(stock: 10, status: 'inactive');

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_user_cannot_order_product_with_zero_stock(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(stock: 0);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_stores_price_pv_and_product_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(name: 'Safi Serum', price: 18000, pv: 30, stock: 10);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $item = OrderItem::query()->firstOrFail();

        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('Safi Serum', $item->product_name);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('18000.00', $item->unit_price);
        $this->assertSame('36000.00', $item->total_price);
        $this->assertSame('30.00', $item->unit_pv);
        $this->assertSame('60.00', $item->total_pv);
        $this->assertSame('Safi Serum', $item->item_snapshot['name']);
        $this->assertSame($product->sku, $item->item_snapshot['sku']);
        $this->assertSame('18000.00', $item->item_snapshot['price']);
        $this->assertSame('30.00', $item->item_snapshot['pv']);
    }

    public function test_order_item_snapshot_stores_product_image_path_and_url(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(name: 'Safi Image Product', imagePath: 'https://cdn.test/safi-image.jpg');

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.items.0.image_url', 'https://cdn.test/safi-image.jpg');

        $item = OrderItem::query()->firstOrFail();

        $this->assertSame($product->id, $item->item_snapshot['product_id']);
        $this->assertSame('https://cdn.test/safi-image.jpg', $item->item_snapshot['image_path']);
        $this->assertSame('https://cdn.test/safi-image.jpg', $item->item_snapshot['image_url']);
    }

    public function test_user_order_list_and_detail_return_item_image_url(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(imagePath: 'https://cdn.test/user-order.jpg');

        Sanctum::actingAs($user);

        $orderId = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('order.id');

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.image_url', 'https://cdn.test/user-order.jpg');

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('order.items.0.image_url', 'https://cdn.test/user-order.jpg');
    }

    public function test_admin_order_list_returns_item_image_url(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(imagePath: 'https://cdn.test/admin-order.jpg');

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.image_url', 'https://cdn.test/admin-order.jpg');
    }

    public function test_order_item_returns_null_image_url_when_product_has_no_image(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = $this->product(imagePath: null);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.items.0.image_url', null);
    }

    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $product = $this->product(stock: 10);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnauthorized();

        $this->assertSame(10, $product->refresh()->stock_quantity);
    }

    public function test_super_admin_can_update_product_stock(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $product = $this->product(stock: 3);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/products/{$product->id}", [
            'stock_quantity' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('product.stock_quantity', 15);

        $this->assertSame(15, $product->refresh()->stock_quantity);
    }

    public function test_admin_can_upload_product_image_and_api_returns_image_url(): void
    {
        Storage::fake('public');

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $response = $this->post('/api/admin/products', [
            'name' => 'Image Upload Product',
            'description' => 'Product with uploaded image.',
            'price' => 12000,
            'pv' => 40,
            'stock_quantity' => 5,
            'status' => 'active',
            'category' => 'Beauty',
            'image' => $this->tinyPngUpload('product.png'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('product.category', 'Beauty');

        $imagePath = $response->json('product.image_path');
        $imageUrl = $response->json('product.image_url');

        $this->assertIsString($imagePath);
        $this->assertStringStartsWith('products/', $imagePath);
        Storage::disk('public')->assertExists($imagePath);

        $this->assertIsString($imageUrl);
        $this->assertStringContainsString('/storage/products/', $imageUrl);

        $this->getJson('/api/public/products')
            ->assertOk()
            ->assertJsonPath('products.0.image_url', $imageUrl);
    }

    public function test_admin_can_replace_and_remove_product_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/old.png', 'old-image');

        $product = $this->product(imagePath: 'products/old.png');

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $replaceResponse = $this->post("/api/admin/products/{$product->id}", [
            '_method' => 'PUT',
            'image' => $this->tinyPngUpload('new-product.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk();

        $newPath = $replaceResponse->json('product.image_path');

        Storage::disk('public')->assertMissing('products/old.png');
        Storage::disk('public')->assertExists($newPath);

        $this->post("/api/admin/products/{$product->id}", [
            '_method' => 'PUT',
            'remove_image' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('product.image_path', null)
            ->assertJsonPath('product.image_url', null);

        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_order_creation_uses_transaction_and_product_row_lock(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/OrderController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('DB::transaction', $source);
        $this->assertStringContainsString('lockForUpdate', $source);
    }

    private function product(
        string $name = 'Safi Product',
        int $price = 18000,
        int $pv = 30,
        int $stock = 10,
        string $status = 'active',
        ?string $imagePath = null,
    ): Product {
        return Product::query()->create([
            'name' => $name,
            'sku' => 'SKU-'.uniqid(),
            'description' => $name,
            'price' => $price,
            'pv' => $pv,
            'stock_quantity' => $stock,
            'reserved_quantity' => 0,
            'status' => $status,
            'image_path' => $imagePath,
        ]);
    }

    private function tinyPngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));
    }
}
