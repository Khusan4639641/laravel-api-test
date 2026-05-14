<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Controller;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __construct(
        private readonly StatusService $statusService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->statusService->publicStatuses(),
            'statuses' => $this->statusService->publicStatuses(),
        ]);
    }
}
