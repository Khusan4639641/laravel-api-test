<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageActivationTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_start_activation_creates_package_activation_transaction_with_package_price(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $start->id)
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0)
            ->assertJsonPath('user.package_activity_pv', 100);

        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $transaction = WalletTransaction::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('0.00', $wallet->balance);
        $this->assertSame('package_activation', $transaction->type);
        $this->assertSame('neutral', $transaction->direction);
        $this->assertFalse($transaction->affects_balance);
        $this->assertSame('60000.00', $transaction->amount);
        $this->assertSame('60000.00', $transaction->metadata['package_price']);
        $this->assertSame('100.00', $transaction->metadata['activity_pv']);
        $this->assertSame('100.00', $transaction->metadata['turnover_pv']);
        $this->assertFalse($transaction->metadata['affects_balance']);
    }

    public function test_user_package_activation_does_not_credit_own_balance(): void
    {
        $user = User::factory()->create();
        $vip = $this->package('VIP');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/activate")
            ->assertOk()
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0);

        $this->assertSame('0.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_activation',
            'direction' => 'neutral',
            'amount' => '180000.00',
            'affects_balance' => false,
        ]);
    }

    public function test_upgrade_start_to_vip_creates_transaction_with_price_difference(): void
    {
        [$start, $vip] = $this->packages(['START', 'VIP']);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();
        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('payment_amount', '120000.00')
            ->assertJsonPath('transaction_amount', '120000.00')
            ->assertJsonPath('additional_pv', '200.00')
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0);

        $this->assertSame('0.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'package_upgrade',
            'direction' => 'neutral',
            'amount' => '120000.00',
            'affects_balance' => false,
        ]);
    }

    public function test_upgrade_does_not_credit_own_balance(): void
    {
        [$vip, $elite] = $this->packages(['VIP', 'ELITE']);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/activate")->assertOk();
        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('payment_amount', '120000.00')
            ->assertJsonPath('transaction_amount', '120000.00')
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0);

        $this->assertSame('0.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $user->id)->where('type', 'package_activation')->count());
        $this->assertSame(1, WalletTransaction::query()->where('user_id', $user->id)->where('type', 'package_upgrade')->count());
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
        $this->assertSame('0.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
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
        $this->assertSame('0.00', $child->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_user_sees_package_purchase_transaction_in_dashboard_transactions(): void
    {
        $user = User::factory()->create();
        $start = $this->package('START');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$start->id}/activate")->assertOk();

        $this->getJson('/api/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('transactions.0.type', 'package_activation')
            ->assertJsonPath('transactions.0.amount', '60000.00')
            ->assertJsonPath('transactions.0.affects_balance', false)
            ->assertJsonPath('transactions.0.metadata.package_price', '60000.00')
            ->assertJsonPath('summary.total_earned', '0')
            ->assertJsonPath('summary.available', '0');
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
