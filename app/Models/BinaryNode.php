<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'parent_id', 'position', 'depth', 'path'])]
class BinaryNode extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
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
        return $this->hasOne(BinaryNode::class, 'parent_id')->where('position', 'left');
    }

    public function rightChild(): HasOne
    {
        return $this->hasOne(BinaryNode::class, 'parent_id')->where('position', 'right');
    }
}
