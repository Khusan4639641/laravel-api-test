<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPackageAssignmentTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_start_assignment_creates_package_transaction_with_package_price(): void
    {
        [$partner, $transaction] = $this->assignPackage('START');

        $this->assertSame('package_assignment', $transaction->type);
        $this->assertSame('neutral', $transaction->direction);
        $this->assertFalse($transaction->affects_balance);
        $this->assertSame('60000.00', $transaction->amount);
        $this->assertSame('START', $transaction->metadata['package_code']);
        $this->assertSame('60000.00', $transaction->metadata['package_price']);
        $this->assertSame('100.00', $transaction->metadata['activity_pv']);
        $this->assertSame('100.00', $transaction->metadata['turnover_pv']);
        $this->assertFalse($transaction->metadata['affects_balance']);
        $this->assertSame('0.00', $partner->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_admin_start_assignment_does_not_credit_balance(): void
    {
        [$partner] = $this->assignPackage('START');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson("/api/admin/partners/{$partner->id}")
            ->assertOk()
            ->assertJsonPath('user.available_balance', 0)
            ->assertJsonPath('user.total_earned', 0)
            ->assertJsonPath('user.package_activity_pv', 100)
            ->assertJsonPath('recent_transactions.0.type', 'package_assignment')
            ->assertJsonPath('recent_transactions.0.amount', '60000.00')
            ->assertJsonPath('recent_transactions.0.affects_balance', false);
    }

    public function test_admin_vip_assignment_transaction_amount_is_package_price_and_balance_stays_zero(): void
    {
        [$partner, $transaction] = $this->assignPackage('VIP');

        $this->assertSame('180000.00', $transaction->amount);
        $this->assertSame('0.00', $partner->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_admin_elite_assignment_transaction_amount_is_package_price_and_balance_stays_zero(): void
    {
        [$partner, $transaction] = $this->assignPackage('ELITE');

        $this->assertSame('300000.00', $transaction->amount);
        $this->assertSame('0.00', $partner->wallets()->where('type', 'main')->firstOrFail()->balance);
    }

    public function test_personal_pv_is_correct_for_each_package(): void
    {
        foreach (['START' => '100.00', 'VIP' => '300.00', 'ELITE' => '500.00'] as $code => $expectedPv) {
            [$partner] = $this->assignPackage($code);

            $this->assertSame($expectedPv, $partner->refresh()->total_pv);
        }
    }

    public function test_turnover_pv_to_uplines_is_correct(): void
    {
        foreach (['START' => '100.00', 'VIP' => '300.00', 'ELITE' => '200.00'] as $code => $expectedTurnoverPv) {
            $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
            $sponsor = User::factory()->create();
            $partner = User::factory()->create(['sponsor_id' => $sponsor->id]);
            $package = $this->package($code);
            $tree = app(BinaryTreeService::class);
            $tree->placeUser($sponsor);
            $tree->placeUser($partner, $sponsor, 'L');

            Sanctum::actingAs($admin);

            $this->patchJson("/api/admin/partners/{$partner->id}/package", [
                'package_id' => $package->id,
                'apply_business_effects' => true,
            ])->assertOk();

            $this->assertSame($expectedTurnoverPv, $sponsor->refresh()->left_pv);
            $this->assertSame('0.00', $partner->refresh()->left_pv);
            $this->assertSame('0.00', $partner->right_pv);
        }
    }

    /**
     * @return array{0: User, 1: WalletTransaction}
     */
    private function assignPackage(string $code): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create(['total_pv' => 0]);
        $package = $this->package($code);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $package->id,
            'apply_business_effects' => true,
        ])->assertOk();

        return [
            $partner->refresh(),
            WalletTransaction::query()->where('user_id', $partner->id)->firstOrFail(),
        ];
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
