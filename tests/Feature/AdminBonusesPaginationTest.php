<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBonusesPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bonuses_endpoint_returns_paginated_data(): void
    {
        $partner = User::factory()->create();
        $this->createBonuses($partner, 25);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/bonuses?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'bonuses')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25);
    }

    public function test_per_page_20_returns_20_rows(): void
    {
        $partner = User::factory()->create();
        $this->createBonuses($partner, 25);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/bonuses?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonCount(20, 'bonuses');
    }

    public function test_page_2_returns_second_page(): void
    {
        $partner = User::factory()->create();
        $bonuses = $this->createBonuses($partner, 25);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/bonuses?page=2&per_page=20')
            ->assertOk()
            ->assertJsonCount(5, 'bonuses')
            ->assertJsonPath('meta.current_page', 2);

        $ids = collect($response->json('bonuses'))->pluck('id')->all();

        $this->assertContains($bonuses[20]->id, $ids);
        $this->assertNotContains($bonuses[0]->id, $ids);
    }

    public function test_meta_total_is_correct(): void
    {
        $partner = User::factory()->create();
        $this->createBonuses($partner, 23);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $this->getJson('/api/admin/bonuses?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 23)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_admin_bonuses_are_ordered_by_updated_at_desc_and_include_updated_at(): void
    {
        $partner = User::factory()->create();
        $freshlyUpdated = $this->createBonusWithDates($partner, 'referral_bonus', 100, 'completed', now()->subDays(10), now()->subMinute());
        $staleUpdated = $this->createBonusWithDates($partner, 'referral_bonus', 100, 'completed', now(), now()->subDays(3));
        $middleUpdated = $this->createBonusWithDates($partner, 'referral_bonus', 100, 'completed', now()->subDays(5), now()->subDay());

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/bonuses?per_page=10')
            ->assertOk();

        $this->assertSame(
            [$freshlyUpdated->id, $middleUpdated->id, $staleUpdated->id],
            collect($response->json('bonuses'))->pluck('id')->take(3)->all()
        );
        $this->assertNotEmpty($response->json('bonuses.0.updated_at'));
    }

    public function test_bonus_search_type_and_status_filters_keep_updated_at_desc_order(): void
    {
        $partner = User::factory()->create([
            'login' => 'updated-order-bonus-login',
        ]);
        $olderMatch = $this->createBonusWithDates($partner, 'referral_bonus', 100, 'completed', now()->subDays(10), now()->subHours(5));
        $newerMatch = $this->createBonusWithDates($partner, 'referral_bonus', 100, 'completed', now()->subDays(8), now()->subMinutes(5));
        $this->createBonusWithDates($partner, 'referral_bonus', 100, 'pending', now()->subDays(9), now()->subMinute());
        $this->createBonusWithDates($partner, 'binary_bonus_main', 100, 'completed', now()->subDays(9), now()->subMinutes(2));

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/bonuses?search=updated-order-bonus-login&type=referral_bonus&status=completed&per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'bonuses');

        $this->assertSame(
            [$newerMatch->id, $olderMatch->id],
            collect($response->json('bonuses'))->pluck('id')->all()
        );
    }

    public function test_search_finds_bonus_globally(): void
    {
        $this->createBonuses(User::factory()->create(), 30);
        $targetUser = User::factory()->create([
            'name' => 'Global Bonus Partner',
            'login' => 'global-bonus-login',
            'email' => 'global-bonus@safi.test',
        ]);
        $target = $this->createBonus($targetUser, 'referral_bonus', 777, 'completed', 200);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/bonuses?search=global-bonus-login&per_page=5')
            ->assertOk()
            ->assertJsonCount(1, 'bonuses')
            ->assertJsonPath('meta.total', 1);

        $this->assertSame($target->id, $response->json('bonuses.0.id'));
    }

    public function test_filters_work_with_pagination(): void
    {
        $partner = User::factory()->create();
        $this->createBonuses($partner, 25, 'referral_bonus', 'completed');
        $this->createBonuses($partner, 10, 'binary_bonus_main', 'completed', 100);
        $this->createBonuses($partner, 5, 'referral_bonus', 'pending', 200);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $response = $this->getJson('/api/admin/bonuses?type=referral_bonus&status=completed&page=2&per_page=20')
            ->assertOk()
            ->assertJsonCount(5, 'bonuses')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.current_page', 2);

        collect($response->json('bonuses'))->each(function (array $bonus): void {
            $this->assertSame('referral_bonus', $bonus['bonus_type']);
            $this->assertSame('completed', $bonus['status']);
        });
    }

    /**
     * @return array<int, BonusTransaction>
     */
    private function createBonuses(
        User $user,
        int $count,
        string $type = 'referral_bonus',
        string $status = 'completed',
        int $offset = 0,
    ): array {
        $bonuses = [];

        for ($index = 1; $index <= $count; $index++) {
            $bonuses[] = $this->createBonus($user, $type, 100, $status, $offset + $index);
        }

        return $bonuses;
    }

    private function createBonus(User $user, string $type, int $amount, string $status, int $minutesAgo): BonusTransaction
    {
        $date = now()->subMinutes($minutesAgo);

        return $this->createBonusWithDates($user, $type, $amount, $status, $date, $date);
    }

    private function createBonusWithDates(
        User $user,
        string $type,
        int $amount,
        string $status,
        CarbonInterface $createdAt,
        CarbonInterface $updatedAt,
    ): BonusTransaction {
        $bonus = BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => $type,
            'amount' => $amount,
            'left_pv' => 0,
            'right_pv' => 0,
            'matched_pv' => 0,
            'status' => $status,
            'calculated_at' => $createdAt,
        ]);

        BonusTransaction::withoutTimestamps(fn () => $bonus->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ])->save());

        return $bonus->refresh();
    }
}
