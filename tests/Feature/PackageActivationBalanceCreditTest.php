<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageActivationBalanceCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_activation_credits_balance_by_pv_rate(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.available_balance', 50000)
            ->assertJsonPath('user.total_earned', 50000)
            ->assertJsonPath('user.package_activity_pv', 100)
            ->assertJsonPath('user.package_activity_amount', 50000);

        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $transaction = WalletTransaction::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('50000.00', $wallet->balance);
        $this->assertSame('package_activation_credit', $transaction->type);
        $this->assertSame('50000.00', $transaction->amount);
        $this->assertSame('60000.00', $transaction->metadata['package_price']);
        $this->assertSame('100.00', $transaction->metadata['credited_pv']);
        $this->assertSame('500', $transaction->metadata['pv_rate']);
    }

    public function test_vip_activation_credits_balance_by_pv_rate(): void
    {
        $user = User::factory()->create();
        $vip = $this->package('VIP');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.available_balance', 150000)
            ->assertJsonPath('user.total_earned', 150000)
            ->assertJsonPath('user.package_activity_pv', 300);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_activation_credit',
            'amount' => '150000.00',
        ]);
    }

    public function test_package_price_is_not_used_as_credit_amount(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_activation_credit',
            'amount' => '50000.00',
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_activation_credit',
            'amount' => '60000.00',
        ]);
    }

    public function test_start_to_vip_upgrade_credits_only_delta_pv(): void
    {
        [$start, $vip] = $this->packages(['START', 'VIP']);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();
        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('credit_amount', '100000.00')
            ->assertJsonPath('additional_pv', '200.00')
            ->assertJsonPath('user.available_balance', 150000)
            ->assertJsonPath('user.total_earned', 150000);

        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('150000.00', $wallet->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_upgrade_credit',
            'amount' => '100000.00',
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_upgrade_credit',
            'amount' => '150000.00',
        ]);
    }

    public function test_vip_to_elite_upgrade_credits_only_delta_pv(): void
    {
        [$vip, $elite] = $this->packages(['VIP', 'ELITE']);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/activate")->assertOk();
        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('credit_amount', '100000.00')
            ->assertJsonPath('additional_pv', '200.00')
            ->assertJsonPath('user.available_balance', 250000)
            ->assertJsonPath('user.total_earned', 250000);

        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('250000.00', $wallet->balance);
    }

    public function test_repeated_activation_or_upgrade_does_not_duplicate_credit(): void
    {
        [$start, $vip] = $this->packages(['START', 'VIP']);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();
        $this->postJson("/api/packages/{$start->id}/activate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
        $this->postJson("/api/packages/{$vip->id}/upgrade")->assertOk();
        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');

        $this->assertSame('150000.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $user->id)->where('type', 'package_activation_credit')->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $user->id)->where('type', 'package_upgrade_credit')->count());
    }

    public function test_admin_package_assignment_with_business_effects_credits_balance(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
            'apply_business_effects' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.available_balance', 50000)
            ->assertJsonPath('user.total_earned', 50000);

        $transaction = WalletTransaction::query()->where('user_id', $partner->id)->firstOrFail();

        $this->assertSame('admin_package_assignment_credit', $transaction->type);
        $this->assertSame('50000.00', $transaction->amount);
        $this->assertSame($admin->id, $transaction->metadata['actor_id']);
    }

    public function test_admin_package_assignment_without_business_effects_does_not_credit_balance(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create();
        app(WalletService::class)->createUserWallets($partner);
        $start = $this->package('START');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
            'apply_business_effects' => false,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $start->id)
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0);

        $this->assertSame('0.00', $partner->wallets()->where('type', 'main')->firstOrFail()->balance);
        $this->assertSame(0, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_own_package_purchase_does_not_add_pv_to_own_branches(): void
    {
        $user = User::factory()->create();
        app(BinaryTreeService::class)->placeUser($user);
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $user->refresh();

        $this->assertSame('100.00', $user->total_pv);
        $this->assertSame('0.00', $user->left_pv);
        $this->assertSame('0.00', $user->right_pv);
        $this->assertSame('50000.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_upline_receives_turnover_pv_from_package_purchase(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create(['sponsor_id' => $parent->id]);
        $start = $this->package('START');
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($parent);
        $tree->placeUser($child, $parent, 'L');

        Sanctum::actingAs($child);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->assertSame('100.00', $parent->refresh()->left_pv);
        $this->assertSame('0.00', $child->refresh()->left_pv);
        $this->assertSame('50000.00', $child->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_dashboard_transactions_shows_package_purchase_transaction(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'package_activation_credit')
            ->assertJsonPath('transactions.0.amount', '50000.00')
            ->assertJsonPath('transactions.0.metadata.package_price', '60000.00')
            ->assertJsonPath('transactions.0.metadata.credited_pv', '100.00');
    }

    public function test_admin_partner_recent_transactions_includes_package_transaction(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $start->id,
            'apply_business_effects' => true,
        ])->assertOk();

        $this->getJson("/api/admin/partners/{$partner->id}/transactions")
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'admin_package_assignment_credit')
            ->assertJsonPath('transactions.0.amount', '50000.00')
            ->assertJsonPath('transactions.0.metadata.package_price', '60000.00');
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, Package>
     */
    private function packages(array $codes): array
    {
        return array_map(fn (string $code): Package => $this->package($code), $codes);
    }

    private function package(string $code): Package
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
            'binary_percent' => match ($code) {
                'VIP' => 8,
                'ELITE' => 10,
                default => 7,
            },
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
