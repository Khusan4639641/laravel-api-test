<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $news = News::query()
            ->where('is_published', true)
            ->where('status', 'published')
            ->latest('published_at')
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($news, NewsResource::class, 'news', $request);
    }

    public function show(News $news): JsonResponse
    {
        if (! $news->is_published || $news->status !== 'published') {
            abort(404);
        }

        return response()->json([
            'news' => NewsResource::make($news),
        ]);
    }
}
