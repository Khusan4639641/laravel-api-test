<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageSeederBonusPercentTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_packages_have_expected_bonus_percentages(): void
    {
        $this->seed(PackageSeeder::class);

        $this->assertPackage('START', '60000.00', '60000.00', '10.00', '7.00');
        $this->assertPackage('VIP', '180000.00', '180000.00', '10.00', '8.00');
        $this->assertPackage('ELITE', '300000.00', '300000.00', '10.00', '10.00');
    }

    public function test_business_package_is_not_an_active_public_starter_package(): void
    {
        Package::query()->create([
            'code' => 'BUSINESS',
            'name' => 'BUSINESS',
            'slug' => 'business',
            'price' => 60000,
            'pv' => 60000,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'sort_order' => 2,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);

        $this->seed(PackageSeeder::class);

        $business = Package::query()->where('code', 'BUSINESS')->firstOrFail();

        $this->assertFalse($business->is_active);
        $this->assertSame('inactive', $business->status);

        $this->getJson('/api/public/packages')
            ->assertOk()
            ->assertJsonCount(3, 'packages')
            ->assertJsonMissing(['code' => 'BUSINESS']);
    }

    public function test_seeded_package_referral_bonus_is_non_zero(): void
    {
        $this->seed(PackageSeeder::class);

        $sponsorPackage = Package::query()->where('code', 'VIP')->firstOrFail();
        $activatedPackage = Package::query()->where('code', 'START')->firstOrFail();
        $sponsor = User::factory()->create([
            'current_package_id' => $sponsorPackage->id,
        ]);
        $user = User::factory()->create([
            'sponsor_id' => $sponsor->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/packages/{$activatedPackage->id}/activate")
            ->assertOk();

        $bonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();
        $wallet = $sponsor->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('6000.00', $bonus->amount);
        $this->assertSame('6000.00', $wallet->balance);
    }

    public function test_seeded_package_binary_bonus_is_non_zero(): void
    {
        $this->seed(PackageSeeder::class);

        $package = Package::query()->where('code', 'START')->firstOrFail();
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/bonuses/binary/calculate')
            ->assertOk()
            ->assertJsonPath('bonus_transaction.amount', '70.00');

        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();

        $this->assertSame('63.00', $mainWallet->balance);
        $this->assertSame('7.00', $depositWallet->balance);
    }

    private function assertPackage(
        string $code,
        string $price,
        string $pv,
        string $referralPercent,
        string $binaryPercent,
    ): void
    {
        $package = Package::query()->where('code', $code)->firstOrFail();

        $this->assertSame($price, $package->price);
        $this->assertSame($pv, $package->pv);
        $this->assertSame($referralPercent, $package->referral_percent);
        $this->assertSame($binaryPercent, $package->binary_percent);
        $this->assertTrue($package->is_active);
        $this->assertSame('active', $package->status);
    }
}
