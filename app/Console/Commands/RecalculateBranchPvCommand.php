<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DashboardBranchVolumeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('safi:recalculate-branch-pv {--chunk=200 : Users per chunk} {--force-production : Allow running in production after a backup}')]
#[Description('Recalculate cached branch PV indicators from binary downline without creating bonus or money transactions.')]
class RecalculateBranchPvCommand extends Command
{
    public function handle(DashboardBranchVolumeService $branchVolumeService): int
    {
        if (app()->environment('production') && ! $this->option('force-production')) {
            $this->error('Refusing to recalculate branch PV in production without --force-production. Run only after a backup.');

            return self::FAILURE;
        }

        $chunkSize = max((int) $this->option('chunk'), 1);
        $processed = 0;
        $changed = 0;

        User::query()
            ->where('role', User::ROLE_USER)
            ->with('binaryNode')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use (&$processed, &$changed, $branchVolumeService): void {
                foreach ($users as $user) {
                    $result = $branchVolumeService->syncCachedBranchVolumes($user);
                    $processed++;

                    if ($result['changed']) {
                        $changed++;
                    }
                }
            });

        $this->info('Branch PV recalculation completed:');
        $this->line("- processed users: {$processed}");
        $this->line("- updated users: {$changed}");
        $this->line('- remaining_left_pv / remaining_right_pv were not changed');
        $this->line('- no bonus transactions or wallet transactions were created');

        return self::SUCCESS;
    }
}
