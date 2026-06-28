<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StatusBonusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('safi:status-bonuses:repair
    {--user-id= : Repair one user only}
    {--all : Check all active partner users}
    {--dry-run : Show what would be repaired without changing data}
    {--force : Allow write mode}
    {--seed-definitions : Seed missing default status bonus definitions idempotently}
    {--repair-ledger : Repair missing bonus/wallet ledger when marker already exists}
    {--only-missing : Show only statuses with missing marker or ledger}
    {--status= : Limit checks to one status code, for example director}')]
#[Description('Check and repair missed eligible status bonuses without changing PV or binary calculations.')]
class RepairStatusBonusesCommand extends Command
{
    public function handle(StatusBonusService $statusBonusService): int
    {
        $userId = (int) $this->option('user-id');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $seedDefinitions = (bool) $this->option('seed-definitions');
        $statusCode = $this->statusCode();

        if ($userId <= 0 && ! $all) {
            $this->error('Provide --user-id=ID or --all.');

            return self::FAILURE;
        }

        if ($userId > 0 && $all) {
            $this->error('Use either --user-id=ID or --all, not both.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $force) {
            $this->error('Refusing to write status bonus repairs without --force. Use --dry-run to inspect first.');

            return self::FAILURE;
        }

        if ($userId > 0 && ! User::query()->activeAccount()->where('role', User::ROLE_USER)->whereKey($userId)->exists()) {
            $this->error("Active partner user {$userId} was not found.");

            return self::FAILURE;
        }

        $health = $statusBonusService->definitionsHealth($statusCode);
        $this->line("Definitions: {$health['existing']}/{$health['required']} present.");

        if ($health['missing'] > 0) {
            $this->warn('status_bonus_definitions is missing required statuses: '.implode(', ', $health['missing_codes']));
        }

        if ($seedDefinitions) {
            $seedResult = $statusBonusService->seedDefaultDefinitions($dryRun, $statusCode);
            $this->table(['Metric', 'Value'], [
                ['seed_dry_run', $dryRun ? 'yes' : 'no'],
                ['defaults_total', $seedResult['total']],
                ['existing_before', $seedResult['existing']],
                ['missing_before', $seedResult['missing']],
                ['created', $seedResult['created']],
                ['updated', $seedResult['updated']],
                ['missing_codes', implode(', ', $seedResult['missing_codes'])],
            ]);
        }

        if ($health['missing'] > 0 && ! $seedDefinitions) {
            $this->warn('Run with --seed-definitions to create/update default definitions idempotently.');
        }

        $summary = [
            'total_users' => 0,
            'eligible_users' => 0,
            'awarded_count' => 0,
            'repaired_count' => 0,
            'already_awarded_count' => 0,
            'skipped_count' => 0,
            'warnings_count' => 0,
            'errors_count' => 0,
        ];

        $query = User::query()
            ->with('currentPackage')
            ->activeAccount()
            ->where('role', User::ROLE_USER)
            ->when($userId > 0, fn ($query) => $query->whereKey($userId))
            ->orderBy('id');

        $query->chunkById(200, function ($users) use ($statusBonusService, $dryRun, $statusCode, &$summary): void {
            foreach ($users as $user) {
                $summary['total_users']++;

                try {
                    $result = $statusBonusService->repairForUser($user, [
                        'dry_run' => $dryRun,
                        'repair_ledger' => (bool) $this->option('repair-ledger'),
                        'only_missing' => (bool) $this->option('only-missing'),
                        'status_code' => $statusCode,
                        'source' => 'status_bonus_repair',
                    ]);

                    if ($result['eligible']) {
                        $summary['eligible_users']++;
                    }

                    foreach (['awarded_count', 'repaired_count', 'already_awarded_count', 'skipped_count', 'warnings_count', 'errors_count'] as $key) {
                        $summary[$key] += (int) ($result[$key] ?? 0);
                    }

                    $this->renderUserResult($result);
                } catch (\Throwable $exception) {
                    $summary['errors_count']++;
                    $this->error("User #{$user->id}: {$exception->getMessage()}");
                    report($exception);
                }
            }
        });

        $this->table(['Metric', 'Value'], collect($summary)
            ->map(fn (int $value, string $key): array => [$key, $value])
            ->values()
            ->all());

        if ($dryRun) {
            $this->line('Dry run completed. No wallet, bonus, marker, PV or turnover records were changed.');
        }

        return $summary['errors_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function statusCode(): ?string
    {
        $status = trim((string) ($this->option('status') ?? ''));

        return $status === '' ? null : $status;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderUserResult(array $result): void
    {
        $this->line(sprintf(
            'User #%s package=%s weak_leg_pv=%s eligible=%s skipped=%s',
            $result['user_id'],
            $result['package_code'] ?? 'none',
            $result['weak_leg_pv'],
            $result['eligible'] ? 'yes' : 'no',
            $result['skipped_reason'] ?? '-',
        ));

        $rows = collect($result['rows'] ?? []);

        if ($rows->isEmpty()) {
            return;
        }

        $this->table(
            ['Status', 'Threshold PV', 'Amount', 'Action', 'Missing', 'Warnings'],
            $rows
                ->map(fn (array $row): array => [
                    $row['status_code'],
                    $row['threshold_pv'],
                    $row['amount'],
                    $row['action'] ?? '-',
                    implode(', ', $row['missing'] ?? []),
                    implode(', ', $row['warnings'] ?? []),
                ])
                ->all(),
        );
    }
}
