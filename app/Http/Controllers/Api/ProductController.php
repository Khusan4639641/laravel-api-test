<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'products' => ProductResource::collection(Product::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->get()),
        ]);
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
