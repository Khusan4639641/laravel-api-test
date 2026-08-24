<?php

namespace Tests\Feature\BusinessRules;

use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Services\StatusBonusService;
use Database\Seeders\StatusBonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusBonusRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bronze_director_default_reward_is_trip_plus_one_hundred_thousand(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 10000,
            'right_pv' => 12000,
            'status' => 'bronze_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $bonus = UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_code', 'bronze_director')
            ->firstOrFail();

        $this->assertSame('100000.00', $bonus->amount);
        $this->assertStringContainsString('Путевка', $bonus->reward_text);
        $this->assertSame('100000.00', $bonus->metadata['cash_amount']);
        $this->assertSame('400000.00', $bonus->metadata['compensation_amount']);
        $this->assertTrue($bonus->metadata['compensation_available']);
        $this->assertFalse($bonus->metadata['compensation_paid']);

        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '100000.00',
        ]);
    }

    public function test_bronze_director_cash_compensation_is_only_paid_after_trip_refusal(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 10000,
            'right_pv' => 10000,
            'status' => 'bronze_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $this->assertSame(0, BonusTransaction::query()
            ->where('user_id', $user->id)
            ->where('bonus_type', 'status_bonus')
            ->where('amount', '400000.00')
            ->count());
    }

    public function test_silver_director_default_reward_is_foreign_trip_plus_two_hundred_fifty_thousand(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 25000,
            'right_pv' => 30000,
            'status' => 'silver_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $bonus = UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_code', 'silver_director')
            ->firstOrFail();

        $this->assertSame('250000.00', $bonus->amount);
        $this->assertStringContainsString('Зарубежная поездка', $bonus->reward_text);
        $this->assertSame('250000.00', $bonus->metadata['cash_amount']);
        $this->assertSame('750000.00', $bonus->metadata['compensation_amount']);
        $this->assertTrue($bonus->metadata['compensation_available']);
        $this->assertFalse($bonus->metadata['compensation_paid']);

        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
        ]);
    }

    public function test_silver_director_cash_compensation_is_only_paid_after_trip_refusal(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 25000,
            'right_pv' => 25000,
            'status' => 'silver_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $this->assertSame(0, BonusTransaction::query()
            ->where('user_id', $user->id)
            ->where('bonus_type', 'status_bonus')
            ->where('amount', '750000.00')
            ->count());
    }

    public function test_status_bonus_is_not_duplicated(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 10000,
            'right_pv' => 10000,
            'status' => 'bronze_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);
        app(StatusBonusService::class)->awardEligible($user->refresh());

        $this->assertSame(1, UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_code', 'bronze_director')
            ->count());
        $this->assertSame(1, BonusTransaction::query()
            ->where('user_id', $user->id)
            ->where('bonus_type', 'status_bonus')
            ->where('amount', '100000.00')
            ->count());
    }

    private function createPackage(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => $code === 'ELITE' ? 300000 : 60000,
            'pv' => $code === 'ELITE' ? 500 : 100,
            'activity_pv' => $code === 'ELITE' ? 500 : 100,
            'turnover_pv' => $code === 'ELITE' ? 200 : 100,
            'referral_percent' => 10,
            'binary_percent' => $code === 'ELITE' ? 10 : 7,
            'sort_order' => $code === 'ELITE' ? 3 : 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
