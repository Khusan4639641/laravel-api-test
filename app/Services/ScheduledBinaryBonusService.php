<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ScheduledBinaryBonusService
{
    public function __construct(
        private readonly BonusService $bonusService,
        private readonly BinaryBonusPeriodResolver $periodResolver,
    ) {}

    /**
     * Scheduled calculation is deliberately separate from the admin/manual
     * recalculation use case. It can only create a run for an exact new period.
     *
     * @return array<string, mixed>
     */
    public function calculateForAllPartners(?CarbonInterface $scheduledFor = null): array
    {
        $period = $this->periodResolver->resolve($scheduledFor);
        $batchUuid = (string) Str::uuid();
        $lockName = 'safi:scheduled-binary:'.$period['start']->utc()->format('YmdHis');
        $lock = Cache::lock($lockName, 3600);

        if (! $lock->get()) {
            throw new RuntimeException('Another scheduled binary calculation is already running for this period.');
        }

        try {
            return $this->runLocked($period, $batchUuid);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{start: mixed, end: mixed, scheduled_for: mixed, timezone: string}  $period
     * @return array<string, mixed>
     */
    private function runLocked(array $period, string $batchUuid): array
    {
        $total = User::query()->where('role', User::ROLE_USER)->activeMlm()->count();
        $processed = 0;
        $created = 0;
        $failed = 0;
        $errors = [];
        $results = [];
        $metadata = [
            'batch_uuid' => $batchUuid,
            'source' => 'scheduled_binary_calculation',
            'scheduled_for' => $period['scheduled_for']->utc()->toISOString(),
            'timezone' => $period['timezone'],
        ];

        User::query()
            ->where('role', User::ROLE_USER)
            ->activeMlm()
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$processed, &$created, &$failed, &$errors, &$results, $period, $metadata): void {
                foreach ($users as $user) {
                    $processed++;

                    try {
                        $bonus = $this->bonusService->calculateBinaryBonus(
                            $user,
                            $period['start'],
                            $period['end'],
                            $metadata,
                        );
                        $action = $bonus ? 'created' : 'skipped';
                        $created += $bonus ? 1 : 0;
                        $results[] = [
                            'user_id' => $user->id,
                            'bonus_transaction_id' => $bonus?->id,
                            'action' => $action,
                        ];
                    } catch (\Throwable $exception) {
                        report($exception);
                        $failed++;
                        $errors[] = ['user_id' => $user->id, 'message' => $exception->getMessage()];
                        $results[] = [
                            'user_id' => $user->id,
                            'bonus_transaction_id' => null,
                            'action' => 'failed',
                            'error' => $exception->getMessage(),
                        ];
                    }
                }
            });

        $skipped = max($processed - $created - $failed, 0);

        AdminActionLog::query()->create([
            'admin_id' => null,
            'target_user_id' => null,
            'action' => 'binary_calculate_all_scheduled',
            'reason' => 'Scheduled binary calculation completed',
            'metadata' => [
                ...$metadata,
                'period_start' => $period['start']->utc()->toISOString(),
                'period_end' => $period['end']->utc()->toISOString(),
                'total' => $total,
                'processed_count' => $processed,
                'created_count' => $created,
                'skipped_count' => $skipped,
                'failed_count' => $failed,
                'errors' => $errors,
            ],
        ]);

        Log::info('Scheduled binary calculation completed', [
            ...$metadata,
            'period_start' => $period['start']->toISOString(),
            'period_end' => $period['end']->toISOString(),
            'total' => $total,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return [
            'batch_uuid' => $batchUuid,
            'period_start' => $period['start']->utc()->toISOString(),
            'period_end' => $period['end']->utc()->toISOString(),
            'timezone' => $period['timezone'],
            'total' => $total,
            'processed' => $processed,
            'created' => $created,
            'updated' => 0,
            'skipped' => $skipped,
            'failed' => $failed,
            'total_count' => $total,
            'processed_count' => $processed,
            'created_count' => $created,
            'updated_count' => 0,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
            'errors' => $errors,
            'results' => $results,
        ];
    }
}
