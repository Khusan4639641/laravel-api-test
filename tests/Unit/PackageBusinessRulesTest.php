<?php

namespace Tests\Unit;

use App\Models\Package;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_matrix_matches_business_tz(): void
    {
        $this->seed(PackageSeeder::class);

        $this->assertPackage('START', '60000.00', '100.00', '100.00', '10.00', '7.00');
        $this->assertPackage('VIP', '180000.00', '300.00', '300.00', '10.00', '8.00');
        $this->assertPackage('ELITE', '300000.00', '500.00', '200.00', '10.00', '10.00');
    }

    public function test_business_is_not_active_starter_package(): void
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

        $business = Package::query()->where('code', 'BUSINESS')->firstOrFail();

        $this->assertFalse($business->is_active);
        $this->assertSame('inactive', $business->status);
        $this->assertFalse(Package::query()->activeStarter()->where('code', 'BUSINESS')->exists());
    }

    private function assertPackage(
        string $code,
        string $price,
        string $activityPv,
        string $turnoverPv,
        string $referralPercent,
        string $binaryPercent,
    ): void
    {
        $package = Package::query()->where('code', $code)->firstOrFail();

        $this->assertSame($price, $package->price);
        $this->assertSame($activityPv, $package->pv);
        $this->assertSame($activityPv, $package->activity_pv);
        $this->assertSame($turnoverPv, $package->turnover_pv);
        $this->assertSame($referralPercent, $package->referral_percent);
        $this->assertSame($binaryPercent, $package->binary_percent);
        $this->assertTrue($package->is_active);
        $this->assertSame('active', $package->status);
    }
}
