<?php

namespace Tests\Feature;

use App\Models\BinaryNode;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerSoftDeleteStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_user_not_counted_in_admin_partners_summary(): void
    {
        $deleted = $this->partner('summary-deleted');
        $active = $this->partner('summary-active');
        $admin = $this->superAdmin();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/partners/{$deleted->id}", $this->deletePayload())->assertOk();

        $response = $this->getJson('/api/admin/partners')
            ->assertOk();

        $this->assertSame(1, $response->json('summary.total_partners'));
        $this->assertSame(1, $response->json('summary.active_partners'));
        $this->assertSame([$active->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_deleted_user_not_counted_in_dashboard_structure_or_admin_structure(): void
    {
        [$root, $child] = [$this->partner('structure-root'), $this->partner('structure-child')];
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($root);
        $tree->placeUser($child, $root, 'L');

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$child->id}", $this->deletePayload())->assertOk();

        Sanctum::actingAs($root->refresh());
        $dashboard = $this->getJson('/api/dashboard/structure')
            ->assertOk();
        $this->assertSame(0, $dashboard->json('summary.total_partners'));
        $this->assertSame([], collect($dashboard->json('partners.data'))->pluck('id')->all());

        Sanctum::actingAs($this->superAdmin());
        $adminStructure = $this->getJson("/api/admin/structure?user_id={$root->id}&include_flat=1")
            ->assertOk();
        $this->assertSame(0, $adminStructure->json('summary.total_downline_count'));
        $this->assertNotContains($child->id, collect($adminStructure->json('nodes'))->pluck('id')->all());
    }

    public function test_deleted_user_transactions_excluded_from_active_admin_transactions_summary(): void
    {
        $partner = $this->partner('tx-deleted');
        $wallet = $this->wallet($partner);
        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $partner->id,
            'type' => 'package_activation',
            'direction' => 'neutral',
            'amount' => 60000,
            'balance_before' => 0,
            'balance_after' => 0,
            'status' => 'completed',
            'affects_balance' => false,
        ]);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonPath('summary.operation_turnover', '0');
    }

    public function test_deleted_user_orders_excluded_from_active_report_order_totals(): void
    {
        $active = $this->partner('orders-active');
        $deleted = $this->partner('orders-deleted');
        Order::query()->create([
            'user_id' => $active->id,
            'order_number' => 'ORD-ACTIVE',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100,
            'total_pv' => 10,
        ]);
        Order::query()->create([
            'user_id' => $deleted->id,
            'order_number' => 'ORD-DELETED',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 200,
            'total_pv' => 20,
        ]);
        $deleted->forceFill(['account_status' => 'inactive'])->save();
        $deleted->delete();

        Sanctum::actingAs($this->superAdmin());
        $this->getJson('/api/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('summary.total_turnover', 100)
            ->assertJsonPath('packages.sold', 1);
    }

    public function test_pending_withdrawal_of_deleted_user_is_cancelled_and_excluded(): void
    {
        $partner = $this->partner('withdrawal-deleted');
        $wallet = $this->wallet($partner, 500, 250);
        $withdrawal = WithdrawalRequest::query()->create([
            'user_id' => $partner->id,
            'wallet_id' => $wallet->id,
            'amount' => 250,
            'fee_amount' => 0,
            'net_amount' => 250,
            'currency' => 'KZT',
            'status' => 'pending',
            'payout_period_days' => 14,
        ]);

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/admin/partners/{$partner->id}", $this->deletePayload())->assertOk();

        $this->assertSame('cancelled', $withdrawal->refresh()->status);
        $this->getJson('/api/admin/withdrawals')
            ->assertOk()
            ->assertJsonCount(0, 'withdrawals');
    }

    public function test_stale_active_binary_node_with_deleted_user_is_not_counted(): void
    {
        $root = $this->partner('stale-root');
        $deleted = $this->partner('stale-deleted');
        BinaryNode::query()->create([
            'user_id' => $root->id,
            'parent_id' => null,
            'position' => null,
            'depth' => 0,
            'path' => (string) $root->id,
            'is_active' => true,
        ]);
        BinaryNode::query()->create([
            'user_id' => $deleted->id,
            'parent_id' => $root->binaryNode()->firstOrFail()->id,
            'position' => 'L',
            'depth' => 1,
            'path' => "{$root->id}.{$deleted->id}",
            'is_active' => true,
        ]);
        $deleted->forceFill(['account_status' => 'inactive'])->save();
        $deleted->delete();

        Sanctum::actingAs($root);
        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('team_count', 0)
            ->assertJsonPath('structure.total_partners', 0);
    }

    private function partner(string $login): User
    {
        return User::factory()->create([
            'login' => $login,
            'email' => "{$login}@example.test",
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $this->package()->id,
        ]);
    }

    private function package(): Package
    {
        return Package::query()->firstOrCreate(
            ['code' => 'START'],
            [
                'name' => 'START',
                'slug' => 'start',
                'price' => 60000,
                'pv' => 100,
                'activity_pv' => 100,
                'turnover_pv' => 100,
                'referral_percent' => 10,
                'binary_percent' => 7,
                'sort_order' => 1,
                'status' => 'active',
                'is_active' => true,
                'is_upgradeable' => true,
            ],
        );
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function wallet(User $user, int $balance = 0, int $holdBalance = 0): Wallet
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function deletePayload(array $overrides = []): array
    {
        return [
            'delete_subtree' => false,
            'reason' => 'Тестовое удаление stats',
            ...$overrides,
        ];
    }
}
