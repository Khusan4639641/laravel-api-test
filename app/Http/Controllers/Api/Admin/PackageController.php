<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $packages = Package::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($packages, PackageResource::class, 'packages', $request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePackage($request);
        $package = Package::query()->create($validated);

        return response()->json([
            'package' => PackageResource::make($package),
        ], 201);
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        $validated = $this->validatePackage($request, $package);
        $package->update($validated);

        return response()->json([
            'package' => PackageResource::make($package->refresh()),
        ]);
    }

    private function validatePackage(Request $request, ?Package $package = null): array
    {
        return $request->validate([
            'code' => [$package ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('packages', 'code')->ignore($package)],
            'name' => [$package ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [$package ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('packages', 'slug')->ignore($package)],
            'description' => ['nullable', 'string'],
            'price' => [$package ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'pv' => ['nullable', 'numeric', 'min:0'],
            'referral_percent' => ['nullable', 'numeric', 'min:0'],
            'binary_percent' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_upgradeable' => ['nullable', 'boolean'],
        ]);
    }
}
