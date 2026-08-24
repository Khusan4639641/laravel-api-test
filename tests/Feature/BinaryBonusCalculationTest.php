<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\BinaryBonusCalculation;
use App\Models\BinaryBonusRun;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class BinaryBonusCalculationTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_admin_can_calculate_binary_bonus_with_wallet_split_period_and_pv_carryover(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $package = $this->createPackage('VIP', 8);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'left_pv' => 1000,
            'right_pv' => 600,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 600,
            'total_pv' => 1600,
        ]);
        $this->makeBinaryBonusEligible($user);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('bonus_transaction.bonus_type', 'binary')
            ->assertJsonPath('bonus_transaction.amount', '24000.00')
            ->assertJsonPath('bonus_transaction.matched_pv', '600.00');

        $user->refresh();
        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();
        $bonusTransaction = BonusTransaction::query()->firstOrFail();
        $walletTransactions = WalletTransaction::query()->orderBy('id')->get();
        $run = BinaryBonusRun::query()->firstOrFail();
        $calculation = BinaryBonusCalculation::query()->firstOrFail();

        $this->assertSame('400.00', $user->remaining_left_pv);
        $this->assertSame('0.00', $user->remaining_right_pv);
        $this->assertSame('21600.00', $mainWallet->balance);
        $this->assertSame('2400.00', $depositWallet->balance);
        $this->assertSame('24000.00', $bonusTransaction->amount);
        $this->assertSame('600.00', $bonusTransaction->matched_pv);
        $this->assertSame('600.00', $bonusTransaction->metadata['base_pv']);
        $this->assertSame('300000.00', $bonusTransaction->metadata['money_base_amount']);
        $this->assertSame('500', $bonusTransaction->metadata['pv_money_rate']);
        $this->assertSame('21600.00', $bonusTransaction->metadata['main_amount']);
        $this->assertSame('2400.00', $bonusTransaction->metadata['deposit_amount']);
        $this->assertSame('600.00', $run->weak_leg_pv);
        $this->assertSame('600.00', $run->used_left_pv);
        $this->assertSame('600.00', $run->used_right_pv);
        $this->assertSame('400.00', $run->carry_left_pv);
        $this->assertSame('0.00', $run->carry_right_pv);
        $this->assertSame('completed', $run->status);
        $this->assertSame('0.00', $run->pending_amount);
        $this->assertSame('300000.00', $calculation->money_base_amount);
        $this->assertSame('24000.00', $calculation->bonus_amount);
        $this->assertCount(2, $walletTransactions);
        $this->assertSame('binary_bonus_main', $walletTransactions[0]->type);
        $this->assertSame('21600.00', $walletTransactions[0]->amount);
        $this->assertSame('binary_bonus_deposit', $walletTransactions[1]->type);
        $this->assertSame('2400.00', $walletTransactions[1]->amount);
    }

    public function test_binary_bonus_percent_matches_current_package_matrix(): void
    {
        $cases = [
            ['START', 7, '35000.00'],
            ['VIP', 8, '40000.00'],
            ['ELITE', 10, '50000.00'],
        ];

        foreach ($cases as [$code, $percent, $expectedAmount]) {
            $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
            $package = $this->createPackage($code, $percent);
            $user = User::factory()->create([
                'current_package_id' => $package->id,
                'remaining_left_pv' => 1000,
                'remaining_right_pv' => 1000,
            ]);
            $this->makeBinaryBonusEligible($user);

            Sanctum::actingAs($admin);

            $this->postJson('/api/admin/bonuses/binary/calculate', [
                'user_id' => $user->id,
            ])
                ->assertOk()
                ->assertJsonPath('bonus_transaction.amount', $expectedAmount);
        }
    }

    public function test_binary_bonus_is_not_created_without_matched_pv(): void
    {
        $package = $this->createPackage('START', 10);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 500,
            'remaining_right_pv' => 0,
        ]);
        $this->makeBinaryBonusEligible($user);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null);

        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_binary_bonus_is_not_created_without_binary_percent(): void
    {
        $package = $this->createPackage('START', 0);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 500,
            'remaining_right_pv' => 500,
        ]);
        $this->makeBinaryBonusEligible($user);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null);

        $user->refresh();

        $this->assertSame('500.00', $user->remaining_left_pv);
        $this->assertSame('500.00', $user->remaining_right_pv);
        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_binary_bonus_is_not_created_without_direct_referrals_on_both_binary_sides(): void
    {
        $package = $this->createPackage('START', 7);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);
        $this->makeDirectReferralInBinaryBranch($user, 'L');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null);

        $user->refresh();

        $this->assertSame('1000.00', $user->remaining_left_pv);
        $this->assertSame('1000.00', $user->remaining_right_pv);
        $this->assertDatabaseCount('binary_bonus_runs', 0);
        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_binary_bonus_ignores_non_direct_downline_users_for_eligibility(): void
    {
        $package = $this->createPackage('START', 7);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);
        $sponsorNode = $this->ensureBinaryNode($user);
        $otherSponsor = User::factory()->create();
        $leftDownline = User::factory()->create(['sponsor_id' => $otherSponsor->id]);
        $rightDownline = User::factory()->create(['sponsor_id' => $otherSponsor->id]);

        $this->createChildNode($sponsorNode, $leftDownline, 'L');
        $this->createChildNode($sponsorNode, $rightDownline, 'R');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null);

        $this->assertDatabaseCount('binary_bonus_runs', 0);
        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_binary_period_does_not_recount_already_counted_pv(): void
    {
        Carbon::setTestNow('2026-06-04 10:00:00');

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $package = $this->createPackage('START', 7);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);
        $this->makeBinaryBonusEligible($user);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])->assertOk()
            ->assertJsonPath('bonus_transaction.amount', '35000.00');

        $user->forceFill([
            'remaining_left_pv' => 500,
            'remaining_right_pv' => 500,
        ])->save();

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])->assertOk()
            ->assertJsonPath('bonus_transaction', null);

        $this->assertSame(1, BonusTransaction::query()->where('bonus_type', 'binary')->count());
        $this->assertSame(1, BinaryBonusRun::query()->count());

        Carbon::setTestNow();
    }

    public function test_binary_calculation_after_period_uses_remaining_carryover_pv(): void
    {
        Carbon::setTestNow('2026-06-04 10:00:00');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $package = $this->createPackage('VIP', 8);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 600,
        ]);
        $eligible = $this->makeBinaryBonusEligible($user);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])->assertOk()
            ->assertJsonPath('bonus_transaction.amount', '24000.00');

        $user->refresh()->forceFill([
            'remaining_right_pv' => 400,
        ])->save();
        PvTransaction::query()->create([
            'buyer_id' => $eligible['right']->id,
            'upline_id' => $user->id,
            'source' => 'test_next_binary_period',
            'branch' => 'R',
            'pv' => '400.00',
            'is_bonusable' => true,
        ]);

        Carbon::setTestNow('2026-06-20 10:00:00');

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])->assertOk()
            ->assertJsonPath('bonus_transaction.amount', '16000.00');

        $user->refresh();

        $this->assertSame('0.00', $user->remaining_left_pv);
        $this->assertSame('0.00', $user->remaining_right_pv);
        $this->assertSame(2, BinaryBonusRun::query()->count());

        Carbon::setTestNow();
    }

    public function test_user_cannot_trigger_binary_recalculation(): void
    {
        $package = $this->createPackage('START', 7);
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/bonuses/binary/calculate')
            ->assertForbidden();

        $this->postJson('/api/admin/bonuses/binary/calculate', [
            'user_id' => $user->id,
        ])->assertForbidden();

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'binary')->count());
    }

    public function test_admin_and_super_admin_can_trigger_binary_recalculation(): void
    {
        $package = $this->createPackage('START', 7);

        foreach ([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
            $targetUser = User::factory()->create([
                'current_package_id' => $package->id,
                'remaining_left_pv' => 1000,
                'remaining_right_pv' => 1000,
            ]);
            $this->makeBinaryBonusEligible($targetUser);

            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->postJson('/api/admin/bonuses/binary/calculate', [
                'user_id' => $targetUser->id,
            ])->assertOk()
                ->assertJsonPath('bonus_transaction.amount', '35000.00');
        }
    }

    private function createPackage(string $code, int $binaryPercent): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => 60000,
            'pv' => 60000,
            'referral_percent' => 0,
            'binary_percent' => $binaryPercent,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
