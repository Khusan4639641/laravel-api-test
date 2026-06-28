<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserStatusBonus;
use App\Services\StatusBonusService;
use App\Services\StatusService;
use Database\Seeders\StatusBonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_bonus_created_when_user_reaches_status(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);

        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 6000,
            'status' => 'user',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $this->assertDatabaseHas('user_status_bonuses', [
            'user_id' => $user->id,
            'status_code' => 'director',
            'amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
            'status' => 'completed',
        ]);
    }

    public function test_status_bonus_is_not_duplicated(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);

        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
            'status' => 'director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);
        app(StatusBonusService::class)->awardEligible($user->refresh());

        $this->assertSame(1, UserStatusBonus::query()->where('status_code', 'director')->count());
        $this->assertSame(3, UserStatusBonus::query()->count());
        $this->assertDatabaseCount('bonus_transactions', 1);
    }

    public function test_status_bonus_requires_elite_package(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);

        $start = $this->createPackage('START');
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'left_pv' => 5000,
            'right_pv' => 5000,
            'status' => 'director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $this->assertDatabaseCount('user_status_bonuses', 0);
        $this->assertDatabaseCount('bonus_transactions', 0);
    }

    public function test_missed_status_bonuses_are_awarded_when_user_becomes_elite(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);

        $start = $this->createPackage('START');
        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $start->id,
            'left_pv' => 5000,
            'right_pv' => 7000,
            'status' => 'director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);
        $this->assertDatabaseCount('user_status_bonuses', 0);

        $user->forceFill(['current_package_id' => $elite->id])->save();
        app(StatusBonusService::class)->checkMissedStatusBonuses($user->refresh());

        $this->assertDatabaseCount('user_status_bonuses', 3);
        $this->assertDatabaseHas('user_status_bonuses', [
            'user_id' => $user->id,
            'status_code' => 'director',
            'amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
            'status' => 'completed',
        ]);
    }

    public function test_status_recalculation_triggers_status_bonus_sync_when_pv_crosses_director_threshold(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);

        $elite = $this->createPackage('ELITE');
        $user = User::factory()->create([
            'current_package_id' => $elite->id,
            'left_pv' => 5000,
            'right_pv' => 5100,
            'status' => 'user',
        ]);

        app(StatusService::class)->recalculate($user);

        $this->assertSame('director', $user->refresh()->status);
        $this->assertDatabaseHas('user_status_bonuses', [
            'user_id' => $user->id,
            'status_code' => 'director',
            'amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'status_bonus',
            'amount' => '250000.00',
            'status' => 'completed',
        ]);
    }

    public function test_reward_text_matches_business_tz(): void
    {
        app()->setLocale('ru');

        $statuses = collect(app(StatusService::class)->publicStatuses())->keyBy('id');

        $this->assertSame('2 продукта в подарок', $statuses['manager']['reward']);
        $this->assertSame('Набор косметики', $statuses['leader']['reward']);
        $this->assertSame('250 000 ₸ cash bonus', $statuses['director']['reward']);
        $this->assertSame('Путевка в санаторий + 100 000 ₸, при отказе 400 000 ₸', $statuses['bronze_director']['reward']);
        $this->assertSame('Зарубежная поездка + 250 000 ₸, при отказе 750 000 ₸', $statuses['silver_director']['reward']);
        $this->assertSame('100000.00', $statuses['bronze_director']['cash_amount']);
        $this->assertSame('400000.00', $statuses['bronze_director']['compensation_amount']);
        $this->assertTrue($statuses['bronze_director']['compensation_available']);
        $this->assertSame('250000.00', $statuses['silver_director']['cash_amount']);
        $this->assertSame('750000.00', $statuses['silver_director']['compensation_amount']);
        $this->assertTrue($statuses['silver_director']['compensation_available']);
        $this->assertSame('5 000 000 ₸ cash bonus', $statuses['gold_director']['reward']);
        $this->assertSame('6 000 000 ₸ cash bonus', $statuses['platinum_director']['reward']);
        $this->assertSame('10 000 000 ₸ auto bonus', $statuses['emerald_director']['reward']);
        $this->assertSame('20 000 000 ₸ apartment bonus', $statuses['diamond_director']['reward']);
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
