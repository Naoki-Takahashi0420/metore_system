<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Models\Store;
use App\Models\FcOrder;
use App\Models\FcInvoiceItem;

class FcInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'fc_store_id',
        'headquarters_store_id',
        'status',
        'billing_period_start',
        'billing_period_end',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    // ステータス定数
    const STATUS_DRAFT = 'draft';      // 下書き
    const STATUS_ISSUED = 'issued';    // 発行済み
    const STATUS_SENT = 'sent';        // 送付済み
    const STATUS_PAID = 'paid';        // 入金完了
    const STATUS_CANCELLED = 'cancelled'; // キャンセル

    /**
     * FC店舗（請求先）
     */
    public function fcStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'fc_store_id');
    }

    /**
     * 本部店舗（請求元）
     */
    public function headquartersStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'headquarters_store_id');
    }

    /**
     * 請求明細
     */
    public function items(): HasMany
    {
        return $this->hasMany(FcInvoiceItem::class, 'fc_invoice_id')->orderBy('sort_order');
    }

    /**
     * 入金記録
     */
    public function payments(): HasMany
    {
        return $this->hasMany(FcPayment::class, 'fc_invoice_id');
    }

    /**
     * 請求書番号を自動生成
     */
    public static function generateInvoiceNumber(): string
    {
        $yearMonth = Carbon::now()->format('Ym');
        $lastInvoice = self::where('invoice_number', 'like', "INV-{$yearMonth}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -4)) + 1 : 1;

        return sprintf('INV-%s-%04d', $yearMonth, $sequence);
    }

    /**
     * ステータスを日本語で取得
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '下書き',
            self::STATUS_ISSUED => '発行済み',
            self::STATUS_SENT => '送付済み',
            self::STATUS_PAID => '入金完了',
            self::STATUS_CANCELLED => 'キャンセル',
            default => $this->status,
        };
    }

    /**
     * 入金を記録して未払い金額を更新
     */
    public function recordPayment(float $amount): void
    {
        $newPaidAmount = floatval($this->paid_amount) + $amount;
        $newOutstanding = floatval($this->total_amount) - $newPaidAmount;

        $newStatus = $this->status;
        if ($newOutstanding <= 0) {
            $newStatus = self::STATUS_PAID;
            $newOutstanding = 0;
        }

        $this->update([
            'paid_amount' => $newPaidAmount,
            'outstanding_amount' => $newOutstanding,
            'status' => $newStatus,
        ]);
    }

    /**
     * 支払期限が過ぎているか（警告表示用）
     */
    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->isPast() &&
               !in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    /**
     * 注文から請求書を自動作成（単一注文用 - レガシー）
     */
    public static function createFromOrder(FcOrder $order): self
    {
        // 既に同じ注文番号で請求書が作成されている場合はスキップ
        $existingInvoice = self::where('notes', 'like', '%' . $order->order_number . '%')->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        // 請求書を作成（金額は明細から再計算するので0で初期化）
        $invoice = self::create([
            'invoice_number' => self::generateInvoiceNumber(),
            'fc_store_id' => $order->fc_store_id,
            'headquarters_store_id' => $order->headquarters_store_id,
            'status' => self::STATUS_ISSUED,
            'billing_period_start' => $order->delivered_at ? $order->delivered_at->startOfDay() : now()->startOfDay(),
            'billing_period_end' => $order->delivered_at ? $order->delivered_at->endOfDay() : now()->endOfDay(),
            'issue_date' => now(),
            'due_date' => now()->addDays(30), // 30日後が支払期限
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'outstanding_amount' => 0,
            'notes' => "発注番号: {$order->order_number}\n納品日: " . ($order->delivered_at ? $order->delivered_at->format('Y年m月d日') : '未定'),
        ]);

        // 発注明細から請求明細を作成
        $order->load('items.product');
        $sortOrder = 0;
        foreach ($order->items as $item) {
            FcInvoiceItem::create([
                'fc_invoice_id' => $invoice->id,
                'type' => FcInvoiceItem::TYPE_PRODUCT,
                'fc_product_id' => $item->fc_product_id,
                'description' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => 0,
                'subtotal' => $item->subtotal,
                'tax_rate' => $item->tax_rate ?? 10,
                'tax_amount' => $item->tax_amount,
                'total_amount' => $item->total,
                'notes' => null,
                'sort_order' => $sortOrder++,
            ]);
        }

        // 請求書合計を明細から再計算
        $invoice->recalculateTotals();
        $invoice->refresh();

        return $invoice;
    }

    /**
     * FC店舗の月次請求書を生成
     *
     * @param Store $fcStore FC店舗
     * @param Carbon|null $targetMonth 対象月（nullの場合は全未請求を対象）
     * @return self|null 生成された請求書（該当注文がない場合はnull）
     */
    public static function createMonthlyInvoice(Store $fcStore, ?Carbon $targetMonth = null): ?self
    {
        // 未請求の納品済み発注を取得
        $query = FcOrder::where('fc_store_id', $fcStore->id)
            ->where('status', FcOrder::STATUS_DELIVERED)
            ->whereNull('fc_invoice_id')
            ->with(['items.product'])
            ->orderBy('delivered_at', 'asc');

        // 対象月が指定されている場合は期間で絞り込み
        if ($targetMonth) {
            $startOfMonth = $targetMonth->copy()->startOfMonth();
            $endOfMonth = $targetMonth->copy()->endOfMonth();
            $query->whereBetween('delivered_at', [$startOfMonth, $endOfMonth]);
        }

        $unbilledOrders = $query->get();

        if ($unbilledOrders->isEmpty()) {
            return null;
        }

        // 請求期間を発注の納品日から算出
        $firstDeliveredAt = $unbilledOrders->min('delivered_at');
        $lastDeliveredAt = $unbilledOrders->max('delivered_at');
        $billingStart = Carbon::parse($firstDeliveredAt)->startOfDay();
        $billingEnd = Carbon::parse($lastDeliveredAt)->endOfDay();

        // 本部店舗ID取得
        $headquartersStoreId = $unbilledOrders->first()->headquarters_store_id;

        // 請求書作成
        $invoice = self::create([
            'invoice_number' => self::generateInvoiceNumber(),
            'fc_store_id' => $fcStore->id,
            'headquarters_store_id' => $headquartersStoreId,
            'status' => self::STATUS_DRAFT,
            'billing_period_start' => $billingStart,
            'billing_period_end' => $billingEnd,
            'issue_date' => null, // 発行時に設定
            'due_date' => Carbon::now()->addMonth()->endOfMonth(), // 翌月末
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'outstanding_amount' => 0,
            'notes' => "対象発注: " . $unbilledOrders->pluck('order_number')->join(', '),
        ]);

        // 各発注の商品を明細に追加 & 発注に請求書IDを紐付け
        $sortOrder = 0;
        foreach ($unbilledOrders as $order) {
            // 発注に請求書IDを紐付け
            $order->update(['fc_invoice_id' => $invoice->id]);

            foreach ($order->items as $item) {
                FcInvoiceItem::create([
                    'fc_invoice_id' => $invoice->id,
                    'type' => FcInvoiceItem::TYPE_PRODUCT,
                    'fc_product_id' => $item->fc_product_id,
                    'description' => $item->product_name . " (発注: {$order->order_number})",
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => 0,
                    'subtotal' => $item->subtotal,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'total_amount' => $item->total,
                    'notes' => "納品日: {$order->delivered_at->format('Y/m/d')}",
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // 請求書合計を再計算
        $invoice->recalculateTotals();

        // 日付のキャストを正しくするためリフレッシュ
        $invoice->refresh();

        return $invoice;
    }

    /**
     * 発注番号からアイテムを再生成（空の請求書修正用）
     */
    public function regenerateItemsFromOrders(): bool
    {
        // 既にアイテムがある場合はスキップ
        if ($this->items()->count() > 0) {
            return false;
        }

        // notesから発注番号を抽出 (ORD-YYYYMMDD-XXXX形式)
        preg_match_all('/ORD-\d{8}-\d{4}/', $this->notes ?? '', $matches);
        $orderNumbers = $matches[0] ?? [];

        if (empty($orderNumbers)) {
            return false;
        }

        $sortOrder = 0;
        foreach ($orderNumbers as $orderNumber) {
            $order = FcOrder::where('order_number', $orderNumber)
                ->with('items.product')
                ->first();

            if (!$order) {
                continue;
            }

            foreach ($order->items as $item) {
                FcInvoiceItem::create([
                    'fc_invoice_id' => $this->id,
                    'type' => FcInvoiceItem::TYPE_PRODUCT,
                    'fc_product_id' => $item->fc_product_id,
                    'description' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => 0,
                    'subtotal' => $item->subtotal,
                    'tax_rate' => $item->tax_rate ?? 10,
                    'tax_amount' => $item->tax_amount,
                    'total_amount' => $item->total,
                    'notes' => "発注: {$orderNumber}",
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // 合計を再計算
        $this->recalculateTotals();
        $this->refresh();

        return $sortOrder > 0;
    }

    /**
     * 請求書合計を明細から再計算
     */
    public function recalculateTotals(): void
    {
        $subtotal = 0;
        $taxAmount = 0;
        $totalAmount = 0;

        foreach ($this->items as $item) {
            $subtotal += floatval($item->subtotal);
            $taxAmount += floatval($item->tax_amount);
            $totalAmount += floatval($item->total_amount);
        }

        $paidAmount = floatval($this->paid_amount);
        $outstandingAmount = $totalAmount - $paidAmount;

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'outstanding_amount' => $outstandingAmount,
        ]);
    }

    /**
     * 全FC店舗の月次請求書を一括生成
     *
     * @param Carbon|null $targetMonth 対象月
     * @return array 生成結果 ['created' => [...], 'skipped' => [...]]
     */
    public static function generateMonthlyInvoicesForAllStores(?Carbon $targetMonth = null): array
    {
        $targetMonth = $targetMonth ?? Carbon::now()->subMonth();

        // 全FC店舗を取得
        $fcStores = Store::where('fc_type', 'fc_store')
            ->where('is_active', true)
            ->get();

        $created = [];
        $skipped = [];

        foreach ($fcStores as $fcStore) {
            $invoice = self::createMonthlyInvoice($fcStore, $targetMonth);

            if ($invoice) {
                $created[] = [
                    'store_name' => $fcStore->name,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => $invoice->total_amount,
                ];

                // 通知
                try {
                    app(\App\Services\FcNotificationService::class)->notifyMonthlyInvoiceGenerated($invoice);
                } catch (\Exception $e) {
                    \Log::error("月次請求書通知エラー: " . $e->getMessage());
                }
            } else {
                $skipped[] = [
                    'store_name' => $fcStore->name,
                    'reason' => '対象期間に未請求の納品がありません',
                ];
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * ステータス別スコープ
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNotIn('status', [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    /**
     * 支払期限超過の請求書を取得
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->startOfDay())
                     ->whereNotIn('status', [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    /**
     * 支払期限超過の請求書を自動的にマーク
     * @return int マークした請求書の件数
     */
    public static function markOverdueInvoices(): int
    {
        // 既に期限超過ステータスになっているものは除外
        // 現在は特別なステータス変更は行わず、カウントのみ返す
        // 将来的に'overdue'ステータスを追加する場合はここで更新処理を実装
        
        $overdueCount = self::overdue()->count();
        
        // 必要に応じてログ記録
        if ($overdueCount > 0) {
            \Log::info("FC請求書期限超過チェック: {$overdueCount}件の期限超過を検出");
        }
        
        return $overdueCount;
    }
}
