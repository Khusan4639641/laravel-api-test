<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FrontendContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_route_is_served_and_package_content_endpoint_has_correct_prices(): void
    {
        $this->seed(PackageSeeder::class);

        $this->get('/business')->assertOk();

        $packages = collect($this->getJson('/api/public/packages')->assertOk()->json('packages'))->keyBy('code');

        $this->assertSame('60000.00', $packages['START']['price']);
        $this->assertSame('180000.00', $packages['VIP']['price']);
        $this->assertSame('300000.00', $packages['ELITE']['price']);
    }

    public function test_marketing_route_is_served_and_status_endpoint_has_correct_statuses(): void
    {
        $this->get('/marketing')->assertOk();

        $statuses = collect($this->getJson('/api/public/statuses', ['Accept-Language' => 'ru'])->assertOk()->json('statuses'))->keyBy('id');

        $this->assertSame(1000, $statuses['manager']['pv']);
        $this->assertSame(2500, $statuses['leader']['pv']);
        $this->assertSame(5000, $statuses['director']['pv']);
        $this->assertSame(500000, $statuses['diamond_director']['pv']);
        $this->assertSame('20 000 000 ₸ apartment bonus', $statuses['diamond_director']['reward']);
    }

    public function test_register_package_dropdown_is_backed_by_public_packages_without_business(): void
    {
        $this->seed(PackageSeeder::class);

        $packages = collect($this->getJson('/api/public/registration-packages')->assertOk()->json('packages'))->pluck('code')->all();
        $registerPage = file_get_contents(resource_path('js/safi/pages/RegisterPage.tsx'));

        $this->assertSame(['START', 'VIP'], $packages);
        $this->assertNotContains('BUSINESS', $packages);
        $this->assertNotContains('ELITE', $packages);
        $this->assertStringContainsString('getRegistrationPackages', $registerPage);
    }

    public function test_dashboard_package_endpoint_displays_start_vip_elite_only(): void
    {
        $this->seed(PackageSeeder::class);

        Sanctum::actingAs(User::factory()->create());

        $this->get('/dashboard/package-status')->assertOk();

        $packages = collect($this->getJson('/api/dashboard/packages')->assertOk()->json('packages'))->pluck('code')->all();

        $this->assertSame(['START', 'VIP', 'ELITE'], $packages);
        $this->assertNotContains('BUSINESS', $packages);
    }

    public function test_support_frontend_is_enabled_without_floating_external_contacts(): void
    {
        $features = file_get_contents(resource_path('js/safi/config/features.ts'));
        $routes = file_get_contents(resource_path('js/safi/router/routes.tsx'));
        $overview = file_get_contents(resource_path('js/safi/pages/dashboard/Overview.tsx'));

        $this->assertStringContainsString('support: true', $features);
        $this->assertStringContainsString('floatingExternalContacts: false', $features);
        $this->assertStringContainsString('path="support"', $routes);
        $this->assertStringContainsString('to="/dashboard/support"', $overview);
    }

    public function test_floating_and_public_external_support_contacts_are_not_rendered(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/safi/components/layout/FloatingContactButtons.tsx'));

        $mainLayout = file_get_contents(resource_path('js/safi/components/layout/MainLayout.tsx'));
        $footer = file_get_contents(resource_path('js/safi/components/layout/Footer.tsx'));
        $contacts = file_get_contents(resource_path('js/safi/pages/ContactsPage.tsx'));
        $dashboardSupport = file_get_contents(resource_path('js/safi/pages/dashboard/Support.tsx'));

        foreach ([$mainLayout, $footer, $contacts, $dashboardSupport] as $contents) {
            $this->assertStringNotContainsString('FloatingContactButtons', $contents);
            $this->assertStringNotContainsString('WhatsApp', $contents);
            $this->assertStringNotContainsString('Telegram', $contents);
            $this->assertStringNotContainsString('href="tel:', $contents);
            $this->assertStringNotContainsString('safilife_support', $contents);
        }
    }

    public function test_header_uses_authenticated_cabinet_actions(): void
    {
        $header = file_get_contents(resource_path('js/safi/components/layout/Header.tsx'));

        $this->assertStringContainsString("t('nav.cabinet', 'Кабинет')", $header);
        $this->assertStringContainsString("t('nav.logout', 'Выйти')", $header);
        $this->assertStringContainsString('to={cabinetPath}', $header);
        $this->assertStringContainsString('isAuthenticated ? (', $header);
        $this->assertStringContainsString('to="/login"', $header);
        $this->assertStringContainsString('to="/register"', $header);
    }

    public function test_header_cabinet_routes_are_role_based_and_public_home_does_not_logout(): void
    {
        $header = file_get_contents(resource_path('js/safi/components/layout/Header.tsx'));
        $api = file_get_contents(resource_path('js/safi/lib/api.ts'));

        $this->assertStringContainsString("const BACKOFFICE_ROLES = ['super_admin', 'admin', 'accountant', 'support']", $header);
        $this->assertStringContainsString("return BACKOFFICE_ROLES.includes(role.toLowerCase()) ? '/admin' : '/dashboard';", $header);
        $this->assertStringContainsString("path: '/'", $header);
        $this->assertStringContainsString('redirectOnUnauthorized: false', $header);
        $this->assertStringNotContainsString("path: '/', onClick: handleLogout", $header);
        $this->assertStringContainsString('redirectOnUnauthorized: false', $api);
    }

    public function test_product_with_no_image_uses_local_placeholder(): void
    {
        $this->assertFileExists(public_path('images/product-placeholder.svg'));

        $api = file_get_contents(resource_path('js/safi/lib/api.ts'));
        $adminProducts = file_get_contents(resource_path('js/safi/pages/admin/AdminProducts.tsx'));

        $this->assertStringContainsString("export const productImagePlaceholder = '/images/product-placeholder.svg'", $api);
        $this->assertStringContainsString('|| productImagePlaceholder', $api);
        $this->assertStringContainsString('product.image || productImagePlaceholder', $adminProducts);
    }

    public function test_frontend_system_label_helper_contains_required_mappings(): void
    {
        $helper = file_get_contents(resource_path('js/safi/lib/systemLabels.ts'));
        $adminProducts = file_get_contents(resource_path('js/safi/pages/admin/AdminProducts.tsx'));

        $this->assertStringContainsString('mlmStatuses', $helper);
        $this->assertStringContainsString('GOLD_DIRECTOR', $helper);
        $this->assertStringContainsString('gold_director', $helper);
        $this->assertStringContainsString('Золотой директор', $helper);
        $this->assertStringContainsString("inactive: { ru: 'Неактивно'", $helper);
        $this->assertStringContainsString('orderStatuses', $helper);
        $this->assertStringContainsString('Ожидает подтверждения', $helper);
        $this->assertStringContainsString('productStatusLabel(product.status)', $adminProducts);
    }

    public function test_dashboard_earnings_summary_frontend_uses_endpoint_and_i18n(): void
    {
        $endpoints = file_get_contents(resource_path('js/safi/lib/endpoints.ts'));
        $api = file_get_contents(resource_path('js/safi/lib/api.ts'));
        $bonuses = file_get_contents(resource_path('js/safi/pages/dashboard/Bonuses.tsx'));
        $ru = file_get_contents(resource_path('js/safi/locales/ru.json'));

        $this->assertStringContainsString("earningsSummary: '/dashboard/earnings-summary'", $endpoints);
        $this->assertStringContainsString('getDashboardEarningsSummary', $api);
        $this->assertStringContainsString('getDashboardEarningsSummary', $bonuses);
        $this->assertStringContainsString("t('earningsSummary.totalEarned')", $bonuses);
        $this->assertStringContainsString('"earningsSummary"', $ru);
        $this->assertStringContainsString('"Всего заработано"', $ru);
    }
}
