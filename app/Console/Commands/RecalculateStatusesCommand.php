<?php

namespace App\Console\Commands;

use App\Services\StatusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mlm:recalculate-statuses')]
#[Description('Recalculate MLM statuses for all users.')]
class RecalculateStatusesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(StatusService $statusService): int
    {
        $count = $statusService->recalculateAll();

        $this->info("Recalculated statuses for {$count} users.");

        return self::SUCCESS;
    }
}
