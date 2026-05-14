<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $packages = Package::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($packages, PackageResource::class, 'packages', $request);
    }
}
