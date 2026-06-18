<?php

namespace Tests\Feature\BusinessRules;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationPackageRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_does_not_allow_elite_as_starting_package(): void
    {
        $elite = $this->createPackage('ELITE', 300000, 500);

        $this->postJson('/api/register', $this->registerPayload('elite-start', [
            'package_id' => $elite->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package_id');
    }

    public function test_register_allows_start_as_starting_package_choice(): void
    {
        $start = $this->createPackage('START', 60000, 100);

        $this->postJson('/api/register', $this->registerPayload('start-choice', [
            'package_id' => $start->id,
        ]))
            ->assertCreated();
    }

    public function test_register_allows_vip_as_starting_package_choice(): void
    {
        $vip = $this->createPackage('VIP', 180000, 300);

        $this->postJson('/api/register', $this->registerPayload('vip-choice', [
            'package_id' => $vip->id,
        ]))
            ->assertCreated();
    }

    public function test_register_with_package_choice_assigns_package_and_accrues_referral_bonus(): void
    {
        $start = $this->createPackage('START', 60000, 100);
        $sponsor = User::factory()->create(['current_package_id' => $start->id]);

        $this->postJson('/api/register', $this->registerPayload('start-not-paid', [
            'package_id' => $start->id,
            'sponsor_id' => $sponsor->id,
            'branch' => 'L',
        ]))
            ->assertCreated();

        $user = User::query()->where('login', 'start-not-paid')->firstOrFail();
        $bonus = BonusTransaction::query()->where('bonus_type', 'referral')->firstOrFail();

        $this->assertSame($start->id, $user->current_package_id);
        $this->assertSame('100.00', $user->total_pv);
        $this->assertSame('5000.00', $bonus->amount);
    }

    public function test_seeded_package_values_match_business_tz(): void
    {
        $this->seed(PackageSeeder::class);

        $this->assertPackageValues('START', '60000.00', '100.00');
        $this->assertPackageValues('VIP', '180000.00', '300.00');
        $this->assertPackageValues('ELITE', '300000.00', '500.00');
    }

    public function test_registration_package_list_excludes_elite(): void
    {
        $this->seed(PackageSeeder::class);

        $packages = collect($this->getJson('/api/public/registration-packages')->assertOk()->json('packages'))->pluck('code')->all();

        $this->assertSame(['START', 'VIP'], $packages);
        $this->assertNotContains('ELITE', $packages);
    }

    public function test_public_packages_still_include_elite_for_marketing_and_upgrade(): void
    {
        $this->seed(PackageSeeder::class);

        $packages = collect($this->getJson('/api/public/packages')->assertOk()->json('packages'))->pluck('code')->all();

        $this->assertSame(['START', 'VIP', 'ELITE'], $packages);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registerPayload(string $suffix, array $overrides = []): array
    {
        return [
            'name' => "Business Rule {$suffix}",
            'login' => $suffix,
            'email' => "{$suffix}@safilife.test",
            'phone' => '+7700'.str_pad((string) crc32($suffix), 10, '0', STR_PAD_LEFT),
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }

    private function createPackage(string $code, int $price, int $pv): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $price,
            'pv' => $pv,
            'referral_percent' => 10,
            'binary_percent' => 8,
            'sort_order' => $code === 'START' ? 1 : ($code === 'VIP' ? 2 : 3),
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }

    private function assertPackageValues(string $code, string $expectedPrice, string $expectedPv): void
    {
        $package = Package::query()->where('code', $code)->firstOrFail();

        $this->assertSame($expectedPrice, $package->price);
        $this->assertSame($expectedPv, $package->pv);
    }
}
