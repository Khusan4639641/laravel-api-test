<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BinaryTreeService;
use Illuminate\Http\JsonResponse;

class ReferralController extends Controller
{
    public function __construct(
        private readonly BinaryTreeService $binaryTreeService,
    ) {
    }

    public function show(int $userId, string $branch): JsonResponse
    {
        $branch = strtoupper($branch);

        if (! in_array($branch, ['L', 'R'], true)) {
            return response()->json([
                'message' => 'Branch must be L or R.',
            ], 422);
        }

        $sponsor = User::query()
            ->with('binaryNode')
            ->find($userId);

        if (! $sponsor) {
            return response()->json([
                'message' => 'Sponsor not found.',
            ], 404);
        }

        $spilloverParent = $this->binaryTreeService->findSpilloverPosition($sponsor, $branch);

        return response()->json([
            'sponsor' => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'login' => $sponsor->login,
            ],
            'branch' => $branch,
            'is_valid' => true,
            'direct_branch_available' => $sponsor->binaryNode
                ? $this->binaryTreeService->hasFreePosition($sponsor->binaryNode, $branch)
                : true,
            'spillover_parent_id' => $spilloverParent?->user_id,
        ]);
    }
}
