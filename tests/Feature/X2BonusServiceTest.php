<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserX2Bonus;
use App\Services\BinaryTreeService;
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

        $this->createFirstLinePartners($user, 'gold_director', 2, 3);

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

        $this->createFirstLinePartners($user, 'diamond_director', 2, 2);

        app(X2BonusService::class)->awardEligible($user);

        $this->assertDatabaseMissing('user_x2_bonuses', [
            'code' => 'five_diamond_directors',
        ]);

        $this->placePersonalPartner($user, 'diamond_director', 'R');

        app(X2BonusService::class)->awardEligible($user->refresh());

        $this->assertDatabaseHas('user_x2_bonuses', [
            'code' => 'five_diamond_directors',
            'amount' => '20000000.00',
        ]);
    }

    public function test_three_left_and_two_right_qualifies(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);

        $user = User::factory()->create();

        $this->createFirstLinePartners($user, 'director', 3, 2);

        app(X2BonusService::class)->awardEligible($user);

        $bonus = UserX2Bonus::query()->where('code', 'five_directors')->firstOrFail();

        $this->assertSame(5, $bonus->qualified_count);
        $this->assertSame(3, $bonus->metadata['left_count']);
        $this->assertSame(2, $bonus->metadata['right_count']);
    }

    /**
     * @param string|array<int, string> $status
     */
    private function createFirstLinePartners(User $sponsor, string|array $status, int $leftCount, int $rightCount): void
    {
        foreach (['L' => $leftCount, 'R' => $rightCount] as $side => $count) {
            for ($index = 0; $index < $count; $index++) {
                $statuses = is_array($status) ? array_values($status) : [$status];
                $this->placePersonalPartner($sponsor, $statuses[$index % count($statuses)], $side);
            }
        }
    }

    private function placePersonalPartner(User $sponsor, string $status, string $side): User
    {
        $this->ensureBinaryRoot($sponsor);

        $partner = User::factory()->create([
            'sponsor_id' => $sponsor->id,
            'status' => $status,
        ]);

        app(BinaryTreeService::class)->placeUser($partner, $sponsor, $side);

        return $partner;
    }

    private function ensureBinaryRoot(User $user): void
    {
        if (! $user->binaryNode()->exists()) {
            app(BinaryTreeService::class)->placeUser($user);
        }
    }
}
