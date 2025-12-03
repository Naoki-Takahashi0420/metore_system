<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcInvoiceItem extends Model
{
    protected $fillable = [
        'fc_invoice_id',
        'type',
        'fc_product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // アイテムタイプ定数
    const TYPE_PRODUCT = 'product';         // 商品
    const TYPE_ROYALTY = 'royalty';         // ロイヤリティ
    const TYPE_SYSTEM_FEE = 'system_fee';   // システム使用料
    const TYPE_CUSTOM = 'custom';           // カスタム項目

    public static function getTypes()
    {
        return [
            self::TYPE_PRODUCT => '商品',
            self::TYPE_ROYALTY => 'ロイヤリティ',
            self::TYPE_SYSTEM_FEE => 'システム使用料',
            self::TYPE_CUSTOM => 'その他',
        ];
    }

    /**
     * 請求書
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FcInvoice::class, 'fc_invoice_id');
    }

    /**
     * 商品（商品タイプの場合）
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(FcProduct::class, 'fc_product_id');
    }

    /**
     * 自動計算
     */
    public function calculateAmounts(): void
    {
        // 小計 = 数量 × 単価 - 値引き額
        $this->subtotal = (floatval($this->quantity) * floatval($this->unit_price)) - floatval($this->discount_amount);
        
        // 税額 = 小計 × 税率 / 100
        $this->tax_amount = $this->subtotal * (floatval($this->tax_rate) / 100);
        
        // 合計 = 小計 + 税額
        $this->total_amount = $this->subtotal + $this->tax_amount;
    }

    /**
     * 商品から明細を作成
     */
    public static function createFromProduct(FcProduct $product, int $quantity = 1): self
    {
        $item = new self([
            'type' => self::TYPE_PRODUCT,
            'fc_product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'tax_rate' => 10.00, // デフォルト10%
        ]);

        $item->calculateAmounts();
        return $item;
    }

    /**
     * カスタム明細を作成
     */
    public static function createCustom(string $type, string $description, float $amount): self
    {
        $item = new self([
            'type' => $type,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 10.00,
        ]);

        $item->calculateAmounts();
        return $item;
    }

    /**
     * タイプを日本語で取得
     */
    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }
}
