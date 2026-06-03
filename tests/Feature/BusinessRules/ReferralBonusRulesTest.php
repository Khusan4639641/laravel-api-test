<?php

namespace Tests\Feature\BusinessRules;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Services\BonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralBonusRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_bonus_is_ten_percent_of_correct_order_base(): void
    {
        $sponsor = User::factory()->create();
        $referral = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        $bonus = app(BonusService::class)->accrueReferralBonus($sponsor, $referral, 135000);

        $this->assertNotNull($bonus);
        $this->assertSame('13500.00', $bonus->amount);
        $this->assertSame('135000', $bonus->metadata['base_amount']);
    }

    public function test_package_purchase_referral_bonus_uses_bonusable_base_not_full_package_price(): void
    {
        $sponsorPackage = $this->createPackage('START', 60000, 100);
        $vip = $this->createPackage('VIP', 180000, 300);
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $referral = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        Sanctum::actingAs($referral);

        $this->postJson("/api/packages/{$vip->id}/activate")
            ->assertOk();

        $bonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();

        $this->assertSame('13500.00', $bonus->amount);
        $this->assertNotSame('18000.00', $bonus->amount);
    }

    private function createPackage(string $code, int $price, int $pv): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $pv,
            'referral_percent' => 10,
            'binary_percent' => 8,
            'sort_order' => $code === 'START' ? 1 : ($code === 'VIP' ? 2 : 3),
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
