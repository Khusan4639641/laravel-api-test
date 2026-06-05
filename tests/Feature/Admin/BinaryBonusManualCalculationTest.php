<?php

namespace Tests\Feature\Admin;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BinaryBonusManualCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_calculate_binary_for_selected_partner_with_split_and_recent_transactions(): void
    {
        $package = $this->createPackage('VIP', 8);
        $partner = User::factory()->create([
            'role' => User::ROLE_USER,
            'current_package_id' => $package->id,
            'left_pv' => 1000,
            'right_pv' => 600,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 600,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->postJson("/api/admin/partners/{$partner->id}/binary-bonus/calculate")
            ->assertOk()
            ->assertJsonPath('bonus_transaction.bonus_type', 'binary')
            ->assertJsonPath('bonus_transaction.amount', '24000.00')
            ->assertJsonPath('bonus_transaction.matched_pv', '600.00')
            ->assertJsonPath('user.available_balance', 21600)
            ->assertJsonPath('user.deposit_balance', 2400)
            ->assertJsonPath('user.total_balance', 24000);

        $partner->refresh();
        $this->assertSame('400.00', $partner->remaining_left_pv);
        $this->assertSame('0.00', $partner->remaining_right_pv);

        $mainWallet = $partner->wallets()->where('type', 'main')->firstOrFail();
        $depositWallet = $partner->wallets()->where('type', 'deposit')->firstOrFail();

        $this->assertSame('21600.00', $mainWallet->balance);
        $this->assertSame('2400.00', $depositWallet->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_main',
            'amount' => '21600.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $partner->id,
            'type' => 'binary_bonus_deposit',
            'amount' => '2400.00',
        ]);

        $recentTypes = collect($response->json('recent_transactions'))->pluck('type')->all();
        $this->assertContains('binary_bonus_main', $recentTypes);
        $this->assertContains('binary_bonus_deposit', $recentTypes);
    }

    public function test_user_cannot_calculate_binary_for_partner(): void
    {
        $package = $this->createPackage('START', 7);
        $partner = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

        $this->postJson("/api/admin/partners/{$partner->id}/binary-bonus/calculate")
            ->assertForbidden();

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'binary')->count());
    }

    public function test_partner_binary_manual_calculation_does_not_duplicate_same_period(): void
    {
        $package = $this->createPackage('START', 7);
        $partner = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson("/api/admin/partners/{$partner->id}/binary-bonus/calculate")
            ->assertOk()
            ->assertJsonPath('bonus_transaction.amount', '35000.00');

        $partner->refresh()->forceFill([
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ])->save();

        $this->postJson("/api/admin/partners/{$partner->id}/binary-bonus/calculate")
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null)
            ->assertJsonPath('message', 'No binary bonus available.');

        $this->assertSame(1, BonusTransaction::query()->where('bonus_type', 'binary')->count());
        $this->assertSame(2, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    private function createPackage(string $code, int $binaryPercent): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => match ($code) {
                'VIP' => 180000,
                'ELITE' => 300000,
                default => 60000,
            },
            'pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'activity_pv' => match ($code) {
                'VIP' => 300,
                'ELITE' => 500,
                default => 100,
            },
            'turnover_pv' => $code === 'ELITE' ? 200 : match ($code) {
                'VIP' => 300,
                default => 100,
            },
            'referral_percent' => 10,
            'binary_percent' => $binaryPercent,
            'sort_order' => match ($code) {
                'VIP' => 2,
                'ELITE' => 3,
                default => 1,
            },
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
