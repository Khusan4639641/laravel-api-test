<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BinaryBonusCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_calculate_binary_bonus_with_wallet_split_and_pv_carryover(): void
    {
        $package = $this->createPackage('VIP', 10);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'left_pv' => 1000,
            'right_pv' => 600,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 600,
            'total_pv' => 1600,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bonuses/binary/calculate');

        $response
            ->assertOk()
            ->assertJsonPath('bonus_transaction.bonus_type', 'binary')
            ->assertJsonPath('bonus_transaction.amount', '60.00')
            ->assertJsonPath('bonus_transaction.matched_pv', '600.00');

        $user->refresh();
        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $bonusWallet = $user->wallets()->where('type', 'bonus')->firstOrFail();
        $bonusTransaction = BonusTransaction::query()->firstOrFail();
        $walletTransactions = WalletTransaction::query()->orderBy('id')->get();

        $this->assertSame('400.00', $user->remaining_left_pv);
        $this->assertSame('0.00', $user->remaining_right_pv);
        $this->assertSame('54.00', $mainWallet->balance);
        $this->assertSame('6.00', $bonusWallet->balance);
        $this->assertSame('60.00', $bonusTransaction->amount);
        $this->assertSame('600.00', $bonusTransaction->matched_pv);
        $this->assertSame('600.00', $bonusTransaction->metadata['base_pv']);
        $this->assertSame('54.00', $bonusTransaction->metadata['main_amount']);
        $this->assertSame('6.00', $bonusTransaction->metadata['bonus_amount']);
        $this->assertCount(2, $walletTransactions);
        $this->assertSame('binary_bonus_main', $walletTransactions[0]->type);
        $this->assertSame('54.00', $walletTransactions[0]->amount);
        $this->assertSame('binary_bonus_deposit', $walletTransactions[1]->type);
        $this->assertSame('6.00', $walletTransactions[1]->amount);
    }

    public function test_binary_bonus_is_not_created_without_matched_pv(): void
    {
        $package = $this->createPackage('START', 10);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 500,
            'remaining_right_pv' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/bonuses/binary/calculate')
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

        Sanctum::actingAs($user);

        $this->postJson('/api/bonuses/binary/calculate')
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null);

        $user->refresh();

        $this->assertSame('500.00', $user->remaining_left_pv);
        $this->assertSame('500.00', $user->remaining_right_pv);
        $this->assertDatabaseCount('bonus_transactions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function createPackage(string $code, int $binaryPercent): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => 30000,
            'pv' => 30000,
            'referral_percent' => 0,
            'binary_percent' => $binaryPercent,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
