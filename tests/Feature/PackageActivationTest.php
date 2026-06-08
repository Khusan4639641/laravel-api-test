<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_activate_package(): void
    {
        $user = User::factory()->create();
        $package = $this->createPackage('START', 60000, 100, 10, 1);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/packages/{$package->id}/activate");

        $response
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $package->id);

        $this->assertSame($package->id, $user->refresh()->current_package_id);
        $this->assertSame('100.00', $user->total_pv);
    }

    public function test_start_activation_sets_personal_pv_and_upline_turnover_without_buyer_branch_pv(): void
    {
        $package = $this->createPackage('START', 60000, 100, 10, 1);
        $sponsor = User::factory()->create([
            'current_package_id' => $package->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($user, $sponsor, 'L');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.total_pv', '100.00');

        $user->refresh();
        $sponsor->refresh();

        $this->assertSame('100.00', $user->total_pv);
        $this->assertSame('0.00', $user->left_pv);
        $this->assertSame('0.00', $user->right_pv);
        $this->assertSame('100.00', $sponsor->left_pv);
        $this->assertSame('100.00', $sponsor->remaining_left_pv);
        $this->assertSame('0.00', $sponsor->right_pv);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_activation',
            'direction' => 'neutral',
            'amount' => '60000.00',
            'affects_balance' => false,
        ]);
    }

    public function test_vip_activation_sets_personal_pv_and_upline_turnover_without_buyer_branch_pv(): void
    {
        $sponsorPackage = $this->createPackage('START', 60000, 100, 10, 1);
        $vip = $this->createPackage('VIP', 180000, 300, 10, 2);
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($user, $sponsor, 'R');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.total_pv', '300.00');

        $user->refresh();
        $sponsor->refresh();

        $this->assertSame('300.00', $user->total_pv);
        $this->assertSame('0.00', $user->left_pv);
        $this->assertSame('0.00', $user->right_pv);
        $this->assertSame('0.00', $sponsor->left_pv);
        $this->assertSame('300.00', $sponsor->right_pv);
        $this->assertSame('300.00', $sponsor->remaining_right_pv);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_activation',
            'direction' => 'neutral',
            'amount' => '180000.00',
            'affects_balance' => false,
        ]);
    }

    public function test_start_activation_pays_sponsor_ten_percent(): void
    {
        $package = $this->createPackage('START', 60000, 100, 10, 1);
        $sponsor = User::factory()->create([
            'current_package_id' => $package->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.total_pv', '100.00');

        $user->refresh();
        $bonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();
        $walletTransaction = WalletTransaction::query()->where('type', 'referral_bonus')->firstOrFail();

        $this->assertSame('100.00', $user->total_pv);
        $this->assertSame('5000.00', $bonus->amount);
        $this->assertSame('5000.00', $walletTransaction->amount);
        $this->assertSame($sponsor->id, $bonus->user_id);
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'package_activation')
            ->count());
    }

    public function test_activation_accrues_referral_bonus_to_sponsor_main_wallet(): void
    {
        $sponsorPackage = $this->createPackage('START', 60000, 100, 10, 1);
        $activatedPackage = $this->createPackage('VIP', 180000, 300, 10, 2);
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        Wallet::query()->create([
            'user_id' => $sponsor->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 0,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$activatedPackage->id}/activate")
            ->assertOk();

        $wallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();
        $bonus = BonusTransaction::query()->firstOrFail();
        $walletTransaction = WalletTransaction::query()->where('type', 'referral_bonus')->firstOrFail();

        $this->assertSame('15000.00', $wallet->balance);
        $this->assertSame($sponsor->id, $bonus->user_id);
        $this->assertSame($user->id, $bonus->source_user_id);
        $this->assertSame('referral', $bonus->bonus_type);
        $this->assertSame('15000.00', $bonus->amount);
        $this->assertSame('150000.00', $bonus->metadata['base_amount']);
        $this->assertSame('business_tz', $bonus->metadata['percent_source']);
        $this->assertSame($wallet->id, $walletTransaction->wallet_id);
        $this->assertSame('credit', $walletTransaction->direction);
        $this->assertSame('referral_bonus', $walletTransaction->type);
        $this->assertSame('15000.00', $walletTransaction->amount);
    }

    public function test_referral_bonus_is_always_ten_percent(): void
    {
        $sponsorPackage = $this->createPackage('START', 60000, 100, 5, 1);
        $activatedPackage = $this->createPackage('VIP', 180000, 300, 30, 2);
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
        $this->assertSame('10', $bonus->metadata['referral_percent']);
        $this->assertSame($sponsorPackage->id, $bonus->metadata['sponsor_package_id']);
        $this->assertSame($activatedPackage->id, $bonus->metadata['referral_package_id']);
        $this->assertSame('15000.00', $wallet->balance);
    }

    public function test_user_with_current_package_cannot_activate_again(): void
    {
        $start = $this->createPackage('START', 60000, 100, 10, 1);
        $vip = $this->createPackage('VIP', 180000, 300, 10, 2);
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'total_pv' => $start->turnoverPv(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/activate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');

        $this->assertSame($start->id, $user->refresh()->current_package_id);
        $this->assertSame('100.00', $user->total_pv);
    }

    public function test_elite_package_cannot_be_activated_as_first_package(): void
    {
        $elite = $this->createPackage('ELITE', 300000, 500, 10, 3);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/activate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');

        $this->assertNull($user->refresh()->current_package_id);
        $this->assertSame('0.00', $user->total_pv);
    }

    public function test_inactive_package_cannot_be_activated(): void
    {
        $package = $this->createPackage('START', 60000, 100, 10, 1, false);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$package->id}/activate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
    }

    private function createPackage(
        string $code,
        int $price,
        int $pv,
        int $referralPercent,
        int $sortOrder,
        bool $active = true,
    ): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $pv,
            'referral_percent' => $referralPercent,
            'binary_percent' => 0,
            'sort_order' => $sortOrder,
            'status' => $active ? 'active' : 'inactive',
            'is_active' => $active,
            'is_upgradeable' => $active,
        ]);
    }
}
