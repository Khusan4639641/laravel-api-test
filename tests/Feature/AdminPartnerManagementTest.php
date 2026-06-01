<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_change_partner_password(): void
    {
        $partner = User::factory()->create(['password' => Hash::make('old-password')]);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->postJson("/api/admin/partners/{$partner->id}/change-password", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed')
            ->assertJsonPath('credentials.login', $partner->login)
            ->assertJsonPath('credentials.password', 'new-password');

        $partner->refresh();

        $this->assertNotSame('new-password', $partner->password);
        $this->assertTrue(Hash::check('new-password', $partner->password));

        $this->postJson('/api/login', [
            'login' => $partner->login,
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_normal_user_and_support_cannot_change_partner_password(): void
    {
        $partner = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->postJson("/api/admin/partners/{$partner->id}/change-password", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'support']));
        $this->postJson("/api/admin/partners/{$partner->id}/change-password", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertForbidden();
    }

    public function test_super_admin_can_block_and_unblock_partner(): void
    {
        $partner = User::factory()->create(['password' => Hash::make('password')]);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->patchJson("/api/admin/partners/{$partner->id}/block")
            ->assertOk()
            ->assertJsonPath('user.account_status', 'blocked');

        $this->postJson('/api/login', [
            'login' => $partner->login,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->patchJson("/api/admin/partners/{$partner->id}/unblock")
            ->assertOk()
            ->assertJsonPath('user.account_status', 'active');

        $this->postJson('/api/login', [
            'login' => $partner->login,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_blocked_partner_cannot_use_protected_api(): void
    {
        $partner = User::factory()->create(['account_status' => 'blocked']);

        Sanctum::actingAs($partner);

        $this->getJson('/api/me')->assertForbidden();
    }

    public function test_super_admin_can_change_partner_package_manually(): void
    {
        $partner = User::factory()->create();
        $package = $this->package('VIP');

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => $package->id,
        ])
            ->assertOk()
            ->assertJsonPath('user.current_package_id', $package->id);

        $this->assertDatabaseHas('users', [
            'id' => $partner->id,
            'current_package_id' => $package->id,
        ]);
    }

    public function test_package_must_exist_and_normal_user_cannot_change_package(): void
    {
        $partner = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => 999999,
        ])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->patchJson("/api/admin/partners/{$partner->id}/package", [
            'package_id' => 999999,
        ])->assertForbidden();
    }

    public function test_super_admin_can_change_partner_status_manually(): void
    {
        $partner = User::factory()->create(['status' => 'user']);

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'manager',
        ])
            ->assertOk()
            ->assertJsonPath('user.status', 'manager');

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'invalid',
        ])->assertUnprocessable();
    }

    public function test_normal_user_cannot_change_status_or_note(): void
    {
        $partner = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->patchJson("/api/admin/partners/{$partner->id}/status", [
            'status' => 'manager',
        ])->assertForbidden();

        $this->patchJson("/api/admin/partners/{$partner->id}/note", [
            'admin_note' => 'Hidden note',
        ])->assertForbidden();
    }

    public function test_super_admin_can_save_admin_note(): void
    {
        $partner = User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->patchJson("/api/admin/partners/{$partner->id}/note", [
            'admin_note' => 'Important internal note',
        ])
            ->assertOk()
            ->assertJsonPath('user.admin_note', 'Important internal note');

        $this->assertDatabaseHas('users', [
            'id' => $partner->id,
            'admin_note' => 'Important internal note',
        ]);
    }

    public function test_super_admin_can_view_only_selected_partner_transactions(): void
    {
        $partner = User::factory()->create();
        $otherPartner = User::factory()->create();
        $partnerWallet = $this->walletFor($partner);
        $otherWallet = $this->walletFor($otherPartner);

        $partnerTransaction = $this->transactionFor($partner, $partnerWallet, 'partner-credit');
        $this->transactionFor($otherPartner, $otherWallet, 'other-credit');

        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson("/api/admin/partners/{$partner->id}/transactions")
            ->assertOk()
            ->assertJsonCount(1, 'transactions')
            ->assertJsonPath('transactions.0.id', $partnerTransaction->id)
            ->assertJsonPath('transactions.0.user_id', $partner->id);
    }

    private function package(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => 100,
            'pv' => 10,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function walletFor(User $user): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 100,
            'hold_balance' => 0,
            'status' => 'active',
        ]);
    }

    private function transactionFor(User $user, Wallet $wallet, string $type): WalletTransaction
    {
        return WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'type' => $type,
            'direction' => 'credit',
            'amount' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'status' => 'completed',
        ]);
    }
}
