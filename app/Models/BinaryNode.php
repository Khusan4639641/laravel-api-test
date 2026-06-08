<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'parent_id', 'position', 'depth', 'path', 'is_active', 'deleted_by', 'deleted_reason', 'deleted_meta'])]
class BinaryNode extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
            'deleted_meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BinaryNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BinaryNode::class, 'parent_id');
    }

    public function leftChild(): HasOne
    {
        return $this->hasOne(BinaryNode::class, 'parent_id')->where('position', 'L');
    }

    public function rightChild(): HasOne
    {
        return $this->hasOne(BinaryNode::class, 'parent_id')->where('position', 'R');
    }
}
