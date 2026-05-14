<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NewsController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $news = News::query()
            ->latest('published_at')
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($news, NewsResource::class, 'news', $request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateNews($request);
        $validated['slug'] ??= Str::slug($validated['title']);
        $validated['published_at'] ??= ($validated['is_published'] ?? true) ? now() : null;

        $news = News::query()->create($validated);

        return response()->json([
            'news' => NewsResource::make($news),
        ], 201);
    }

    public function update(Request $request, News $news): JsonResponse
    {
        $validated = $this->validateNews($request, $news);

        if (array_key_exists('title', $validated) && ! array_key_exists('slug', $validated)) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        if (($validated['is_published'] ?? false) && ! $news->published_at && ! array_key_exists('published_at', $validated)) {
            $validated['published_at'] = now();
        }

        $news->update($validated);

        return response()->json([
            'news' => NewsResource::make($news->refresh()),
        ]);
    }

    public function destroy(News $news): JsonResponse
    {
        $news->delete();

        return response()->json([
            'message' => 'News deleted.',
        ]);
    }

    private function validateNews(Request $request, ?News $news = null): array
    {
        return $request->validate([
            'title' => [$news ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('news', 'slug')->ignore($news)],
            'category' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => [$news ? 'sometimes' : 'required', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'status' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
