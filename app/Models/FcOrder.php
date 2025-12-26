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
        'fc_invoice_id',
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
        'is_partial_shipped',
        'shipping_notes',
        'cutoff_date',
        'cutoff_cycle',
        'shipping_history',
        'shipped_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'ordered_at' => 'datetime',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cutoff_date' => 'date',
        'is_partial_shipped' => 'boolean',
        'shipping_history' => 'array',
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
     * 請求書
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FcInvoice::class, 'fc_invoice_id');
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
     * 発送処理者
     */
    public function shippedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
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

    /**
     * 締め日サイクル別スコープ
     */
    public function scopeByCutoffCycle($query, string $cycle)
    {
        return $query->where('cutoff_cycle', $cycle);
    }

    /**
     * 締め日計算
     */
    public static function calculateCutoffDate(string $cycle = 'month_end', ?Carbon $baseDate = null): Carbon
    {
        $date = $baseDate ?: Carbon::now();
        
        return match($cycle) {
            '15th' => $date->day <= 15 
                ? $date->copy()->day(15) 
                : $date->copy()->addMonth()->day(15),
            'month_end' => $date->copy()->endOfMonth(),
            default => $date->copy()->endOfMonth(),
        };
    }

    /**
     * 月初請求書発行対象の注文を取得
     */
    public static function getOrdersForMonthlyInvoicing(?Carbon $month = null): \Illuminate\Database\Eloquent\Collection
    {
        $targetMonth = $month ?: Carbon::now()->subMonth();
        
        return self::where('status', self::STATUS_DELIVERED)
            ->whereBetween('delivered_at', [
                $targetMonth->copy()->startOfMonth(),
                $targetMonth->copy()->endOfMonth()
            ])
            ->with(['fcStore', 'headquartersStore', 'items.product'])
            ->orderBy('fc_store_id')
            ->orderBy('delivered_at')
            ->get();
    }

    /**
     * 部分発送記録
     */
    public function recordPartialShipping(array $itemIds, ?string $notes = null, ?int $shippedBy = null): void
    {
        $history = $this->shipping_history ?? [];
        $history[] = [
            'type' => 'partial',
            'item_ids' => $itemIds,
            'notes' => $notes,
            'shipped_at' => now(),
            'shipped_by' => $shippedBy ?: auth()->id(),
        ];

        $this->update([
            'is_partial_shipped' => true,
            'shipping_history' => $history,
            'shipping_notes' => $notes,
            'shipped_by' => $shippedBy ?: auth()->id(),
        ]);
    }

    /**
     * 完全発送記録
     */
    public function recordFullShipping(?string $trackingNumber = null, ?string $notes = null, ?int $shippedBy = null): void
    {
        $history = $this->shipping_history ?? [];
        $history[] = [
            'type' => 'full',
            'tracking_number' => $trackingNumber,
            'notes' => $notes,
            'shipped_at' => now(),
            'shipped_by' => $shippedBy ?: auth()->id(),
        ];

        $this->update([
            'status' => self::STATUS_SHIPPED,
            'shipped_at' => now(),
            'shipping_tracking_number' => $trackingNumber,
            'shipping_notes' => $notes,
            'shipping_history' => $history,
            'shipped_by' => $shippedBy ?: auth()->id(),
        ]);
    }

    /**
     * 納品完了記録（請求書は月次発行に変更）
     *
     * @param string|null $notes 備考
     * @return void
     */
    public function recordDelivery(?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        // 納品完了通知（請求書は月次発行なので通知しない）
        try {
            app(FcNotificationService::class)->notifyOrderDeliveredWithoutInvoice($this);
        } catch (\Exception $e) {
            \Log::error("FC納品完了通知エラー: " . $e->getMessage());
        }
    }

    /**
     * 締め日サイクル設定
     */
    public function setCutoffCycle(string $cycle): void
    {
        $this->update([
            'cutoff_cycle' => $cycle,
            'cutoff_date' => self::calculateCutoffDate($cycle, $this->ordered_at)
        ]);
    }

    /**
     * 締め日文言取得
     */
    public function getCutoffCycleLabel(): string
    {
        return match($this->cutoff_cycle) {
            '15th' => '毎月15日締め',
            'month_end' => '月末締め',
            default => '月末締め',
        };
    }
}
