<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'menu_option_id',
        'quantity',
        'price',
        'duration_minutes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
        'duration_minutes' => 'integer',
    ];

    /**
     * 予約
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * メニューオプション
     */
    public function menuOption(): BelongsTo
    {
        return $this->belongsTo(MenuOption::class);
    }

    /**
     * 合計金額
     */
    public function getTotalPriceAttribute(): int
    {
        return $this->price * $this->quantity;
    }

    /**
     * 合計追加時間
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->duration_minutes * $this->quantity;
    }
}