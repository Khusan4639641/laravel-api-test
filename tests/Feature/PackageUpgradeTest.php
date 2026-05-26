<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upgrade_start_to_vip_with_additional_pv_and_referral_bonus(): void
    {
        [$start, $vip] = $this->createPackages(['START', 'VIP']);
        $sponsor = User::factory()->create([
            'current_package_id' => $start->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'current_package_id' => $start->id,
            'total_pv' => $start->pv,
            'status' => 'gold_director',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/packages/{$vip->id}/upgrade");

        $response
            ->assertOk()
            ->assertJsonPath('user.current_package.id', $vip->id)
            ->assertJsonPath('payment_amount', '120000.00')
            ->assertJsonPath('additional_pv', '120000.00')
            ->assertJsonPath('cashback_amount', '0.00');

        $user->refresh();
        $sponsorMainWallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();
        $referralBonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();

        $this->assertSame($vip->id, $user->current_package_id);
        $this->assertSame('180000.00', $user->total_pv);
        $this->assertSame('platinum_director', $user->status);
        $this->assertSame('12000.00', $referralBonus->amount);
        $this->assertSame('12000.00', $sponsorMainWallet->balance);
        $this->assertDatabaseMissing('wallet_transactions', [
            'type' => 'package_upgrade_cashback',
        ]);
    }

    public function test_user_cannot_skip_start_to_elite_upgrade(): void
    {
        [$start, , $elite] = $this->createPackages(['START', 'VIP', 'ELITE']);
        $user = User::factory()->create([
            'current_package_id' => $start->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');

        $this->assertSame($start->id, $user->refresh()->current_package_id);
    }

    public function test_user_can_upgrade_vip_to_elite(): void
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
        $this->assertSame('emerald_director', $user->status);
    }

    public function test_elite_package_cannot_upgrade_further(): void
    {
        [$elite] = $this->createPackages(['ELITE']);
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'total_pv' => $elite->pv,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$elite->id}/upgrade")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
    }

    public function test_inactive_package_cannot_be_upgrade_target(): void
    {
        [$start, $vip] = $this->createPackages(['START', 'VIP']);
        $vip->forceFill([
            'status' => 'inactive',
            'is_active' => false,
        ])->save();
        $user = User::factory()->create([
            'current_package_id' => $start->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
    }

    public function test_user_without_current_package_cannot_upgrade(): void
    {
        [$vip] = $this->createPackages(['VIP']);
        $user = User::factory()->create([
            'current_package_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package');
    }

    public function test_upgrade_preserves_existing_accumulated_pv(): void
    {
        [$start, $vip] = $this->createPackages(['START', 'VIP']);
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'total_pv' => 75000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$vip->id}/upgrade")
            ->assertOk();

        $this->assertSame('195000.00', $user->refresh()->total_pv);
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, Package>
     */
    private function createPackages(array $codes): array
    {
        $prices = [
            'START' => 60000,
            'VIP' => 180000,
            'ELITE' => 300000,
        ];
        $sortOrders = [
            'START' => 1,
            'VIP' => 2,
            'ELITE' => 3,
        ];
        $binaryPercents = [
            'START' => 7,
            'VIP' => 8,
            'ELITE' => 10,
        ];

        return array_map(
            fn (string $code): Package => Package::query()->create([
                'code' => $code,
                'name' => $code,
                'slug' => strtolower($code),
                'price' => $prices[$code],
                'pv' => $prices[$code],
                'referral_percent' => 10,
                'binary_percent' => $binaryPercents[$code],
                'sort_order' => $sortOrders[$code],
                'status' => 'active',
                'is_active' => true,
                'is_upgradeable' => true,
            ]),
            $codes,
        );
    }
}
