<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\Product;
use App\Models\PvTransaction;
use App\Models\User;
use App\Models\UserX2Bonus;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Models\X2BonusDefinition;
use App\Services\BinaryTreeService;
use App\Services\PartnerRegistrationService;
use App\Services\WalletService;
use App\Services\X2BonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_preview_delete(): void
    {
        $partner = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->getJson("/api/admin/partners/{$partner->id}/delete-preview")
            ->assertForbidden();

        Sanctum::actingAs($this->superAdmin());
        $this->getJson("/api/admin/partners/{$partner->id}/delete-preview")
            ->assertOk()
            ->assertJsonPath('has_children', false)
            ->assertJsonPath('descendants_count', 0);
    }

    public function test_only_super_admin_can_delete(): void
    {
        $partner = User::factory()->create(['role' => User::ROLE_USER]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())
            ->assertForbidden();
        $this->assertNull($partner->fresh()->deleted_at);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())
            ->assertOk()
            ->assertJsonPath('message', 'Партнёр удалён, перерасчёт выполнен')
            ->assertJsonPath('deleted_users_count', 1);

        $this->assertSoftDeleted('users', ['id' => $partner->id]);
    }

    public function test_cannot_delete_self(): void
    {
        $admin = $this->superAdmin();
        $this->superAdmin();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/partners/{$admin->id}", $this->deletePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }

    public function test_cannot_delete_last_super_admin(): void
    {
        $admin = $this->superAdmin();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/partners/{$admin->id}", $this->deletePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user')
            ->assertJsonFragment(['Нельзя удалить последнего super_admin.']);
    }

    public function test_cannot_delete_partner_with_children_without_delete_subtree_true(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'delete_tree_root']);
        $child = $this->registerPartner('delete_tree_child', $sponsor, 'left', $start);
        $this->registerPartner('delete_tree_grandchild', $child, 'left', $start);

        Sanctum::actingAs($this->superAdmin());

        $this->deleteJson("/api/admin/partners/{$child->id}", $this->deletePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delete_subtree')
            ->assertJsonFragment(['У партнёра есть структура. Выберите удаление вместе с поддеревом.']);
    }

    public function test_leaf_delete_soft_deletes_user(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'leaf_soft_sponsor']);
        $partner = $this->registerPartner('leaf_soft_partner', $sponsor, 'left', $start);
        $admin = $this->superAdmin();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload(['reason' => 'Тестовый leaf']))
            ->assertOk();

        $deleted = User::withTrashed()->findOrFail($partner->id);

        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame($admin->id, $deleted->deleted_by);
        $this->assertSame('Тестовый leaf', $deleted->deleted_reason);
        $this->assertSame('inactive', $deleted->account_status);
    }

    public function test_deleted_partner_disappears_from_admin_partners(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'admin_list_sponsor']);
        $partner = $this->registerPartner('admin-list-deleted', $sponsor, 'left', $start);
        $admin = $this->superAdmin();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $response = $this->getJson('/api/admin/partners?search=admin-list-deleted')
            ->assertOk();

        $this->assertSame([], collect($response->json('data'))->pluck('login')->all());
    }

    public function test_deleted_partner_disappears_from_dashboard_structure(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'dash_structure_sponsor']);
        $partner = $this->registerPartner('dash-structure-deleted', $sponsor, 'left', $start);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        Sanctum::actingAs($sponsor->refresh());
        $response = $this->getJson('/api/dashboard/structure')
            ->assertOk();

        $this->assertSame(0, $response->json('summary.total_partners'));
        $this->assertNotContains($partner->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_affected_parent_left_right_counts_recalculated(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'count_recalc_sponsor']);
        $left = $this->registerPartner('count-recalc-left', $sponsor, 'left', $start);
        $this->registerPartner('count-recalc-right', $sponsor, 'right', $start);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$left->id}", $this->deletePayload())->assertOk();

        Sanctum::actingAs($sponsor->refresh());
        $this->getJson('/api/dashboard/structure')
            ->assertOk()
            ->assertJsonPath('summary.left_count', 0)
            ->assertJsonPath('summary.right_count', 1);
    }

    public function test_affected_parent_left_right_pv_recalculated(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'pv_recalc_sponsor']);
        $left = $this->registerPartner('pv-recalc-left', $sponsor, 'left', $start);

        $this->assertSame('100.00', $sponsor->refresh()->left_pv);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$left->id}", $this->deletePayload())->assertOk();

        $sponsor->refresh();
        $this->assertSame('0.00', $sponsor->left_pv);
        $this->assertSame('0.00', $sponsor->right_pv);
        $this->assertSame('0.00', $sponsor->remaining_left_pv);
    }

    public function test_referral_bonus_caused_by_deleted_user_is_reversed(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'bonus_reverse_sponsor']);
        $partner = $this->registerPartner('bonus-reverse-partner', $sponsor, 'left', $start);
        $bonus = BonusTransaction::query()
            ->where('bonus_type', 'referral')
            ->where('source_user_id', $partner->id)
            ->firstOrFail();

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->assertSame('reversed', $bonus->refresh()->status);
        $this->assertDatabaseHas('wallet_transactions', [
            'source_type' => BonusTransaction::class,
            'source_id' => $bonus->id,
            'type' => 'referral_bonus_reversal',
            'direction' => 'debit',
            'amount' => '5000.00',
            'status' => 'completed',
        ]);
    }

    public function test_sponsor_balance_decreases_after_reversal(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'balance_reverse_sponsor']);
        $partner = $this->registerPartner('balance-reverse-partner', $sponsor, 'left', $start);

        $wallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();
        $this->assertSame('5000.00', $wallet->balance);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->assertSame('0.00', $wallet->refresh()->balance);
    }

    public function test_package_transaction_is_voided_but_does_not_affect_balance(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'package_void_sponsor']);
        $partner = $this->registerPartner('package-void-partner', $sponsor, 'left', $start);
        $transaction = WalletTransaction::query()
            ->where('user_id', $partner->id)
            ->where('type', 'package_assignment')
            ->firstOrFail();

        $this->assertFalse((bool) $transaction->affects_balance);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->assertSame('voided', $transaction->refresh()->status);
        $this->assertSame(0, WalletTransaction::query()
            ->where('user_id', $partner->id)
            ->where('type', 'package_assignment')
            ->where('status', 'completed')
            ->count());
    }

    public function test_pending_withdrawal_of_deleted_user_cancelled(): void
    {
        $partner = User::factory()->create(['role' => User::ROLE_USER]);
        $wallet = $this->mainWallet($partner, 750, 250);
        $withdrawal = WithdrawalRequest::query()->create([
            'user_id' => $partner->id,
            'wallet_id' => $wallet->id,
            'amount' => 250,
            'fee_amount' => 0,
            'net_amount' => 250,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->assertSame('cancelled', $withdrawal->refresh()->status);
        $this->assertSame('0.00', $wallet->refresh()->hold_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal_cancelled',
            'amount' => '250.00',
            'status' => 'completed',
        ]);
    }

    public function test_active_order_of_deleted_user_cancelled_voided_and_stock_restored(): void
    {
        $partner = User::factory()->create(['role' => User::ROLE_USER]);
        $product = $this->product(stock: 10);

        Sanctum::actingAs($partner);
        $orderId = $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertCreated()->json('order.id');

        $this->assertSame(8, $product->refresh()->stock_quantity);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'voided',
            'payment_status' => 'cancelled',
        ]);
        $this->assertSame(10, $product->refresh()->stock_quantity);
    }

    public function test_delete_subtree_deletes_all_descendants(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'subtree_root']);
        $child = $this->registerPartner('subtree-child', $sponsor, 'left', $start);
        $grandchild = $this->registerPartner('subtree-grandchild', $child, 'left', $start);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$child->id}", $this->deletePayload(['delete_subtree' => true]))
            ->assertOk()
            ->assertJsonPath('deleted_users_count', 2);

        $this->assertSoftDeleted('users', ['id' => $child->id]);
        $this->assertSoftDeleted('users', ['id' => $grandchild->id]);
        $this->assertSoftDeleted('binary_nodes', ['user_id' => $child->id]);
        $this->assertSoftDeleted('binary_nodes', ['user_id' => $grandchild->id]);
    }

    public function test_deleted_users_cannot_login(): void
    {
        $partner = User::factory()->create([
            'role' => User::ROLE_USER,
            'login' => 'deleted_login_user',
            'email' => 'deleted-login@example.test',
        ]);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->postJson('/api/login', [
            'login' => 'deleted_login_user',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_deleted_users_excluded_from_binary_calculation(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create([
            'login' => 'binary_exclusion_sponsor',
            'current_package_id' => $start->id,
        ]);
        app(WalletService::class)->createUserWallets($sponsor);
        $left = $this->registerPartner('binary-exclusion-left', $sponsor, 'left', $start);
        $this->registerPartner('binary-exclusion-right', $sponsor, 'right', $start);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$left->id}", $this->deletePayload())->assertOk();

        $this->postJson("/api/admin/partners/{$sponsor->id}/binary-bonus/calculate")
            ->assertOk()
            ->assertJsonPath('bonus_transaction', null);
        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'binary')->where('status', 'completed')->count());
    }

    public function test_deleted_users_excluded_from_status_calculation(): void
    {
        $start = $this->createPackage('START', ['activity_pv' => 1000, 'turnover_pv' => 1000]);
        $sponsor = User::factory()->create(['login' => 'status_exclusion_sponsor']);
        $left = $this->registerPartner('status-exclusion-left', $sponsor, 'left', $start);
        $this->registerPartner('status-exclusion-right', $sponsor, 'right', $start);

        $this->assertSame('manager', $sponsor->refresh()->status);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$left->id}", $this->deletePayload())->assertOk();

        $this->assertSame('user', $sponsor->refresh()->status);
    }

    public function test_deleted_users_excluded_from_x2_calculation(): void
    {
        $sponsor = User::factory()->create(['login' => 'x2_exclusion_sponsor']);
        app(BinaryTreeService::class)->placeUser($sponsor);
        $deletedLeaf = null;

        foreach (['L' => 2, 'R' => 2] as $side => $count) {
            for ($index = 0; $index < $count; $index++) {
                $partner = User::factory()->create([
                    'sponsor_id' => $sponsor->id,
                    'status' => 'manager',
                ]);
                app(BinaryTreeService::class)->placeUser($partner, $sponsor, $side);

                if ($side === 'L' && $index === 1) {
                    $deletedLeaf = $partner;
                }
            }
        }

        $this->createX2Definition('x2_before_delete');
        app(X2BonusService::class)->awardEligible($sponsor);
        $this->assertDatabaseHas('user_x2_bonuses', ['code' => 'x2_before_delete']);

        $this->createX2Definition('x2_after_delete');
        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$deletedLeaf->id}", $this->deletePayload())->assertOk();

        app(X2BonusService::class)->awardEligible($sponsor->refresh());

        $this->assertDatabaseMissing('user_x2_bonuses', ['code' => 'x2_after_delete']);
        $this->assertSame(3, BinaryNode::query()
            ->where('path', 'like', $sponsor->binaryNode()->firstOrFail()->path.'.%')
            ->where('is_active', true)
            ->count());
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function deletePayload(array $overrides = []): array
    {
        return [
            'delete_subtree' => false,
            'reason' => 'Тестовое удаление',
            ...$overrides,
        ];
    }

    private function registerPartner(string $login, User $sponsor, string $branch, Package $package): User
    {
        return app(PartnerRegistrationService::class)->register([
            'name' => "Partner {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+7700'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'referral_code' => $sponsor->login,
            'branch' => $branch,
            'package_id' => $package->id,
            'role' => User::ROLE_USER,
        ], source: 'admin_test_registration');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPackage(string $code, array $overrides = []): Package
    {
        $matrix = [
            'START' => ['price' => 60000, 'activity_pv' => 100, 'turnover_pv' => 100, 'binary_percent' => 7, 'sort_order' => 1],
            'VIP' => ['price' => 180000, 'activity_pv' => 300, 'turnover_pv' => 300, 'binary_percent' => 8, 'sort_order' => 2],
            'ELITE' => ['price' => 300000, 'activity_pv' => 500, 'turnover_pv' => 200, 'binary_percent' => 10, 'sort_order' => 3],
        ];
        $values = [...$matrix[$code], ...$overrides];

        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $values['price'],
            'pv' => $values['activity_pv'],
            'activity_pv' => $values['activity_pv'],
            'turnover_pv' => $values['turnover_pv'],
            'referral_percent' => 10,
            'binary_percent' => $values['binary_percent'],
            'sort_order' => $values['sort_order'],
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function mainWallet(User $user, int $balance = 0, int $holdBalance = 0): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => $balance,
            'hold_balance' => $holdBalance,
            'status' => 'active',
        ]);
    }

    private function product(int $stock): Product
    {
        return Product::query()->create([
            'name' => 'Safi Delete Test Product',
            'sku' => 'DELETE-'.uniqid(),
            'description' => 'Safi Delete Test Product',
            'price' => 18000,
            'pv' => 30,
            'stock_quantity' => $stock,
            'reserved_quantity' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function orderPayload(array $items): array
    {
        return [
            'items' => $items,
            'recipient_name' => 'Safi Client',
            'phone' => '+77010000000',
            'city' => 'Almaty',
            'delivery_address' => 'Abay 10',
        ];
    }

    private function createX2Definition(string $code): X2BonusDefinition
    {
        return X2BonusDefinition::query()->create([
            'code' => $code,
            'required_status' => 'manager',
            'required_count' => 4,
            'reward_type' => 'cash',
            'amount' => 100,
            'currency' => 'KZT',
            'reward_text' => $code,
            'is_cash_bonus' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
