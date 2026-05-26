<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserX2Bonus;
use App\Services\X2BonusService;
use Database\Seeders\X2BonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X2BonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_x2_trip_reward_if_five_first_line_partners_become_directors(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();
        $this->createFirstLinePartners($user, 'director', 5);

        app(X2BonusService::class)->awardEligible($user);

        $bonus = UserX2Bonus::query()->where('code', 'five_directors')->firstOrFail();

        $this->assertSame($user->id, $bonus->user_id);
        $this->assertSame(5, $bonus->qualified_count);
        $this->assertSame('0.00', $bonus->amount);
        $this->assertSame('5 Directors => warm country trip', $bonus->reward_text);
    }

    public function test_user_gets_five_million_reward_if_five_first_line_partners_become_gold_directors(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();
        $this->createFirstLinePartners($user, 'gold_director', 5);

        app(X2BonusService::class)->awardEligible($user);

        $bonus = UserX2Bonus::query()->where('code', 'five_gold_directors')->firstOrFail();
        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('5000000.00', $bonus->amount);
        $this->assertSame('5000000.00', $wallet->balance);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'bonus_x2',
            'amount' => '5000000.00',
        ]);
    }

    public function test_user_gets_twenty_million_reward_if_five_first_line_partners_become_diamond_directors(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();
        $this->createFirstLinePartners($user, 'diamond_director', 5);

        app(X2BonusService::class)->awardEligible($user);

        $bonus = UserX2Bonus::query()->where('code', 'five_diamond_directors')->firstOrFail();
        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('20000000.00', $bonus->amount);
        $this->assertSame('25000000.00', $wallet->balance);
        $this->assertDatabaseHas('bonus_transactions', [
            'user_id' => $user->id,
            'bonus_type' => 'bonus_x2',
            'amount' => '20000000.00',
        ]);
    }

    public function test_x2_bonus_is_not_duplicated(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();
        $this->createFirstLinePartners($user, 'director', 5);

        app(X2BonusService::class)->awardEligible($user);
        app(X2BonusService::class)->awardEligible($user->refresh());

        $this->assertSame(1, UserX2Bonus::query()->where('code', 'five_directors')->count());
        $this->assertDatabaseCount('user_x2_bonuses', 1);
    }

    private function createFirstLinePartners(User $sponsor, string $status, int $count): void
    {
        User::factory()
            ->count($count)
            ->create([
                'sponsor_id' => $sponsor->id,
                'status' => $status,
            ]);
    }
}
