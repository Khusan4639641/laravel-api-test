<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\BinaryNodeResource;
use App\Models\BinaryNode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StructureController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $rootNode = null;

        if ($request->filled('user_id')) {
            $user = User::query()->find((int) $request->integer('user_id'));
            $rootNode = $user?->binaryNode()->first();
        }

        $nodes = BinaryNode::query()
            ->with(['user.profile', 'user.currentPackage'])
            ->when($request->filled('user_id'), function ($query) use ($rootNode): void {
                if (! $rootNode) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where(function ($query) use ($rootNode): void {
                    $query->where('id', $rootNode->id)
                        ->orWhere('path', 'like', $rootNode->path.'.%');
                });
            })
            ->orderBy('depth')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($nodes, BinaryNodeResource::class, 'nodes', $request);
    }
}
