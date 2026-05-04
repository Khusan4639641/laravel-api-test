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

        $this->assertPackagePercents('START', '5.00', '5.00');
        $this->assertPackagePercents('BUSINESS', '7.00', '7.00');
        $this->assertPackagePercents('VIP', '10.00', '10.00');
        $this->assertPackagePercents('ELITE', '12.00', '12.00');
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

        $this->assertSame('3000.00', $bonus->amount);
        $this->assertSame('3000.00', $wallet->balance);
    }

    public function test_seeded_package_binary_bonus_is_non_zero(): void
    {
        $this->seed(PackageSeeder::class);

        $package = Package::query()->where('code', 'BUSINESS')->firstOrFail();
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
        $bonusWallet = $user->wallets()->where('type', 'bonus')->firstOrFail();

        $this->assertSame('63.00', $mainWallet->balance);
        $this->assertSame('7.00', $bonusWallet->balance);
    }

    private function assertPackagePercents(string $code, string $referralPercent, string $binaryPercent): void
    {
        $package = Package::query()->where('code', $code)->firstOrFail();

        $this->assertSame($referralPercent, $package->referral_percent);
        $this->assertSame($binaryPercent, $package->binary_percent);
    }
}
