<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Store;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationTimelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.reservation-timeline';
    
    protected int|string|array $columnSpan = 'full';
    
    protected static ?int $sort = 1;
    
    public $selectedStore = null;
    public $selectedDate = null;
    public $stores = [];
    public $timelineData = [];
    public $categories = [];
    public $selectedReservation = null;
    
    public function mount(): void
    {
        $this->stores = Store::where('is_active', true)->get();
        $this->selectedStore = $this->stores->first()?->id;
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->loadTimelineData();
    }
    
    public function updatedSelectedStore(): void
    {
        $this->loadTimelineData();
        $this->dispatch('store-changed', storeId: $this->selectedStore, date: $this->selectedDate);
    }
    
    public function updatedSelectedDate(): void
    {
        $this->loadTimelineData();
        $this->dispatch('store-changed', storeId: $this->selectedStore, date: $this->selectedDate);
    }
    
    public function changeDate($direction): void
    {
        $date = Carbon::parse($this->selectedDate);
        if ($direction === 'prev') {
            $this->selectedDate = $date->subDay()->format('Y-m-d');
        } else {
            $this->selectedDate = $date->addDay()->format('Y-m-d');
        }
        $this->loadTimelineData();
        $this->dispatch('store-changed', storeId: $this->selectedStore, date: $this->selectedDate);
    }
    
    public function loadTimelineData(): void
    {
        if (!$this->selectedStore || !$this->selectedDate) {
            return;
        }
        
        // カテゴリー情報も読み込む
        $this->categories = $this->getCategories();
        
        $store = Store::find($this->selectedStore);
        if (!$store) {
            return;
        }
        
        $date = Carbon::parse($this->selectedDate);
        
        // 店舗のライン設定を取得
        $mainSeats = $store->main_lines_count ?? 3;
        $subSeats = $store->sub_lines_count ?? 1;
        
        // 店舗の営業時間を取得（選択された日付の曜日に基づく）
        $dayOfWeek = $date->format('l'); // Monday, Tuesday, etc.
        $dayMapping = [
            'Monday' => 'monday',
            'Tuesday' => 'tuesday',
            'Wednesday' => 'wednesday',
            'Thursday' => 'thursday',
            'Friday' => 'friday',
            'Saturday' => 'saturday',
            'Sunday' => 'sunday',
        ];
        $dayKey = $dayMapping[$dayOfWeek] ?? 'monday';
        
        $businessHours = $store->business_hours ?? [];
        $todayHours = null;
        
        // 該当曜日の営業時間を探す
        foreach ($businessHours as $hours) {
            if (isset($hours['day']) && $hours['day'] === $dayKey) {
                $todayHours = $hours;
                break;
            }
        }
        
        // 営業時間を設定（見つからない場合はデフォルト10:00-20:00）
        $startHour = 10;
        $endHour = 20;
        
        if ($todayHours && !empty($todayHours['open_time']) && !empty($todayHours['close_time'])) {
            $startHour = (int)substr($todayHours['open_time'], 0, 2);
            $endHour = (int)substr($todayHours['close_time'], 0, 2);
        }
        
        // タイムラインデータを構築
        $timeline = [];
        
        // 予約データを取得（スタッフ情報も含む）
        $reservations = Reservation::with(['customer', 'menu', 'staff'])
            ->where('store_id', $this->selectedStore)
            ->whereDate('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('start_time')
            ->get();
        
        // ブロック時間帯を取得
        $blockedPeriods = \App\Models\BlockedTimePeriod::where('store_id', $this->selectedStore)
            ->whereDate('blocked_date', $date)
            ->orderBy('start_time')
            ->get();
        
        $slots = [];
        
        for ($hour = $startHour; $hour <= $endHour; $hour++) {
            $slots[] = sprintf('%02d:00', $hour);
        }
        
        // 座席データを初期化
        for ($seat = 1; $seat <= $mainSeats; $seat++) {
            $timeline['seat_' . $seat] = [
                'label' => '席' . $seat,
                'type' => 'main',
                'reservations' => []
            ];
        }
        
        // サブ枠（複数対応）
        for ($subSeat = 1; $subSeat <= $subSeats; $subSeat++) {
            $timeline['sub_' . $subSeat] = [
                'label' => 'サブ' . $subSeat,
                'type' => 'sub',
                'reservations' => []
            ];
        }
        
        // ブロック時間帯をタイムラインに配置
        $blockedSlots = [];
        foreach ($blockedPeriods as $blocked) {
            // 終日休みの場合は全スロットをブロック
            if ($blocked->is_all_day) {
                for ($i = 0; $i < count($slots); $i++) {
                    $blockedSlots[] = $i;
                }
            } else {
                $blockStart = Carbon::parse($blocked->start_time);
                $blockEnd = Carbon::parse($blocked->end_time);
                
                // 時間スロットのインデックスを計算
                $startSlot = max(0, ($blockStart->hour - $startHour) + ($blockStart->minute / 60));
                $endSlot = min(count($slots), ($blockEnd->hour - $startHour) + ($blockEnd->minute / 60));
                
                // ブロックされているスロットを記録
                for ($i = floor($startSlot); $i < ceil($endSlot); $i++) {
                    $blockedSlots[] = $i;
                }
            }
        }
        
        // ブロック時間帯と重複する予約をチェック
        $conflictingReservations = [];
        
        // 予約をタイムラインに配置
        foreach ($reservations as $reservation) {
            // start_timeフィールドを使用（時刻部分のみ取得）
            $startTime = Carbon::parse($reservation->start_time);
            // 日付がおかしい場合は時刻のみ再パース
            if ($startTime->format('Y-m-d') !== $date->format('Y-m-d')) {
                $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime->format('H:i:s'));
            }
            
            $duration = $reservation->menu->duration_minutes ?? 60;
            $endTime = $startTime->copy()->addMinutes($duration);
            
            // 顧客の初回訪問かチェック（この予約より前の予約があるか）
            $isNewCustomer = false;
            if ($reservation->customer_id) {
                $previousReservationCount = Reservation::where('customer_id', $reservation->customer_id)
                    ->where('id', '<', $reservation->id)
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->count();
                $isNewCustomer = ($previousReservationCount === 0);
            }
            
            // 時間スロットのインデックスを計算
            $startSlot = ($startTime->hour - $startHour) + ($startTime->minute / 60);
            $span = $duration / 60; // 1時間を1単位とする
            
            // ブロック時間帯との競合をチェック
            $isConflicting = false;
            for ($i = floor($startSlot); $i < ceil($startSlot + $span); $i++) {
                if (in_array($i, $blockedSlots)) {
                    $isConflicting = true;
                    $conflictingReservations[] = [
                        'reservation' => $reservation,
                        'customer_name' => $reservation->customer ? 
                            ($reservation->customer->last_name . ' ' . $reservation->customer->first_name) : '名前なし',
                        'time' => $startTime->format('H:i') . '-' . $endTime->format('H:i')
                    ];
                    break;
                }
            }
            
            $reservationData = [
                'id' => $reservation->id,
                'customer_name' => $reservation->customer ? 
                    ($reservation->customer->last_name . ' ' . $reservation->customer->first_name) : '名前なし',
                'menu_name' => $reservation->menu->name ?? 'メニューなし',
                'staff_name' => $reservation->staff ? $reservation->staff->name : null,
                'start_slot' => $startSlot,
                'span' => $span,
                'course_type' => $this->getCourseType($reservation->menu->category_id ?? null),
                'status' => $reservation->status,
                'is_conflicting' => $isConflicting,
                'is_new_customer' => $isNewCustomer
            ];
            
            if ($reservation->is_sub) {
                // サブ枠の予約を適切なサブラインに配置
                $subSeatNumber = $reservation->sub_seat_number ?? 1; // デフォルトはサブ1
                $subKey = 'sub_' . $subSeatNumber;
                if (isset($timeline[$subKey])) {
                    $timeline[$subKey]['reservations'][] = $reservationData;
                } else {
                    // サブ番号が存在しない場合は最初のサブ枠に配置
                    $firstSubKey = 'sub_1';
                    if (isset($timeline[$firstSubKey])) {
                        $timeline[$firstSubKey]['reservations'][] = $reservationData;
                    }
                }
            } elseif ($reservation->seat_number) {
                $seatKey = 'seat_' . $reservation->seat_number;
                if (isset($timeline[$seatKey])) {
                    $timeline[$seatKey]['reservations'][] = $reservationData;
                }
            } else {
                // seat_numberがnullで is_sub = 0 の場合、空いている席に自動配置
                for ($seat = 1; $seat <= $mainSeats; $seat++) {
                    $seatKey = 'seat_' . $seat;
                    if (isset($timeline[$seatKey])) {
                        $timeline[$seatKey]['reservations'][] = $reservationData;
                        break; // 最初の席に配置して終了
                    }
                }
            }
        }
        
        $this->timelineData = [
            'slots' => $slots,
            'timeline' => $timeline,
            'blockedSlots' => $blockedSlots,
            'conflictingReservations' => $conflictingReservations,
            'blockedPeriods' => $blockedPeriods->toArray() // デバッグ用
        ];
    }
    
    private function getCourseType($categoryId): string
    {
        // カテゴリーIDがnullの場合はデフォルトを返す
        if (!$categoryId) {
            return 'care';
        }
        
        // カテゴリーIDに基づいて色を動的に割り当て
        $colors = ['care', 'hydrogen', 'training', 'special', 'premium', 'vip'];
        $index = ($categoryId - 1) % count($colors);
        return $colors[$index];
    }
    
    public function getCategories()
    {
        return \App\Models\MenuCategory::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function ($category, $index) {
                $colors = ['care', 'hydrogen', 'training', 'special', 'premium', 'vip'];
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color_class' => $colors[$index % count($colors)]
                ];
            });
    }
    
    public function openReservationDetail($reservationId): void
    {
        $this->selectedReservation = Reservation::with(['customer', 'menu', 'staff'])->find($reservationId);
        
        if ($this->selectedReservation && $this->selectedReservation->customer_id) {
            // 顧客の総訪問回数を取得
            $this->selectedReservation->customer_visit_count = Reservation::where('customer_id', $this->selectedReservation->customer_id)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->where('id', '<=', $this->selectedReservation->id)
                ->count();
            
            // 初回訪問かどうか
            $this->selectedReservation->is_new_customer = ($this->selectedReservation->customer_visit_count === 1);
        }
    }
    
    public function closeModal(): void
    {
        $this->selectedReservation = null;
    }
    
    public function moveToSub($reservationId): void
    {
        $reservation = Reservation::find($reservationId);
        if ($reservation) {
            // 過去の予約は移動不可
            if ($reservation->reservation_date->isPast()) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '過去の予約の席移動はできません'
                ]);
                return;
            }
            // サブ枠に既に予約があるかチェック
            $temp = clone $reservation;
            $temp->is_sub = true;
            $temp->seat_number = null;
            
            if (!Reservation::checkAvailability($temp)) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'サブ枠は既に予約が入っています'
                ]);
                return;
            }
            
            // 重複チェックを一時的に無効化して保存
            $reservation->timestamps = false;
            $reservation->is_sub = true;
            $reservation->seat_number = null;
            $reservation->saveQuietly();
            
            $this->loadTimelineData();
            $this->selectedReservation = null;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'サブ枠に移動しました'
            ]);
        }
    }
    
    public function moveToMain($reservationId, $seatNumber): void
    {
        $reservation = Reservation::find($reservationId);
        if ($reservation) {
            // 過去の予約は移動不可
            if ($reservation->reservation_date->isPast()) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '過去の予約の席移動はできません'
                ]);
                return;
            }
            // 指定席に既に予約があるかチェック
            $temp = clone $reservation;
            $temp->is_sub = false;
            $temp->seat_number = $seatNumber;
            
            if (!Reservation::checkAvailability($temp)) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '席' . $seatNumber . 'は既に予約が入っています'
                ]);
                return;
            }
            
            // 重複チェックを一時的に無効化して保存
            $reservation->timestamps = false;
            $reservation->is_sub = false;
            $reservation->seat_number = $seatNumber;
            $reservation->saveQuietly();
            
            $this->loadTimelineData();
            $this->selectedReservation = null;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '席' . $seatNumber . 'に移動しました'
            ]);
        }
    }
    
    public function canMoveToSub($reservationId): bool
    {
        $reservation = Reservation::find($reservationId);
        if (!$reservation || $reservation->is_sub) {
            return false;
        }
        
        $temp = clone $reservation;
        $temp->is_sub = true;
        $temp->seat_number = null;
        
        return Reservation::checkAvailability($temp);
    }
    
    public function canMoveToMain($reservationId, $seatNumber): bool
    {
        $reservation = Reservation::find($reservationId);
        if (!$reservation || !$reservation->is_sub) {
            return false;
        }
        
        $temp = clone $reservation;
        $temp->is_sub = false;
        $temp->seat_number = $seatNumber;
        
        return Reservation::checkAvailability($temp);
    }
    
}