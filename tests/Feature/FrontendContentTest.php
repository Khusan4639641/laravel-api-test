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

        $packages = collect($this->getJson('/api/public/packages')->assertOk()->json('packages'))->pluck('code')->all();
        $registerPage = file_get_contents(resource_path('js/safi/pages/RegisterPage.tsx'));

        $this->assertSame(['START', 'VIP', 'ELITE'], $packages);
        $this->assertNotContains('BUSINESS', $packages);
        $this->assertStringContainsString('getPublicPackages', $registerPage);
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
}
