<?php

namespace Tests\Feature\BusinessRules;

use App\Models\BonusTransaction;
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
        $user = User::factory()->create([
            'total_pv' => 10000,
            'status' => 'bronze_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $bonus = UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_code', 'bronze_director')
            ->firstOrFail();

        $this->assertSame('100000.00', $bonus->amount);
        $this->assertStringContainsString('Путевка', $bonus->reward_text);
    }

    public function test_bronze_director_cash_compensation_is_only_paid_after_trip_refusal(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $user = User::factory()->create([
            'total_pv' => 10000,
            'status' => 'bronze_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $this->assertSame(0, BonusTransaction::query()
            ->where('user_id', $user->id)
            ->where('bonus_type', 'status')
            ->where('amount', '400000.00')
            ->count());
    }

    public function test_silver_director_default_reward_is_foreign_trip_plus_two_hundred_fifty_thousand(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $user = User::factory()->create([
            'total_pv' => 25000,
            'status' => 'silver_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $bonus = UserStatusBonus::query()
            ->where('user_id', $user->id)
            ->where('status_code', 'silver_director')
            ->firstOrFail();

        $this->assertSame('250000.00', $bonus->amount);
        $this->assertStringContainsString('Путевка', $bonus->reward_text);
    }

    public function test_silver_director_cash_compensation_is_only_paid_after_trip_refusal(): void
    {
        $this->seed(StatusBonusDefinitionSeeder::class);
        $user = User::factory()->create([
            'total_pv' => 25000,
            'status' => 'silver_director',
        ]);

        app(StatusBonusService::class)->awardEligible($user);

        $this->assertSame(0, BonusTransaction::query()
            ->where('user_id', $user->id)
            ->where('bonus_type', 'status')
            ->where('amount', '750000.00')
            ->count());
    }
}
