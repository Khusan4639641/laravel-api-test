<?php

namespace Tests\Feature;

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

        $user = User::factory()->create([
            'total_pv' => 5000,
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
            'bonus_type' => 'status',
            'amount' => '250000.00',
            'status' => 'completed',
        ]);
    }

    public function test_status_bonus_is_not_duplicated(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);

        $user = User::factory()->create([
            'total_pv' => 5000,
            'status' => 'director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);
        app(StatusBonusService::class)->awardEligible($user->refresh());

        $this->assertSame(1, UserStatusBonus::query()->where('status_code', 'director')->count());
        $this->assertSame(3, UserStatusBonus::query()->count());
        $this->assertDatabaseCount('bonus_transactions', 1);
    }

    public function test_reward_text_matches_business_tz(): void
    {
        app()->setLocale('ru');

        $statuses = collect(app(StatusService::class)->publicStatuses())->keyBy('id');

        $this->assertSame('2 продукта в подарок', $statuses['manager']['reward']);
        $this->assertSame('Набор косметики', $statuses['leader']['reward']);
        $this->assertSame('250 000 ₸ cash bonus', $statuses['director']['reward']);
        $this->assertSame('Путевка в санаторий + 100 000 ₸ или компенсация 400 000 ₸', $statuses['bronze_director']['reward']);
        $this->assertSame('Путевка в теплые страны + 250 000 ₸ или компенсация 750 000 ₸', $statuses['silver_director']['reward']);
        $this->assertSame('5 000 000 ₸ cash bonus', $statuses['gold_director']['reward']);
        $this->assertSame('6 000 000 ₸ cash bonus', $statuses['platinum_director']['reward']);
        $this->assertSame('10 000 000 ₸ auto bonus', $statuses['emerald_director']['reward']);
        $this->assertSame('20 000 000 ₸ apartment bonus', $statuses['diamond_director']['reward']);
    }
}
