<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStatusProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_overview_exposes_weak_leg_for_status_progress(): void
    {
        $user = User::factory()->create([
            'left_pv' => 60000,
            'right_pv' => 50000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '60000.00')
            ->assertJsonPath('structure.right_pv', '50000.00')
            ->assertJsonPath('structure.weak_leg_pv', 50000)
            ->assertJsonPath('structure.weak_leg', 'right')
            ->assertJsonPath('user.weak_leg_pv', 50000);
    }

    public function test_dashboard_overview_weak_leg_is_zero_when_one_branch_is_empty(): void
    {
        $user = User::factory()->create([
            'left_pv' => 100000,
            'right_pv' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('structure.left_pv', '100000.00')
            ->assertJsonPath('structure.right_pv', '0.00')
            ->assertJsonPath('structure.weak_leg_pv', 0)
            ->assertJsonPath('structure.weak_leg', 'right')
            ->assertJsonPath('user.weak_leg_pv', 0);
    }
}
