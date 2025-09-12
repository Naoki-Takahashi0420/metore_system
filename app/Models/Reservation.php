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
        'line_type',  // 追加: main/sub
        'line_number',  // 追加: ライン番号
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
        'is_sub',  // 互換性のため保持
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
        'line_number' => 'integer',
    ];
    
    /**
     * サブラインに移動
     */
    public function moveToSubLine($subLineNumber = 1)
    {
        $this->update([
            'line_type' => 'sub',
            'line_number' => $subLineNumber
        ]);
    }
    
    /**
     * メインラインに戻す
     */
    public function moveToMainLine($mainLineNumber = 1)
    {
        $this->update([
            'line_type' => 'main',
            'line_number' => $mainLineNumber
        ]);
    }

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
        
        // サブラインの場合
        if ($reservation->line_type === 'sub' || $reservation->is_sub) {
            // 同じ時間帯でサブ枠の重複をチェック（時間の重複は既にチェック済み）
            $overlappingQuery = $overlappingReservations
                ->where(function($q) use ($reservation) {
                    // サブ枠の予約のみをチェック
                    $q->where('is_sub', true)
                      ->orWhere(function($sub) use ($reservation) {
                          $sub->where('line_type', 'sub')
                              ->where('line_number', $reservation->line_number ?? 1);
                      });
                });
            
            \Log::info('checkAvailability for sub:', [
                'reservation_id' => $reservation->id,
                'query' => $overlappingQuery->toSql(),
                'bindings' => $overlappingQuery->getBindings(),
                'count' => $overlappingQuery->count()
            ]);
            
            $overlapping = $overlappingQuery->exists();
                
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
        
        // シフトベースモードの場合、スタッフ勤務時間をチェック
        if ($store->use_staff_assignment && ($reservation->line_type === 'main' || !$reservation->is_sub)) {
            $hasAvailableStaff = self::checkStaffAvailability($store, $reservation);
            if (!$hasAvailableStaff) {
                return false; // スタッフ不在のため予約不可
            }
        }
        
        $maxSeats = $store->main_lines_count ?? 3;
        
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
    
    /**
     * シフトベースモードでスタッフが予約時間に勤務しているかチェック
     */
    private static function checkStaffAvailability($store, $reservation): bool
    {
        $startTime = Carbon::parse($reservation->start_time);
        $endTime = Carbon::parse($reservation->end_time);
        
        // その日のシフトを取得
        $shifts = \App\Models\Shift::where('store_id', $store->id)
            ->whereDate('shift_date', $reservation->reservation_date)
            ->where('status', 'scheduled')
            ->where('is_available_for_reservation', true)
            ->get();
        
        $availableStaffCount = 0;
        
        foreach ($shifts as $shift) {
            $shiftStart = Carbon::parse($shift->start_time);
            $shiftEnd = Carbon::parse($shift->end_time);
            
            // 予約時間がシフト時間に収まるかチェック（休憩時間は考慮しない）
            if ($startTime->gte($shiftStart) && $endTime->lte($shiftEnd)) {
                $availableStaffCount++;
            }
        }
        
        // min(設備台数, スタッフ数) > 0 かチェック
        $equipmentCapacity = $store->shift_based_capacity ?? 1;
        return min($equipmentCapacity, $availableStaffCount) > 0;
    }
}