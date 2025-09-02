<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
        'is_sub',
        'seat_number',
        'reservation_time',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'guest_count' => 'integer',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'menu_items' => 'array',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_sub' => 'boolean',
        'seat_number' => 'integer',
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
            
            // 重複チェック
            if (!self::checkAvailability($reservation)) {
                throw new \Exception('この時間帯は既に予約が入っています。');
            }
        });
        
        static::updating(function ($reservation) {
            // 更新時も重複チェック（キャンセル済み以外）
            if (!in_array($reservation->status, ['cancelled', 'canceled']) && 
                $reservation->isDirty(['start_time', 'end_time', 'seat_number', 'is_sub', 'reservation_date'])) {
                if (!self::checkAvailability($reservation)) {
                    throw new \Exception('この時間帯は既に予約が入っています。');
                }
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
    
    /**
     * 予約の重複をチェック
     */
    public static function checkAvailability($reservation): bool
    {
        // 開始時間と終了時間を取得
        $startTime = Carbon::parse($reservation->start_time);
        $endTime = Carbon::parse($reservation->end_time);
        
        // 同じ店舗、同じ日付の予約を取得
        $query = self::where('store_id', $reservation->store_id)
            ->whereDate('reservation_date', $reservation->reservation_date)
            ->whereNotIn('status', ['cancelled', 'canceled']);
        
        // 更新の場合は自分自身を除外
        if ($reservation->id) {
            $query->where('id', '!=', $reservation->id);
        }
        
        // 時間の重複チェック
        $overlappingReservations = $query->where(function ($q) use ($startTime, $endTime) {
            $q->where(function ($sub) use ($startTime, $endTime) {
                // 既存の予約の時間内に新規予約が重なる
                $sub->where('start_time', '<=', $startTime)
                    ->where('end_time', '>', $startTime);
            })->orWhere(function ($sub) use ($startTime, $endTime) {
                // 新規予約の時間内に既存の予約が重なる
                $sub->where('start_time', '<', $endTime)
                    ->where('end_time', '>=', $endTime);
            })->orWhere(function ($sub) use ($startTime, $endTime) {
                // 既存の予約が新規予約に完全に含まれる
                $sub->where('start_time', '>=', $startTime)
                    ->where('end_time', '<=', $endTime);
            });
        });
        
        // サブ枠の場合
        if ($reservation->is_sub) {
            // サブ枠でも時間の重複をチェック
            $overlapping = $overlappingReservations
                ->where('is_sub', true)
                ->exists();
                
            return !$overlapping;
        }
        
        // 通常席の場合
        if ($reservation->seat_number) {
            // 同じ席番号での重複をチェック
            $overlapping = $overlappingReservations
                ->where('seat_number', $reservation->seat_number)
                ->where('is_sub', false)
                ->exists();
                
            return !$overlapping;
        }
        
        // 席番号が未指定の場合は空いている席があるかチェック
        $store = Store::find($reservation->store_id);
        $maxSeats = $store->main_seat_count ?? 3;
        
        for ($seatNum = 1; $seatNum <= $maxSeats; $seatNum++) {
            $seatTaken = $overlappingReservations
                ->where('seat_number', $seatNum)
                ->where('is_sub', false)
                ->exists();
                
            if (!$seatTaken) {
                // 空いている席を自動割り当て
                $reservation->seat_number = $seatNum;
                return true;
            }
        }
        
        return false;
    }
}