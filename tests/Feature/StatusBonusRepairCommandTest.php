<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\BonusTransaction;
use App\Models\StatusBonusDefinition;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Database\Seeders\StatusBonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatusBonusRepairCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_command_seeds_status_bonus_definitions_idempotently(): void
    {
        $user = User::factory()->create();

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--dry-run' => true,
            '--seed-definitions' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, StatusBonusDefinition::query()->count());

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
            '--seed-definitions' => true,
        ])->assertExitCode(0);

        $this->assertSame(9, StatusBonusDefinition::query()->count());
        $this->assertDatabaseHas('status_bonus_definitions', [
            'status_code' => 'director',
            'threshold_pv' => '5000.00',
            'cash_amount' => '250000.00',
            'is_cash_bonus' => true,
        ]);

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
            '--seed-definitions' => true,
        ])->assertExitCode(0);

        $this->assertSame(9, StatusBonusDefinition::query()->count());
    }

    public function test_repair_command_awards_director_cash_bonus_for_eligible_elite_user(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 9500,
            'right_pv' => 5100,
            'status' => 'director',
        ]);

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('user_status_bonuses', [
            'user_id' => $user->id,
            'status_code' => 'director',
            'amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'status_bonus',
            'direction' => 'credit',
            'amount' => '250000.00',
            'description' => 'Статусный бонус: Директор',
        ]);

        $this->assertSame('250000.00', (string) Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', 'main')
            ->value('balance'));

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson("/api/admin/transactions?user_id={$user->id}&type=status_bonus")
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'status_bonus')
            ->assertJsonPath('transactions.0.amount', '250000.00');

        $this->getJson("/api/admin/bonuses?search={$user->id}&type=status_bonus")
            ->assertOk()
            ->assertJsonPath('bonuses.0.bonus_type', 'status_bonus')
            ->assertJsonPath('bonuses.0.amount', '250000.00');

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'status_bonus')
            ->assertJsonPath('transactions.0.amount', '250000.00');
    }

    public function test_repair_command_does_not_duplicate_director_bonus_on_rerun(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
        ]);

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);
        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'status_bonus')
            ->where('amount', '250000.00')
            ->count());
        $this->assertSame(1, UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_code', 'director')
            ->count());
    }

    public function test_repair_ledger_creates_missing_transactions_when_marker_exists(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $definition = StatusBonusDefinition::query()->where('status_code', 'director')->firstOrFail();
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
        ]);
        UserStatusBonus::query()->create([
            'user_id' => $user->id,
            'status_bonus_definition_id' => $definition->id,
            'bonus_transaction_id' => null,
            'status_code' => 'director',
            'amount' => '250000.00',
            'currency' => 'KZT',
            'reward_text' => $definition->reward_text,
            'awarded_at' => now(),
            'metadata' => ['source' => 'test_marker_only'],
        ]);

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
            '--repair-ledger' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'status_bonus')
            ->where('amount', '250000.00')
            ->count());
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
        ]);
        $this->assertNotNull(UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_code', 'director')
            ->value('bonus_transaction_id'));
    }

    public function test_repair_creates_marker_from_existing_wallet_transaction_without_second_credit(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
        ]);

        app(WalletService::class)->createUserWallets($user);
        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', 'main')
            ->firstOrFail();
        app(WalletService::class)->credit($wallet, '250000.00', 'status_bonus', null, [
            'status_code' => 'director',
            'status_name' => 'Директор',
        ], 'Статусный бонус: Директор');

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame('250000.00', (string) $wallet->refresh()->balance);
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'status_bonus')
            ->where('amount', '250000.00')
            ->count());
        $this->assertDatabaseHas('user_status_bonuses', [
            'user_id' => $user->id,
            'status_code' => 'director',
            'amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
        ]);
    }

    public function test_repair_normalizes_legacy_status_bonus_type_without_second_credit(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $definition = StatusBonusDefinition::query()->where('status_code', 'director')->firstOrFail();
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
        ]);

        app(WalletService::class)->createUserWallets($user);
        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', 'main')
            ->firstOrFail();
        $legacyBonus = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'status',
            'amount' => '250000.00',
            'left_pv' => '5000.00',
            'right_pv' => '5000.00',
            'matched_pv' => '5000.00',
            'status' => 'completed',
            'metadata' => [
                'status_code' => 'director',
                'status_bonus_definition_id' => $definition->id,
                'left_pv' => '5000.00',
                'right_pv' => '5000.00',
                'weak_leg_pv' => '5000.00',
            ],
            'calculated_at' => now(),
        ]);
        $walletTransaction = app(WalletService::class)->credit($wallet, '250000.00', 'status_bonus', $legacyBonus, [
            'status_code' => 'director',
        ], 'Статусный бонус: Директор');
        $legacyBonus->forceFill(['wallet_transaction_id' => $walletTransaction->id])->save();
        UserStatusBonus::query()->create([
            'user_id' => $user->id,
            'status_bonus_definition_id' => $definition->id,
            'bonus_transaction_id' => $legacyBonus->id,
            'status_code' => 'director',
            'amount' => '250000.00',
            'currency' => 'KZT',
            'reward_text' => $definition->reward_text,
            'awarded_at' => now(),
            'metadata' => ['source' => 'legacy_status_type'],
        ]);

        $this->artisan('safi:status-bonuses:repair', [
            '--user-id' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame('status_bonus', $legacyBonus->refresh()->bonus_type);
        $this->assertSame('5000.00', (string) $legacyBonus->left_pv);
        $this->assertSame('5000.00', (string) $legacyBonus->right_pv);
        $this->assertSame('5000.00', (string) $legacyBonus->matched_pv);
        $this->assertSame('250000.00', (string) $wallet->refresh()->balance);
        $this->assertSame(1, WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'status_bonus')
            ->where('amount', '250000.00')
            ->count());
    }

    private function createPackage(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $code === 'ELITE' ? 300000 : 60000,
            'pv' => $code === 'ELITE' ? 500 : 100,
            'activity_pv' => $code === 'ELITE' ? 500 : 100,
            'turnover_pv' => $code === 'ELITE' ? 200 : 100,
            'referral_percent' => 10,
            'binary_percent' => $code === 'ELITE' ? 10 : 7,
            'sort_order' => $code === 'ELITE' ? 3 : 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
