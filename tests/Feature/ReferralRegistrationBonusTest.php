<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BinaryTreeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralRegistrationBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_referral_registration_with_start_gives_sponsor_5000(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'sponsor_start']);

        $this->postJson('/api/register', $this->registrationPayload('public-start', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
            'package_id' => $start->id,
        ]))->assertCreated();

        $bonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();
        $wallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('5000.00', $bonus->amount);
        $this->assertSame('50000.00', $bonus->metadata['base_amount']);
        $this->assertSame('5000.00', $wallet->balance);
    }

    public function test_public_referral_registration_with_vip_gives_sponsor_15000(): void
    {
        $vip = $this->createPackage('VIP');
        $sponsor = User::factory()->create(['login' => 'sponsor_vip']);

        $this->postJson('/api/register', $this->registrationPayload('public-vip', [
            'ref' => $sponsor->login,
            'branch' => 'right',
            'package_id' => $vip->id,
        ]))->assertCreated();

        $user = User::query()->where('login', 'public-vip')->firstOrFail();
        $bonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();
        $walletTransaction = WalletTransaction::query()->where('type', 'referral_bonus')->firstOrFail();

        $this->assertSame($vip->id, $user->current_package_id);
        $this->assertSame('300.00', $user->total_pv);
        $this->assertSame('15000.00', $bonus->amount);
        $this->assertSame('15000.00', $walletTransaction->amount);
    }

    public function test_vip_referral_uses_pv_base_not_price(): void
    {
        $vip = $this->createPackage('VIP');
        $sponsor = User::factory()->create(['login' => 'sponsor_pv_base']);

        $this->postJson('/api/register', $this->registrationPayload('vip-pv-base', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
            'package_id' => $vip->id,
        ]))->assertCreated();

        $bonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();

        $this->assertSame('150000.00', $bonus->metadata['base_amount']);
        $this->assertSame('15000.00', $bonus->amount);
        $this->assertNotSame('18000.00', $bonus->amount);
    }

    public function test_public_registration_without_ref_gives_no_referral_bonus(): void
    {
        $start = $this->createPackage('START');

        $this->postJson('/api/register', $this->registrationPayload('public-no-ref', [
            'package_id' => $start->id,
        ]))->assertCreated();

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
    }

    public function test_elite_is_not_available_as_initial_package(): void
    {
        $elite = $this->createPackage('ELITE');

        $this->postJson('/api/register', $this->registrationPayload('elite-initial', [
            'package_id' => $elite->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package_id');

        $this->assertDatabaseMissing('users', ['login' => 'elite-initial']);
    }

    public function test_elite_upgrade_does_not_create_referral_bonus(): void
    {
        $vip = $this->createPackage('VIP');
        $elite = $this->createPackage('ELITE');
        $sponsor = User::factory()->create();
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $vip->id,
            'total_pv' => 300,
        ]);
        $tree = app(BinaryTreeService::class);
        $tree->placeUser($sponsor);
        $tree->placeUser($user, $sponsor, 'L');

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertOk();

        $this->assertSame(0, BonusTransaction::query()->where('bonus_type', 'referral')->count());
        $this->assertSame(0, WalletTransaction::query()->where('type', 'referral_bonus')->count());
    }

    public function test_new_user_package_transaction_has_affects_balance_false(): void
    {
        $vip = $this->createPackage('VIP');

        $this->postJson('/api/register', $this->registrationPayload('package-tx', [
            'package_id' => $vip->id,
        ]))->assertCreated();

        $user = User::query()->where('login', 'package-tx')->firstOrFail();
        $transaction = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'package_activation')
            ->firstOrFail();

        $this->assertSame('180000.00', $transaction->amount);
        $this->assertFalse((bool) $transaction->affects_balance);
    }

    public function test_sponsor_referral_transaction_has_affects_balance_true(): void
    {
        $start = $this->createPackage('START');
        $sponsor = User::factory()->create(['login' => 'sponsor_affects']);

        $this->postJson('/api/register', $this->registrationPayload('affects-balance', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
            'package_id' => $start->id,
        ]))->assertCreated();

        $transaction = WalletTransaction::query()->where('type', 'referral_bonus')->firstOrFail();

        $this->assertSame($sponsor->id, $transaction->user_id);
        $this->assertTrue((bool) $transaction->affects_balance);
    }

    public function test_sponsor_balance_increases_by_referral_bonus(): void
    {
        $vip = $this->createPackage('VIP');
        $sponsor = User::factory()->create(['login' => 'sponsor_balance']);
        app(WalletService::class)->createUserWallets($sponsor);

        $this->postJson('/api/register', $this->registrationPayload('balance-vip', [
            'ref' => $sponsor->login,
            'branch' => 'right',
            'package_id' => $vip->id,
        ]))->assertCreated();

        $wallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('15000.00', $wallet->refresh()->balance);
    }

    public function test_new_user_balance_does_not_increase_from_package_purchase(): void
    {
        $vip = $this->createPackage('VIP');
        $sponsor = User::factory()->create(['login' => 'sponsor_buyer_balance']);

        $this->postJson('/api/register', $this->registrationPayload('buyer-balance', [
            'referral_code' => $sponsor->login,
            'branch' => 'left',
            'package_id' => $vip->id,
        ]))->assertCreated();

        $user = User::query()->where('login', 'buyer-balance')->firstOrFail();
        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('0.00', $wallet->balance);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(string $login, array $overrides = []): array
    {
        return [
            'name' => "Referral {$login}",
            'login' => $login,
            'email' => "{$login}@example.test",
            'phone' => '+77000000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ];
    }

    private function createPackage(string $code): Package
    {
        $matrix = [
            'START' => ['price' => 60000, 'activity_pv' => 100, 'turnover_pv' => 100, 'binary_percent' => 7, 'sort_order' => 1],
            'VIP' => ['price' => 180000, 'activity_pv' => 300, 'turnover_pv' => 300, 'binary_percent' => 8, 'sort_order' => 2],
            'ELITE' => ['price' => 300000, 'activity_pv' => 500, 'turnover_pv' => 200, 'binary_percent' => 10, 'sort_order' => 3],
        ];
        $values = $matrix[$code];

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
}
