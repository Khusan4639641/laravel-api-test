<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'slug',
    'category',
    'excerpt',
    'content',
    'image_url',
    'status',
    'is_published',
    'published_at',
    'sort_order',
    'metadata',
])]
class News extends Model
{
    protected $table = 'news';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
