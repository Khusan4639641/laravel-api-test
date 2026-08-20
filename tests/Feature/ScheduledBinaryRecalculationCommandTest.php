<?php

namespace Tests\Feature;

use App\Models\AdminActionLog;
use App\Models\BinaryBonusRun;
use App\Models\BonusTransaction;
use App\Models\Package;
use App\Models\User;
use App\Services\ScheduledBinaryBonusService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ScheduledBinaryRecalculationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_calls_dedicated_scheduled_use_case(): void
    {
        $scheduledService = Mockery::mock(ScheduledBinaryBonusService::class);
        $scheduledService->shouldReceive('calculateForAllPartners')
            ->once()
            ->with(Mockery::type(CarbonInterface::class))
            ->andReturn([
                'total' => 2,
                'processed' => 2,
                'skipped' => 1,
                'failed' => 0,
                'errors' => [],
                'total_count' => 2,
                'processed_count' => 2,
                'recalculated_count' => 1,
                'created_count' => 1,
                'updated_count' => 0,
                'skipped_count' => 1,
                'failed_count' => 0,
                'results' => [],
            ]);
        $this->app->instance(ScheduledBinaryBonusService::class, $scheduledService);

        $this->artisan('safi:binary-recalculate-all', ['--force' => true])
            ->expectsOutput('Binary recalculation completed.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_supports_dry_run_without_creating_binary_records(): void
    {
        $package = $this->package();
        User::factory()->create([
            'role' => User::ROLE_USER,
            'account_status' => 'active',
            'current_package_id' => $package->id,
        ]);

        $this->artisan('safi:binary-recalculate-all', ['--dry-run' => true, '--force' => true])
            ->expectsOutput('Binary recalculation dry run completed.')
            ->expectsOutput('No bonus, wallet, PV or turnover records were changed.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(0, BinaryBonusRun::query()->count());
        $this->assertSame(0, BonusTransaction::query()->count());
        $this->assertSame(0, AdminActionLog::query()->count());
    }

    public function test_scheduler_contains_binary_recalculation_on_first_and_fifteenth_in_tashkent(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'safi:binary-recalculate-all --scheduled'))
            ->values();

        $this->assertCount(2, $events);
        $this->assertSame(['0 3 1 * *', '0 3 15 * *'], $events->pluck('expression')->sort()->values()->all());

        foreach ($events as $event) {
            $this->assertSame('Asia/Tashkent', $event->timezone);
            $this->assertTrue($event->withoutOverlapping);
            $this->assertSame('safi-binary-recalculate-all', $event->mutexName());
        }
    }

    private function package(): Package
    {
        return Package::query()->create([
            'code' => 'START',
            'name' => 'Start',
            'slug' => 'start',
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'status' => 'active',
            'is_active' => true,
        ]);
    }
}
