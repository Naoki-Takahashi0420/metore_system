<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

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
}
