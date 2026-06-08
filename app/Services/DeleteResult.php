<?php

namespace App\Services;

class DeleteResult
{
    /**
     * @param  array<int, int>  $deletedUserIds
     * @param  array<int, int>  $affectedUplineIds
     */
    public function __construct(
        public readonly int $deletedUsersCount,
        public readonly int $affectedUplinesCount,
        public readonly array $deletedUserIds = [],
        public readonly array $affectedUplineIds = [],
    ) {
    }

    /**
     * @return array{deleted_users_count: int, affected_uplines_count: int, deleted_user_ids: array<int, int>, affected_upline_ids: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'deleted_users_count' => $this->deletedUsersCount,
            'affected_uplines_count' => $this->affectedUplinesCount,
            'deleted_user_ids' => $this->deletedUserIds,
            'affected_upline_ids' => $this->affectedUplineIds,
        ];
    }
}
