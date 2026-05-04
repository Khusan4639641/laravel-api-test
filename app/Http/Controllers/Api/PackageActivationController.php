<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package' => 'Package is inactive.',
            ]);
        }

        if (! $this->packageService->canUpgrade($user, $package)) {
            throw ValidationException::withMessages([
                'package' => 'Selected package is lower than the current package.',
            ]);
        }

        $user = $this->packageService->upgradePackage($user, $package);

        return response()->json([
            'user' => $user,
        ]);
    }
}
