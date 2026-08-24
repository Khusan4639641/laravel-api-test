<?php

namespace App\Http\Controllers\Api\PublicApi;

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
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($faqs, FaqResource::class, 'faqs', $request);
    }
}
