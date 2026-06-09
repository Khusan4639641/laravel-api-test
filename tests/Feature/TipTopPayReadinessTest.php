<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipTopPayReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_page_only_super_admin(): void
    {
        $this->getJson('/api/admin/payment-readiness')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/payment-readiness')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->getJson('/api/admin/payment-readiness')
            ->assertOk()
            ->assertJsonStructure([
                'app_url',
                'https_enabled',
                'tiptop_enabled',
                'public_terminal_id_set',
                'legal_pages',
                'requisites_filled',
                'products_active_count',
                'orders_payment_status_support',
                'webhook_routes_work',
            ]);

        $this->get('/admin/payment-readiness')->assertOk();
    }

    public function test_legal_pages_exist(): void
    {
        foreach ([
            '/legal/offer',
            '/legal/privacy',
            '/legal/delivery',
            '/legal/refund',
            '/legal/requisites',
            '/contacts',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_payment_page_exists(): void
    {
        $this->get('/payment')->assertOk();
        $this->get('/payment/success')->assertOk();
        $this->get('/payment/fail')->assertOk();
    }

    public function test_products_prices_are_kzt(): void
    {
        Product::query()->create([
            'name' => 'Safi Readiness Product',
            'sku' => 'SAFI-READINESS-001',
            'description' => 'Safi Readiness Product',
            'price' => 12500,
            'pv' => 25,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'status' => 'active',
            'is_deposit_product' => false,
            'image_path' => '/images/product-placeholder.svg',
        ]);

        $this->getJson('/api/public/products')
            ->assertOk()
            ->assertJsonPath('products.0.price', '12500.00');

        $this->assertStringContainsString('₸', file_get_contents(resource_path('js/safi/pages/ProductsPage.tsx')));
        $this->assertStringContainsString('₸', file_get_contents(resource_path('js/safi/pages/CartPage.tsx')));
    }

    public function test_checkout_requires_delivery_fields(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $product = Product::query()->create([
            'name' => 'Checkout Delivery Product',
            'sku' => 'CHECKOUT-DELIVERY-001',
            'description' => 'Checkout Delivery Product',
            'price' => 12500,
            'pv' => 25,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_name', 'phone', 'city', 'delivery_address']);
    }

    public function test_requisites_settings_render(): void
    {
        Config::set('tiptoppay.enabled', true);
        Config::set('tiptoppay.public_terminal_id', 'public-terminal-readiness');
        Config::set('tiptoppay.currency', 'KZT');

        $this->setLegalSettings([
            'company_legal_name' => 'ТОО "Safi Life Test"',
            'company_bin' => '123456789012',
            'legal_address' => 'Республика Казахстан, г. Алматы, ул. Тестовая 10',
            'actual_address' => 'Республика Казахстан, г. Алматы, офис 12',
            'bank_name' => 'АО Банк Тест',
            'iban' => 'KZ123456789012345678',
            'bik' => 'TESTKZKX',
            'kbe' => '17',
            'support_phone' => '+7 (701) 111-22-33',
            'support_email' => 'support-test@safilife.kz',
            'dispute_email' => 'dispute-test@safilife.kz',
            'website_url' => 'https://safilife.kz',
            'director_name' => 'Тестовый Руководитель',
            'privacy_email' => 'privacy-test@safilife.kz',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson('/api/admin/payment-readiness')
            ->assertOk()
            ->assertJsonPath('requisites_filled', true)
            ->assertJsonPath('public_terminal_id_set', true)
            ->assertJsonPath('currency', 'KZT');

        $this->getJson('/api/public/legal-settings')
            ->assertOk()
            ->assertJsonPath('settings.company_legal_name', 'ТОО "Safi Life Test"')
            ->assertJsonPath('settings.company_bin', '123456789012')
            ->assertJsonPath('settings.iban', 'KZ123456789012345678');

        $this->get('/legal/requisites')->assertOk();
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function setLegalSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => 'string',
                    'group' => in_array($key, ['support_phone', 'support_email', 'dispute_email', 'privacy_email'], true) ? 'contacts' : 'legal',
                ]
            );
        }
    }
}
