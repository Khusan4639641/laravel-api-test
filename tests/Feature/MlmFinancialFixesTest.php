<?php

namespace Tests\Feature;

use App\Models\AdminActionLog;
use App\Models\BinaryNode;
use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\Package;
use App\Models\Product;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\BonusService;
use App\Services\DashboardBranchVolumeService;
use App\Services\StatusService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class MlmFinancialFixesTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_deposit_purchase_cashback_is_idempotent_by_order(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Deposit Idempotent',
            'sku' => 'DEP-IDEMPOTENT',
            'price' => 1000,
            'pv' => 10,
            'stock_quantity' => 2,
            'status' => 'active',
            'is_deposit_product' => true,
        ]);
        $this->wallet($user, 'deposit', '2000.00');

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'payment_strategy' => Order::PAYMENT_STRATEGY_DEPOSIT_100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Алматы',
            'delivery_address' => 'Абая 10',
        ])->assertCreated();

        $order = Order::query()->firstOrFail();
        $depositTransaction = WalletTransaction::query()
            ->where('type', 'deposit_product_purchase')
            ->firstOrFail();

        app(BonusService::class)->accrueDepositPurchaseCashback(
            $user,
            $order->total_amount,
            $depositTransaction,
            "deposit_purchase_cashback:{$order->id}",
        );

        $this->assertSame(1, BonusTransaction::query()->where('bonus_type', 'cashback')->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', 'deposit_purchase_cashback')->count());
        $this->assertSame('200.00', $this->walletFor($user, 'main')->balance);
    }

    public function test_own_package_pv_transaction_is_not_counted_in_own_left_or_right_branch(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'left_pv' => 0,
            'right_pv' => 0,
            'remaining_left_pv' => 0,
            'remaining_right_pv' => 0,
        ]);

        PvTransaction::query()->create([
            'buyer_id' => $user->id,
            'upline_id' => $user->id,
            'source' => 'package_activation',
            'branch' => 'L',
            'pv' => '100.00',
            'is_bonusable' => true,
        ]);

        $volumes = app(DashboardBranchVolumeService::class)->getBranchVolumes($user);

        $this->assertSame('0.00', $volumes['left_pv']);
        $this->assertSame('0.00', $volumes['right_pv']);
    }

    public function test_branch_pv_is_calculated_bottom_up_without_own_package_pv(): void
    {
        $start = $this->package('START', 100);
        $vip = $this->package('VIP', 300);
        $a = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active']);
        $b = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active', 'current_package_id' => $start->id]);
        $c = User::factory()->create(['role' => User::ROLE_USER, 'account_status' => 'active', 'current_package_id' => $vip->id]);
        $aNode = $this->node($a);
        $bNode = $this->node($b, $aNode, 'R');
        $this->node($c, $bNode, 'R');
        $service = app(DashboardBranchVolumeService::class);

        $aVolumes = $service->getBranchVolumes($a->refresh());
        $bVolumes = $service->getBranchVolumes($b->refresh());
        $cVolumes = $service->getBranchVolumes($c->refresh());

        $this->assertSame('0.00', $cVolumes['left_pv']);
        $this->assertSame('0.00', $cVolumes['right_pv']);
        $this->assertSame(300.0, $service->getUserPersonalPv($c));
        $this->assertSame('0.00', $bVolumes['left_pv']);
        $this->assertSame('300.00', $bVolumes['right_pv']);
        $this->assertSame(100.0, $service->getUserPersonalPv($b));
        $this->assertSame('0.00', $aVolumes['left_pv']);
        $this->assertSame('400.00', $aVolumes['right_pv']);
    }

    public function test_cached_own_package_pv_is_ignored_for_tree_status_and_binary_bonus(): void
    {
        $package = $this->package('START', 100);
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $package->id,
            'status' => 'manager',
            'left_pv' => 1000,
            'right_pv' => 1000,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);
        $rootNode = $this->ensureBinaryNode($user);
        $leftReferral = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'sponsor_id' => $user->id,
        ]);
        $rightReferral = User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'sponsor_id' => $user->id,
        ]);
        $this->createChildNode($rootNode, $leftReferral, 'L');
        $this->createChildNode($rootNode, $rightReferral, 'R');

        $volumes = app(DashboardBranchVolumeService::class)->getBranchVolumes($user->refresh());

        $this->assertSame('0.00', $volumes['left_pv']);
        $this->assertSame('0.00', $volumes['right_pv']);
        $this->assertSame('user', app(StatusService::class)->recalculate($user->refresh())->status);
        $this->assertNull(app(BonusService::class)->calculateBinaryBonus($user->refresh()));
    }

    public function test_registration_by_referral_link_is_forbidden_when_sponsor_has_no_active_package(): void
    {
        $package = $this->package('START');
        $sponsor = User::factory()->create([
            'login' => 'inactive-sponsor',
            'current_package_id' => null,
        ]);

        $this->postJson('/api/register', [
            'name' => 'No Sponsor Package',
            'login' => 'no-sponsor-package',
            'email' => 'no-sponsor-package@safi.test',
            'phone' => '+77000000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $sponsor->login,
            'branch' => 'left',
            'package_id' => $package->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referral_code');

        $this->assertDatabaseMissing('users', ['login' => 'no-sponsor-package']);
    }

    public function test_admin_period_recalculation_processes_all_active_packaged_users(): void
    {
        $package = $this->package('START');
        User::factory()->count(2)->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $package->id,
            'remaining_left_pv' => 0,
            'remaining_right_pv' => 0,
        ]);
        User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => null,
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->postJson('/api/admin/bonuses/recalculate', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('processed_count', 2)
            ->assertJsonPath('recalculated_count', 0);
    }

    public function test_super_admin_can_update_and_delete_bonus_with_wallet_adjustments(): void
    {
        $user = User::factory()->create();
        $bonus = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'cashback',
            'amount' => '100.00',
            'status' => 'completed',
            'calculated_at' => now(),
        ]);
        app(WalletService::class)->createUserWallets($user);
        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $walletTransaction = app(WalletService::class)->credit(
            $wallet,
            '100.00',
            'deposit_purchase_cashback',
            $bonus,
        );
        $bonus->forceFill(['wallet_transaction_id' => $walletTransaction->id])->save();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->patchJson("/api/admin/bonuses/{$bonus->id}", [
            'amount' => 150,
            'reason' => 'Корректировка ошибочной суммы',
        ])
            ->assertOk()
            ->assertJsonPath('bonus.amount', '150.00');

        $this->assertSame('150.00', $this->walletFor($user, 'main')->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'bonus_manual_adjustment',
            'direction' => 'credit',
            'amount' => '50.00',
        ]);

        $this->deleteJson("/api/admin/bonuses/{$bonus->id}", [
            'reason' => 'Удаление ошибочного бонуса',
        ])->assertOk();

        $this->assertSame('0.00', $this->walletFor($user, 'main')->balance);
        $this->assertSame('voided', $bonus->refresh()->status);
        $this->assertSame(2, AdminActionLog::query()->where('target_user_id', $user->id)->count());
    }

    public function test_admin_cannot_manually_delete_bonus(): void
    {
        $bonus = BonusTransaction::query()->create([
            'user_id' => User::factory()->create()->id,
            'bonus_type' => 'cashback',
            'amount' => '100.00',
            'status' => 'completed',
            'calculated_at' => now(),
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->deleteJson("/api/admin/bonuses/{$bonus->id}", [
            'reason' => 'Недостаточно прав',
        ])->assertForbidden();
    }

    private function node(User $user, ?BinaryNode $parent = null, ?string $position = null): BinaryNode
    {
        return BinaryNode::query()->create([
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'position' => $position,
            'depth' => $parent ? $parent->depth + 1 : 0,
            'path' => $parent ? $parent->path.'.'.$user->id : (string) $user->id,
            'is_active' => true,
        ]);
    }

    private function package(string $code, int $pv = 100): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code).'-'.uniqid(),
            'price' => 60000,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $pv,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function wallet(User $user, string $type, string $balance): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'currency' => 'KZT',
            'balance' => $balance,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }

    private function walletFor(User $user, string $type): Wallet
    {
        return $user->wallets()->where('type', $type)->firstOrFail()->refresh();
    }
}
