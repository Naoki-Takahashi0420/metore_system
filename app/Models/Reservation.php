<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_number',
        'store_id',
        'customer_id',
        'menu_id',
        'shift_id',
        'staff_id',
        'reservation_date',
        'start_time',
        'end_time',
        'status',
        'guest_count',
        'total_amount',
        'deposit_amount',
        'payment_method',
        'payment_status',
        'menu_items',
        'notes',
        'internal_notes',
        'source',
        'cancel_reason',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'guest_count' => 'integer',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'menu_items' => 'array',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * モデル作成時の処理
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            if (empty($reservation->reservation_number)) {
                $reservation->reservation_number = self::generateReservationNumber();
            }
        });
    }

    /**
     * 予約番号生成
     */
    public static function generateReservationNumber(): string
    {
        do {
            $number = 'R' . date('Ymd') . strtoupper(Str::random(6));
        } while (self::where('reservation_number', $number)->exists());

        return $number;
    }

    /**
     * リレーション: 店舗
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション: 顧客
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * リレーション: スタッフ
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * リレーション: メニュー
     */
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * リレーション: シフト
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * リレーション: カルテ
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * リレーション: オプションメニュー
     */
    public function optionMenus()
    {
        return $this->belongsToMany(Menu::class, 'reservation_menu_options')
            ->withPivot('price', 'duration')
            ->withTimestamps();
    }

    /**
     * リレーション: 予約オプション
     */
    public function reservationOptions()
    {
        return $this->hasMany(ReservationOption::class);
    }

    /**
     * スコープ: ステータス別
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * スコープ: 日付範囲
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('reservation_date', [$startDate, $endDate]);
    }

    /**
     * キャンセル可能かチェック
     */
    public function canCancel(): bool
    {
        if (in_array($this->status, ['cancelled', 'completed', 'no_show'])) {
            return false;
        }

        // 予約設定から締切時間を取得
        $deadlineHours = $this->store->reservation_settings['cancellation_deadline_hours'] ?? 24;
        $deadline = $this->reservation_date->copy()
            ->setTimeFromTimeString($this->start_time)
            ->subHours($deadlineHours);

        return now()->isBefore($deadline);
    }
}