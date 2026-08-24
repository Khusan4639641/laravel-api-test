<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $newsData = $this->newsData($validated);

        if ($request->hasFile('image')) {
            $newsData['image_url'] = $this->storeNewsImage($request);
        }

        $newsData['slug'] ??= Str::slug($newsData['title']);
        $newsData['published_at'] ??= ($newsData['is_published'] ?? true) ? now() : null;

        $news = News::query()->create($newsData);
        $resource = NewsResource::make($news);

        return response()->json([
            'message' => __('api.news.created'),
            'data' => $resource,
            'news' => $resource,
        ], 201);
    }

    public function show(News $news): JsonResponse
    {
        return response()->json([
            'news' => NewsResource::make($news),
        ]);
    }

    public function update(Request $request, News $news): JsonResponse
    {
        $validated = $this->validateNews($request, $news);
        $newsData = $this->newsData($validated, $news);

        if ($request->boolean('remove_image')) {
            $this->deleteLocalNewsImage($news);
            $newsData['image_url'] = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteLocalNewsImage($news);
            $newsData['image_url'] = $this->storeNewsImage($request);
        }

        if (array_key_exists('title', $newsData) && ! array_key_exists('slug', $newsData)) {
            $newsData['slug'] = Str::slug($newsData['title']);
        }

        if (($newsData['is_published'] ?? false) && ! $news->published_at && ! array_key_exists('published_at', $newsData)) {
            $newsData['published_at'] = now();
        }

        $news->update($newsData);
        $resource = NewsResource::make($news->refresh());

        return response()->json([
            'message' => __('api.news.saved'),
            'data' => $resource,
            'news' => $resource,
        ]);
    }

    public function destroy(News $news): JsonResponse
    {
        $news->delete();

        return response()->json([
            'message' => __('api.news.deleted'),
        ]);
    }

    private function validateNews(Request $request, ?News $news = null): array
    {
        $aliases = [];

        if (! $request->has('excerpt') && $request->has('summary')) {
            $aliases['excerpt'] = $request->input('summary');
        }

        if (! $request->has('content') && $request->has('body')) {
            $aliases['content'] = $request->input('body');
        }

        if ($aliases !== []) {
            $request->merge($aliases);
        }

        return $request->validate([
            'title' => [$news ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('news', 'slug')->ignore($news)],
            'category' => [$news ? 'sometimes' : 'required', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => [$news ? 'sometimes' : 'required', 'string'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'status' => [$news ? 'sometimes' : 'required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function newsData(array $validated, ?News $news = null): array
    {
        unset($validated['image'], $validated['remove_image'], $validated['summary'], $validated['body']);

        if (array_key_exists('status', $validated)) {
            $validated['is_published'] = $validated['status'] === 'published';

            if ($validated['status'] !== 'published' && ! array_key_exists('published_at', $validated)) {
                $validated['published_at'] = null;
            }
        } elseif (array_key_exists('is_published', $validated)) {
            $validated['status'] = $validated['is_published'] ? 'published' : 'draft';
        } elseif (! $news) {
            $validated['status'] = 'published';
            $validated['is_published'] = true;
        }

        return $validated;
    }

    private function storeNewsImage(Request $request): string
    {
        $path = $request->file('image')->store('news', 'public');

        return Storage::url($path);
    }

    private function deleteLocalNewsImage(News $news): void
    {
        $path = parse_url((string) $news->image_url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return;
        }

        if (str_starts_with($path, '/storage/news/')) {
            Storage::disk('public')->delete(Str::after($path, '/storage/'));

            return;
        }

        if (str_starts_with($path, 'news/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
