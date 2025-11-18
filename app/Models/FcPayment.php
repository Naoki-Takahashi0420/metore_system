<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcPayment extends Model
{
    protected $fillable = [
        'fc_invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
        'confirmed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    // 支払方法定数
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CASH = 'cash';
    const METHOD_OTHER = 'other';

    /**
     * 請求書
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FcInvoice::class, 'fc_invoice_id');
    }

    /**
     * 確認者（ユーザー）
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * 支払方法を日本語で取得
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_BANK_TRANSFER => '銀行振込',
            self::METHOD_CASH => '現金',
            self::METHOD_OTHER => 'その他',
            default => $this->payment_method,
        };
    }

    /**
     * 作成時に請求書の入金額を自動更新
     */
    protected static function booted(): void
    {
        static::created(function (FcPayment $payment) {
            $payment->invoice->recordPayment(floatval($payment->amount));
        });
    }
}
