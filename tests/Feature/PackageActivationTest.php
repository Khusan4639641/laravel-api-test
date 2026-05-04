<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_activate_package(): void
    {
        $user = User::factory()->create();
        $package = $this->createPackage('START', 30000, 10, 1);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/packages/{$package->id}/activate");

        $response
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $package->id);

        $this->assertSame($package->id, $user->refresh()->current_package_id);
    }

    public function test_activation_accrues_referral_bonus_to_sponsor_main_wallet(): void
    {
        $sponsorPackage = $this->createPackage('BUSINESS', 60000, 10, 2);
        $activatedPackage = $this->createPackage('VIP', 180000, 20, 3);
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        Wallet::query()->create([
            'user_id' => $sponsor->id,
            'type' => 'main',
            'currency' => 'USD',
            'balance' => 0,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$activatedPackage->id}/activate")
            ->assertOk();

        $wallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();
        $bonus = BonusTransaction::query()->firstOrFail();
        $walletTransaction = WalletTransaction::query()->firstOrFail();

        $this->assertSame('18000.00', $wallet->balance);
        $this->assertSame($sponsor->id, $bonus->user_id);
        $this->assertSame($user->id, $bonus->source_user_id);
        $this->assertSame('referral', $bonus->bonus_type);
        $this->assertSame('18000.00', $bonus->amount);
        $this->assertSame('sponsor_package', $bonus->metadata['percent_source']);
        $this->assertSame($wallet->id, $walletTransaction->wallet_id);
        $this->assertSame('credit', $walletTransaction->direction);
        $this->assertSame('referral_bonus', $walletTransaction->type);
        $this->assertSame('18000.00', $walletTransaction->amount);
    }

    public function test_referral_bonus_percent_comes_from_sponsor_package(): void
    {
        $sponsorPackage = $this->createPackage('START', 30000, 5, 1);
        $activatedPackage = $this->createPackage('VIP', 300000, 30, 4);
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$activatedPackage->id}/activate")
            ->assertOk();

        $bonus = BonusTransaction::query()->firstOrFail();
        $wallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('15000.00', $bonus->amount);
        $this->assertSame('5.00', $bonus->metadata['referral_percent']);
        $this->assertSame($sponsorPackage->id, $bonus->metadata['sponsor_package_id']);
        $this->assertSame($activatedPackage->id, $bonus->metadata['referral_package_id']);
        $this->assertSame('15000.00', $wallet->balance);
    }

    private function createPackage(string $code, int $price, int $referralPercent, int $sortOrder): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $price,
            'referral_percent' => $referralPercent,
            'binary_percent' => 0,
            'sort_order' => $sortOrder,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
