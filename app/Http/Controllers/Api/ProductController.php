<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'products' => Product::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        return response()->json([
            'product' => $product,
        ]);
    }
}
