<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPvVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_does_not_render_product_pv(): void
    {
        $this->createProductWithPv(60);

        $this->get('/')->assertOk()->assertDontSee('60 PV');

        $source = $this->source('resources/js/safi/pages/HomePage.tsx');

        $this->assertStringNotContainsString('product.pv', $source);
    }

    public function test_public_products_page_does_not_render_product_pv(): void
    {
        $this->createProductWithPv(60);

        $this->get('/products')->assertOk()->assertDontSee('60 PV');

        $source = $this->source('resources/js/safi/pages/ProductsPage.tsx');

        $this->assertStringNotContainsString('product.pv', $source);
    }

    public function test_admin_products_page_does_not_render_product_pv(): void
    {
        $this->createProductWithPv(60);

        $this->get('/admin/products')->assertOk()->assertDontSee('60 PV');

        $source = $this->source('resources/js/safi/pages/admin/AdminProducts.tsx');

        $this->assertStringNotContainsString('product.pv', $source);
        $this->assertStringNotContainsString("adminText('a_0KbQtdC90LAg')", $source);
    }

    public function test_cart_does_not_render_product_pv(): void
    {
        $this->createProductWithPv(60);

        $this->get('/cart')->assertOk()->assertDontSee('60 PV');

        $cartPage = $this->source('resources/js/safi/pages/CartPage.tsx');
        $cartContext = $this->source('resources/js/safi/context/CartContext.tsx');

        $this->assertStringNotContainsString('pvTotal', $cartPage);
        $this->assertStringNotContainsString('totalPv', $cartPage);
        $this->assertStringNotContainsString('pvTotal', $cartContext);
        $this->assertStringNotContainsString('totalPv', $cartContext);
    }

    public function test_order_pages_do_not_render_product_pv(): void
    {
        foreach ([
            'resources/js/safi/pages/admin/AdminOrders.tsx',
            'resources/js/safi/pages/dashboard/Orders.tsx',
            'resources/js/safi/pages/dashboard/OrderDetail.tsx',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringNotContainsString('order.totalPv', $source);
            $this->assertStringNotContainsString('item.totalPv', $source);
            $this->assertStringNotContainsString("t('orders.pv')", $source);
            $this->assertStringNotContainsString("t('orders.totalPv')", $source);
        }
    }

    private function createProductWithPv(int $pv): Product
    {
        return Product::query()->create([
            'name' => 'ZOSTERAL+',
            'sku' => 'PV-VISIBILITY-'.$pv.'-'.Product::query()->count(),
            'description' => 'Visibility test product',
            'price' => 30000,
            'pv' => $pv,
            'stock_quantity' => 3000,
            'status' => 'active',
        ]);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }
}
