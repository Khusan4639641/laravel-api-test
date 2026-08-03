<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PackageActivationController extends Controller
{
    public function __construct(
        private readonly PackageService $packageService,
    ) {
    }

    public function __invoke(Request $request, Package $package): JsonResponse
    {
        $user = $request->user();

        if (! config('safi.user_package_changes_enabled', false)) {
            return response()->json([
                'message' => __('api.package_purchase_disabled'),
            ], 403);
        }

        if (! $package->is_active || $package->status !== 'active') {
            throw ValidationException::withMessages([
                'package' => 'Package is inactive.',
            ]);
        }

        if (! in_array($package->code, Package::STARTER_CODES, true)) {
            throw ValidationException::withMessages([
                'package' => 'Package is not available for activation.',
            ]);
        }

        if ($user->current_package_id) {
            throw ValidationException::withMessages([
                'package' => 'User already has an active package. Use upgrade flow.',
            ]);
        }

        $user = $this->packageService->upgradePackage($user, $package);

        return response()->json([
            'user' => UserResource::make($user),
        ]);
    }
}
