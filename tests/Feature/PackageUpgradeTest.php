<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upgrade_start_to_business_with_cashback_and_additional_pv(): void
    {
        [$start, $business] = $this->createPackages(['START', 'BUSINESS']);
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'total_pv' => $start->pv,
            'status' => 'silver_director',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/packages/{$business->id}/upgrade");

        $response
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $business->id)
            ->assertJsonPath('payment_amount', '30000.00')
            ->assertJsonPath('additional_pv', '30000.00')
            ->assertJsonPath('cashback_amount', '3000.00');

        $user->refresh();
        $bonusWallet = $user->wallets()->where('type', 'bonus')->firstOrFail();
        $cashbackTransaction = WalletTransaction::query()
            ->where('type', 'package_upgrade_cashback')
            ->firstOrFail();

        $this->assertSame($business->id, $user->current_package_id);
        $this->assertSame('60000.00', $user->total_pv);
        $this->assertSame('gold_director', $user->status);
        $this->assertSame('3000.00', $bonusWallet->balance);
        $this->assertSame('3000.00', $cashbackTransaction->amount);
        $this->assertSame('credit', $cashbackTransaction->direction);
    }

    public function test_user_cannot_skip_upgrade_chain(): void
    {
        [$start, , $vip] = $this->createPackages(['START', 'BUSINESS', 'VIP']);
        $user = User::factory()->create([
            'current_package_id' => $start->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');

        $this->assertSame($start->id, $user->refresh()->current_package_id);
    }

    public function test_user_cannot_activate_elite_directly(): void
    {
        [$elite] = $this->createPackages(['ELITE']);
        $user = User::factory()->create([
            'current_package_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/activate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
    }

    public function test_vip_to_elite_upgrade_does_not_create_cashback(): void
    {
        [$vip, $elite] = $this->createPackages(['VIP', 'ELITE']);
        $user = User::factory()->create([
            'current_package_id' => $vip->id,
            'total_pv' => $vip->pv,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('payment_amount', '120000.00')
            ->assertJsonPath('additional_pv', '120000.00')
            ->assertJsonPath('cashback_amount', '0.00');

        $user->refresh();

        $this->assertSame($elite->id, $user->current_package_id);
        $this->assertSame('300000.00', $user->total_pv);
        $this->assertDatabaseMissing('wallet_transactions', [
            'type' => 'package_upgrade_cashback',
        ]);
    }

    public function test_user_without_current_package_cannot_upgrade(): void
    {
        [$business] = $this->createPackages(['BUSINESS']);
        $user = User::factory()->create([
            'current_package_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$business->id}/upgrade")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, Package>
     */
    private function createPackages(array $codes): array
    {
        $prices = [
            'START' => 30000,
            'BUSINESS' => 60000,
            'VIP' => 180000,
            'ELITE' => 300000,
        ];
        $sortOrders = [
            'START' => 1,
            'BUSINESS' => 2,
            'VIP' => 3,
            'ELITE' => 4,
        ];

        return array_map(
            fn (string $code): Package => Package::query()->create([
                'code' => $code,
                'name' => $code,
                'slug' => strtolower($code),
                'price' => $prices[$code],
                'pv' => $prices[$code],
                'referral_percent' => 0,
                'binary_percent' => 0,
                'sort_order' => $sortOrders[$code],
                'status' => 'active',
                'is_active' => true,
                'is_upgradeable' => true,
            ]),
            $codes,
        );
    }
}
