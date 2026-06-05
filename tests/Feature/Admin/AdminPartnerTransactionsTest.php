<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPartnerTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_selected_partner_transactions_sorted_desc(): void
    {
        $partner = User::factory()->create();
        app(WalletService::class)->createUserWallets($partner);
        $wallet = $partner->wallets()->where('type', 'main')->firstOrFail();

        $old = app(WalletService::class)->credit($wallet, 1000, 'referral_bonus', null, [], 'Old transaction');
        $old->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $new = app(WalletService::class)->credit($wallet->refresh(), 50000, 'package_activity_credit', null, [], 'Manual package assignment: START');
        $new->forceFill(['created_at' => now(), 'updated_at' => now()])->save();

        $other = User::factory()->create();
        app(WalletService::class)->createUserWallets($other);
        app(WalletService::class)->credit($other->wallets()->where('type', 'main')->firstOrFail(), 99999, 'package_activity_credit');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $transactions = $this->getJson("/api/admin/partners/{$partner->id}/transactions?limit=10")
            ->assertOk()
            ->json('transactions');

        $this->assertCount(2, $transactions);
        $this->assertSame($new->id, $transactions[0]['id']);
        $this->assertSame('package_activity_credit', $transactions[0]['type']);
        $this->assertSame('50000.00', $transactions[0]['amount']);
        $this->assertSame('Manual package assignment: START', $transactions[0]['description']);
        $this->assertSame($old->id, $transactions[1]['id']);
        $this->assertEquals([$partner->id], collect($transactions)->pluck('user_id')->unique()->values()->all());
    }

    public function test_user_and_support_cannot_access_admin_partner_transactions(): void
    {
        $partner = User::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));
        $this->getJson("/api/admin/partners/{$partner->id}/transactions")->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPPORT]));
        $this->getJson("/api/admin/partners/{$partner->id}/transactions")->assertForbidden();
    }
}
