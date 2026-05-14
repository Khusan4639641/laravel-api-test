<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use RespondsWithPagination;

    public function __invoke(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($products, ProductResource::class, 'products', $request);
    }
}
