<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserX2Bonus;
use App\Services\X2BonusService;
use Database\Seeders\X2BonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X2BonusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_x2_bonuses_are_awarded_once_for_first_line_statuses(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);

        $user = User::factory()->create();

        User::factory()
            ->count(5)
            ->create([
                'sponsor_id' => $user->id,
                'status' => 'gold_director',
            ]);

        app(X2BonusService::class)->awardEligible($user);
        app(X2BonusService::class)->awardEligible($user->refresh());

        $this->assertDatabaseCount('user_x2_bonuses', 2);
        $this->assertDatabaseCount('bonus_transactions', 1);

        $tripBonus = UserX2Bonus::query()->where('code', 'five_directors')->firstOrFail();
        $cashBonus = UserX2Bonus::query()->where('code', 'five_gold_directors')->firstOrFail();
        $wallet = $user->wallets()->where('type', 'main')->firstOrFail();

        $this->assertSame('0.00', $tripBonus->amount);
        $this->assertSame('5000000.00', $cashBonus->amount);
        $this->assertSame('5000000.00', $wallet->balance);
    }

    public function test_x2_diamond_bonus_requires_five_diamond_first_line_partners(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);

        $user = User::factory()->create();

        User::factory()
            ->count(4)
            ->create([
                'sponsor_id' => $user->id,
                'status' => 'diamond_director',
            ]);

        app(X2BonusService::class)->awardEligible($user);

        $this->assertDatabaseMissing('user_x2_bonuses', [
            'code' => 'five_diamond_directors',
        ]);

        User::factory()->create([
            'sponsor_id' => $user->id,
            'status' => 'diamond_director',
        ]);

        app(X2BonusService::class)->awardEligible($user->refresh());

        $this->assertDatabaseHas('user_x2_bonuses', [
            'code' => 'five_diamond_directors',
            'amount' => '20000000.00',
        ]);
    }
}
