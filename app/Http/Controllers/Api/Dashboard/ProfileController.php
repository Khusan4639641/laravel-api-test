<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make(
                $request->user()->load(['profile', 'wallets', 'currentPackage', 'sponsor', 'binaryNode'])
            ),
        ]);
    }
}
