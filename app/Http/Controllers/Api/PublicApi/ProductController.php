<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($products, ProductResource::class, 'products', $request);
    }

    public function show(Product $product): JsonResponse
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        return response()->json([
            'product' => ProductResource::make($product),
        ]);
    }
}
