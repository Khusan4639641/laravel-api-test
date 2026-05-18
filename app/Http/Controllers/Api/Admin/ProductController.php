<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($products, ProductResource::class, 'products', $request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);
        $product = Product::query()->create($validated);

        return response()->json([
            'product' => ProductResource::make($product),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => ProductResource::make($product),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validateProduct($request, $product);
        $product->update($validated);

        return response()->json([
            'product' => ProductResource::make($product->refresh()),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted.',
        ]);
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'name' => [$product ? 'sometimes' : 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'description' => ['nullable', 'string'],
            'price' => [$product ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'pv' => [$product ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($product && array_key_exists('metadata', $validated)) {
            $validated['metadata'] = array_replace($product->metadata ?? [], $validated['metadata'] ?? []);
        }

        return $validated;
    }
}
