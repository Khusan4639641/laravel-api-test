<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __construct(
        private readonly StatusService $statusService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $statuses = collect($this->statusService->publicStatuses())
            ->map(function (array $status): array {
                $status['partners_count'] = User::query()->where('status', $status['id'])->count();

                return $status;
            })
            ->values()
            ->all();

        return response()->json([
            'statuses' => $statuses,
        ]);
    }
}
