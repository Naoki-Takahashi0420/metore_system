<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FcProduct extends Model
{
    protected $fillable = [
        'headquarters_store_id',
        'category_id',
        'sku',
        'name',
        'image_path',
        'description',
        'unit_price',
        'tax_rate',
        'unit',
        'stock_quantity',
        'min_order_quantity',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
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
     * カテゴリ
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FcProductCategory::class, 'category_id');
    }

    /**
     * 発注明細
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(FcOrderItem::class, 'fc_product_id');
    }

    /**
     * アクティブな商品のみ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 税込み単価を計算
     */
    public function getTaxIncludedPriceAttribute(): float
    {
        return floatval($this->unit_price) * (1 + floatval($this->tax_rate) / 100);
    }

    /**
     * 在庫があるか
     */
    public function hasStock(int $quantity = 1): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    /**
     * 在庫を減らす
     */
    public function decrementStock(int $quantity): void
    {
        $this->decrement('stock_quantity', $quantity);
    }

    /**
     * 在庫を増やす
     */
    public function incrementStock(int $quantity): void
    {
        $this->increment('stock_quantity', $quantity);
    }

    /**
     * 商品コード(SKU)を自動生成
     */
    public static function generateSku(): string
    {
        $prefix = 'FC-PRD-';
        $lastProduct = self::where('sku', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastProduct && preg_match('/FC-PRD-(\d+)/', $lastProduct->sku, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * モデル保存時に自動でSKU生成
     */
    protected static function booted(): void
    {
        static::creating(function (FcProduct $product) {
            if (empty($product->sku)) {
                $product->sku = self::generateSku();
            }
        });
    }
}
