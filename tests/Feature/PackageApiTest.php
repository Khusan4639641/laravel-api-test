<?php

namespace Tests\Feature;

use App\Models\Package;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_packages_api_returns_only_active_business_tz_packages(): void
    {
        Package::query()->create([
            'code' => 'BUSINESS',
            'name' => 'BUSINESS',
            'slug' => 'business',
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 2,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);

        $this->seed(PackageSeeder::class);

        $response = $this->getJson('/api/public/packages')->assertOk();
        $packages = collect($response->json('packages'))->keyBy('code');

        $this->assertSame(['START', 'VIP', 'ELITE'], $packages->keys()->all());
        $this->assertFalse($packages->has('BUSINESS'));
        $this->assertPackagePayload($packages['START'], '60000.00', '100.00', '100.00', 10, 7);
        $this->assertPackagePayload($packages['VIP'], '180000.00', '300.00', '300.00', 10, 8);
        $this->assertPackagePayload($packages['ELITE'], '300000.00', '500.00', '200.00', 10, 10);
    }

    private function assertPackagePayload(
        array $package,
        string $price,
        string $activityPv,
        string $turnoverPv,
        int $referralPercent,
        int $binaryPercent,
    ): void {
        $this->assertSame($price, $package['price']);
        $this->assertSame($activityPv, $package['pv']);
        $this->assertSame($activityPv, $package['activity_pv']);
        $this->assertSame($turnoverPv, $package['turnover_pv']);
        $this->assertSame($referralPercent, $package['referralBonus']);
        $this->assertSame($binaryPercent, $package['binaryBonus']);
        $this->assertTrue($package['is_active']);
    }
}
