<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FcProductCategory extends Model
{
    protected $fillable = [
        'headquarters_store_id',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * 本部店舗
     */
    public function headquartersStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'headquarters_store_id');
    }

    /**
     * このカテゴリの商品
     */
    public function products(): HasMany
    {
        return $this->hasMany(FcProduct::class, 'category_id');
    }

    /**
     * アクティブなカテゴリのみ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
