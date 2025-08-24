<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_number',
        'customer_id',
        'total_points',
        'available_points',
        'used_points',
        'expired_points',
        'status',
        'issued_date',
        'last_used_date',
        'expiry_date',
    ];

    protected $casts = [
        'total_points' => 'integer',
        'available_points' => 'integer',
        'used_points' => 'integer',
        'expired_points' => 'integer',
        'issued_date' => 'date',
        'last_used_date' => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * カード番号を生成
     */
    public static function generateCardNumber(): string
    {
        do {
            $number = 'XS' . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (self::where('card_number', $number)->exists());
        
        return $number;
    }

    /**
     * ポイントを追加
     */
    public function addPoints(int $points, string $description, ?int $saleId = null): void
    {
        $this->available_points += $points;
        $this->total_points += $points;
        $this->last_used_date = now();
        $this->save();
        
        // 履歴を記録
        $this->transactions()->create([
            'type' => 'earned',
            'points' => $points,
            'balance_after' => $this->available_points,
            'sale_id' => $saleId,
            'description' => $description,
            'expiry_date' => now()->addDays(365), // 1年後に失効
        ]);
    }

    /**
     * ポイントを使用
     */
    public function usePoints(int $points, string $description, ?int $saleId = null): bool
    {
        if ($this->available_points < $points) {
            return false;
        }
        
        $this->available_points -= $points;
        $this->used_points += $points;
        $this->last_used_date = now();
        $this->save();
        
        // 履歴を記録
        $this->transactions()->create([
            'type' => 'used',
            'points' => -$points,
            'balance_after' => $this->available_points,
            'sale_id' => $saleId,
            'description' => $description,
        ]);
        
        return true;
    }

    /**
     * 期限切れポイントを処理
     */
    public function expirePoints(): void
    {
        $expiredTransactions = $this->transactions()
            ->where('type', 'earned')
            ->where('expiry_date', '<', now())
            ->whereRaw('points > 0')
            ->get();
        
        $totalExpired = 0;
        foreach ($expiredTransactions as $transaction) {
            $remainingPoints = min($transaction->points, $this->available_points);
            if ($remainingPoints > 0) {
                $totalExpired += $remainingPoints;
                $transaction->points = 0;
                $transaction->save();
            }
        }
        
        if ($totalExpired > 0) {
            $this->available_points -= $totalExpired;
            $this->expired_points += $totalExpired;
            $this->save();
            
            $this->transactions()->create([
                'type' => 'expired',
                'points' => -$totalExpired,
                'balance_after' => $this->available_points,
                'description' => "{$totalExpired}ポイントが有効期限切れになりました",
            ]);
        }
    }

    /**
     * リレーション：顧客
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * リレーション：ポイント履歴
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }
}