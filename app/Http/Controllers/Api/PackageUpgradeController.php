<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PackageUpgradeController extends Controller
{
    public function __construct(
        private readonly PackageService $packageService,
    ) {
    }

    public function __invoke(Request $request, Package $package): JsonResponse
    {
        if (! config('safi.user_package_changes_enabled', false)) {
            return response()->json([
                'message' => 'Смена пакета временно доступна только через администратора',
            ], 403);
        }

        if (! $package->is_active || $package->status !== 'active' || ! $package->is_upgradeable) {
            throw ValidationException::withMessages([
                'package' => 'Package is inactive.',
            ]);
        }

        $result = $this->packageService->upgradeExistingPackage($request->user(), $package);

        return response()->json([
            ...$result,
            'user' => UserResource::make($result['user']),
        ]);
    }
}
