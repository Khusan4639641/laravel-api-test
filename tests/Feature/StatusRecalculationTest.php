<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StatusAchievedNotification;
use App\Services\StatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
                'left_pv' => $pv,
                'right_pv' => $pv,
            ]);

            app(StatusService::class)->recalculate($user);

            $this->assertSame($expectedStatus, $user->refresh()->status);
        }
    }

    public function test_status_pv_is_cumulative(): void
    {
        $user = User::factory()->create([
            'status' => 'user',
            'left_pv' => 900,
            'right_pv' => 900,
        ]);

        $user->forceFill([
            'left_pv' => '1000.00',
            'right_pv' => '1000.00',
        ])->save();

        app(StatusService::class)->recalculate($user);
        $this->assertSame('manager', $user->refresh()->status);

        $user->forceFill([
            'left_pv' => '2500.00',
            'right_pv' => '3500.00',
        ])->save();
        app(StatusService::class)->recalculate($user);
        $user->refresh();

        $this->assertSame('2500.00', $user->left_pv);
        $this->assertSame('3500.00', $user->right_pv);
        $this->assertSame('leader', $user->status);
    }

    public function test_status_uses_weak_branch_pv(): void
    {
        $user = User::factory()->create([
            'status' => 'user',
            'left_pv' => 60000,
            'right_pv' => 50000,
        ]);

        app(StatusService::class)->recalculate($user);

        $this->assertSame('gold_director', $user->refresh()->status);
    }

    public function test_status_does_not_close_when_one_branch_is_empty(): void
    {
        $user = User::factory()->create([
            'status' => 'gold_director',
            'left_pv' => 100000,
            'right_pv' => 0,
        ]);

        app(StatusService::class)->recalculate($user);

        $this->assertSame('user', $user->refresh()->status);
    }

    public function test_status_notification_created_on_new_status(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'status' => 'user',
            'left_pv' => 1000,
            'right_pv' => 1200,
        ]);

        app(StatusService::class)->recalculate($user);

        Notification::assertSentTo($user, StatusAchievedNotification::class);
    }
}
