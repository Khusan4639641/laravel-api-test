<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $faqs = Faq::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($faqs, FaqResource::class, 'faqs', $request);
    }

    public function store(Request $request): JsonResponse
    {
        $faq = Faq::query()->create($this->validateFaq($request));

        return response()->json([
            'faq' => FaqResource::make($faq),
        ], 201);
    }

    public function update(Request $request, Faq $faq): JsonResponse
    {
        $faq->update($this->validateFaq($request, true));

        return response()->json([
            'faq' => FaqResource::make($faq->refresh()),
        ]);
    }

    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json([
            'message' => 'FAQ deleted.',
        ]);
    }

    private function validateFaq(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'category' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'question' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'answer' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
