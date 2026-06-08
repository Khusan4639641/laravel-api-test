<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $productData = $this->productData($validated);

        if ($request->hasFile('image')) {
            $productData['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::query()->create($productData);

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
        $productData = $this->productData($validated, $product);

        if ($request->boolean('remove_image')) {
            $this->deleteLocalImage($product);
            $productData['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteLocalImage($product);
            $productData['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($productData);

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
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => [$product ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'pv' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reserved_quantity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'is_deposit_product' => ['nullable', 'boolean'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        return $validated;
    }

    private function productData(array $validated, ?Product $product = null): array
    {
        unset($validated['image'], $validated['remove_image'], $validated['pv']);

        $metadata = $validated['metadata'] ?? null;

        if ($product && is_array($metadata)) {
            $metadata = array_replace($product->metadata ?? [], $metadata);
        }

        if (array_key_exists('category', $validated)) {
            $metadata = array_replace(is_array($metadata) ? $metadata : ($product?->metadata ?? []), [
                'category' => $validated['category'],
            ]);
        }

        unset($validated['category']);

        if (array_key_exists('price', $validated)) {
            $validated['pv'] = Product::priceToTurnoverPv($validated['price']);
        }

        if (is_array($metadata)) {
            $validated['metadata'] = $metadata;
        }

        return $validated;
    }

    private function deleteLocalImage(Product $product): void
    {
        if (! $product->image_path || str_starts_with($product->image_path, 'http://') || str_starts_with($product->image_path, 'https://') || str_starts_with($product->image_path, '/')) {
            return;
        }

        Storage::disk('public')->delete($product->image_path);
    }
}
