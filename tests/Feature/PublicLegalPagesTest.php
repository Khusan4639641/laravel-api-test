<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_pages_return_ok(): void
    {
        foreach ([
            '/payment',
            '/legal/offer',
            '/legal/privacy',
            '/legal/delivery',
            '/legal/refund',
            '/legal/requisites',
            '/contacts',
            '/offer',
            '/privacy',
            '/refund',
            '/requisites',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_footer_contains_legal_links(): void
    {
        $footer = file_get_contents(resource_path('js/safi/components/layout/Footer.tsx'));

        foreach ([
            '/payment' => 'Онлайн-оплата',
            '/legal/offer' => 'Договор оферты',
            '/legal/privacy' => 'Политика конфиденциальности',
            '/legal/delivery' => 'Доставка',
            '/legal/refund' => 'Возврат',
            '/legal/requisites' => 'Реквизиты',
            '/contacts' => 'Контакты',
        ] as $path => $label) {
            $this->assertStringContainsString($path, $footer);
            $this->assertStringContainsString($label, $footer);
        }
    }

    public function test_payment_page_contains_tiptoppay_text(): void
    {
        $page = file_get_contents(resource_path('js/safi/pages/LegalInfoPage.tsx'));

        $this->assertStringContainsString('TipTop Pay', $page);
        $this->assertStringContainsString('Visa и Mastercard', $page);
        $this->assertStringContainsString('3-D Secure', $page);
        $this->assertStringContainsString('Данные карты не сохраняются на сайте Safi Life', $page);
    }

    public function test_requisites_endpoint_renders_legal_settings(): void
    {
        SystemSetting::query()->create([
            'key' => 'company_legal_name',
            'value' => 'ТОО "Safi Life Test"',
            'type' => 'string',
            'group' => 'legal',
        ]);
        SystemSetting::query()->create([
            'key' => 'company_bin',
            'value' => '123456789012',
            'type' => 'string',
            'group' => 'legal',
        ]);
        SystemSetting::query()->create([
            'key' => 'iban',
            'value' => 'KZ123456789012345678',
            'type' => 'string',
            'group' => 'legal',
        ]);

        $this->getJson('/api/public/legal-settings')
            ->assertOk()
            ->assertJsonPath('settings.company_legal_name', 'ТОО "Safi Life Test"')
            ->assertJsonPath('settings.company_bin', '123456789012')
            ->assertJsonPath('settings.iban', 'KZ123456789012345678');

        $page = file_get_contents(resource_path('js/safi/pages/LegalInfoPage.tsx'));

        $this->assertStringContainsString('company_legal_name', $page);
        $this->assertStringContainsString('Реквизиты продавца', $page);
    }

    public function test_product_prices_are_displayed_in_kzt(): void
    {
        Product::query()->create([
            'name' => 'Safi KZT Product',
            'sku' => 'SAFI-KZT-001',
            'price' => 12500,
            'pv' => 25,
            'status' => 'active',
            'is_deposit_product' => false,
        ]);

        $this->getJson('/api/public/products')
            ->assertOk()
            ->assertJsonPath('products.0.price', '12500.00');

        $productsPage = file_get_contents(resource_path('js/safi/pages/ProductsPage.tsx'));
        $cartPage = file_get_contents(resource_path('js/safi/pages/CartPage.tsx'));
        $adminProductsPage = file_get_contents(resource_path('js/safi/pages/admin/AdminProducts.tsx'));

        foreach ([$productsPage, $cartPage, $adminProductsPage] as $contents) {
            $this->assertStringContainsString('₸', $contents);
            $this->assertStringNotContainsString('RUB', $contents);
            $this->assertStringNotContainsString('USD', $contents);
        }
    }

    public function test_tiptop_pages_do_not_contain_forbidden_placeholders(): void
    {
        $contents = implode("\n", [
            file_get_contents(resource_path('js/safi/pages/LegalInfoPage.tsx')),
            file_get_contents(resource_path('js/safi/pages/LegalPage.tsx')),
            file_get_contents(resource_path('js/safi/pages/ContactsPage.tsx')),
            file_get_contents(app_path('Support/LegalSettings.php')),
        ]);

        foreach ([
            'ВАШ НОМЕР ТЕЛЕФОНА',
            'E-MAIL адрес',
            'ВАШ АДРЕС САЙТА',
            'Здесь необходимо указать',
        ] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $contents);
        }
    }
}
