<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActionLog;
use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerBalanceAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_set_partner_balance_with_audit_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $package = $this->package('START', 100);
        $partner = User::factory()->create([
            'current_package_id' => $package->id,
            'left_pv' => 300,
            'right_pv' => 500,
            'total_pv' => 900,
        ]);
        Wallet::query()->create([
            'user_id' => $partner->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 100000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/partners/{$partner->id}/balance", [
            'mode' => 'set',
            'amount' => 150000,
            'comment' => 'Manual correction',
        ])->assertOk();

        $this->assertSame('Balance updated', $response->json('message'));
        $this->assertEquals(100000, $response->json('data.old_balance'));
        $this->assertEquals(150000, $response->json('data.new_balance'));

        $partner->refresh();
        $this->assertSame($package->id, $partner->current_package_id);
        $this->assertSame('300.00', $partner->left_pv);
        $this->assertSame('500.00', $partner->right_pv);
        $this->assertSame('900.00', $partner->total_pv);
        $this->assertSame('150000.00', Wallet::query()->where('user_id', $partner->id)->where('type', 'main')->firstOrFail()->balance);

        $transaction = WalletTransaction::query()
            ->where('user_id', $partner->id)
            ->where('type', 'manual_adjustment')
            ->firstOrFail();

        $this->assertSame('credit', $transaction->direction);
        $this->assertSame('50000.00', $transaction->amount);
        $this->assertSame('100000.00', $transaction->balance_before);
        $this->assertSame('150000.00', $transaction->balance_after);
        $this->assertTrue((bool) $transaction->affects_balance);

        $auditLog = AdminActionLog::query()
            ->where('admin_id', $admin->id)
            ->where('target_user_id', $partner->id)
            ->where('action', 'balance_update')
            ->firstOrFail();

        $this->assertSame('Manual correction', $auditLog->reason);
        $this->assertSame('set', $auditLog->metadata['mode']);
        $this->assertSame('100000.00', $auditLog->metadata['old_balance']);
        $this->assertSame('150000.00', $auditLog->metadata['new_balance']);
        $this->assertSame($transaction->id, $auditLog->metadata['wallet_transaction_id']);
    }

    public function test_super_admin_can_adjust_partner_balance_down_without_changing_pv(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create([
            'left_pv' => 900,
            'right_pv' => 1000,
            'total_pv' => 2000,
        ]);
        Wallet::query()->create([
            'user_id' => $partner->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 150000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/balance", [
            'mode' => 'adjust',
            'amount' => -50000,
            'comment' => 'Correction',
        ])->assertOk()
            ->assertJsonPath('data.old_balance', 150000)
            ->assertJsonPath('data.new_balance', 100000);

        $partner->refresh();
        $this->assertSame('900.00', $partner->left_pv);
        $this->assertSame('1000.00', $partner->right_pv);
        $this->assertSame('2000.00', $partner->total_pv);

        $transaction = WalletTransaction::query()->where('user_id', $partner->id)->firstOrFail();

        $this->assertSame('debit', $transaction->direction);
        $this->assertSame('50000.00', $transaction->amount);
        $this->assertSame('100000.00', $transaction->balance_after);
    }

    public function test_regular_admin_cannot_update_partner_balance(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $partner = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $partner->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 100000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/balance", [
            'mode' => 'set',
            'amount' => 150000,
        ])->assertForbidden();

        $this->assertSame('100000.00', Wallet::query()->where('user_id', $partner->id)->where('type', 'main')->firstOrFail()->balance);
        $this->assertSame(0, AdminActionLog::query()->where('target_user_id', $partner->id)->count());
        $this->assertSame(0, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_partner_cannot_update_another_partner_balance(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_USER]);
        $partner = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $partner->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 100000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $this->patchJson("/api/admin/partners/{$partner->id}/balance", [
            'mode' => 'set',
            'amount' => 150000,
        ])->assertForbidden();

        $this->assertSame('100000.00', Wallet::query()->where('user_id', $partner->id)->where('type', 'main')->firstOrFail()->balance);
        $this->assertSame(0, AdminActionLog::query()->where('target_user_id', $partner->id)->count());
        $this->assertSame(0, WalletTransaction::query()->where('user_id', $partner->id)->count());
    }

    public function test_adjust_balance_cannot_make_balance_negative(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $partner = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $partner->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 100,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/partners/{$partner->id}/balance", [
            'mode' => 'adjust',
            'amount' => -500,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertSame('100.00', Wallet::query()->where('user_id', $partner->id)->where('type', 'main')->firstOrFail()->balance);
    }

    private function package(string $code, int $pv): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $pv * 500,
            'pv' => $pv,
            'activity_pv' => $pv,
            'turnover_pv' => $pv,
            'referral_percent' => 0,
            'binary_percent' => 0,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
