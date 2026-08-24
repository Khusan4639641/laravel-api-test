<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\EarningsSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsSummaryController extends Controller
{
    public function __construct(
        private readonly EarningsSummaryService $earningsSummaryService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'summary' => $this->earningsSummaryService->forUser($request->user()),
        ]);
    }
}
