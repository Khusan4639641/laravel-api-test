<?php

namespace Tests\Unit;

use App\Models\Package;
use App\Models\User;
use App\Services\BonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralBonusPercentTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_bonus_is_always_ten_percent(): void
    {
        $sponsorPackage = $this->createPackage('START', 5);
        $referralPackage = $this->createPackage('ELITE', 30);
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $referral = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $referralPackage->id,
        ]);

        $bonus = app(BonusService::class)->accrueReferralBonus($sponsor, $referral, 300000);

        $this->assertNotNull($bonus);
        $this->assertSame('30000.00', $bonus->amount);
        $this->assertSame('10', $bonus->metadata['referral_percent']);
        $this->assertSame('business_tz', $bonus->metadata['percent_source']);
    }

    private function createPackage(string $code, int $referralPercent): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.uniqid(),
            'price' => 60000,
            'pv' => 60000,
            'referral_percent' => $referralPercent,
            'binary_percent' => 7,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
