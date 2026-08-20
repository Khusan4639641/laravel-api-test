<?php

namespace App\Console\Commands;

use App\Services\BinaryRecalculationRollbackService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

#[Signature('safi:binary-recalculation:rollback-batch
    {--started-at= : UTC start datetime}
    {--ended-at= : UTC end datetime}
    {--run-ids= : Optional comma-separated explicit run IDs}
    {--scope=entire-batch : adjustments-only|entire-batch}
    {--dry-run : Generate plan without database writes}
    {--manifest= : Path to generated or approved manifest}
    {--confirm-fingerprint= : Required for force mode}
    {--report= : JSON report path}
    {--force : Execute an approved rollback plan}
    {--control-user-id=69 : Control user shown separately in the report}')]
#[Description('Safely discover or roll back one binary recalculation batch from an approved immutable manifest.')]
class BinaryRecalculationRollbackBatchCommand extends Command
{
    public function handle(BinaryRecalculationRollbackService $service): int
    {
        try {
            return $this->option('force')
                ? $this->executeApprovedManifest($service)
                : $this->generateDryRun($service);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            $this->writeFailureReport($exception);

            return self::FAILURE;
        }
    }

    private function generateDryRun(BinaryRecalculationRollbackService $service): int
    {
        $startedAt = $this->utcOption('started-at');
        $endedAt = $this->utcOption('ended-at');
        $scope = (string) $this->option('scope');
        $runIds = $this->runIdsOption();
        $controlUserId = (int) $this->option('control-user-id');
        $manifest = $service->discover($startedAt, $endedAt, $scope, $runIds, $controlUserId);
        $manifestPath = $this->option('manifest')
            ? $this->resolvePath((string) $this->option('manifest'))
            : $this->defaultArtifactPath($startedAt, 'manifest');
        $reportPath = $this->option('report')
            ? $this->resolvePath((string) $this->option('report'))
            : $this->defaultArtifactPath($startedAt, 'dry-run');

        $this->writeJson($manifestPath, $manifest);
        $this->writeJson($reportPath, [
            'report_type' => 'binary_recalculation_rollback_dry_run',
            ...$manifest,
            'manifest_path' => $manifestPath,
        ]);

        $this->info('Binary recalculation rollback dry-run completed. No database records were changed.');
        $this->line('Manifest: '.$manifestPath);
        $this->line('Report: '.$reportPath);
        $this->line('Fingerprint: '.$manifest['fingerprint']);
        $this->renderOperations($manifest['operations']);
        $this->renderSummary($manifest['totals']);

        if ($manifest['control_user'] !== null) {
            $this->newLine();
            $this->info('Control user result');
            $this->table(['Metric', 'Value'], collect($manifest['control_user'])->map(
                fn ($value, $key): array => [$key, is_array($value) ? implode(', ', $value) : (string) $value]
            )->values()->all());
        }

        if ($manifest['blockers'] !== []) {
            $this->newLine();
            $this->error('Strict rollback blockers:');

            foreach ($manifest['blockers'] as $blocker) {
                $this->line('- '.$blocker);
            }
        }

        foreach ($manifest['errors'] as $error) {
            $this->error($error);
        }

        return ($manifest['blockers'] === [] && $manifest['errors'] === []) ? self::SUCCESS : self::FAILURE;
    }

    private function executeApprovedManifest(BinaryRecalculationRollbackService $service): int
    {
        if ($this->option('dry-run')) {
            throw new RuntimeException('--force and --dry-run cannot be used together.');
        }

        $manifestOption = trim((string) $this->option('manifest'));
        $confirmedFingerprint = trim((string) $this->option('confirm-fingerprint'));

        if ($manifestOption === '') {
            throw new RuntimeException('Force mode requires --manifest.');
        }

        if ($confirmedFingerprint === '') {
            throw new RuntimeException('Force mode requires --confirm-fingerprint.');
        }

        $manifestPath = $this->resolvePath($manifestOption);
        $manifest = $this->readJson($manifestPath);
        $scope = (string) $this->option('scope');

        if (($manifest['scope'] ?? null) !== $scope) {
            throw new RuntimeException('The command scope does not match the approved manifest scope.');
        }

        $startedAt = CarbonImmutable::parse((string) $manifest['started_at'])->utc();

        if ($service->hasCompletedRollback((string) ($manifest['fingerprint'] ?? ''))) {
            $this->warn('Binary recalculation batch has already been rolled back.');

            return self::SUCCESS;
        }

        $beforePath = $this->defaultArtifactPath($startedAt, 'before');
        $afterPath = $this->defaultArtifactPath($startedAt, 'after');
        $reportPath = $this->option('report')
            ? $this->resolvePath((string) $this->option('report'))
            : $this->defaultArtifactPath($startedAt, 'result');
        $manifest['report_path'] = $reportPath;
        $this->writeJson($beforePath, [
            'snapshot_type' => 'before',
            'approved_manifest_path' => $manifestPath,
            ...$manifest,
        ]);

        $result = $service->execute($manifest, $confirmedFingerprint);

        if (($result['already_rolled_back'] ?? false) === true) {
            $this->warn('Binary recalculation batch has already been rolled back.');

            return self::SUCCESS;
        }

        $this->writeJson($afterPath, [
            'snapshot_type' => 'after',
            'generated_at' => now('UTC')->toISOString(),
            ...$result,
        ]);
        $this->writeJson($reportPath, [
            ...$result,
            'approved_manifest_path' => $manifestPath,
            'before_snapshot_path' => $beforePath,
            'after_snapshot_path' => $afterPath,
            'report_path' => $reportPath,
        ]);

        $this->info($result['message']);
        $this->line('Result report: '.$reportPath);

        return self::SUCCESS;
    }

    private function renderOperations(array $operations): void
    {
        $rows = collect($operations)->map(fn (array $operation): array => [
            $operation['user_id'],
            $operation['email'],
            $operation['operation_type'],
            $operation['run_id'],
            $operation['bonus_transaction_id'],
            implode(',', $operation['wallet_transaction_ids']),
            $operation['current_main_balance'],
            $operation['current_deposit_balance'],
            $operation['financial']['main'],
            $operation['financial']['deposit'],
            $operation['expected_main_after_rollback'],
            $operation['expected_deposit_after_rollback'],
            $operation['later_financial_transactions_count'],
            implode(',', $operation['later_transaction_ids']),
            implode(',', $operation['related_pv_transaction_ids']),
            implode(',', [...$operation['source_calculation_ids'], ...$operation['batch_calculation_ids']]),
            $operation['can_rollback'] ? 'yes' : 'no',
            $operation['blocker_reason'],
        ])->all();

        $this->table([
            'user_id', 'email', 'operation_type', 'run_id', 'bonus_id', 'wallet_tx_ids',
            'main_now', 'deposit_now', 'main_reverse', 'deposit_reverse', 'main_after',
            'deposit_after', 'later_count', 'later_ids', 'pv_tx_ids', 'calculation_ids',
            'can_rollback', 'blocker_reason',
        ], $rows);
    }

    private function renderSummary(array $totals): void
    {
        $this->newLine();
        $this->info('Summary');
        $this->table(['Metric', 'Value'], collect($totals)->map(
            fn ($value, $key): array => [$key, (string) $value]
        )->values()->all());
    }

    private function utcOption(string $name): CarbonImmutable
    {
        $value = trim((string) $this->option($name));

        if ($value === '') {
            throw new RuntimeException("--{$name} is required for dry-run discovery and must be UTC.");
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        } catch (\Throwable) {
            throw new RuntimeException("--{$name} is not a valid UTC datetime.");
        }
    }

    /** @return array<int, int>|null */
    private function runIdsOption(): ?array
    {
        $value = trim((string) $this->option('run-ids'));

        if ($value === '') {
            return null;
        }

        $ids = collect(explode(',', $value))->map(fn (string $id): int => (int) trim($id))->filter(fn (int $id): bool => $id > 0)->unique()->values()->all();

        if ($ids === []) {
            throw new RuntimeException('--run-ids did not contain any valid positive IDs.');
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (! File::isFile($path)) {
            throw new RuntimeException('Manifest file does not exist: '.$path);
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Manifest is not valid JSON: '.$path);
        }

        return $decoded;
    }

    private function writeJson(string $path, array $payload): void
    {
        File::ensureDirectoryExists(dirname($path), 0700, true);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;

        if (File::put($path, $json, true) === false) {
            throw new RuntimeException('Could not write JSON artifact: '.$path);
        }

        @chmod($path, 0600);
    }

    private function resolvePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    private function defaultArtifactPath(CarbonImmutable $startedAtUtc, string $suffix): string
    {
        $local = $startedAtUtc->setTimezone((string) config('app.timezone', 'Asia/Tashkent'));
        $name = sprintf('binary-%s-%s-%s.json', $local->format('Y-m-d'), $local->format('Hi'), $suffix);

        return storage_path('app/private/binary-rollbacks/'.$name);
    }

    private function writeFailureReport(\Throwable $exception): void
    {
        $report = trim((string) $this->option('report'));

        if ($report === '') {
            return;
        }

        try {
            $this->writeJson($this->resolvePath($report), [
                'result' => 'failed',
                'generated_at' => now('UTC')->toISOString(),
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            // Preserve the original failure as the command result.
        }
    }
}
