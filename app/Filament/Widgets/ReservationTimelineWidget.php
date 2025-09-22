<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Store;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ReservationTimelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.reservation-timeline';
    
    protected int|string|array $columnSpan = 'full';
    
    protected static ?int $sort = 10;
    
    public $selectedStore = null;
    public $selectedDate = null;
    public $stores = [];
    public $timelineData = [];
    public $categories = [];
    public $selectedReservation = null;
    
    // 新規予約作成用のプロパティ
    public $showNewReservationModal = false;
    public $reservationStep = 1; // 1: 顧客検索, 2: 新規顧客登録, 3: 予約詳細
    public $customerSelectionMode = 'existing'; // 'existing' or 'new'
    public $phoneSearch = '';
    public $searchResults = [];
    public $selectedCustomer = null;
    public $menuSearch = '';  // メニュー検索用
    public $showAllMenus = false;  // 全メニュー表示フラグ
    public $newCustomer = [
        'last_name' => '',
        'first_name' => '',
        'email' => '',
        'phone' => ''
    ];
    public $newReservation = [
        'date' => '',
        'start_time' => '',
        'duration' => 60,
        'menu_id' => '',
        'line_type' => 'main',
        'line_number' => 1,
        'notes' => '電話予約'
    ];
    
    public function mount(): void
    {
        $user = auth()->user();

        // ユーザーの権限に応じて店舗を取得
        if ($user->hasRole('super_admin')) {
            $this->stores = Store::where('is_active', true)->get();
        } elseif ($user->hasRole('owner')) {
            $this->stores = $user->manageableStores()->where('is_active', true)->get();
        } else {
            // 店長・スタッフは所属店舗のみ
            $this->stores = $user->store ? collect([$user->store]) : collect();
        }

        $this->selectedStore = $this->stores->first()?->id;
        $this->selectedDate = Carbon::today()->format('Y-m-d');

        // 明確にこのウィジェットが使用されていることを示す
        logger('🟢 ReservationTimelineWidget が使用されています - selectedStore: ' . $this->selectedStore);

        logger('🔧 mount() - selectedStore設定完了: ' . $this->selectedStore);
        logger('🔧 mount() - selectedDate設定完了: ' . $this->selectedDate);

        // マウント時のデバッグ情報
        $this->dispatch('debug-log', [
            'message' => 'Widget mounted',
            'userRole' => $user->getRoleNames()->first(),
            'selectedStore' => $this->selectedStore,
            'storeCount' => $this->stores->count(),
            'allStores' => $this->stores->pluck('name', 'id')->toArray()
        ]);

        logger('🔧 mount() - loadTimelineData()を呼び出します');
        $this->loadTimelineData();
        logger('🔧 mount() - loadTimelineData()完了');
    }
    
    public function updatedSelectedStore(): void
    {
        // 店舗選択変更時のデバッグ情報
        $this->dispatch('debug-log', [
            'message' => 'Store selection updated',
            'newSelectedStore' => $this->selectedStore
        ]);

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
        $this->dispatch('date-changed', date: $this->selectedDate);
    }
    
    #[On('calendar-date-clicked')]
    public function updateFromCalendar($date): void
    {
        \Log::info('Calendar date clicked received:', ['date' => $date]);

        $this->selectedDate = $date;
        $this->loadTimelineData();
        $this->dispatch('date-changed', date: $this->selectedDate);
    }
    
    protected function getBaseQuery()
    {
        $query = Reservation::query();
        $user = auth()->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        
        // スーパーアドミンは全予約を表示
        if ($user->hasRole('super_admin')) {
            return $query;
        }
        
        // オーナーは管理可能店舗の予約のみ表示
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $query->whereIn('store_id', $manageableStoreIds);
        }
        
        // 店長・スタッフは所属店舗の予約のみ表示
        if ($user->hasRole(['manager', 'staff'])) {
            if ($user->store_id) {
                return $query->where('store_id', $user->store_id);
            }
            return $query->whereRaw('1 = 0');
        }
        
        return $query->whereRaw('1 = 0');
    }
    
    public function loadTimelineData(): void
    {
        // 強制的にログに出力
        logger('🚀 loadTimelineData() が呼び出されました - selectedStore: ' . ($this->selectedStore ?? 'null') . ', selectedDate: ' . ($this->selectedDate ?? 'null'));

        if (!$this->selectedStore || !$this->selectedDate) {
            logger('❌ loadTimelineData() 早期リターン - 店舗または日付が未設定');
            return;
        }

        logger('✅ loadTimelineData() カテゴリー読み込み開始');

        // カテゴリー情報も読み込む
        logger('🔥 loadTimelineData() - getCategories()を呼び出します');
        $this->categories = $this->getCategories();
        logger('🔥 loadTimelineData() - getCategories()完了 - カテゴリー数: ' . count($this->categories));

        // 日付変更イベントを発火
        $this->dispatch('date-changed', date: $this->selectedDate);

        $store = Store::find($this->selectedStore);
        if (!$store) {
            return;
        }
        
        $date = Carbon::parse($this->selectedDate);
        
        // 店舗の予約管理モードを確認
        $useStaffAssignment = $store->use_staff_assignment ?? false;
        
        // シフトベースモードの場合、設備制約を考慮
        if ($useStaffAssignment) {
            // シフトベースモード: 設備制約（機械台数）
            $maxCapacity = $store->shift_based_capacity ?? 1;
            $subSeats = 1; // サブライン1で固定
            
            // その日のシフトデータを取得
            $shifts = \App\Models\Shift::where('store_id', $this->selectedStore)
                ->whereDate('shift_date', $date)
                ->where('status', 'scheduled')
                ->where('is_available_for_reservation', true)
                ->get();
        } else {
            // 営業時間ベースモード: 従来通りライン設定を使用
            $mainSeats = $store->main_lines_count ?? 3;
            $subSeats = 1; // サブライン1で固定
        }
        
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
        
        // 営業時間を設定（デフォルト10:00-21:00）
        $startHour = 10;
        $endHour = 21;
        
        // 新形式（曜日ごと）の営業時間チェック
        if (is_array($businessHours)) {
            foreach ($businessHours as $hours) {
                if (isset($hours['day']) && $hours['day'] === $dayKey) {
                    $todayHours = $hours;
                    break;
                }
            }
            
            if ($todayHours && !empty($todayHours['open_time']) && !empty($todayHours['close_time'])) {
                $startHour = (int)substr($todayHours['open_time'], 0, 2);
                $closeTime = $todayHours['close_time'];
                $endHour = (int)substr($closeTime, 0, 2);
            }
        } 
        // 旧形式（単純なopen/close）の営業時間チェック
        elseif (is_string($businessHours)) {
            $hours = json_decode($businessHours, true);
            if ($hours && isset($hours['open']) && isset($hours['close'])) {
                $startHour = (int)substr($hours['open'], 0, 2);
                $endHour = (int)substr($hours['close'], 0, 2);
            }
        }
        
        // タイムラインデータを構築
        $timeline = [];
        
        // 予約データを取得（スタッフ情報も含む）
        $reservations = $this->getBaseQuery()
            ->with(['customer', 'menu', 'staff'])
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
            for ($minute = 0; $minute < 60; $minute += 15) {
                // 21時までのすべてのスロットを表示
                $slots[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }
        
        // 座席データを初期化
        if ($useStaffAssignment) {
            // シフトベースモードの場合は動的に席数を決定
            // 初期化時は最大席数で作成し、後でスタッフ数に応じて調整
            for ($seat = 1; $seat <= $maxCapacity; $seat++) {
                $timeline['seat_' . $seat] = [
                    'label' => '席' . $seat,
                    'type' => 'main',
                    'reservations' => []
                ];
            }
        } else {
            // 営業時間ベースモードの場合は固定席数
            for ($seat = 1; $seat <= $mainSeats; $seat++) {
                $timeline['seat_' . $seat] = [
                    'label' => '席' . $seat,
                    'type' => 'main',
                    'reservations' => []
                ];
            }
        }
        
        // サブ枠（固定1席）
        $timeline['sub_1'] = [
            'label' => 'サブ',
            'type' => 'sub',
            'reservations' => []
        ];
        
        // シフトベースモードの場合、時間帯ごとの利用可能席数を計算
        $shiftBasedAvailability = [];
        if ($useStaffAssignment) {
            foreach ($slots as $index => $timeSlot) {
                $staffCount = $this->getAvailableStaffCount($shifts, $timeSlot);
                $availableSeats = min($maxCapacity, $staffCount);
                $shiftBasedAvailability[$index] = $availableSeats;
            }
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
                
                // 時間スロットのインデックスを計算（15分刻み）
                $startSlot = max(0, ($blockStart->hour - $startHour) * 4 + ($blockStart->minute / 15));
                $endSlot = min(count($slots), ($blockEnd->hour - $startHour) * 4 + ($blockEnd->minute / 15));
                
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
            
            // 時間スロットのインデックスを計算（15分刻み）
            $startSlot = ($startTime->hour - $startHour) * 4 + ($startTime->minute / 15);
            $span = $duration / 15; // 15分を1単位とする
            
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
            
            if ($reservation->line_type === 'sub' || $reservation->is_sub) {
                // サブ枠の予約を適切なサブラインに配置
                $subSeatNumber = $reservation->line_number ?? 1; // デフォルトはサブ1
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
            } elseif (($reservation->line_type === 'main' && $reservation->line_number) || ($reservation->seat_number && !$reservation->is_sub)) {
                // メインラインの予約
                $seatNumber = $reservation->seat_number ?: ($reservation->line_number ?: 1);
                $seatKey = 'seat_' . $seatNumber;
                if (isset($timeline[$seatKey])) {
                    $timeline[$seatKey]['reservations'][] = $reservationData;
                }
            } else {
                // line_numberがない場合、空いている席に自動配置
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
            'blockedPeriods' => $blockedPeriods->toArray(),
            'useStaffAssignment' => $useStaffAssignment,
            'shiftBasedAvailability' => $shiftBasedAvailability ?? [],
            'maxCapacity' => $useStaffAssignment ? $maxCapacity : ($mainSeats ?? 3)
        ];
    }
    
    private function getCourseType($categoryId): string
    {
        // カテゴリーIDがnullの場合はデフォルトを返す
        if (!$categoryId) {
            return 'default';
        }
        
        // 見やすく区別しやすい配色パターンを使用
        // 同じ系統の色が連続しないように配置
        $colorPatterns = [
            'care',      // 青系
            'hydrogen',  // 紫系
            'training',  // オレンジ系
            'special',   // 緑系
            'premium',   // 赤系
            'vip',       // 黄系
        ];
        
        // カテゴリーIDを元に色を決定（循環使用）
        $index = ($categoryId - 1) % count($colorPatterns);
        return $colorPatterns[$index];
    }
    
    public function getCategories()
    {
        // 強制的にログに出力
        logger('🔥 getCategories() が呼び出されました - selectedStore: ' . ($this->selectedStore ?? 'null'));

        $query = \App\Models\MenuCategory::where('is_active', true);

        // デバッグ情報をJavaScriptコンソールに出力
        $this->dispatch('debug-log', [
            'message' => 'getCategories called',
            'selectedStore' => $this->selectedStore,
            'hasSelectedStore' => !empty($this->selectedStore)
        ]);

        // 選択された店舗がある場合、その店舗のカテゴリーのみ取得
        if ($this->selectedStore) {
            $query->where('store_id', $this->selectedStore);
            $this->dispatch('debug-log', [
                'message' => 'Store filter applied',
                'storeId' => $this->selectedStore
            ]);
        } else {
            $this->dispatch('debug-log', [
                'message' => 'No store filter - showing all stores',
                'selectedStore' => $this->selectedStore
            ]);
        }

        $categories = $query->orderBy('id')->get();

        // 取得されたカテゴリーの詳細をログ出力
        $categoryDetails = $categories->map(function($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'store_id' => $cat->store_id
            ];
        })->toArray();

        $this->dispatch('debug-log', [
            'message' => 'Categories retrieved',
            'count' => $categories->count(),
            'categories' => $categoryDetails
        ]);

        return $categories->map(function ($category, $index) {
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
            // 過去の予約は移動不可（日付と時刻を合わせて判定）
            $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->start_time);
            if ($reservationDateTime->isPast()) {
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
            
            \Log::info('moveToSub: Checking availability', [
                'reservation_id' => $reservation->id,
                'date' => $reservation->reservation_date,
                'start_time' => $reservation->start_time,
                'end_time' => $reservation->end_time,
                'is_sub' => $temp->is_sub,
                'seat_number' => $temp->seat_number,
            ]);
            
            $isAvailable = Reservation::checkAvailability($temp);
            \Log::info('moveToSub: Availability result', ['available' => $isAvailable]);
            
            if (!$isAvailable) {
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
            $reservation->line_type = 'sub';
            $reservation->line_number = 1; // デフォルトサブライン1
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
            // 過去の予約は移動不可（日付と時刻を合わせて判定）
            $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->start_time);
            if ($reservationDateTime->isPast()) {
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
            $reservation->line_type = 'main';
            $reservation->line_number = $seatNumber; // メインラインの番号は席番号と同じ
            $reservation->saveQuietly();
            
            $this->loadTimelineData();
            $this->selectedReservation = null;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '席' . $seatNumber . 'に移動しました'
            ]);
        }
    }
    
    /**
     * 特定の時間帯にスタッフが勤務しているかチェック
     */
    private function getAvailableStaffCount($shifts, $targetTime): int
    {
        $staffCount = 0;
        $targetTimeCarbon = \Carbon\Carbon::parse($targetTime);
        
        foreach ($shifts as $shift) {
            $shiftStart = \Carbon\Carbon::parse($shift->start_time);
            $shiftEnd = \Carbon\Carbon::parse($shift->end_time);
            
            // 勤務時間内かチェック（休憩時間は考慮しない）
            if ($targetTimeCarbon->between($shiftStart, $shiftEnd)) {
                $staffCount++;
            }
        }
        
        return $staffCount;
    }

    public function canMoveToSub($reservationId): bool
    {
        $reservation = Reservation::find($reservationId);
        if (!$reservation || $reservation->is_sub) {
            \Log::info('canMoveToSub: false - reservation not found or already in sub', [
                'id' => $reservationId,
                'is_sub' => $reservation ? $reservation->is_sub : null
            ]);
            return false;
        }
        
        $temp = clone $reservation;
        $temp->is_sub = true;
        $temp->seat_number = null;
        
        $result = Reservation::checkAvailability($temp);
        \Log::info('canMoveToSub result:', [
            'reservation_id' => $reservationId,
            'can_move' => $result
        ]);
        
        return $result;
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
    
    // 新規予約作成関連のメソッド
    public function openNewReservationModal(): void
    {
        $this->showNewReservationModal = true;
        $this->reservationStep = 1;
        $this->phoneSearch = '';
        $this->menuSearch = '';  // メニュー検索をリセット
        $this->searchResults = [];
        $this->selectedCustomer = null;
        $this->newCustomer = [
            'last_name' => '',
            'first_name' => '',
            'last_name_kana' => '',
            'first_name_kana' => '',
            'email' => '',
            'phone' => ''
        ];
        $this->newReservation = [
            'date' => $this->selectedDate,
            'start_time' => '',
            'duration' => 60,
            'menu_id' => '',
            'line_type' => 'main',
            'line_number' => 1,
            'notes' => '電話予約'
        ];

        // モーダルが開いたことをブラウザに通知
        $this->dispatch('modal-opened');
    }
    
    public function openNewReservationFromSlot($seatKey, $timeSlot): void
    {
        \Log::info('Slot clicked:', ['seat' => $seatKey, 'time' => $timeSlot]);
        
        // 席タイプとライン番号を解析
        if (strpos($seatKey, 'sub_') === 0) {
            $lineType = 'sub';
            $lineNumber = intval(substr($seatKey, 4));
        } else {
            $lineType = 'main';
            $lineNumber = intval(substr($seatKey, 5));
        }
        
        $this->showNewReservationModal = true;
        $this->reservationStep = 1;
        $this->phoneSearch = '';
        $this->menuSearch = '';  // メニュー検索をリセット
        $this->searchResults = [];
        $this->selectedCustomer = null;
        $this->newCustomer = [
            'last_name' => '',
            'first_name' => '',
            'last_name_kana' => '',
            'first_name_kana' => '',
            'email' => '',
            'phone' => ''
        ];
        $this->newReservation = [
            'date' => $this->selectedDate,
            'start_time' => $timeSlot,
            'duration' => 60,
            'menu_id' => '',
            'line_type' => $lineType,
            'line_number' => $lineNumber,
            'notes' => '電話予約'
        ];

        // モーダルが開いたことをブラウザに通知
        $this->dispatch('modal-opened');
    }
    
    public function closeNewReservationModal(): void
    {
        $this->showNewReservationModal = false;
    }
    
    public function updatedPhoneSearch(): void
    {
        if (strlen($this->phoneSearch) >= 2) {
            // 電話番号、名前、カナで顧客を検索（選択中の店舗に来店履歴がある顧客のみ）
            $search = $this->phoneSearch;
            $storeId = $this->selectedStore;
            
            $this->searchResults = \App\Models\Customer::where(function($query) use ($search) {
                    $query->where('phone', 'LIKE', '%' . $search . '%')
                          ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                          ->orWhere('first_name', 'LIKE', '%' . $search . '%')
                          ->orWhere('last_name_kana', 'LIKE', '%' . $search . '%')
                          ->orWhere('first_name_kana', 'LIKE', '%' . $search . '%');
                })
                ->whereHas('reservations', function($query) use ($storeId) {
                    // この店舗での予約履歴がある顧客のみ
                    $query->where('store_id', $storeId);
                })
                ->withCount(['reservations' => function($query) use ($storeId) {
                    // この店舗での予約回数をカウント
                    $query->where('store_id', $storeId);
                }])
                ->with(['reservations' => function($query) use ($storeId) {
                    // この店舗での最新予約を取得
                    $query->where('store_id', $storeId)
                          ->latest('reservation_date')
                          ->first();
                }])
                ->limit(10)
                ->get()
                ->map(function($customer) {
                    $lastReservation = $customer->reservations->first();
                    $customer->last_visit_date = $lastReservation ? $lastReservation->reservation_date : null;
                    return $customer;
                });
        } else {
            $this->searchResults = [];
        }
    }
    
    public function selectCustomer($customerId): void
    {
        $this->selectedCustomer = \App\Models\Customer::find($customerId);
        $this->reservationStep = 3; // 予約詳細入力へ

        // ステップ3に移行したことをブラウザに通知
        $this->dispatch('modal-opened');
    }
    
    public function startNewCustomerRegistration(): void
    {
        $this->newCustomer['phone'] = $this->phoneSearch;
        $this->reservationStep = 2; // 新規顧客登録へ
    }
    
    public function createNewCustomer(): void
    {
        // バリデーション
        if (empty($this->newCustomer['last_name']) || empty($this->newCustomer['first_name'])) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '姓名は必須です'
            ]);
            return;
        }
        
        if (empty($this->newCustomer['phone'])) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '電話番号は必須です'
            ]);
            return;
        }
        
        // 電話番号の重複チェック
        $existingCustomer = \App\Models\Customer::where('phone', $this->newCustomer['phone'])->first();
        if ($existingCustomer) {
            // 既存顧客だった場合は、情報を更新して次へ進む
            $this->selectedCustomer = $existingCustomer;
            $this->reservationStep = 3;

            // ステップ3に移行したことをブラウザに通知
            $this->dispatch('modal-opened');

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => '既存のお客様でした（' . $existingCustomer->last_name . ' ' . $existingCustomer->first_name . '様）。予約詳細を入力してください。'
            ]);
            return;
        }
        
        // 新規顧客を作成
        $customer = \App\Models\Customer::create([
            'last_name' => $this->newCustomer['last_name'],
            'first_name' => $this->newCustomer['first_name'],
            'last_name_kana' => '',  // カナは空で設定
            'first_name_kana' => '', // カナは空で設定
            'email' => $this->newCustomer['email'],
            'phone' => $this->newCustomer['phone'],
        ]);
        
        $this->selectedCustomer = $customer;
        $this->reservationStep = 3; // 予約詳細入力へ

        // ステップ3に移行したことをブラウザに通知
        $this->dispatch('modal-opened');
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '新規顧客を登録しました'
        ]);
    }
    
    public function createReservation(): void
    {
        // バリデーション
        if (!$this->selectedCustomer || empty($this->newReservation['menu_id'])) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '必須項目を入力してください'
            ]);
            return;
        }
        
        // 過去の日時チェック（現在時刻から30分前まで許可）
        $reservationDateTime = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $this->newReservation['start_time']);
        $minimumTime = \Carbon\Carbon::now()->subMinutes(30);
        if ($reservationDateTime->lt($minimumTime)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '過去の時間には予約できません'
            ]);
            return;
        }
        
        // メニュー情報を取得
        $menu = \App\Models\Menu::find($this->newReservation['menu_id']);
        if (!$menu) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'メニューが見つかりません'
            ]);
            return;
        }
        
        // 終了時刻を計算
        $startTime = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $this->newReservation['start_time']);
        $endTime = $startTime->copy()->addMinutes($menu->duration_minutes ?? $this->newReservation['duration']);

        // 営業時間チェック（終了時刻ベース）
        $store = \App\Models\Store::find($this->selectedStore);
        $dayOfWeek = $startTime->format('l');
        $closingTime = '20:00'; // デフォルト

        // 曜日別営業時間があるか確認
        if ($store && isset($store->business_hours[$dayOfWeek])) {
            $closingTime = $store->business_hours[$dayOfWeek]['close'] ?? '20:00';
        } elseif ($store && isset($store->business_hours['close'])) {
            $closingTime = $store->business_hours['close'];
        }

        $closingDateTime = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $closingTime);

        // 終了時刻が営業時間を超える場合はエラー
        if ($endTime->gt($closingDateTime)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '予約終了時刻（' . $endTime->format('H:i') . '）が営業時間（' . $closingTime . '）を超えています'
            ]);
            return;
        }

        // 予約番号を生成
        $reservationNumber = 'R' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // 予約を作成
        $reservation = Reservation::create([
            'reservation_number' => $reservationNumber,
            'store_id' => $this->selectedStore,
            'customer_id' => $this->selectedCustomer->id,
            'menu_id' => $this->newReservation['menu_id'],
            'reservation_date' => $this->newReservation['date'],
            'start_time' => $this->newReservation['start_time'],
            'end_time' => $endTime->format('H:i'),
            'guest_count' => 1,
            'status' => 'booked',
            'source' => 'phone',
            'line_type' => $this->newReservation['line_type'],
            'line_number' => $this->newReservation['line_type'] === 'main' ? $this->newReservation['line_number'] : null,
            'notes' => $this->newReservation['notes'],
            'total_amount' => $menu->price ?? 0,
            'deposit_amount' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);
        
        // モーダルを閉じる
        $this->closeNewReservationModal();
        
        // タイムラインを更新
        $this->loadTimelineData();
        
        // 成功通知
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '予約を作成しました（予約番号: ' . $reservationNumber . '）'
        ]);
    }

    public function getFilteredMenus()
    {
        $query = \App\Models\Menu::where('is_available', true);

        if (!empty($this->menuSearch)) {
            $search = $this->menuSearch;
            $query->where('name', 'like', '%' . $search . '%');
        }

        return $query->orderBy('id')->get();
    }

    public function updatedMenuSearch()
    {
        // メニュー検索が更新されたときの処理
        // Livewireが自動的に再レンダリングする
    }

    public function selectMenu($menuId)
    {
        $this->newReservation['menu_id'] = $menuId;

        // メニューの時間を自動設定
        $menu = \App\Models\Menu::find($menuId);
        if ($menu && $menu->duration_minutes) {
            $this->newReservation['duration'] = $menu->duration_minutes;
        }

        // 検索フィールドをクリア & ドロップダウンを閉じる
        $this->menuSearch = '';
        $this->showAllMenus = false;
    }

}