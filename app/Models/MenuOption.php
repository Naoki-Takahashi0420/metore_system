<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'sort_order',
        'is_active',
        'is_required',
        'max_quantity',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'max_quantity' => 'integer',
    ];

    /**
     * 所属メニュー
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * フォーマット済み価格
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === 0) {
            return '無料';
        }
        return '¥' . number_format($this->price);
    }

    /**
     * フォーマット済み時間
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_minutes === 0) {
            return '';
        }
        return '+' . $this->duration_minutes . '分';
    }

    /**
     * スコープ: アクティブなオプション
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * スコープ: 必須オプション
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * スコープ: 任意オプション
     */
    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }

    /**
     * 並び順でソート
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}