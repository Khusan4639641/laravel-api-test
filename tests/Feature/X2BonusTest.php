<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserX2Bonus;
use App\Notifications\X2BonusAwardedNotification;
use App\Services\BinaryTreeService;
use App\Services\X2BonusService;
use Database\Seeders\X2BonusDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class X2BonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_x2_trip_reward_if_five_first_line_partners_become_directors(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();
        $this->createFirstLinePartners($user, 'director', 2, 3);

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
        $this->createFirstLinePartners($user, 'gold_director', 2, 3);

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
        $this->createFirstLinePartners($user, 'diamond_director', 3, 2);

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
        $this->createFirstLinePartners($user, 'director', 2, 3);

        app(X2BonusService::class)->awardEligible($user);
        app(X2BonusService::class)->awardEligible($user->refresh());

        $this->assertSame(1, UserX2Bonus::query()->where('code', 'five_directors')->count());
        $this->assertDatabaseCount('user_x2_bonuses', 1);
    }

    public function test_downline_but_not_personally_invited_does_not_count(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();
        $otherSponsor = User::factory()->create();

        $this->createFirstLinePartners($user, 'director', 2, 2);
        $this->placeNonPersonalDownline($user, $otherSponsor, 'director', 'R');

        app(X2BonusService::class)->awardEligible($user);

        $this->assertDatabaseMissing('user_x2_bonuses', [
            'user_id' => $user->id,
            'code' => 'five_directors',
        ]);
    }

    public function test_four_left_and_one_right_does_not_qualify(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();

        $this->createFirstLinePartners($user, 'director', 4, 1);

        app(X2BonusService::class)->awardEligible($user);

        $this->assertDatabaseMissing('user_x2_bonuses', [
            'user_id' => $user->id,
            'code' => 'five_directors',
        ]);
    }

    public function test_status_or_higher_qualifies(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();

        $this->createFirstLinePartners($user, ['director', 'gold_director'], 2, 0);
        $this->createFirstLinePartners($user, ['director', 'gold_director', 'diamond_director'], 0, 3);

        app(X2BonusService::class)->awardEligible($user);

        $bonus = UserX2Bonus::query()->where('code', 'five_directors')->firstOrFail();

        $this->assertSame(5, $bonus->qualified_count);
        $this->assertSame(2, $bonus->metadata['left_count']);
        $this->assertSame(3, $bonus->metadata['right_count']);
    }

    public function test_no_limit_on_invite_count(): void
    {
        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();

        $this->createFirstLinePartners($user, 'director', 10, 10);

        app(X2BonusService::class)->awardEligible($user);

        $bonus = UserX2Bonus::query()->where('code', 'five_directors')->firstOrFail();

        $this->assertSame(20, $bonus->qualified_count);
        $this->assertSame(10, $bonus->metadata['left_count']);
        $this->assertSame(10, $bonus->metadata['right_count']);
    }

    public function test_x2_bonus_sends_notification(): void
    {
        Notification::fake();

        $this->seed(X2BonusDefinitionSeeder::class);
        $user = User::factory()->create();
        $this->createFirstLinePartners($user, 'director', 2, 3);

        app(X2BonusService::class)->awardEligible($user);

        Notification::assertSentTo($user, X2BonusAwardedNotification::class);
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

    private function placeNonPersonalDownline(User $root, User $actualSponsor, string $status, string $side): User
    {
        $this->ensureBinaryRoot($root);

        $partner = User::factory()->create([
            'sponsor_id' => $actualSponsor->id,
            'status' => $status,
        ]);

        app(BinaryTreeService::class)->placeUser($partner, $root, $side);

        return $partner;
    }

    private function ensureBinaryRoot(User $user): void
    {
        if (! $user->binaryNode()->exists()) {
            app(BinaryTreeService::class)->placeUser($user);
        }
    }
}
