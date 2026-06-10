<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\User;
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
        $createdAt = now()->subMinutes($minutesAgo);

        return BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => $type,
            'amount' => $amount,
            'left_pv' => 0,
            'right_pv' => 0,
            'matched_pv' => 0,
            'status' => $status,
            'calculated_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
