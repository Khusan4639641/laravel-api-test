<?php

namespace App\Http\Resources;

use App\Support\LocalizedValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => LocalizedValue::get($this->title_translations, $this->title),
            'slug' => $this->slug,
            'category' => LocalizedValue::get($this->category_translations, $this->category),
            'excerpt' => LocalizedValue::get($this->excerpt_translations, $this->excerpt),
            'content' => LocalizedValue::get($this->content_translations, $this->content),
            'summary' => LocalizedValue::get($this->excerpt_translations, $this->excerpt),
            'body' => LocalizedValue::get($this->content_translations, $this->content),
            'image_url' => $this->image_url,
            'imageUrl' => $this->image_url,
            'status' => $this->status,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toISOString(),
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'translations' => [
                'title' => $this->title_translations,
                'category' => $this->category_translations,
                'excerpt' => $this->excerpt_translations,
                'content' => $this->content_translations,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
