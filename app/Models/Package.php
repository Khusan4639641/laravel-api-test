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
    'activity_pv',
    'turnover_pv',
    'referral_percent',
    'binary_percent',
    'sort_order',
    'status',
    'is_active',
    'is_upgradeable',
    'name_translations',
    'description_translations',
])]
class Package extends Model
{
    public const PUBLIC_CODES = ['START', 'VIP', 'ELITE'];

    public const STARTER_CODES = ['START', 'VIP'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pv' => 'decimal:2',
            'activity_pv' => 'decimal:2',
            'turnover_pv' => 'decimal:2',
            'referral_percent' => 'decimal:2',
            'binary_percent' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_upgradeable' => 'boolean',
            'name_translations' => 'array',
            'description_translations' => 'array',
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

    public function scopeActiveRegistrationStarter(Builder $query): Builder
    {
        return $query
            ->whereIn('code', self::STARTER_CODES)
            ->where('status', 'active')
            ->where('is_active', true);
    }

    public function activityPv(): string
    {
        $activityPv = (string) ($this->activity_pv ?? 0);

        return bccomp($activityPv, '0', 2) > 0 ? $activityPv : (string) $this->pv;
    }

    public function turnoverPv(): string
    {
        $turnoverPv = (string) ($this->turnover_pv ?? 0);

        return bccomp($turnoverPv, '0', 2) > 0 ? $turnoverPv : $this->activityPv();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
