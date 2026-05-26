<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PvService;
use App\Services\StatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_recalculation_uses_business_tz_thresholds(): void
    {
        $cases = [
            [1000, 'manager'],
            [2500, 'leader'],
            [5000, 'director'],
            [500000, 'diamond_director'],
        ];

        foreach ($cases as [$pv, $expectedStatus]) {
            $user = User::factory()->create([
                'status' => 'user',
                'total_pv' => $pv,
            ]);

            app(StatusService::class)->recalculate($user);

            $this->assertSame($expectedStatus, $user->refresh()->status);
        }
    }

    public function test_status_pv_is_cumulative(): void
    {
        $user = User::factory()->create([
            'status' => 'user',
            'total_pv' => 900,
        ]);

        app(PvService::class)->addUserPv($user, 100);
        $this->assertSame('manager', $user->refresh()->status);

        app(PvService::class)->addUserPv($user, 1500);
        $user->refresh();

        $this->assertSame('2500.00', $user->total_pv);
        $this->assertSame('leader', $user->status);
    }

    public function test_status_does_not_downgrade_when_cumulative_pv_is_preserved(): void
    {
        $user = User::factory()->create([
            'status' => 'diamond_director',
            'total_pv' => 500000,
        ]);

        app(StatusService::class)->recalculate($user);
        app(StatusService::class)->recalculate($user->refresh());

        $this->assertSame('diamond_director', $user->refresh()->status);
        $this->assertSame('500000.00', $user->total_pv);
    }
}
