<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category',
    'question',
    'answer',
    'sort_order',
    'status',
    'is_active',
    'metadata',
    'category_translations',
    'question_translations',
    'answer_translations',
])]
class Faq extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'category_translations' => 'array',
            'question_translations' => 'array',
            'answer_translations' => 'array',
        ];
    }
}
