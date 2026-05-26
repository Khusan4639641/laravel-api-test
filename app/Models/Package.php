<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'slug',
    'description',
    'price',
    'pv',
    'referral_percent',
    'binary_percent',
    'sort_order',
    'status',
    'is_active',
    'is_upgradeable',
])]
class Package extends Model
{
    public const PUBLIC_CODES = ['START', 'VIP', 'ELITE'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pv' => 'decimal:2',
            'referral_percent' => 'decimal:2',
            'binary_percent' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_upgradeable' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_package_id');
    }

    public function scopeActiveStarter(Builder $query): Builder
    {
        return $query
            ->whereIn('code', self::PUBLIC_CODES)
            ->where('status', 'active')
            ->where('is_active', true);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
