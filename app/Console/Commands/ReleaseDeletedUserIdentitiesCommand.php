<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PartnerDeletionService;
use Illuminate\Console\Command;

class ReleaseDeletedUserIdentitiesCommand extends Command
{
    protected $signature = 'safi:release-deleted-user-identities {--chunk=200 : Users per chunk}';

    protected $description = 'Release login, email, phone and referral identity values for soft-deleted or archived users.';

    public function handle(PartnerDeletionService $partnerDeletionService): int
    {
        $chunkSize = max((int) $this->option('chunk'), 1);
        $found = $this->deletedOrArchivedUsersQuery()->count();
        $updated = 0;
        $skippedActive = 0;

        $this->deletedOrArchivedUsersQuery()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use (&$updated, &$skippedActive, $partnerDeletionService): void {
                foreach ($users as $user) {
                    if (! $user->trashed() && ! in_array((string) $user->account_status, ['deleted', 'archived'], true)) {
                        $skippedActive++;

                        continue;
                    }

                    if ($partnerDeletionService->releaseDeletedUserIdentity($user)) {
                        $updated++;
                    }
                }
            });

        $this->line("found count: {$found}");
        $this->line("updated count: {$updated}");
        $this->line("skipped active count: {$skippedActive}");

        return self::SUCCESS;
    }

    private function deletedOrArchivedUsersQuery()
    {
        return User::withTrashed()
            ->where(function ($query): void {
                $query->whereNotNull('deleted_at')
                    ->orWhereIn('account_status', ['deleted', 'archived']);
            });
    }
}
