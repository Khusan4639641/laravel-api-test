<?php

namespace App\Http\Resources;

use App\Support\LocalizedValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => LocalizedValue::get($this->category_translations, $this->category),
            'question' => LocalizedValue::get($this->question_translations, $this->question),
            'answer' => LocalizedValue::get($this->answer_translations, $this->answer),
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'translations' => [
                'category' => $this->category_translations,
                'question' => $this->question_translations,
                'answer' => $this->answer_translations,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
