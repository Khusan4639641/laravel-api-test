<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PartnerDeletionService;
use Illuminate\Console\Command;

class SafiRecalculateMlmCommand extends Command
{
    protected $signature = 'safi:recalculate-mlm {--user= : Recalculate one active user by id} {--all : Recalculate all active non-deleted users}';

    protected $description = 'Recalculate MLM branch PV, weak leg and statuses from active non-deleted data without creating new bonuses.';

    public function handle(PartnerDeletionService $partnerDeletionService): int
    {
        if ($this->option('all')) {
            $count = $partnerDeletionService->recalculateAllActiveUsers();
            $this->info("Recalculated MLM for {$count} active users.");

            return self::SUCCESS;
        }

        $userId = $this->option('user');

        if ($userId === null || $userId === '') {
            $this->error('Use --user={id} or --all.');

            return self::FAILURE;
        }

        $user = User::query()->find((int) $userId);

        if (! $user) {
            $this->error('Active user not found.');

            return self::FAILURE;
        }

        $partnerDeletionService->recalculateAffectedUplines([(int) $user->id]);
        $this->info("Recalculated MLM for user {$user->id}.");

        return self::SUCCESS;
    }
}
