<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'point_card_id',
        'type',
        'points',
        'balance_after',
        'sale_id',
        'reservation_id',
        'description',
        'expiry_date',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'expiry_date' => 'date',
    ];

    /**
     * リレーション：ポイントカード
     */
    public function pointCard(): BelongsTo
    {
        return $this->belongsTo(PointCard::class);
    }

    /**
     * リレーション：売上
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * リレーション：予約
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * タイプのラベル
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'earned' => '獲得',
            'used' => '使用',
            'expired' => '失効',
            'adjusted' => '調整',
            'bonus' => 'ボーナス',
            default => '不明',
        };
    }
}