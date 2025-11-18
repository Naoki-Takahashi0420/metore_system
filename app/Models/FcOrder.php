<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class FcOrder extends Model
{
    protected $fillable = [
        'order_number',
        'fc_store_id',
        'headquarters_store_id',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'rejection_reason',
        'ordered_at',
        'approved_at',
        'shipped_at',
        'delivered_at',
        'shipping_tracking_number',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'ordered_at' => 'datetime',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // ステータス定数
    const STATUS_DRAFT = 'draft';        // 下書き
    const STATUS_ORDERED = 'ordered';    // 発注済み（承認プロセスなし）
    const STATUS_SHIPPED = 'shipped';    // 発送済み
    const STATUS_DELIVERED = 'delivered'; // 納品完了
    const STATUS_CANCELLED = 'cancelled'; // キャンセル

    /**
     * FC店舗（発注元）
     */
    public function fcStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'fc_store_id');
    }

    /**
     * 本部店舗（発注先）
     */
    public function headquartersStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'headquarters_store_id');
    }

    /**
     * 発注明細
     */
    public function items(): HasMany
    {
        return $this->hasMany(FcOrderItem::class, 'fc_order_id');
    }

    /**
     * 発注番号を自動生成
     */
    public static function generateOrderNumber(): string
    {
        $date = Carbon::now()->format('Ymd');
        $lastOrder = self::whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastOrder ? intval(substr($lastOrder->order_number, -4)) + 1 : 1;

        return sprintf('ORD-%s-%04d', $date, $sequence);
    }

    /**
     * 合計金額を再計算
     */
    public function recalculateTotals(): void
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($this->items as $item) {
            $subtotal += floatval($item->subtotal);
            $taxAmount += floatval($item->tax_amount);
        }

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
        ]);
    }

    /**
     * ステータスを日本語で取得
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '下書き',
            self::STATUS_ORDERED => '発注済み',
            self::STATUS_SHIPPED => '発送済み',
            self::STATUS_DELIVERED => '納品完了',
            self::STATUS_CANCELLED => 'キャンセル',
            default => $this->status,
        };
    }

    /**
     * 編集可能か
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT]);
    }

    /**
     * キャンセル可能か
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ORDERED]);
    }

    /**
     * 発送可能か
     */
    public function isShippable(): bool
    {
        return $this->status === self::STATUS_ORDERED;
    }

    /**
     * 納品完了にできるか
     */
    public function isDeliverable(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    /**
     * ステータス別スコープ
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOrdered($query)
    {
        return $query->where('status', self::STATUS_ORDERED);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_DELIVERED]);
    }
}
