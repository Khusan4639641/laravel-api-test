<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BinaryNodeResource;
use App\Models\BinaryNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StructureController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $nodes = BinaryNode::query()
            ->with(['user.profile', 'user.currentPackage'])
            ->orderBy('depth')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($nodes, BinaryNodeResource::class, 'nodes', $request);
    }
}
