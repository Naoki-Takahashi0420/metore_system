<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'closing_date',
        'open_time',
        'close_time',
        'opening_cash',
        'cash_sales',
        'card_sales',
        'digital_sales',
        'total_sales',
        'expected_cash',
        'actual_cash',
        'cash_difference',
        'transaction_count',
        'customer_count',
        'sales_by_staff',
        'sales_by_menu',
        'status',
        'closed_by',
        'verified_by',
        'closed_at',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'open_time' => 'datetime:H:i',
        'close_time' => 'datetime:H:i',
        'opening_cash' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'digital_sales' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'transaction_count' => 'integer',
        'customer_count' => 'integer',
        'sales_by_staff' => 'array',
        'sales_by_menu' => 'array',
        'closed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * リレーション：店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション：締め処理者
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * リレーション：承認者
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * 差異があるかチェック
     */
    public function hasDifference(): bool
    {
        return abs($this->cash_difference) > 0;
    }

    /**
     * ステータスのラベル
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'open' => '未精算',
            'closed' => '精算済み',
            'verified' => '承認済み',
            default => '不明',
        };
    }
}