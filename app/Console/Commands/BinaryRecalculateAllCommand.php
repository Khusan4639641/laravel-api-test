<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ScheduledBinaryBonusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('safi:binary-recalculate-all {--dry-run : Show how many active partners would be processed without changing data} {--force : Allow manual production run} {--scheduled : Mark run as scheduler-approved}')]
#[Description('Create immutable binary bonus runs for the current scheduled half-month period.')]
class BinaryRecalculateAllCommand extends Command
{
    public function handle(ScheduledBinaryBonusService $scheduledBinaryBonusService): int
    {
        $scheduled = (bool) $this->option('scheduled');
        $dryRun = (bool) $this->option('dry-run');
        $source = $scheduled ? 'scheduler' : 'command';

        if (app()->environment('production') && ! $scheduled && ! $this->option('force')) {
            $this->error('Refusing to run binary recalculation manually in production without --force.');

            return self::FAILURE;
        }

        $startedAt = now('Asia/Tashkent');

        if ($dryRun) {
            $total = $this->activePartnerCount();

            $this->info('Binary recalculation dry run completed.');
            $this->table(['Metric', 'Value'], [
                ['started_at', $startedAt->toDateTimeString()],
                ['source', $source],
                ['total', $total],
                ['processed', 0],
                ['skipped', $total],
                ['failed', 0],
            ]);
            $this->line('No bonus, wallet, PV or turnover records were changed.');

            return self::SUCCESS;
        }

        $logPrefix = $scheduled ? 'Scheduled binary recalculation' : 'Binary recalculation command';

        Log::info("{$logPrefix} started", [
            'source' => $source,
            'started_at' => $startedAt->toDateTimeString(),
        ]);

        try {
            $result = $scheduledBinaryBonusService->calculateForAllPartners($startedAt);
        } catch (\Throwable $exception) {
            Log::error("{$logPrefix} failed", [
                'source' => $source,
                'started_at' => $startedAt->toDateTimeString(),
                'failed_at' => now('Asia/Tashkent')->toDateTimeString(),
                'message' => $exception->getMessage(),
            ]);

            $this->error('Binary recalculation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $finishedAt = now('Asia/Tashkent');
        $summary = $this->summary($result);

        Log::info("{$logPrefix} finished", [
            'source' => $source,
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => $finishedAt->toDateTimeString(),
            ...$summary,
            'errors' => $result['errors'] ?? [],
        ]);

        $this->info('Binary recalculation completed.');
        $this->table(['Metric', 'Value'], [
            ['started_at', $startedAt->toDateTimeString()],
            ['finished_at', $finishedAt->toDateTimeString()],
            ['source', $source],
            ['total', $summary['total']],
            ['processed', $summary['processed']],
            ['skipped', $summary['skipped']],
            ['failed', $summary['failed']],
            ['errors', count((array) ($result['errors'] ?? []))],
        ]);

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function activePartnerCount(): int
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->activeMlm()
            ->count();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{total: int, processed: int, skipped: int, failed: int}
     */
    private function summary(array $result): array
    {
        return [
            'total' => (int) ($result['total'] ?? $result['total_count'] ?? 0),
            'processed' => (int) ($result['processed'] ?? $result['processed_count'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? $result['skipped_count'] ?? 0),
            'failed' => (int) ($result['failed'] ?? $result['failed_count'] ?? 0),
        ];
    }
}
