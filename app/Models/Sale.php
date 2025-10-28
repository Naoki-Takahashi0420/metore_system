<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PointCard;
use App\Models\CustomerSubscription;
use App\Models\CustomerTicket;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_number',
        'reservation_id',
        'customer_id',
        'store_id',
        'staff_id',
        'sale_date',
        'sale_time',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'payment_method',
        'payment_source',
        'customer_subscription_id',
        'customer_ticket_id',
        'receipt_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'sale_time' => 'datetime:H:i',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * 売上番号を生成
     */
    public static function generateSaleNumber(): string
    {
        $prefix = 'SL';
        $date = now()->format('ymd');
        $lastSale = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastSale ? (intval(substr($lastSale->sale_number, -4)) + 1) : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 支払方法のラベル
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => '現金',
            'credit_card' => 'クレジットカード',
            'debit_card' => 'デビットカード',
            'paypay' => 'PayPay',
            'line_pay' => 'LINE Pay',
            'other' => 'その他',
            default => '不明',
        };
    }

    /**
     * ステータスのラベル
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'completed' => '完了',
            'cancelled' => 'キャンセル',
            'refunded' => '返金済み',
            'partial_refund' => '部分返金',
            default => '不明',
        };
    }

    /**
     * リレーション：予約
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * リレーション：顧客
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * リレーション：店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション：スタッフ
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * リレーション：売上明細
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * リレーション：サブスク
     */
    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }

    /**
     * リレーション：回数券
     */
    public function customerTicket(): BelongsTo
    {
        return $this->belongsTo(CustomerTicket::class, 'customer_ticket_id');
    }

    /**
     * 支払いソースのラベル
     */
    public function getPaymentSourceLabelAttribute(): string
    {
        return match($this->payment_source) {
            'spot' => 'スポット',
            'subscription' => 'サブスク',
            'ticket' => '回数券',
            'other' => 'その他',
            default => '不明',
        };
    }

    /**
     * 税額を計算
     */
    public function calculateTax($amount, $rate = 10): float
    {
        return round($amount * ($rate / (100 + $rate)), 2);
    }

    /**
     * 小計から合計を計算
     */
    public function calculateTotal(): void
    {
        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;
    }
    
    /**
     * ポイントを付与
     */
    public function grantPoints(): void
    {
        if (!$this->customer_id || $this->status !== 'completed') {
            return;
        }
        
        $customer = $this->customer;
        if (!$customer) {
            return;
        }
        
        // ポイントカードを取得または作成
        $pointCard = PointCard::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'card_number' => PointCard::generateCardNumber(),
                'issued_date' => now(),
                'status' => 'active',
            ]
        );
        
        // ポイント計算（100円につき1ポイント）
        $points = floor($this->total_amount / 100);
        
        if ($points > 0) {
            $pointCard->addPoints(
                $points,
                "売上番号 {$this->sale_number} のお買い上げ",
                $this->id
            );
        }
    }
}