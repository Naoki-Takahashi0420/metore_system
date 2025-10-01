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
        'confirmation_sent_at',
        'confirmation_method',
        'line_confirmation_sent_at',
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
        'confirmation_sent_at' => 'datetime',
        'line_confirmation_sent_at' => 'datetime',
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

            \Log::info('Creating reservation:', [
                'store_id' => $reservation->store_id,
                'date' => $reservation->reservation_date,
                'time' => $reservation->start_time . '-' . $reservation->end_time,
                'staff_id' => $reservation->staff_id ?? null
            ]);

            // 重複チェック
            try {
                $isAvailable = self::checkAvailability($reservation);
                \Log::info('Creating reservation check result:', [
                    'available' => $isAvailable,
                    'data' => [
                        'store_id' => $reservation->store_id,
                        'date' => $reservation->reservation_date,
                        'time' => $reservation->start_time . '-' . $reservation->end_time,
                        'staff_id' => $reservation->staff_id
                    ]
                ]);

                if (!$isAvailable) {
                    throw new \Exception('この時間帯は既に予約が入っています。');
                }
            } catch (\Exception $e) {
                // checkAvailability内でthrowされた具体的なエラーメッセージを再throw
                throw $e;
            }
        });
        
        static::updating(function ($reservation) {
            // 更新時も重複チェック（キャンセル済み以外）
            if (!in_array($reservation->status, ['cancelled', 'canceled']) &&
                $reservation->isDirty(['start_time', 'end_time', 'seat_number', 'is_sub', 'reservation_date', 'staff_id'])) {
                try {
                    self::checkAvailability($reservation);
                } catch (\Exception $e) {
                    throw $e;
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
     * optionMenusを安全に取得するヘルパーメソッド
     */
    public function getOptionMenusSafely()
    {
        try {
            // テーブルの存在確認
            if (!\Schema::hasTable('reservation_menu_options')) {
                \Log::warning('reservation_menu_options table does not exist');
                return collect([]);
            }

            // リレーションが読み込まれていない場合は読み込む
            if (!$this->relationLoaded('optionMenus')) {
                $this->load('optionMenus');
            }

            return $this->optionMenus ?? collect([]);
        } catch (\Exception $e) {
            \Log::error('Error loading optionMenus safely', [
                'reservation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return collect([]);
        }
    }

    /**
     * optionMenusの合計価格を安全に取得
     */
    public function getOptionsTotalPrice()
    {
        try {
            $options = $this->getOptionMenusSafely();
            return $options->sum(function ($option) {
                return $option->pivot->price ?? 0;
            });
        } catch (\Exception $e) {
            \Log::error('Error calculating options total price', [
                'reservation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
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
        // 開始時間と終了時間を文字列として取得（HH:MM形式）
        $startTime = is_string($reservation->start_time)
            ? $reservation->start_time
            : Carbon::parse($reservation->start_time)->format('H:i:s');
        $endTime = is_string($reservation->end_time)
            ? $reservation->end_time
            : Carbon::parse($reservation->end_time)->format('H:i:s');

        \Log::info('checkAvailability input:', [
            'store_id' => $reservation->store_id,
            'reservation_date' => $reservation->reservation_date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'staff_id' => $reservation->staff_id ?? null,
            'reservation_id' => $reservation->id ?? 'new',
            'line_type' => $reservation->line_type ?? null,
            'seat_number' => $reservation->seat_number ?? null,
            'is_sub' => $reservation->is_sub ?? false
        ]);

        // 同じ店舗、同じ日付の予約を取得
        $query = self::where('store_id', $reservation->store_id)
            ->whereDate('reservation_date', $reservation->reservation_date)
            ->whereNotIn('status', ['cancelled', 'canceled']);

        // 更新の場合は自分自身を除外
        if ($reservation->id) {
            $query->where('id', '!=', $reservation->id);
        }

        // 時間の重複チェック（クエリをコピーして独立して使用）
        $overlappingReservations = clone $query;
        $overlappingReservations = $overlappingReservations->where(function ($q) use ($startTime, $endTime) {
            // 既存の予約と時間が重なっているかチェック
            // start_time < endTime AND end_time > startTime
            $q->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime);
        });
        
        // スタッフシフトモードと通常モードの判定
        $store = Store::find($reservation->store_id);

        // サブラインの場合（スタッフシフトモードでも独立して処理）
        if ($reservation->is_sub) {
            // サブ枠は営業時間内であれば利用可能（スタッフ不要）
            // ただし、スタッフシフトモードの場合はシフト時間も考慮
            if (!$store->use_staff_assignment) {
                // 営業時間ベースモードのみ営業時間をチェック
                $isWithinBusinessHours = self::isWithinBusinessHours($store, $reservation);
                if (!$isWithinBusinessHours) {
                    throw new \Exception('営業時間外のため予約できません。');
                }
            }

            // 同じ時間帯でサブ枠の重複をチェック（時間の重複は既にチェック済み）
            $overlappingQuery = clone $overlappingReservations;
            $overlappingQuery = $overlappingQuery->where(function($q) use ($reservation) {
                    // サブ枠の予約のみをチェック
                    $q->where('is_sub', true);
                    $q->orWhere(function($sub) use ($reservation) {
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

        // スタッフシフトモードの場合
        if ($store->use_staff_assignment) {
            // 席番号が指定されている場合は、その席の重複チェックのみ
            if ($reservation->seat_number && !$reservation->is_sub) {
                \Log::info('checkAvailability: 席番号指定あり、席の重複チェックのみ', [
                    'seat_number' => $reservation->seat_number
                ]);
                $seatQuery = clone $overlappingReservations;
                $overlapping = $seatQuery->where('seat_number', $reservation->seat_number)
                    ->where(function($q) {
                        $q->where('is_sub', false);
                        $q->orWhere('line_type', 'main');
                        $q->orWhereNull('line_type');
                    })
                    ->exists();

                return !$overlapping;
            }

            // 席番号未指定の場合は容量チェック
            // スタッフ勤務時間をチェック
            $hasAvailableStaff = self::checkStaffAvailability($store, $reservation);
            if (!$hasAvailableStaff) {
                // スタッフ不在の詳細なメッセージ
                if (!empty($reservation->staff_id)) {
                    throw new \Exception('指名されたスタッフはこの時間帯に勤務していません。');
                } else {
                    throw new \Exception('この時間帯には勤務可能なスタッフがいません。');
                }
            }

            // その時間帯の勤務スタッフ数を取得
            $availableStaffCount = self::getAvailableStaffCount($store, $reservation);

            // 容量チェック（席数とスタッフ数の最小値）
            $equipmentCapacity = $store->shift_based_capacity ?? 1;
            $capacity = min($equipmentCapacity, $availableStaffCount);

            // 同じ時間帯の予約数をカウント
            $existingReservations = $overlappingReservations->get();
            $existingCount = $existingReservations->count();

            \Log::info('checkAvailability for staff mode:', [
                'equipment_capacity' => $equipmentCapacity,
                'available_staff_count' => $availableStaffCount,
                'final_capacity' => $capacity,
                'existing_count' => $existingCount,
                'existing_reservations' => $existingReservations->map(function($r) {
                    return [
                        'id' => $r->id,
                        'time' => $r->start_time . '-' . $r->end_time,
                        'staff_id' => $r->staff_id,
                        'status' => $r->status
                    ];
                })->toArray(),
                'available' => $existingCount < $capacity
            ]);

            if ($existingCount >= $capacity) {
                throw new \Exception("この時間帯の予約枠は満席です。（予約可能数: {$capacity}）");
            }

            return true;
        }

        // 通常席（メインライン）の場合
        $maxSeats = $store->main_lines_count ?? 1;

        // 既に席番号が指定されている場合
        if ($reservation->seat_number) {
            // 同じ席番号での重複をチェック
            $seatQuery = clone $overlappingReservations;
            $overlapping = $seatQuery->where('seat_number', $reservation->seat_number)
                ->where(function($q) {
                    $q->where('is_sub', false);
                    $q->orWhere('line_type', 'main');
                    $q->orWhereNull('line_type');                })
                ->exists();

            return !$overlapping;
        }

        // 席番号が未指定の場合は空いている席を探す
        for ($seatNum = 1; $seatNum <= $maxSeats; $seatNum++) {
            $seatCheckQuery = clone $overlappingReservations;
            $seatTaken = $seatCheckQuery->where('seat_number', $seatNum)
                ->where(function($q) {
                    $q->where('is_sub', false);
                    $q->orWhere('line_type', 'main');
                    $q->orWhereNull('line_type');
                })
                ->exists();

            if (!$seatTaken) {
                // 空いている席を自動割り当て
                $reservation->seat_number = $seatNum;
                $reservation->line_number = $seatNum;
                return true;
            }
        }

        return false;
    }
    
    /**
     * その時間帯に勤務可能なスタッフ数を取得
     */
    private static function getAvailableStaffCount($store, $reservation): int
    {
        $startTime = Carbon::parse($reservation->start_time);
        $endTime = Carbon::parse($reservation->end_time);

        // その日のシフトを取得
        $shifts = \App\Models\Shift::where('store_id', $store->id)
            ->whereDate('shift_date', $reservation->reservation_date)
            ->where('status', 'scheduled')
            ->where('is_available_for_reservation', true)
            ->get();

        $availableStaffIds = [];

        foreach ($shifts as $shift) {
            $shiftStart = Carbon::parse($shift->start_time);
            $shiftEnd = Carbon::parse($shift->end_time);

            // 予約時間とシフト時間が重なっているかチェック
            $overlaps = $startTime->lt($shiftEnd) && $endTime->gt($shiftStart);

            if ($overlaps) {
                $availableStaffIds[$shift->user_id] = true;
            }
        }

        $count = count($availableStaffIds);

        \Log::info('getAvailableStaffCount:', [
            'time' => $startTime->format('H:i') . '-' . $endTime->format('H:i'),
            'available_staff_count' => $count,
            'staff_ids' => array_keys($availableStaffIds)
        ]);

        return $count;
    }

    /**
     * シフトベースモードでスタッフが予約時間に勤務しているかチェック
     */
    private static function checkStaffAvailability($store, $reservation): bool
    {
        $startTime = Carbon::parse($reservation->start_time);
        $endTime = Carbon::parse($reservation->end_time);
        $hasStaffDesignation = !empty($reservation->staff_id);

        \Log::info('checkStaffAvailability start:', [
            'store_id' => $store->id,
            'date' => $reservation->reservation_date,
            'time' => $startTime->format('H:i') . '-' . $endTime->format('H:i'),
            'has_staff_designation' => $hasStaffDesignation,
            'staff_id' => $reservation->staff_id ?? null
        ]);

        // その日のシフトを取得
        $shiftsQuery = \App\Models\Shift::where('store_id', $store->id)
            ->whereDate('shift_date', $reservation->reservation_date)
            ->where('status', 'scheduled')
            ->where('is_available_for_reservation', true);

        // スタッフ指名がある場合は、指名スタッフのシフトのみ取得
        if ($hasStaffDesignation) {
            $shiftsQuery->where('user_id', $reservation->staff_id);
        }

        $shifts = $shiftsQuery->get();

        \Log::info('Shifts found:', [
            'count' => $shifts->count(),
            'shifts' => $shifts->map(function($s) {
                return [
                    'user_id' => $s->user_id,
                    'time' => $s->start_time . '-' . $s->end_time
                ];
            })->toArray()
        ]);

        // シフトが登録されていない場合
        if ($shifts->isEmpty()) {
            // スタッフ指名がある場合は、指名スタッフのシフトがないので予約不可
            if ($hasStaffDesignation) {
                \Log::info('Designated staff has no shift - denying reservation');
                return false;
            }

            // 指名なしの場合、シフトが全くないので営業時間内かどうかで判定
            $dayOfWeek = strtolower(Carbon::parse($reservation->reservation_date)->format('l'));
            $businessHoursArray = is_array($store->business_hours) ? $store->business_hours : json_decode($store->business_hours, true) ?? [];
            $todayHours = null;

            foreach ($businessHoursArray as $hours) {
                if (isset($hours['day']) && $hours['day'] === $dayOfWeek) {
                    $todayHours = $hours;
                    break;
                }
            }

            if ($todayHours && (!isset($todayHours['is_closed']) || !$todayHours['is_closed'])) {
                $openTime = Carbon::parse($todayHours['open_time'] ?? '09:00');
                $closeTime = Carbon::parse($todayHours['close_time'] ?? '20:00');

                // 営業時間内なら予約可能とする
                if ($startTime->gte($openTime) && $endTime->lte($closeTime)) {
                    \Log::info('No shifts but within business hours - allowing reservation');
                    return true;
                }
            }

            \Log::info('No shifts and outside business hours - denying reservation');
            return false;
        }

        // スタッフ指名がある場合
        if ($hasStaffDesignation) {
            // 指名スタッフのシフト時間内に予約時間が収まるかチェック
            foreach ($shifts as $shift) {
                $shiftStart = Carbon::parse($shift->start_time);
                $shiftEnd = Carbon::parse($shift->end_time);

                \Log::info('Checking designated staff shift:', [
                    'shift_time' => $shiftStart->format('H:i') . '-' . $shiftEnd->format('H:i'),
                    'reservation_time' => $startTime->format('H:i') . '-' . $endTime->format('H:i'),
                    'fits' => $startTime->gte($shiftStart) && $endTime->lte($shiftEnd)
                ]);

                if ($startTime->gte($shiftStart) && $endTime->lte($shiftEnd)) {
                    \Log::info('Designated staff is available');
                    return true;
                }
            }

            \Log::info('Designated staff is not available during requested time');
            return false;
        }

        // 指名なしの場合：その時間帯に誰か1人でもシフトがあるかチェック
        foreach ($shifts as $shift) {
            $shiftStart = Carbon::parse($shift->start_time);
            $shiftEnd = Carbon::parse($shift->end_time);

            // 予約時間とシフト時間が重なっているかチェック
            // (予約開始 < シフト終了) AND (予約終了 > シフト開始)
            $overlaps = $startTime->lt($shiftEnd) && $endTime->gt($shiftStart);

            \Log::info('Checking shift overlap:', [
                'shift_user_id' => $shift->user_id,
                'shift_time' => $shiftStart->format('H:i') . '-' . $shiftEnd->format('H:i'),
                'reservation_time' => $startTime->format('H:i') . '-' . $endTime->format('H:i'),
                'overlaps' => $overlaps
            ]);

            if ($overlaps) {
                // この時間帯に勤務しているスタッフがいる
                \Log::info('Found available staff for non-designated reservation');
                return true;
            }
        }

        \Log::info('No available staff during requested time');
        return false;
    }

    /**
     * 営業時間内かどうかをチェック（静的メソッド）
     */
    private static function isWithinBusinessHours($store, $reservation): bool
    {
        // 予約日の曜日を取得
        $reservationDate = is_string($reservation->reservation_date)
            ? Carbon::parse($reservation->reservation_date)
            : $reservation->reservation_date;

        $dayOfWeek = strtolower($reservationDate->format('l'));
        $businessHours = $store->business_hours ?? [];

        if (!is_array($businessHours)) {
            return true; // デフォルトで営業時間制限なし
        }

        foreach ($businessHours as $hours) {
            if (isset($hours['day']) && $hours['day'] === $dayOfWeek) {
                if (isset($hours['is_closed']) && $hours['is_closed']) {
                    return false; // 定休日
                }

                $openTime = Carbon::parse($hours['open_time'] ?? '00:00');
                $closeTime = Carbon::parse($hours['close_time'] ?? '23:59');

                $startTime = Carbon::parse($reservation->start_time);
                $endTime = Carbon::parse($reservation->end_time);

                return $startTime->gte($openTime) && $endTime->lte($closeTime);
            }
        }

        return true; // 営業時間設定がない場合はOK
    }
}