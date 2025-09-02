<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'sort_order',
        'is_active',
        'store_id',
        'available_durations',
        'duration_prices',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'available_durations' => 'array',
        'duration_prices' => 'array',
    ];

    /**
     * 店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * カテゴリーに属するメニュー
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, 'category_id')->orderBy('sort_order');
    }

    /**
     * アクティブなメニュー
     */
    public function activeMenus(): HasMany
    {
        return $this->hasMany(Menu::class, 'category_id')
            ->where('is_available', true)
            ->orderBy('sort_order');
    }

    /**
     * スコープ: アクティブなカテゴリーのみ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * スコープ: 並び順でソート
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * スラッグを自動生成
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = \Str::slug($category->name . '-' . uniqid());
            }
        });
    }
}