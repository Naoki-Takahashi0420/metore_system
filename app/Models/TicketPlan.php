<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'menu_id',
        'name',
        'ticket_count',
        'price',
        'validity_days',
        'validity_months',
        'description',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'ticket_count' => 'integer',
        'price' => 'integer',
        'validity_days' => 'integer',
        'validity_months' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * 店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * メニュー
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * このプランで購入された顧客回数券
     */
    public function customerTickets(): HasMany
    {
        return $this->hasMany(CustomerTicket::class);
    }

    /**
     * スコープ: アクティブなプランのみ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * スコープ: 特定店舗のプラン
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * 表示名を取得（回数と価格を含む）
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->ticket_count}回券 ¥" . number_format($this->price) . ")";
    }

    /**
     * 有効期限の説明文を取得
     */
    public function getValidityDescriptionAttribute(): string
    {
        if ($this->validity_months && $this->validity_days) {
            return "{$this->validity_months}ヶ月{$this->validity_days}日";
        } elseif ($this->validity_months) {
            return "{$this->validity_months}ヶ月";
        } elseif ($this->validity_days) {
            return "{$this->validity_days}日";
        }

        return '無期限';
    }

    /**
     * 1回あたりの単価を計算
     */
    public function getUnitPriceAttribute(): int
    {
        if ($this->ticket_count <= 0) {
            return 0;
        }

        return (int) ($this->price / $this->ticket_count);
    }

    /**
     * このプランが有効かどうか
     */
    public function isAvailable(): bool
    {
        return $this->is_active;
    }
}
