<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Notifications\Notification;
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

    // ポーリング無効化（リアルタイム通知で更新するため）
    protected static ?string $pollingInterval = null;

    public $selectedStore = null;
    public $selectedDate = null;
    public $stores = [];
    public $timelineData = [];
    public $categories = [];
    public $selectedReservation = null;

    // 更新通知用のプロパティ
    public $lastDataHash = null;
    public $hasUpdates = false;

    // モーダル表示フラグ
    public $showMedicalHistoryModal = false;
    public $showReservationHistoryModal = false;

    // 新規予約作成用のプロパティ
    public $showNewReservationModal = false;
    public $modalMode = 'reservation'; // 'reservation' or 'block'
    public $reservationStep = 1; // 1: 顧客検索, 2: 新規顧客登録, 3: 予約詳細
    public $customerSelectionMode = 'existing'; // 'existing' or 'new'
    public $phoneSearch = '';
    public $searchResults = [];
    public $selectedCustomer = null;
    public $menuSearch = '';  // メニュー検索用
    public $showAllMenus = false;  // 全メニュー表示フラグ
    public $availableOptions = [];  // 選択可能なオプションメニュー
    public $selectedOptions = [];  // 選択されたオプションメニュー（詳細情報含む）
    public $newCustomer = [
        'last_name' => '',
        'first_name' => '',
        'email' => '',
        'phone' => ''
    ];

    // 顧客重複時の確認画面用
    public $conflictingCustomer = null;
    public $showCustomerConflictConfirmation = false;

    public $newReservation = [
        'date' => '',
        'start_time' => '',
        'duration' => 60,
        'menu_id' => '',
        'line_type' => 'main',
        'line_number' => 1,
        'staff_id' => '',
        'notes' => '電話予約',
        'option_menu_ids' => [], // オプションメニューID配列
        'customer_ticket_id' => null, // 回数券ID
        'customer_subscription_id' => null // サブスクリプションID
    ];

    // メニュー選択時の所要時間（空き判定の動的更新用）
    public ?int $selectedMenuDuration = null;
    public ?int $selectedOptionsDuration = null;

    // 予約ブロック用のプロパティ
    public $blockSettings = [
        'date' => '',
        'start_time' => '',
        'end_time' => '',
        'reason' => '休憩',
        'apply_to_all_lines' => false,
        'selected_lines' => []
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

    /**
     * リアルタイム通知からのタイムライン更新イベントを受け取る
     */
    #[On('refresh-timeline')]
    public function refreshTimeline(): void
    {
        logger('🔄 リアルタイム通知によりタイムラインを更新');
        $this->loadTimelineData();
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

    public function goToToday(): void
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
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

    #[On('timeline-updated')]
    public function refreshOnTimelineUpdate($data): void
    {
        // 同じ店舗・日付のタイムラインのみ更新
        if (isset($data['store_id']) && $data['store_id'] == $this->selectedStore &&
            isset($data['date']) && $data['date'] == $this->selectedDate) {
            $this->loadTimelineData();
        }
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
    
    /**
     * 更新をチェック（30秒ごとに呼ばれる、画面は更新しない）
     */
    public function checkForUpdates(): void
    {
        logger('🔍 checkForUpdates() が呼ばれました - store: ' . ($this->selectedStore ?? 'null') . ', date: ' . ($this->selectedDate ?? 'null'));

        if (!$this->selectedStore || !$this->selectedDate) {
            logger('⚠️ checkForUpdates() 早期リターン - 店舗または日付が未設定');
            return;
        }

        $store = Store::find($this->selectedStore);
        if (!$store) {
            return;
        }

        $date = Carbon::parse($this->selectedDate);

        // 現在のデータのハッシュ値を計算
        $reservations = $this->getBaseQuery()
            ->where('store_id', $this->selectedStore)
            ->whereDate('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->get();

        $blockedPeriods = \App\Models\BlockedTimePeriod::where('store_id', $this->selectedStore)
            ->whereDate('blocked_date', $date)
            ->get();

        $currentHash = md5(json_encode([
            'reservations' => $reservations->pluck('id', 'updated_at')->toArray(),
            'blocks' => $blockedPeriods->pluck('id', 'updated_at')->toArray(),
        ]));

        // 初回チェック時はハッシュを保存
        if ($this->lastDataHash === null) {
            $this->lastDataHash = $currentHash;
            $this->hasUpdates = false;
            logger('🔍 初回チェック - ハッシュを保存: ' . substr($currentHash, 0, 8));
        }
        // データが変更された場合は通知フラグを立てる
        elseif ($this->lastDataHash !== $currentHash && !$this->hasUpdates) {
            $this->hasUpdates = true;
            logger('🔔 データ変更を検知しました！ 旧: ' . substr($this->lastDataHash, 0, 8) . ' → 新: ' . substr($currentHash, 0, 8));
        }
    }

    /**
     * 更新を適用（ユーザーが「更新」ボタンをクリックした時）
     */
    public function applyUpdates(): void
    {
        // フラグをリセット
        $this->hasUpdates = false;

        // データを再読み込み
        $this->loadTimelineData();

        // 最新のハッシュを保存
        if ($this->selectedStore && $this->selectedDate) {
            $date = Carbon::parse($this->selectedDate);

            $reservations = $this->getBaseQuery()
                ->where('store_id', $this->selectedStore)
                ->whereDate('reservation_date', $date)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->get();

            $blockedPeriods = \App\Models\BlockedTimePeriod::where('store_id', $this->selectedStore)
                ->whereDate('blocked_date', $date)
                ->get();

            $this->lastDataHash = md5(json_encode([
                'reservations' => $reservations->pluck('id', 'updated_at')->toArray(),
                'blocks' => $blockedPeriods->pluck('id', 'updated_at')->toArray(),
            ]));

            logger('✅ 更新適用 - 新しいハッシュを保存: ' . substr($this->lastDataHash, 0, 8));
        }

        Notification::make()
            ->title('タイムラインを更新しました')
            ->success()
            ->send();
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

        // スタッフシフトモードの場合、シフトの時間範囲も考慮
        if ($useStaffAssignment && $shifts->count() > 0) {
            $earliestShiftStart = 24;
            $latestShiftEnd = 0;

            foreach ($shifts as $shift) {
                $shiftStartHour = (int)substr($shift->start_time, 0, 2);
                $shiftEndHour = (int)substr($shift->end_time, 0, 2);

                if ($shiftStartHour < $earliestShiftStart) {
                    $earliestShiftStart = $shiftStartHour;
                }
                if ($shiftEndHour > $latestShiftEnd) {
                    $latestShiftEnd = $shiftEndHour;
                }
            }

            // シフト時間が営業時間外の場合、タイムラインを拡張
            if ($earliestShiftStart < $startHour) {
                $startHour = $earliestShiftStart;
            }
            if ($latestShiftEnd > $endHour) {
                $endHour = $latestShiftEnd;
            }

            logger('📅 スタッフシフトモード時間範囲調整: ' . $startHour . ':00-' . $endHour . ':00');
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

        // 店舗設定から予約枠の長さを取得（デフォルト30分）
        $slotDuration = $store->reservation_slot_duration ?? 30;

        for ($hour = $startHour; $hour <= $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += $slotDuration) {
                // 営業時間内のスロットを表示
                $slots[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }
        
        // 座席データを初期化
        if ($useStaffAssignment) {
            // シフトベースモードの場合はスタッフごとのラインを作成

            // 1. 未指定ラインを最初に追加
            $timeline['unassigned'] = [
                'key' => 'unassigned',
                'label' => '未指定',
                'type' => 'unassigned',
                'reservations' => [],
                'staff_id' => null
            ];

            // 2. この日のシフトがあるスタッフ + 予約で指定されているスタッフを集める
            $staffIds = collect();

            // シフトがあるスタッフ
            foreach ($shifts as $shift) {
                if ($shift->user_id) {
                    $staffIds->push($shift->user_id);
                }
            }

            // この日の予約で指定されているスタッフも追加
            $reservedStaffIds = $reservations->pluck('staff_id')->filter()->unique();
            $staffIds = $staffIds->merge($reservedStaffIds)->unique();

            // スタッフ情報を取得
            $storeStaff = \App\Models\User::whereIn('id', $staffIds)
              ->where('is_active', true)
              ->orderBy('name')
              ->get();

            logger('📊 店舗スタッフ確認 - Store: ' . $this->selectedStore . ', スタッフ数: ' . $storeStaff->count() . ', シフトスタッフ: ' . $shifts->pluck('user_id')->implode(',') . ', 予約スタッフ: ' . $reservedStaffIds->implode(','));

            // 各スタッフのシフト情報を取得
            $staffShifts = [];
            foreach ($shifts as $shift) {
                if ($shift->is_available_for_reservation) {
                    $staffShifts[$shift->user_id] = $shift;
                    logger('🔍 シフト登録: user_id=' . $shift->user_id . ', 時間=' . $shift->start_time . '-' . $shift->end_time);
                }
            }

            logger('📊 取得したシフト数: ' . count($staffShifts) . ', シフトユーザーID: ' . implode(', ', array_keys($staffShifts)));

            // 全スタッフのラインを作成
            foreach ($storeStaff as $staff) {
                $hasShift = isset($staffShifts[$staff->id]);
                $timeline['staff_' . $staff->id] = [
                    'key' => 'staff_' . $staff->id,
                    'label' => $staff->name,
                    'type' => 'staff',
                    'staff_id' => $staff->id,
                    'reservations' => [],
                    'shift' => $hasShift ? $staffShifts[$staff->id] : null,
                    'has_shift' => $hasShift // シフトの有無フラグ
                ];
                logger('  - スタッフライン追加: ' . $staff->name . ' (ID=' . $staff->id . ', シフト: ' . ($hasShift ? 'あり' : 'なし') . ')');
            }

            // 3. サブ枠（シフトモードでも残す）
            $timeline['sub_1'] = [
                'key' => 'sub_1',
                'label' => 'サブ',
                'type' => 'sub',
                'reservations' => []
            ];
        } else {
            // 営業時間ベースモードの場合は固定席数
            for ($seat = 1; $seat <= $mainSeats; $seat++) {
                $timeline['seat_' . $seat] = [
                    'key' => 'seat_' . $seat,
                    'label' => '席' . $seat,
                    'type' => 'main',
                    'reservations' => []
                ];
            }

            // サブ枠（固定1席）
            $timeline['sub_1'] = [
                'key' => 'sub_1',
                'label' => 'サブ',
                'type' => 'sub',
                'reservations' => []
            ];
        }
        
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
        $blockedSlots = []; // 後方互換のため残す（全体ブロック用）
        $lineBlockedSlots = []; // ライン別ブロック情報

        foreach ($blockedPeriods as $blocked) {
            // 終日休みの場合は全スロットをブロック
            if ($blocked->is_all_day) {
                for ($i = 0; $i < count($slots); $i++) {
                    // line_typeがnullの場合は全ラインブロック
                    if ($blocked->line_type === null) {
                        $blockedSlots[] = $i;
                    } else {
                        // 特定ラインのブロック
                        $seatKey = $this->getSeatKeyFromBlock($blocked);
                        if (!isset($lineBlockedSlots[$seatKey])) {
                            $lineBlockedSlots[$seatKey] = [];
                        }
                        $lineBlockedSlots[$seatKey][] = $i;
                    }
                }
            } else {
                $blockStart = Carbon::parse($blocked->start_time);
                $blockEnd = Carbon::parse($blocked->end_time);

                // 時間スロットのインデックスを計算（店舗設定の時間刻み）
                $slotsPerHour = 60 / $slotDuration;
                $startSlot = max(0, ($blockStart->hour - $startHour) * $slotsPerHour + ($blockStart->minute / $slotDuration));
                $endSlot = min(count($slots), ($blockEnd->hour - $startHour) * $slotsPerHour + ($blockEnd->minute / $slotDuration));

                // ブロックされているスロットを記録
                for ($i = floor($startSlot); $i < ceil($endSlot); $i++) {
                    // line_typeがnullの場合は全ラインブロック
                    if ($blocked->line_type === null) {
                        $blockedSlots[] = $i;
                    } else {
                        // 特定ラインのブロック
                        $seatKey = $this->getSeatKeyFromBlock($blocked);
                        if (!isset($lineBlockedSlots[$seatKey])) {
                            $lineBlockedSlots[$seatKey] = [];
                        }
                        $lineBlockedSlots[$seatKey][] = $i;
                    }
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

            // 実際の予約終了時刻を使用（end_timeがある場合）
            if (!empty($reservation->end_time)) {
                // 日付を明示的に指定してパース
                $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $reservation->end_time);
                $duration = $startTime->diffInMinutes($endTime);

                // デバッグログ（全予約）
                \Log::info('🕒 Reservation timeline calculation', [
                    'reservation_id' => $reservation->id,
                    'date' => $date->format('Y-m-d'),
                    'start_time_raw' => $reservation->start_time,
                    'end_time_raw' => $reservation->end_time,
                    'startTime_parsed' => $startTime->format('Y-m-d H:i:s'),
                    'endTime_parsed' => $endTime->format('Y-m-d H:i:s'),
                    'duration_minutes' => $duration,
                    'slotDuration' => $slotDuration,
                    'calculated_span' => $duration / $slotDuration
                ]);
            } else {
                // end_timeがない場合はメニューの所要時間を使用
                $duration = $reservation->menu->duration_minutes ?? 60;
                $endTime = $startTime->copy()->addMinutes($duration);

                \Log::info('🕒 Reservation timeline calculation (no end_time)', [
                    'reservation_id' => $reservation->id,
                    'duration_from_menu' => $duration,
                    'slotDuration' => $slotDuration,
                    'calculated_span' => $duration / $slotDuration
                ]);
            }

            // 顧客の初回訪問かチェック（この予約より前の予約があるか）
            $isNewCustomer = false;
            if ($reservation->customer_id) {
                $previousReservationCount = Reservation::where('customer_id', $reservation->customer_id)
                    ->where('id', '<', $reservation->id)
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->count();
                $isNewCustomer = ($previousReservationCount === 0);
            }

            // 時間スロットのインデックスを計算（店舗設定の時間刻み）
            $slotsPerHour = 60 / $slotDuration; // 1時間あたりのスロット数
            $startSlot = ($startTime->hour - $startHour) * $slotsPerHour + ($startTime->minute / $slotDuration);
            $span = $duration / $slotDuration; // slotDurationを1単位とする

            \Log::info('🎯 Final span value', [
                'reservation_id' => $reservation->id,
                'span' => $span,
                'startSlot' => $startSlot
            ]);

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

            // 6時間以内に作成または変更された予約かどうか
            $isRecentlyCreated = false;
            $now = now();

            // 新規作成から6時間以内
            if ($reservation->created_at && $reservation->created_at->diffInHours($now) < 6) {
                $isRecentlyCreated = true;
            }
            // または変更から6時間以内（created_atとupdated_atが異なる場合=変更あり）
            elseif ($reservation->updated_at &&
                    $reservation->created_at &&
                    $reservation->updated_at->gt($reservation->created_at) &&
                    $reservation->updated_at->diffInHours($now) < 6) {
                $isRecentlyCreated = true;
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
                'is_new_customer' => $isNewCustomer,
                'is_recently_created' => $isRecentlyCreated,
            ];

            \Log::info('📦 Reservation data created', [
                'reservation_id' => $reservation->id,
                'reservationData' => $reservationData
            ]);

            // シフトベースモードの場合
            if ($useStaffAssignment) {
                // サブ枠の場合は、スタッフシフトモードでもサブラインに配置
                if ($reservation->line_type === 'sub' || $reservation->is_sub) {
                    $subKey = 'sub_1';
                    if (isset($timeline[$subKey])) {
                        $timeline[$subKey]['reservations'][] = $reservationData;
                    }
                } else {
                    // 通常の予約はstaff_idベースで配置
                    $staffId = $reservation->staff_id;

                    // デバッグログ
                    \Log::info('Placing reservation in timeline:', [
                        'reservation_id' => $reservation->id,
                        'staff_id' => $staffId,
                        'line_type' => $reservation->line_type,
                        'timeline_keys' => array_keys($timeline)
                    ]);

                    if ($staffId && isset($timeline['staff_' . $staffId])) {
                        // スタッフが指定されており、そのスタッフのラインが存在する場合
                        $timeline['staff_' . $staffId]['reservations'][] = $reservationData;
                        \Log::info('Placed in staff line: staff_' . $staffId);
                    } else {
                        // スタッフが未指定または該当ラインがない場合は「未指定」に配置
                        $timeline['unassigned']['reservations'][] = $reservationData;
                        \Log::info('Placed in unassigned line');
                    }
                }
            } else {
                // 従来の営業時間ベースモードの場合
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
                    for ($seat = 1; $seat <= ($mainSeats ?? 3); $seat++) {
                        $seatKey = 'seat_' . $seat;
                        if (isset($timeline[$seatKey])) {
                            $timeline[$seatKey]['reservations'][] = $reservationData;
                            break; // 最初の席に配置して終了
                        }
                    }
                }
            }
        }
        
        $this->timelineData = [
            'slots' => $slots,
            'timeline' => $timeline,
            'blockedSlots' => $blockedSlots,
            'lineBlockedSlots' => $lineBlockedSlots,
            'conflictingReservations' => $conflictingReservations,
            'blockedPeriods' => $blockedPeriods->toArray(),
            'useStaffAssignment' => $useStaffAssignment,
            'shiftBasedAvailability' => $shiftBasedAvailability ?? [],
            'shiftBasedCapacity' => $store->shift_based_capacity ?? 1,
            'maxCapacity' => $useStaffAssignment ? $maxCapacity : ($mainSeats ?? 3),
            'slotDuration' => $slotDuration,
            'startHour' => $startHour,  // タイムライン開始時刻を追加
            'endHour' => $endHour        // タイムライン終了時刻を追加
        ];
    }
    
    private function getCourseType($categoryId): string
    {
        // カテゴリーIDがnullの場合はデフォルトを返す
        if (!$categoryId) {
            return 'default';
        }

        // カテゴリーIDと色のマッピングをキャッシュから取得
        static $categoryColorMap = null;

        if ($categoryColorMap === null) {
            $categoryColorMap = [];

            // getCategories()と同じロジックでマッピングを作成
            $categories = \App\Models\MenuCategory::where('is_active', true);

            if ($this->selectedStore) {
                $categories->where('store_id', $this->selectedStore);
            }

            $categories = $categories->orderBy('id')->get();

            $colorPatterns = ['care', 'hydrogen', 'training', 'special', 'premium', 'vip'];

            foreach ($categories as $index => $category) {
                $colorIndex = $index % count($colorPatterns);
                $categoryColorMap[$category->id] = $colorPatterns[$colorIndex];
            }
        }

        // マッピングから色を返す
        return $categoryColorMap[$categoryId] ?? 'default';
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
        // ログを追加して問題を追跡
        \Log::info('Opening reservation detail', ['reservation_id' => $reservationId]);

        try {
            $this->selectedReservation = Reservation::with(['customer', 'menu', 'staff'])->find($reservationId);
            // reservationOptionsを安全に読み込み
            if ($this->selectedReservation) {
                $this->selectedReservation->load('reservationOptions.menuOption');
            }
        } catch (\Exception $e) {
            \Log::error('Error loading reservation detail in timeline', [
                'reservation_id' => $reservationId,
                'error' => $e->getMessage()
            ]);
            $this->selectedReservation = Reservation::with(['customer', 'menu', 'staff'])->find($reservationId);
        }

        if ($this->selectedReservation && $this->selectedReservation->customer_id) {
            // 顧客の総訪問回数を取得
            $this->selectedReservation->customer_visit_count = Reservation::where('customer_id', $this->selectedReservation->customer_id)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->where('id', '<=', $this->selectedReservation->id)
                ->count();

            // モーダルが開いたことを通知
            $this->dispatch('modal-opened');
            
            // 初回訪問かどうか
            $this->selectedReservation->is_new_customer = ($this->selectedReservation->customer_visit_count === 1);
        }
    }
    
    public function closeModal(): void
    {
        $this->selectedReservation = null;
    }

    public function selectReservation($reservationId): void
    {
        $this->openReservationDetail($reservationId);
    }

    public function moveToSub($reservationId): void
    {
        \Log::info('=== moveToSub START ===', ['reservation_id' => $reservationId]);

        $reservation = Reservation::find($reservationId);
        if ($reservation) {
            \Log::info('Reservation found', [
                'id' => $reservation->id,
                'store_id' => $reservation->store_id,
                'date' => $reservation->reservation_date,
                'time' => $reservation->start_time . '-' . $reservation->end_time,
                'current_is_sub' => $reservation->is_sub,
                'current_line_type' => $reservation->line_type
            ]);
            // 過去の予約は移動不可（日付と時刻を合わせて判定）
            $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->start_time);
            if ($reservationDateTime->isPast()) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('移動失敗')
                    ->body('過去の予約の席移動はできません')
                    ->send();
                return;
            }
            // サブ枠に既に予約があるかチェック
            // サブ枠の重複チェックのみを直接実施（営業時間チェックは不要）
            $hasConflict = Reservation::where('store_id', $reservation->store_id)
                ->whereDate('reservation_date', $reservation->reservation_date)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->where('id', '!=', $reservation->id)
                ->where(function($q) use ($reservation) {
                    // 時刻フォーマットを統一して比較
                    $endTime = strlen($reservation->end_time) === 5 ? $reservation->end_time . ':00' : $reservation->end_time;
                    $startTime = strlen($reservation->start_time) === 5 ? $reservation->start_time . ':00' : $reservation->start_time;
                    $q->whereRaw('time(start_time) < time(?)', [$endTime])
                      ->whereRaw('time(end_time) > time(?)', [$startTime]);
                })
                ->where(function($q) {
                    $q->where('is_sub', true)
                      ->orWhere('line_type', 'sub');
                })
                ->exists();

            \Log::info('moveToSub: Direct conflict check', [
                'reservation_id' => $reservation->id,
                'has_conflict' => $hasConflict
            ]);

            if ($hasConflict) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('移動失敗')
                    ->body('サブ枠は既に予約が入っています')
                    ->send();
                return;
            }

            // 予約ブロックのチェック
            $isBlocked = \App\Models\BlockedTimePeriod::where('store_id', $reservation->store_id)
                ->whereDate('blocked_date', $reservation->reservation_date)
                ->where('line_type', 'sub')
                ->where(function($q) use ($reservation) {
                    $endTime = strlen($reservation->end_time) === 5 ? $reservation->end_time . ':00' : $reservation->end_time;
                    $startTime = strlen($reservation->start_time) === 5 ? $reservation->start_time . ':00' : $reservation->start_time;
                    $q->whereRaw('time(start_time) < time(?)', [$endTime])
                      ->whereRaw('time(end_time) > time(?)', [$startTime]);
                })
                ->exists();

            if ($isBlocked) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('移動不可')
                    ->body('サブ枠は予約ブロックされています')
                    ->send();
                return;
            }

            // 重複チェックを一時的に無効化して保存
            // 直接DBを更新（モデルイベントを完全にバイパス）
            DB::table('reservations')
                ->where('id', $reservation->id)
                ->update([
                    'is_sub' => true,
                    'seat_number' => null,
                    'line_type' => 'sub',
                    'line_number' => 1,
                    'staff_id' => null, // スタッフシフトモードでもサブ枠はスタッフ不要
                    'updated_at' => now()
                ]);
            
            $this->loadTimelineData();
            $this->selectedReservation = null;
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('移動完了')
                ->body('サブ枠に移動しました')
                ->send();
        }
    }
    
    /**
     * スタッフへの移動（スタッフシフトモード用）
     */
    public function moveToStaff($reservationId, $staffId): void
    {
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            return;
        }

        // 過去の予約は移動不可
        $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->start_time);
        if ($reservationDateTime->isPast()) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('移動失敗')
                ->body('過去の予約の席移動はできません')
                ->send();
            return;
        }

        // スタッフのシフトを確認
        $shift = \App\Models\Shift::where('store_id', $reservation->store_id)
            ->whereDate('shift_date', $reservation->reservation_date)
            ->where('user_id', $staffId)
            ->where('status', 'scheduled')
            ->where('is_available_for_reservation', true)
            ->first();

        if (!$shift) {
            $staff = \App\Models\User::find($staffId);
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('シフトなし')
                ->body(($staff ? $staff->name : 'スタッフ') . 'はこの日シフトがありません')
                ->send();
            return;
        }

        // シフト時間内かチェック
        $startTime = Carbon::parse($reservation->start_time);
        $endTime = Carbon::parse($reservation->end_time);
        $shiftStart = Carbon::parse($shift->start_time);
        $shiftEnd = Carbon::parse($shift->end_time);

        if ($startTime->lt($shiftStart) || $endTime->gt($shiftEnd)) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('シフト時間外')
                ->body('予約時間がスタッフのシフト時間外です（' . $shift->start_time . '-' . $shift->end_time . '）')
                ->send();
            return;
        }

        // 予約ブロックのチェック
        $isBlocked = \App\Models\BlockedTimePeriod::where('store_id', $reservation->store_id)
            ->whereDate('blocked_date', $reservation->reservation_date)
            ->where(function($q) use ($staffId) {
                $q->where('line_type', 'staff')
                  ->where('staff_id', $staffId);
            })
            ->where(function($q) use ($reservation) {
                $endTime = strlen($reservation->end_time) === 5 ? $reservation->end_time . ':00' : $reservation->end_time;
                $startTime = strlen($reservation->start_time) === 5 ? $reservation->start_time . ':00' : $reservation->start_time;
                $q->whereRaw('time(start_time) < time(?)', [$endTime])
                  ->whereRaw('time(end_time) > time(?)', [$startTime]);
            })
            ->exists();

        if ($isBlocked) {
            $staff = \App\Models\User::find($staffId);
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('移動不可')
                ->body(($staff ? $staff->name . 'のライン' : '指定のスタッフライン') . 'は予約ブロックされています')
                ->send();
            return;
        }

        // 直接DBを更新（スタッフラインへ移動）
        // line_numberは必須のため1を設定（スタッフシフトモードでは使用しないが制約対応）
        \Log::info('Moving to staff - Before update:', [
            'reservation_id' => $reservation->id,
            'target_staff_id' => $staffId,
            'current_staff_id' => $reservation->staff_id,
            'current_line_type' => $reservation->line_type
        ]);

        $updateResult = DB::table('reservations')
            ->where('id', $reservation->id)
            ->update([
                'is_sub' => false,
                'line_type' => 'staff',
                'line_number' => 1, // NOT NULL制約のため1を設定
                'seat_number' => null,
                'staff_id' => $staffId,
                'updated_at' => now()
            ]);

        \Log::info('Moving to staff - After update:', [
            'update_result' => $updateResult,
            'reservation_id' => $reservation->id
        ]);

        // データを再読み込みして画面を更新
        $this->loadTimelineData();

        // 更新後の予約を確認
        $updatedReservation = Reservation::find($reservation->id);
        \Log::info('After reload - reservation state:', [
            'reservation_id' => $updatedReservation->id,
            'staff_id' => $updatedReservation->staff_id,
            'line_type' => $updatedReservation->line_type,
            'line_number' => $updatedReservation->line_number
        ]);

        $this->selectedReservation = null;

        $staff = \App\Models\User::find($staffId);
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('割り当て完了')
            ->body(($staff ? $staff->name : 'スタッフ') . 'に割り当てました')
            ->send();
    }

    /**
     * 予約詳細モーダルを閉じる
     */
    public function closeReservationDetailModal(): void
    {
        \Log::info('Closing reservation detail modal');
        $this->selectedReservation = null;
        // モーダルが閉じたことを通知
        $this->dispatch('modal-closed');
    }

    /**
     * サブ枠から未指定ラインへの移動（スタッフシフトモード用）
     */
    public function moveToUnassigned($reservationId): void
    {
        $reservation = Reservation::find($reservationId);
        if ($reservation) {
            // 過去の予約は移動不可
            $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->start_time);
            if ($reservationDateTime->isPast()) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('移動失敗')
                    ->body('過去の予約の席移動はできません')
                    ->send();
                return;
            }

            // 直接DBを更新（サブ枠から未指定へ）
            // line_typeは'unassigned'に設定（NOT NULL制約対応）
            \Log::info('Moving to unassigned - Before update:', [
                'reservation_id' => $reservation->id,
                'current_staff_id' => $reservation->staff_id,
                'current_line_type' => $reservation->line_type,
                'current_line_number' => $reservation->line_number
            ]);

            $updateResult = DB::table('reservations')
                ->where('id', $reservation->id)
                ->update([
                    'is_sub' => false,
                    'line_type' => 'unassigned', // NOT NULL制約のため'unassigned'を設定
                    'line_number' => 1, // NOT NULL制約のため1を設定（nullは不可）
                    'seat_number' => null,
                    'staff_id' => null, // 未指定なのでスタッフIDもnull
                    'updated_at' => now()
                ]);

            \Log::info('Moving to unassigned - After update:', [
                'update_result' => $updateResult,
                'reservation_id' => $reservation->id
            ]);

            // データを再読み込みして画面を更新
            $this->loadTimelineData();

            // 更新後の予約を確認
            $updatedReservation = Reservation::find($reservation->id);
            \Log::info('After reload - reservation state:', [
                'reservation_id' => $updatedReservation->id,
                'staff_id' => $updatedReservation->staff_id,
                'line_type' => $updatedReservation->line_type,
                'line_number' => $updatedReservation->line_number
            ]);

            $this->selectedReservation = null;

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('移動完了')
                ->body('未指定ラインに移動しました')
                ->send();
        }
    }

    public function moveToMain($reservationId, $seatNumber): void
    {
        $reservation = Reservation::find($reservationId);
        if ($reservation) {
            // 過去の予約は移動不可（日付と時刻を合わせて判定）
            $reservationDateTime = \Carbon\Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->start_time);
            if ($reservationDateTime->isPast()) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('移動失敗')
                    ->body('過去の予約の席移動はできません')
                    ->send();
                return;
            }

            $store = Store::find($reservation->store_id);

            // スタッフシフトモードでは使用しない
            if ($store && $store->use_staff_assignment) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('操作不可')
                    ->body('スタッフシフトモードではこの操作は利用できません')
                    ->send();
                return;
            } else {
                // 営業時間ベースモードの重複チェック
                $hasConflict = Reservation::where('store_id', $reservation->store_id)
                    ->whereDate('reservation_date', $reservation->reservation_date)
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->where('id', '!=', $reservation->id)
                    ->where('seat_number', $seatNumber)
                    ->where('is_sub', false)
                    ->where(function($q) use ($reservation) {
                        // 時刻フォーマットを統一して比較
                        $endTime = strlen($reservation->end_time) === 5 ? $reservation->end_time . ':00' : $reservation->end_time;
                        $startTime = strlen($reservation->start_time) === 5 ? $reservation->start_time . ':00' : $reservation->start_time;
                        $q->whereRaw('time(start_time) < time(?)', [$endTime])
                          ->whereRaw('time(end_time) > time(?)', [$startTime]);
                    })
                    ->exists();

                if ($hasConflict) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('移動失敗')
                        ->body('席' . $seatNumber . 'は既に予約が入っています')
                        ->send();
                    return;
                }

                // 予約ブロックのチェック
                $isBlocked = \App\Models\BlockedTimePeriod::where('store_id', $reservation->store_id)
                    ->whereDate('blocked_date', $reservation->reservation_date)
                    ->where(function($q) use ($seatNumber) {
                        $q->where('line_type', 'main')
                          ->where('line_number', $seatNumber);
                    })
                    ->where(function($q) use ($reservation) {
                        $endTime = strlen($reservation->end_time) === 5 ? $reservation->end_time . ':00' : $reservation->end_time;
                        $startTime = strlen($reservation->start_time) === 5 ? $reservation->start_time . ':00' : $reservation->start_time;
                        $q->whereRaw('time(start_time) < time(?)', [$endTime])
                          ->whereRaw('time(end_time) > time(?)', [$startTime]);
                    })
                    ->exists();

                if ($isBlocked) {
                    \Filament\Notifications\Notification::make()
                        ->warning()
                        ->title('移動不可')
                        ->body('席' . $seatNumber . 'は予約ブロックされています')
                        ->send();
                    return;
                }
            }

            // 重複チェックを一時的に無効化して保存
            // 直接DBを更新（モデルイベントを完全にバイパス）
            DB::table('reservations')
                ->where('id', $reservation->id)
                ->update([
                    'is_sub' => false,
                    'seat_number' => $seatNumber,
                    'line_type' => 'main',
                    'line_number' => $seatNumber,
                    'updated_at' => now()
                ]);
            
            $this->loadTimelineData();
            $this->selectedReservation = null;
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('移動完了')
                ->body('席' . $seatNumber . 'に移動しました')
                ->send();
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

        // 予約ブロックのチェック
        $isBlocked = \App\Models\BlockedTimePeriod::where('store_id', $reservation->store_id)
            ->whereDate('blocked_date', $reservation->reservation_date)
            ->where('line_type', 'sub')
            ->where(function($q) use ($reservation) {
                $endTime = strlen($reservation->end_time) === 5 ? $reservation->end_time . ':00' : $reservation->end_time;
                $startTime = strlen($reservation->start_time) === 5 ? $reservation->start_time . ':00' : $reservation->start_time;
                $q->whereRaw('time(start_time) < time(?)', [$endTime])
                  ->whereRaw('time(end_time) > time(?)', [$startTime]);
            })
            ->exists();

        if ($isBlocked) {
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
        \Log::info('🔍 canMoveToMain called', [
            'reservation_id' => $reservationId,
            'target_seat' => $seatNumber
        ]);

        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            \Log::info('❌ canMoveToMain: reservation not found', ['id' => $reservationId]);
            return false;
        }

        \Log::info('📋 Reservation details', [
            'id' => $reservation->id,
            'customer' => $reservation->customer_name,
            'time' => $reservation->start_time . '-' . $reservation->end_time,
            'is_sub' => $reservation->is_sub,
            'current_seat' => $reservation->seat_number,
            'store_id' => $reservation->store_id
        ]);

        // 現在と同じ席番号への移動は不可
        if (!$reservation->is_sub && $reservation->seat_number == $seatNumber) {
            \Log::info('❌ canMoveToMain: same seat', [
                'id' => $reservationId,
                'seat' => $seatNumber
            ]);
            return false;
        }

        // 予約ブロックのチェック
        $isBlocked = \App\Models\BlockedTimePeriod::where('store_id', $reservation->store_id)
            ->whereDate('blocked_date', $reservation->reservation_date)
            ->where(function($q) use ($seatNumber) {
                $q->where('line_type', 'main')
                  ->where('line_number', $seatNumber);
            })
            ->where(function($q) use ($reservation) {
                $endTime = strlen($reservation->end_time) === 5 ? $reservation->end_time . ':00' : $reservation->end_time;
                $startTime = strlen($reservation->start_time) === 5 ? $reservation->start_time . ':00' : $reservation->start_time;
                $q->whereRaw('time(start_time) < time(?)', [$endTime])
                  ->whereRaw('time(end_time) > time(?)', [$startTime]);
            })
            ->exists();

        if ($isBlocked) {
            return false;
        }

        $temp = clone $reservation;
        $temp->is_sub = false;
        $temp->seat_number = $seatNumber;

        \Log::info('🧪 Testing availability', [
            'temp_is_sub' => $temp->is_sub,
            'temp_seat_number' => $temp->seat_number
        ]);

        try {
            $result = Reservation::checkAvailability($temp);
            \Log::info('✅ canMoveToMain result:', [
                'reservation_id' => $reservationId,
                'from' => $reservation->is_sub ? 'sub' : "seat {$reservation->seat_number}",
                'to_seat' => $seatNumber,
                'can_move' => $result
            ]);
            return $result;
        } catch (\Exception $e) {
            \Log::error('❌ canMoveToMain exception:', [
                'reservation_id' => $reservationId,
                'seat' => $seatNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    // 新規予約作成関連のメソッド
    public function openNewReservationModal(): void
    {
        // 全ての関連プロパティを初期化
        $this->showNewReservationModal = true;
        $this->reservationStep = 1; // 必ずステップ1から開始
        $this->phoneSearch = '';
        $this->menuSearch = '';  // メニュー検索をリセット
        $this->searchResults = [];
        $this->selectedCustomer = null;
        $this->noResultsFound = false; // 検索結果フラグもリセット

        // 新規顧客情報を初期化
        $this->newCustomer = [
            'last_name' => '',
            'first_name' => '',
            'last_name_kana' => '',
            'first_name_kana' => '',
            'email' => '',
            'phone' => ''
        ];

        // 予約情報を初期化
        $this->newReservation = [
            'date' => $this->selectedDate,
            'start_time' => '',
            'duration' => 60,
            'menu_id' => '',
            'line_type' => 'main',
            'line_number' => 1,
            'staff_id' => '',
            'notes' => '電話予約',
            'option_menu_ids' => []
        ];

        // JavaScript側のセッションストレージをクリア
        $this->dispatch('clear-reservation-data');

        // モーダルが開いたことをブラウザに通知
        $this->dispatch('modal-opened');

        \Log::info('New reservation modal opened', [
            'step' => $this->reservationStep,
            'customer' => $this->selectedCustomer,
            'search' => $this->phoneSearch
        ]);
    }
    
    public function openNewReservationFromSlot($seatKey, $timeSlot): void
    {
        \Log::info('Slot clicked:', ['seat' => $seatKey, 'time' => $timeSlot]);

        // 🔍 日付ズレ問題の徹底調査ログ
        \Log::info('🚨 [DATE DEBUG] openNewReservationFromSlot called', [
            'selectedDate_before_assignment' => $this->selectedDate,
            'selectedDate_type' => gettype($this->selectedDate),
            'server_timezone' => date_default_timezone_get(),
            'carbon_now' => \Carbon\Carbon::now()->format('Y-m-d H:i:s T')
        ]);

        // 席タイプとライン番号/スタッフIDを解析
        $staffId = '';
        if (strpos($seatKey, 'staff_') === 0) {
            $staffId = intval(substr($seatKey, 6));
            $lineType = 'staff';
            $lineNumber = 1;
        } elseif ($seatKey === 'unassigned') {
            $lineType = 'unassigned';
            $lineNumber = 1;
        } elseif (strpos($seatKey, 'sub_') === 0) {
            $lineType = 'sub';
            $lineNumber = intval(substr($seatKey, 4));
        } else {
            $lineType = 'main';
            $lineNumber = intval(substr($seatKey, 5));
        }

        $this->showNewReservationModal = true;
        $this->modalMode = 'reservation'; // デフォルトは予約モード
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
            'option_menu_ids' => [],
            'line_type' => $lineType,
            'line_number' => $lineNumber,
            'staff_id' => $staffId,
            'notes' => '電話予約'
        ];

        // 🔍 日付ズレ問題の徹底調査ログ
        \Log::info('🚨 [DATE DEBUG] newReservation initialized', [
            'selectedDate' => $this->selectedDate,
            'newReservation_date' => $this->newReservation['date'],
            'are_they_same' => $this->selectedDate === $this->newReservation['date'],
            'selectedDate_type' => gettype($this->selectedDate),
            'newReservation_date_type' => gettype($this->newReservation['date'])
        ]);

        // 予約ブロック設定もリセット
        $this->blockSettings = [
            'date' => $this->selectedDate,
            'start_time' => $timeSlot,
            'end_time' => '',
            'reason' => '休憩',
            'apply_to_all_lines' => false,
            'selected_lines' => [$seatKey]
        ];

        // モーダルが開いたことをブラウザに通知
        $this->dispatch('modal-opened');
    }
    
    public function closeNewReservationModal(): void
    {
        $this->showNewReservationModal = false;
        $this->modalMode = 'reservation'; // モーダルモードをリセット
        $this->reservationStep = 1; // ステップもリセット
        $this->customerSelectionMode = 'existing'; // 顧客選択モードもリセット
        $this->phoneSearch = ''; // 検索もクリア
        $this->searchResults = [];
        $this->selectedCustomer = null;
        $this->noResultsFound = false;
        $this->menuSearch = '';

        // 新規顧客情報もクリア
        $this->newCustomer = [
            'last_name' => '',
            'first_name' => '',
            'last_name_kana' => '',
            'first_name_kana' => '',
            'email' => '',
            'phone' => ''
        ];

        // 予約情報もクリア（日付は保持）
        $this->newReservation = [
            'date' => $this->selectedDate,
            'start_time' => '',
            'duration' => 60,
            'menu_id' => '',
            'line_type' => 'main',
            'line_number' => 1,
            'staff_id' => '',
            'notes' => '電話予約',
            'option_menu_ids' => []
        ];

        // オプション選択情報もクリア
        $this->availableOptions = [];
        $this->selectedOptions = [];

        // JavaScript側のセッションストレージをクリア
        $this->dispatch('clear-reservation-data');

        \Log::info('Reservation modal closed - all data cleared');
    }

    public function createBlockedTime(): void
    {
        // 権限チェック（スタッフは予約ブロックを作成できない）
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'owner', 'manager'])) {
            session()->flash('error', '予約ブロックを設定する権限がありません。');
            return;
        }

        try {
            // バリデーション
            if (empty($this->blockSettings['end_time'])) {
                session()->flash('error', '終了時間を入力してください。');
                return;
            }

            // 終了時間が開始時間より後であることを確認
            if ($this->blockSettings['end_time'] <= $this->blockSettings['start_time']) {
                session()->flash('error', '終了時間は開始時間より後に設定してください。');
                return;
            }

            // seatKeyを解析してline情報を取得
            $lineType = null;
            $lineNumber = null;
            $staffId = null;

            if (!empty($this->blockSettings['selected_lines']) && count($this->blockSettings['selected_lines']) > 0) {
                $seatKey = $this->blockSettings['selected_lines'][0];

                if (strpos($seatKey, 'staff_') === 0) {
                    $lineType = 'staff';
                    $staffId = intval(substr($seatKey, 6));
                    $lineNumber = 1;
                } elseif ($seatKey === 'unassigned') {
                    $lineType = 'unassigned';
                    $lineNumber = 1;
                } elseif (strpos($seatKey, 'sub_') === 0) {
                    $lineType = 'sub';
                    $lineNumber = intval(substr($seatKey, 4));
                } elseif (strpos($seatKey, 'seat_') === 0) {
                    $lineType = 'main';
                    $lineNumber = intval(substr($seatKey, 5));
                }
            }

            // 予約ブロックを作成
            \App\Models\BlockedTimePeriod::create([
                'store_id' => $this->selectedStore,
                'blocked_date' => $this->blockSettings['date'],
                'start_time' => $this->blockSettings['start_time'],
                'end_time' => $this->blockSettings['end_time'],
                'is_all_day' => false,
                'reason' => $this->blockSettings['reason'],
                'is_recurring' => false,
                'line_type' => $lineType,
                'line_number' => $lineNumber,
                'staff_id' => $staffId,
            ]);

            // モーダルを閉じて、データをリロード
            $this->closeNewReservationModal();
            $this->loadTimelineData();

            // 他のユーザーのタイムラインも更新するためのイベントをディスパッチ
            $this->dispatch('timeline-updated', [
                'store_id' => $this->selectedStore,
                'date' => $this->selectedDate
            ]);

            // 成功通知
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('ブロック設定完了')
                ->body('予約ブロックを設定しました')
                ->send();

        } catch (\Exception $e) {
            \Log::error('Failed to create blocked time:', [
                'error' => $e->getMessage(),
                'blockSettings' => $this->blockSettings
            ]);
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('設定失敗')
                ->body('予約ブロックの設定に失敗しました')
                ->send();
        }
    }

    /**
     * BlockedTimePeriodからseatKeyを生成
     */
    private function getSeatKeyFromBlock($blocked): string
    {
        if ($blocked->line_type === 'staff') {
            return 'staff_' . $blocked->staff_id;
        } elseif ($blocked->line_type === 'unassigned') {
            return 'unassigned';
        } elseif ($blocked->line_type === 'sub') {
            return 'sub_' . $blocked->line_number;
        } elseif ($blocked->line_type === 'main') {
            return 'seat_' . $blocked->line_number;
        }
        return '';
    }

    /**
     * ブロック終了時間の選択肢を生成
     */
    public function getBlockEndTimeOptions()
    {
        if (empty($this->blockSettings['start_time']) || empty($this->selectedStore)) {
            return [];
        }

        $store = \App\Models\Store::find($this->selectedStore);
        if (!$store) {
            return [];
        }

        // 店舗の予約間隔を取得（デフォルト30分）
        $interval = $store->reservation_slot_duration ?? 30;

        $options = [];
        $startTime = \Carbon\Carbon::parse($this->blockSettings['start_time']);

        // 開始時間から最大6時間分（または営業終了時刻まで）の選択肢を生成
        for ($i = 1; $i <= 12; $i++) {
            $endTime = $startTime->copy()->addMinutes($interval * $i);

            // 23:59を超えないようにする
            if ($endTime->format('H:i') > '23:59') {
                break;
            }

            $options[] = [
                'value' => $endTime->format('H:i:s'),
                'label' => $endTime->format('H:i') . ' (' . ($interval * $i) . '分間)'
            ];
        }

        return $options;
    }

    /**
     * 顧客選択モードが変更されたときにselectedCustomerをリセット
     */
    public function updatedCustomerSelectionMode($value): void
    {
        // モードを切り替えたら、選択中の顧客をクリア
        $this->selectedCustomer = null;
        $this->searchResults = [];
        $this->phoneSearch = '';

        logger('🔄 Customer selection mode changed', [
            'new_mode' => $value,
            'selectedCustomer_reset' => 'null',
            'searchResults_cleared' => true
        ]);
    }

    public function updatedPhoneSearch(): void
    {
        try {
            logger('🔍 Customer search started', [
                'search_term' => $this->phoneSearch,
                'search_length' => strlen($this->phoneSearch),
                'store_id' => $this->selectedStore
            ]);

            if (strlen($this->phoneSearch) >= 2) {
                // 電話番号、名前、カナで顧客を検索（全ての顧客が対象）
                $search = $this->phoneSearch;
                $storeId = $this->selectedStore;

                // SQLiteとMySQLの互換性対応
                $dbDriver = DB::connection()->getDriverName();
                $concatOperator = $dbDriver === 'sqlite' ? '||' : 'CONCAT';

                // スペースを除去した検索キーワードも用意（フルネーム検索対応）
                $searchNoSpace = str_replace([' ', '　'], '', $search); // 半角・全角スペースを削除

                // 検索結果を取得して、関連度順にソート
                $results = \App\Models\Customer::where(function($query) use ($search, $searchNoSpace, $dbDriver) {
                        $query->where('phone', 'LIKE', '%' . $search . '%')
                              ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                              ->orWhere('first_name', 'LIKE', '%' . $search . '%')
                              ->orWhere('last_name_kana', 'LIKE', '%' . $search . '%')
                              ->orWhere('first_name_kana', 'LIKE', '%' . $search . '%');

                        // フルネーム検索（スペースなし）
                        if ($dbDriver === 'sqlite') {
                            $query->orWhereRaw('(last_name || first_name) LIKE ?', ['%' . $searchNoSpace . '%'])
                                  ->orWhereRaw('(last_name_kana || first_name_kana) LIKE ?', ['%' . $searchNoSpace . '%']);
                        } else {
                            $query->orWhereRaw('CONCAT(last_name, first_name) LIKE ?', ['%' . $searchNoSpace . '%'])
                                  ->orWhereRaw('CONCAT(last_name_kana, first_name_kana) LIKE ?', ['%' . $searchNoSpace . '%']);
                        }

                        // フルネーム検索（スペースあり：半角スペース）
                        if ($dbDriver === 'sqlite') {
                            $query->orWhereRaw('(last_name || " " || first_name) LIKE ?', ['%' . $search . '%'])
                                  ->orWhereRaw('(last_name_kana || " " || first_name_kana) LIKE ?', ['%' . $search . '%']);
                        } else {
                            $query->orWhereRaw('CONCAT(last_name, " ", first_name) LIKE ?', ['%' . $search . '%'])
                                  ->orWhereRaw('CONCAT(last_name_kana, " ", first_name_kana) LIKE ?', ['%' . $search . '%']);
                        }

                        // フルネーム検索（スペースあり：全角スペース）
                        if ($dbDriver === 'sqlite') {
                            $query->orWhereRaw('(last_name || "　" || first_name) LIKE ?', ['%' . $search . '%'])
                                  ->orWhereRaw('(last_name_kana || "　" || first_name_kana) LIKE ?', ['%' . $search . '%']);
                        } else {
                            $query->orWhereRaw('CONCAT(last_name, "　", first_name) LIKE ?', ['%' . $search . '%'])
                                  ->orWhereRaw('CONCAT(last_name_kana, "　", first_name_kana) LIKE ?', ['%' . $search . '%']);
                        }
                    })
                    ->withCount(['reservations' => function($query) use ($storeId) {
                        $query->where('store_id', $storeId);
                    }])
                    ->with(['reservations' => function($query) use ($storeId) {
                        $query->where('store_id', $storeId)
                              ->latest('reservation_date')
                              ->limit(1);
                    }])
                    ->limit(20) // 10件から20件に増やして見つかりやすく
                    ->get()
                    ->map(function($customer) use ($search) {
                        $lastReservation = $customer->reservations->first();
                        $customer->last_visit_date = $lastReservation ? $lastReservation->reservation_date : null;

                        // 関連度スコアを計算（完全一致 > 前方一致 > 部分一致）
                        $score = 0;
                        $searchLower = mb_strtolower($search);
                        $searchNoSpace = str_replace([' ', '　'], '', $searchLower);

                        // 電話番号の完全一致（最優先）
                        if ($customer->phone === $search) {
                            $score += 1000;
                        } elseif (strpos($customer->phone, $search) === 0) {
                            $score += 500; // 前方一致
                        } elseif (strpos($customer->phone, $search) !== false) {
                            $score += 100; // 部分一致
                        }

                        // フルネーム（スペースなし）
                        $fullName = $customer->last_name . $customer->first_name;
                        $fullNameLower = mb_strtolower($fullName);

                        // フルネーム（スペースあり：半角・全角）
                        $fullNameWithSpace = $customer->last_name . ' ' . $customer->first_name;
                        $fullNameWithZenkakuSpace = $customer->last_name . '　' . $customer->first_name;
                        $fullNameWithSpaceLower = mb_strtolower($fullNameWithSpace);
                        $fullNameWithZenkakuSpaceLower = mb_strtolower($fullNameWithZenkakuSpace);

                        // 完全一致チェック（最高点）
                        if ($fullNameLower === $searchNoSpace ||
                            $fullNameWithSpaceLower === $searchLower ||
                            $fullNameWithZenkakuSpaceLower === $searchLower) {
                            $score += 800;
                        }
                        // 前方一致
                        elseif (strpos($fullNameLower, $searchNoSpace) === 0 ||
                                strpos($fullNameWithSpaceLower, $searchLower) === 0 ||
                                strpos($fullNameWithZenkakuSpaceLower, $searchLower) === 0) {
                            $score += 400;
                        }
                        // 部分一致
                        elseif (strpos($fullNameLower, $searchNoSpace) !== false ||
                                strpos($fullNameWithSpaceLower, $searchLower) !== false ||
                                strpos($fullNameWithZenkakuSpaceLower, $searchLower) !== false) {
                            $score += 80;
                        }

                        // 姓名個別の一致
                        if (mb_strtolower($customer->last_name) === $searchNoSpace ||
                            mb_strtolower($customer->first_name) === $searchNoSpace) {
                            $score += 600;
                        }

                        $customer->search_score = $score;
                        return $customer;
                    })
                    ->sortByDesc('search_score') // 関連度順にソート
                    ->values();

                $this->searchResults = $results;

                logger('✅ Customer search completed', [
                    'results_count' => count($this->searchResults)
                ]);
            } else {
                $this->searchResults = [];
                logger('ℹ️ Search term too short, cleared results');
            }
        } catch (\Exception $e) {
            logger('❌ Customer search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'search_term' => $this->phoneSearch,
                'store_id' => $this->selectedStore
            ]);

            $this->searchResults = [];
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('検索エラー')
                ->body('顧客検索中にエラーが発生しました: ' . $e->getMessage())
                ->send();
        }
    }
    
    public function selectCustomer($customerId): void
    {
        $this->selectedCustomer = \App\Models\Customer::find($customerId);
        $this->newReservation['customer_id'] = $customerId; // 顧客IDを設定
        $this->reservationStep = 3; // 予約詳細入力へ

        // ステップ3に移行したことをブラウザに通知
        $this->dispatch('modal-opened');
    }
    
    public function startNewCustomerRegistration(): void
    {
        logger('🆕 Starting new customer registration', [
            'phoneSearch' => $this->phoneSearch,
            'newCustomer_phone_before' => $this->newCustomer['phone'] ?? null,
            'selectedCustomer_before' => $this->selectedCustomer ? [
                'id' => $this->selectedCustomer->id,
                'name' => $this->selectedCustomer->last_name . ' ' . $this->selectedCustomer->first_name,
                'phone' => $this->selectedCustomer->phone
            ] : null
        ]);

        // 検索フィールドの値を初期値として設定するが、電話番号形式の場合のみ
        // 名前検索の場合は電話番号フィールドに入れない
        if (empty($this->newCustomer['phone'])) {
            // 電話番号形式（数字のみ、または数字とハイフン）の場合のみコピー
            if (preg_match('/^[0-9\-]+$/', $this->phoneSearch)) {
                $this->newCustomer['phone'] = $this->phoneSearch;
                logger('📞 Phone copied from search', ['phone' => $this->phoneSearch]);
            } else {
                logger('⚠️ Phone NOT copied (not a phone number format)', ['search' => $this->phoneSearch]);
            }
            // それ以外（名前検索など）の場合は電話番号を空のままにする
        }
        $this->reservationStep = 2; // 新規顧客登録へ
    }
    
    public function createNewCustomer(): void
    {
        // デバッグログ - 開始時点の状態を記録
        logger('🆕 Creating new customer - START', [
            'newCustomer' => $this->newCustomer,
            'phoneSearch' => $this->phoneSearch,
            'selectedCustomer_before' => $this->selectedCustomer ? [
                'id' => $this->selectedCustomer->id,
                'name' => $this->selectedCustomer->last_name . ' ' . $this->selectedCustomer->first_name,
                'phone' => $this->selectedCustomer->phone
            ] : null,
            'reservationStep' => $this->reservationStep,
            'customerSelectionMode' => $this->customerSelectionMode
        ]);

        // バリデーション
        if (empty($this->newCustomer['last_name']) || empty($this->newCustomer['first_name'])) {
            Notification::make()
                ->danger()
                ->title('入力エラー')
                ->body('姓名は必須です')
                ->send();
            return;
        }

        if (empty($this->newCustomer['phone'])) {
            Notification::make()
                ->danger()
                ->title('入力エラー')
                ->body('電話番号は必須です')
                ->send();
            return;
        }

        // 電話番号の重複チェック（完全一致のみ）
        $phoneToCheck = trim($this->newCustomer['phone']);
        $existingCustomer = \App\Models\Customer::where('phone', $phoneToCheck)->first();

        if ($existingCustomer) {
            // 入力された名前と既存顧客の名前を比較
            $inputName = trim($this->newCustomer['last_name']) . trim($this->newCustomer['first_name']);
            $existingName = $existingCustomer->last_name . $existingCustomer->first_name;

            if ($inputName === $existingName) {
                // 名前も一致 → そのまま既存顧客で進む
                logger('✅ Customer already exists with matching name', [
                    'phone' => $phoneToCheck,
                    'existing_customer' => $existingCustomer->id,
                    'name' => $existingName
                ]);

                $this->selectedCustomer = $existingCustomer;
                $this->reservationStep = 3;

                // ステップ3に移行したことをブラウザに通知
                $this->dispatch('modal-opened');

                Notification::make()
                    ->info()
                    ->title('既存のお客様でした')
                    ->body('この電話番号は既に登録されています: ' . $existingCustomer->last_name . ' ' . $existingCustomer->first_name . '様')
                    ->send();
                return;
            } else {
                // 名前が異なる → 確認画面を表示
                logger('⚠️ Customer exists but name is different', [
                    'phone' => $phoneToCheck,
                    'existing_customer' => $existingCustomer->id,
                    'existing_name' => $existingName,
                    'input_name' => $inputName
                ]);

                $this->conflictingCustomer = $existingCustomer;
                $this->showCustomerConflictConfirmation = true;

                Notification::make()
                    ->warning()
                    ->title('電話番号の重複')
                    ->body('入力された電話番号は既に別の名前で登録されています。確認してください。')
                    ->send();
                return;
            }
        }
        
        // 新規顧客を作成（重複チェック強化）
        try {
            $customer = \App\Models\Customer::create([
                'last_name' => $this->newCustomer['last_name'],
                'first_name' => $this->newCustomer['first_name'],
                'last_name_kana' => '',  // カナは空で設定
                'first_name_kana' => '', // カナは空で設定
                'email' => !empty($this->newCustomer['email']) ? $this->newCustomer['email'] : null,
                'phone' => $this->newCustomer['phone'],
                'store_id' => $this->selectedStore, // 予約店舗を顧客の所属店舗として設定
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // メールアドレス重複の場合、確認画面を表示（空文字列の場合はスキップ）
            if (!empty($this->newCustomer['email'])) {
                $existingCustomer = \App\Models\Customer::where('email', $this->newCustomer['email'])->first();
                if ($existingCustomer) {
                    logger('⚠️ Email duplicate detected', [
                        'email' => $this->newCustomer['email'],
                        'existing_customer' => $existingCustomer->id,
                        'existing_name' => $existingCustomer->last_name . ' ' . $existingCustomer->first_name,
                        'input_name' => $this->newCustomer['last_name'] . ' ' . $this->newCustomer['first_name']
                    ]);

                    // 電話番号重複と同じように確認画面を表示
                    $this->conflictingCustomer = $existingCustomer;
                    $this->showCustomerConflictConfirmation = true;

                    Notification::make()
                        ->warning()
                        ->title('メールアドレスの重複')
                        ->body('入力されたメールアドレスは既に登録されています: ' . $existingCustomer->last_name . ' ' . $existingCustomer->first_name . '様')
                        ->send();
                    return;
                }
            }

            // 空emailでの重複エラーの場合はログに記録して再throw
            logger('⚠️ Email constraint violation with empty email', [
                'email' => $this->newCustomer['email'],
                'customer_name' => $this->newCustomer['last_name'] . ' ' . $this->newCustomer['first_name']
            ]);
            throw $e;
        }
        
        $this->selectedCustomer = $customer;
        $this->reservationStep = 3; // 予約詳細入力へ

        // デバッグログ - 完了時点の状態を記録
        logger('✅ Creating new customer - SUCCESS', [
            'created_customer' => [
                'id' => $customer->id,
                'name' => $customer->last_name . ' ' . $customer->first_name,
                'phone' => $customer->phone,
                'email' => $customer->email
            ],
            'selectedCustomer_after' => [
                'id' => $this->selectedCustomer->id,
                'name' => $this->selectedCustomer->last_name . ' ' . $this->selectedCustomer->first_name,
                'phone' => $this->selectedCustomer->phone
            ],
            'match' => $customer->id === $this->selectedCustomer->id
        ]);

        // ステップ3に移行したことをブラウザに通知
        $this->dispatch('modal-opened');

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('登録完了')
            ->body('新規顧客を登録しました')
            ->send();
    }

    /**
     * 既存顧客で予約を続ける（確認画面から）
     */
    public function confirmUseExistingCustomer(): void
    {
        if (!$this->conflictingCustomer) {
            Notification::make()
                ->danger()
                ->title('エラー')
                ->body('既存顧客情報が見つかりません')
                ->send();
            return;
        }

        logger('✅ User confirmed to use existing customer - BEFORE', [
            'conflicting_customer' => [
                'id' => $this->conflictingCustomer->id,
                'name' => $this->conflictingCustomer->last_name . ' ' . $this->conflictingCustomer->first_name,
                'phone' => $this->conflictingCustomer->phone,
                'email' => $this->conflictingCustomer->email
            ],
            'input_data' => [
                'name' => $this->newCustomer['last_name'] . ' ' . $this->newCustomer['first_name'],
                'phone' => $this->newCustomer['phone'],
                'email' => $this->newCustomer['email']
            ]
        ]);

        $this->selectedCustomer = $this->conflictingCustomer;
        $this->newReservation['customer_id'] = $this->conflictingCustomer->id; // 顧客IDを設定
        $this->reservationStep = 3;
        $this->showCustomerConflictConfirmation = false;
        $this->conflictingCustomer = null;

        // CRITICAL: 選択した顧客の情報が変わっていないか確認
        logger('✅ User confirmed to use existing customer - AFTER', [
            'selectedCustomer' => [
                'id' => $this->selectedCustomer->id,
                'name' => $this->selectedCustomer->last_name . ' ' . $this->selectedCustomer->first_name,
                'phone' => $this->selectedCustomer->phone,
                'email' => $this->selectedCustomer->email
            ]
        ]);

        // ステップ3に移行したことをブラウザに通知
        $this->dispatch('modal-opened');

        Notification::make()
            ->success()
            ->title('既存顧客で予約を作成します')
            ->body($this->selectedCustomer->last_name . ' ' . $this->selectedCustomer->first_name . '様の予約を作成します')
            ->send();
    }

    /**
     * 確認をキャンセルして入力画面に戻る
     */
    public function cancelCustomerConflict(): void
    {
        logger('ℹ️ User cancelled customer conflict confirmation');

        $this->showCustomerConflictConfirmation = false;
        $this->conflictingCustomer = null;

        Notification::make()
            ->info()
            ->title('キャンセルしました')
            ->body('電話番号または名前を修正してください')
            ->send();
    }

    public function createReservation(): void
    {
        try {
            // 🔍 日付ズレ問題の徹底調査ログ
            logger('🚨 [DATE DEBUG] createReservation called', [
                'raw_date_value' => $this->newReservation['date'] ?? null,
                'date_type' => gettype($this->newReservation['date'] ?? null),
                'date_is_carbon' => ($this->newReservation['date'] ?? null) instanceof \Carbon\Carbon,
                'selectedDate_widget' => $this->selectedDate,
                'selectedDate_type' => gettype($this->selectedDate),
                'server_timezone' => date_default_timezone_get(),
                'carbon_now' => \Carbon\Carbon::now()->format('Y-m-d H:i:s T'),
                'php_date' => date('Y-m-d H:i:s T'),
                'selectedCustomer' => $this->selectedCustomer ? $this->selectedCustomer->id : null,
                'menu_id' => $this->newReservation['menu_id'] ?? null,
                'start_time' => $this->newReservation['start_time'] ?? null,
                'newReservation_full' => $this->newReservation
            ]);

            // バリデーション
            if (!$this->selectedCustomer || empty($this->newReservation['menu_id'])) {
                logger('Validation failed', [
                    'has_customer' => (bool)$this->selectedCustomer,
                    'has_menu_id' => !empty($this->newReservation['menu_id'])
                ]);

                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('入力エラー')
                    ->body('顧客とメニューを選択してください')
                    ->persistent()
                    ->send();
                return;
            }

            // 過去の日時チェック（現在時刻から30分前まで許可）
            // 日付を明示的にY-m-d形式で正規化（JSTタイムゾーン統一）
            $dateString = $this->newReservation['date'];
            if ($dateString instanceof \Carbon\Carbon) {
                $dateString = $dateString->format('Y-m-d');
            }
            // 日付をJSTで正規化してログ出力
            $normalizedDate = \Carbon\Carbon::createFromFormat('Y-m-d', $dateString, 'Asia/Tokyo')->format('Y-m-d');
            \Log::info('📅 予約日時正規化', [
                'original' => $this->newReservation['date'],
                'normalized' => $normalizedDate,
                'timezone' => 'Asia/Tokyo'
            ]);

            $reservationDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $normalizedDate . ' ' . $this->newReservation['start_time'], 'Asia/Tokyo');
            $minimumTime = \Carbon\Carbon::now('Asia/Tokyo')->subMinutes(30);
            if ($reservationDateTime->lt($minimumTime)) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('予約作成失敗')
                    ->body('過去の時間には予約できません')
                    ->persistent()
                    ->send();
                return;
            }

            // メニュー情報を取得
            $menu = \App\Models\Menu::find($this->newReservation['menu_id']);
            if (!$menu) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('メニューエラー')
                    ->body('選択されたメニューが見つかりません')
                    ->persistent()
                    ->send();
                return;
            }

            // 終了時刻を計算（メニュー + オプションの合計時間）
            $startTime = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $this->newReservation['start_time']);
            $totalDuration = $menu->duration_minutes ?? $this->newReservation['duration'];

            // オプションの所要時間を加算
            if (!empty($this->newReservation['option_menu_ids'])) {
                $optionsDuration = \App\Models\MenuOption::whereIn('id', $this->newReservation['option_menu_ids'])
                    ->sum('duration_minutes');
                $totalDuration += $optionsDuration;
            }

            $endTime = $startTime->copy()->addMinutes($totalDuration);

            // 店舗情報取得
            $store = \App\Models\Store::find($this->selectedStore);

            // 予約ブロックチェック
            $blockedPeriods = \App\Models\BlockedTimePeriod::where('store_id', $this->selectedStore)
                ->whereDate('blocked_date', $this->newReservation['date'])
                ->get();

            foreach ($blockedPeriods as $block) {
                $blockStart = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $block->start_time);
                $blockEnd = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $block->end_time);

                $isOverlapping = (
                    ($startTime->gte($blockStart) && $startTime->lt($blockEnd)) ||
                    ($endTime->gt($blockStart) && $endTime->lte($blockEnd)) ||
                    ($startTime->lte($blockStart) && $endTime->gte($blockEnd))
                );

                if ($isOverlapping) {
                    // 全体ブロック
                    if ($block->line_type === null) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('予約作成失敗')
                            ->body('選択された時間帯は予約ブロックされています')
                            ->persistent()
                            ->send();
                        return;
                    }

                    // スタッフラインブロック
                    if ($block->line_type === 'staff' && isset($this->newReservation['staff_id']) && $block->staff_id == $this->newReservation['staff_id']) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('予約作成失敗')
                            ->body('選択されたスタッフは指定の時間帯がブロックされています')
                            ->persistent()
                            ->send();
                        return;
                    }

                    // メインラインブロック（サブ枠への予約の場合はチェックしない）
                    if ($block->line_type === 'main' && !$store->use_staff_assignment && $this->newReservation['line_type'] !== 'sub') {
                        $blockedMainLinesCount = $blockedPeriods->filter(function($b) use ($startTime, $endTime) {
                            if ($b->line_type !== 'main') return false;
                            $bStart = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $b->start_time);
                            $bEnd = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $b->end_time);
                            return (
                                ($startTime->gte($bStart) && $startTime->lt($bEnd)) ||
                                ($endTime->gt($bStart) && $endTime->lte($bEnd)) ||
                                ($startTime->lte($bStart) && $endTime->gte($bEnd))
                            );
                        })->count();

                        $mainLinesCount = $store->main_lines_count ?? 1;
                        if ($blockedMainLinesCount >= $mainLinesCount) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('予約作成失敗')
                                ->body('選択された時間帯は満席です')
                                ->persistent()
                                ->send();
                            return;
                        }
                    }

                    // サブラインブロック（サブ枠への予約の場合のみチェック）
                    if ($block->line_type === 'sub' && $this->newReservation['line_type'] === 'sub') {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('予約作成失敗')
                            ->body('サブ枠は予約ブロックされています')
                            ->persistent()
                            ->send();
                        return;
                    }
                }
            }

            // スタッフシフトモードの場合、スタッフ可用性をチェック
            if ($store && $store->use_staff_assignment) {
                // 予約可能性をチェック
                $availabilityResult = $this->canReserveAtTimeSlot(
                    $this->newReservation['start_time'],
                    $endTime->format('H:i'),
                    $store,
                    \Carbon\Carbon::parse($this->newReservation['date'])
                );

                if (!$availabilityResult['can_reserve']) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('予約作成失敗')
                        ->body($availabilityResult['reason'] ?: 'この時間帯は予約できません')
                        ->persistent()
                        ->send();
                    return;
                }
            } else {
                // 営業時間ベースモードの場合、営業時間チェック（終了時刻ベース）
                $dayOfWeek = strtolower($startTime->format('l')); // 小文字に変換
                $closingTime = '22:00'; // デフォルトを22:00に変更

                // 曜日別営業時間があるか確認（配列形式）
                if ($store && is_array($store->business_hours)) {
                    foreach ($store->business_hours as $schedule) {
                        if (isset($schedule['day']) && strtolower($schedule['day']) === $dayOfWeek) {
                            $closingTime = substr($schedule['close_time'] ?? $schedule['close'] ?? '22:00', 0, 5);
                            break;
                        }
                    }
                } elseif ($store && isset($store->business_hours['close'])) {
                    // オブジェクト形式の場合
                    $closingTime = $store->business_hours['close'];
                }

                $closingDateTime = \Carbon\Carbon::parse($this->newReservation['date'] . ' ' . $closingTime);

                logger('Business hours check', [
                    'start_time' => $startTime->format('H:i'),
                    'end_time' => $endTime->format('H:i'),
                    'closing_time' => $closingTime,
                    'closing_datetime' => $closingDateTime->format('Y-m-d H:i'),
                    'endTime_gt_closingTime' => $endTime->gt($closingDateTime)
                ]);

                // 終了時刻が営業時間を超える場合はエラー
                if ($endTime->gt($closingDateTime)) {
                    logger('Business hours exceeded', [
                        'end_time' => $endTime->format('H:i'),
                        'closing_time' => $closingTime
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('営業時間外')
                        ->body('予約終了時刻（' . $endTime->format('H:i') . '）が営業時間（' . $closingTime . '）を超えています')
                        ->persistent()
                        ->send();
                    return;
                }

                // 営業時間ベースモードの場合、既存予約との重複をチェック
                $lineType = $this->newReservation['line_type'] ?? 'main';
                $lineNumber = $this->newReservation['line_number'] ?? 1;

                // 同じライン（席）の既存予約を取得
                $conflictingReservations = \App\Models\Reservation::where('store_id', $this->selectedStore)
                    ->whereDate('reservation_date', $this->newReservation['date'])
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->where(function ($q) use ($lineType, $lineNumber) {
                        if ($lineType === 'sub') {
                            // サブラインの場合は全てのサブ予約をチェック
                            $q->where('line_type', 'sub')->orWhere('is_sub', true);
                        } else {
                            // メインラインの場合は、同じ席番号の予約のみをチェック
                            $q->where(function($q2) use ($lineNumber) {
                                $q2->where(function($q3) use ($lineNumber) {
                                    // line_typeがmainで、同じline_numberの予約
                                    $q3->where('line_type', 'main')
                                       ->where('line_number', $lineNumber);
                                })
                                ->orWhere(function($q4) use ($lineNumber) {
                                    // 旧式：line_typeがnullで、同じseat_numberの予約
                                    $q4->whereNull('line_type')
                                       ->where('is_sub', false)
                                       ->where('seat_number', $lineNumber);
                                });
                            });
                        }
                    })
                    ->where(function ($q) use ($startTime, $endTime) {
                        // 時間重複チェック（境界を含まない: 14:30-15:30と15:30-17:00は重複しない）
                        // time()関数で時刻フォーマットを統一（'15:30:00' と '15:30' の比較を正しく処理）
                        $q->whereRaw('time(start_time) < time(?)', [$endTime->format('H:i:s')])
                          ->whereRaw('time(end_time) > time(?)', [$startTime->format('H:i:s')]);
                    })
                    ->get();

                // デバッグログ
                logger('Conflict check for reservation creation', [
                    'line_type' => $lineType,
                    'line_number' => $lineNumber,
                    'start_time' => $startTime->format('H:i'),
                    'end_time' => $endTime->format('H:i'),
                    'conflicting_count' => $conflictingReservations->count(),
                    'conflicting_reservations' => $conflictingReservations->map(function($r) {
                        return [
                            'id' => $r->id,
                            'time' => $r->start_time . '-' . $r->end_time,
                            'seat_number' => $r->seat_number,
                            'line_number' => $r->line_number,
                            'line_type' => $r->line_type
                        ];
                    })->toArray()
                ]);

                if ($conflictingReservations->count() > 0) {
                    $conflictDetails = $conflictingReservations->map(function($r) {
                        return $r->customer->last_name . ' ' . $r->customer->first_name . '様 ' .
                               $r->start_time . '-' . $r->end_time;
                    })->implode('、');

                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('予約が重複しています')
                        ->body("選択された時間帯には既に予約があります：\n{$conflictDetails}\n\n別の時間帯を選択してください。")
                        ->persistent()
                        ->send();
                    return;
                }

                // 容量チェック（席番号も渡す）
                $seatNumber = null;
                if ($lineType === 'main' && isset($this->newReservation['line_number'])) {
                    $seatNumber = $this->newReservation['line_number'];
                }
                
                $availabilityCheck = $this->canReserveAtTimeSlot(
                    $this->newReservation['start_time'],
                    $endTime->format('H:i'),
                    $store,
                    \Carbon\Carbon::parse($this->newReservation['date']),
                    $lineType,
                    $seatNumber
                );

                if (!$availabilityCheck['can_reserve']) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('予約枠が満席です')
                        ->body($availabilityCheck['reason'] ?: 'この時間帯は予約枠が満席です。別の時間帯を選択してください。')
                        ->persistent()
                        ->send();
                    return;
                }
            }

            // 予約番号を生成
            $reservationNumber = 'R' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // スタッフシフトモードかどうか確認（既に取得済みの$storeを使用）
            $useStaffAssignment = $store->use_staff_assignment ?? false;

            // 日付は上で正規化済みの$normalizedDateを使用（L2128で定義済み）
            $reservationDate = $normalizedDate;

            // 予約作成時の顧客情報をログに記録
            logger('Creating reservation with customer', [
                'selectedCustomer' => [
                    'id' => $this->selectedCustomer->id,
                    'name' => $this->selectedCustomer->last_name . ' ' . $this->selectedCustomer->first_name,
                    'phone' => $this->selectedCustomer->phone,
                    'email' => $this->selectedCustomer->email
                ],
                'original_date_value' => $this->newReservation['date'],
                'date_type' => gettype($this->newReservation['date']),
                'normalized_date' => $reservationDate,
                'start_time' => $this->newReservation['start_time'],
                'menu_id' => $this->newReservation['menu_id']
            ]);

            // 予約データを準備
            $reservationData = [
                'reservation_number' => $reservationNumber,
                'store_id' => $this->selectedStore,
                'customer_id' => $this->selectedCustomer->id,
                'menu_id' => $this->newReservation['menu_id'],
                'reservation_date' => $reservationDate,
                'start_time' => $this->newReservation['start_time'],
                'end_time' => $endTime->format('H:i'),
                'guest_count' => 1,
                'status' => 'booked',
                'source' => 'admin',
                'notes' => $this->newReservation['notes'],
                'total_amount' => $menu->price ?? 0,
                'deposit_amount' => 0,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
            ];

            // 回数券IDがある場合は設定
            if (!empty($this->newReservation['customer_ticket_id'])) {
                $reservationData['customer_ticket_id'] = $this->newReservation['customer_ticket_id'];
            }

            // サブスクリプションIDがある場合は設定（手動選択が優先）
            if (!empty($this->newReservation['customer_subscription_id'])) {
                $reservationData['customer_subscription_id'] = $this->newReservation['customer_subscription_id'];
            }

            // 手動選択がない場合は自動判定サービスを使用
            if (!isset($reservationData['customer_subscription_id'])) {
                $binder = app(\App\Services\ReservationSubscriptionBinder::class);
                $reservationData = $binder->bind($reservationData, 'create');
            }

            // スタッフシフトモードの場合
            if ($useStaffAssignment) {
                $rawStaffId = $this->newReservation['staff_id'] ?? '';

                // より厳密な null 判定
                $staffId = null;
                if ($rawStaffId !== '' && $rawStaffId !== null && $rawStaffId !== '0' && trim((string)$rawStaffId) !== '') {
                    $staffId = is_numeric($rawStaffId) ? (int)$rawStaffId : $rawStaffId;
                }

                $reservationData['staff_id'] = $staffId;

                \Log::info('Staff assignment debug:', [
                    'raw_staff_id' => $rawStaffId,
                    'raw_type' => gettype($rawStaffId),
                    'processed_staff_id' => $staffId,
                    'is_empty' => empty($rawStaffId),
                    'is_null_or_empty_string' => in_array($rawStaffId, [null, '', '0'], true)
                ]);

                // スタッフシフトモードではline_typeとseat_numberは設定しない
            } else {
                // 営業時間ベースモードの場合
                $reservationData['line_type'] = $this->newReservation['line_type'];
                if ($this->newReservation['line_type'] === 'main') {
                    $reservationData['seat_number'] = $this->newReservation['line_number'];
                    $reservationData['line_number'] = $this->newReservation['line_number'];
                    $reservationData['is_sub'] = false;
                } elseif ($this->newReservation['line_type'] === 'sub') {
                    $reservationData['is_sub'] = true;
                    $reservationData['line_number'] = 1;
                }
            }

            // 予約を作成
            $reservation = Reservation::create($reservationData);

            // 管理者通知イベントをディスパッチ
            \App\Events\ReservationCreated::dispatch($reservation);

            // オプションメニューを追加
            if (!empty($this->newReservation['option_menu_ids'])) {
                foreach ($this->newReservation['option_menu_ids'] as $optionId) {
                    $optionMenu = \App\Models\Menu::find($optionId);
                    if ($optionMenu) {
                        $reservation->optionMenus()->attach($optionId, [
                            'price' => $optionMenu->price,
                            'duration' => $optionMenu->duration_minutes ?? 0
                        ]);
                    }
                }

                \Log::info('Options attached to reservation', [
                    'reservation_id' => $reservation->id,
                    'option_ids' => $this->newReservation['option_menu_ids']
                ]);
            }

            // モーダルを閉じる
            $this->closeNewReservationModal();

            // タイムラインを更新
            $this->loadTimelineData();

            // 他のユーザーのタイムラインも更新
            $this->dispatch('timeline-updated', [
                'store_id' => $this->selectedStore,
                'date' => $this->selectedDate
            ]);

            // 成功通知（オプション数を含める）
            $optionCount = count($this->newReservation['option_menu_ids']);
            $message = '予約番号: ' . $reservationNumber;
            if ($optionCount > 0) {
                $message .= '、オプション' . $optionCount . '件追加';
            }

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('予約作成完了')
                ->body($message)
                ->send();
        } catch (\Illuminate\Database\QueryException $e) {
            // データベースエラー（重複など）
            logger()->error('Reservation creation database error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'reservation_data' => $reservationData ?? null,
                'customer_id' => $this->selectedCustomer->id ?? null,
                'time' => $this->newReservation['start_time'] ?? null
            ]);

            // SQLSTATEコードで重複エラーを判定
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                Notification::make()
                    ->danger()
                    ->title('予約作成エラー')
                    ->body('この時間帯は既に予約が入っています。別の時間帯を選択してください。')
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title('予約作成エラー')
                    ->body('予約の作成中にエラーが発生しました。時間をおいて再度お試しください。')
                    ->send();
            }
        } catch (\Exception $e) {
            // その他のエラー
            logger()->error('Reservation creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_id' => $this->selectedCustomer->id ?? null,
                'reservation_data' => $reservationData ?? null
            ]);

            Notification::make()
                ->danger()
                ->title('予約作成エラー')
                ->body('予約の作成に失敗しました: ' . $e->getMessage())
                ->send();
        }
    }

    public function getFilteredMenus()
    {
        $query = \App\Models\Menu::where('is_available', true)
            ->where('is_option', false) // オプションメニューを除外
            ->where('show_in_upsell', false); // 追加オプションとして提案するメニューを除外

        // 選択された店舗のメニューのみを表示
        if ($this->selectedStore) {
            $query->where('store_id', $this->selectedStore);

            \Log::info('Filtering menus by store', [
                'store_id' => $this->selectedStore,
                'search_term' => $this->menuSearch
            ]);
        }

        if (!empty($this->menuSearch)) {
            $search = $this->menuSearch;
            $query->where('name', 'like', '%' . $search . '%');
        }

        $menus = $query->orderBy('is_subscription', 'desc')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 既存顧客が選択されている場合、優先順位を調整
        if ($this->selectedCustomer) {
            $customerId = is_object($this->selectedCustomer) ? $this->selectedCustomer->id : $this->selectedCustomer;

            // 契約中のサブスクメニューIDを取得
            $activeSubscriptionMenuIds = \App\Models\CustomerSubscription::where('customer_id', $customerId)
                ->where('store_id', $this->selectedStore)
                ->where('status', 'active')
                ->pluck('menu_id')
                ->toArray();

            // 過去に使用したメニューIDを取得（最新5件）
            $pastMenuIds = \App\Models\Reservation::where('customer_id', $customerId)
                ->where('store_id', $this->selectedStore)
                ->whereNotNull('menu_id')
                ->orderBy('reservation_date', 'desc')
                ->limit(5)
                ->pluck('menu_id')
                ->unique()
                ->toArray();

            // 優先メニューIDのリスト（契約中のサブスク > 過去利用）
            $priorityMenuIds = array_unique(array_merge($activeSubscriptionMenuIds, $pastMenuIds));

            // メニューを並び替え
            $menus = $menus->sortBy(function($menu) use ($priorityMenuIds, $activeSubscriptionMenuIds) {
                // 契約中のサブスクメニューは最優先（0）
                if (in_array($menu->id, $activeSubscriptionMenuIds)) {
                    return 0;
                }
                // 過去利用メニューは次（1）
                if (in_array($menu->id, $priorityMenuIds)) {
                    return 1;
                }
                // その他は通常順（2）
                return 2;
            })->values();

            \Log::info('Menus prioritized for customer', [
                'customer_id' => $customerId,
                'active_subscription_menus' => $activeSubscriptionMenuIds,
                'past_menus' => $pastMenuIds,
                'sorted_menu_names' => $menus->pluck('name')->toArray()
            ]);
        }

        \Log::info('Filtered menus result', [
            'store_id' => $this->selectedStore,
            'menu_count' => $menus->count(),
            'menu_names' => $menus->pluck('name')->toArray()
        ]);

        return $menus;
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

        // サブスクメニューの場合、顧客のアクティブなサブスクリプションIDを自動設定
        if ($menu && $menu->is_subscription && $this->selectedCustomer) {
            $activeSubscription = \App\Models\CustomerSubscription::where('customer_id', $this->selectedCustomer->id)
                ->where('menu_id', $menuId)
                ->where('status', 'active')
                ->where('is_paused', false)
                ->first();

            if ($activeSubscription) {
                $this->newReservation['customer_subscription_id'] = $activeSubscription->id;
                \Log::info('Auto-set subscription ID', [
                    'subscription_id' => $activeSubscription->id,
                    'menu_id' => $menuId,
                    'customer_id' => $this->selectedCustomer->id
                ]);
            }
        }

        // 回数券メニューの場合、顧客のアクティブな回数券IDを自動設定
        if ($menu && !$menu->is_subscription && $this->selectedCustomer) {
            $activeTicket = \App\Models\CustomerTicket::where('customer_id', $this->selectedCustomer->id)
                ->where('status', 'active')
                ->where('remaining_count', '>', 0)
                ->whereHas('ticketPlan', function($q) use ($menuId) {
                    $q->where('menu_id', $menuId);
                })
                ->first();

            if ($activeTicket) {
                $this->newReservation['customer_ticket_id'] = $activeTicket->id;
                \Log::info('Auto-set ticket ID', [
                    'ticket_id' => $activeTicket->id,
                    'menu_id' => $menuId,
                    'customer_id' => $this->selectedCustomer->id
                ]);
            }
        }

        // オプションメニューを読み込む
        $this->loadAvailableOptions($menuId);

        // 検索フィールドをクリア & ドロップダウンを閉じる
        $this->menuSearch = '';
        $this->showAllMenus = false;
    }

    /**
     * メニュー選択時の処理（Livewireフック）
     * 空き判定を動的に更新するため、選択メニューの所要時間を保持
     */
    public function updatedNewReservationMenuId($value)
    {
        if (!$value) {
            $this->selectedMenuDuration = null;
            $this->selectedOptionsDuration = null;
            return;
        }

        $menu = \App\Models\Menu::find($value);
        if ($menu) {
            $this->selectedMenuDuration = $menu->duration_minutes;
            \Log::info('📋 メニュー選択: 所要時間設定', [
                'menu_id' => $value,
                'menu_name' => $menu->name,
                'duration' => $this->selectedMenuDuration
            ]);

            // 空き判定を再計算（フロントエンドに通知）
            $this->dispatch('refresh-slot-availability');
        }
    }

    /**
     * オプションメニュー選択時の処理（Livewireフック）
     */
    public function updatedNewReservationOptionMenuIds($value)
    {
        if (!$value || !is_array($value)) {
            $this->selectedOptionsDuration = 0;
            return;
        }

        $optionsDuration = \App\Models\MenuOption::whereIn('id', $value)
            ->sum('duration_minutes');

        $this->selectedOptionsDuration = $optionsDuration;

        \Log::info('📋 オプション選択: 所要時間更新', [
            'option_ids' => $value,
            'total_options_duration' => $optionsDuration,
            'menu_duration' => $this->selectedMenuDuration,
            'combined_duration' => ($this->selectedMenuDuration ?? 0) + $optionsDuration
        ]);

        // 空き判定を再計算
        $this->dispatch('refresh-slot-availability');
    }

    /**
     * 選択可能なオプションメニューを読み込む
     */
    public function loadAvailableOptions($menuId)
    {
        try {
            // 選択されたメニューを取得
            $mainMenu = \App\Models\Menu::find($menuId);
            if (!$mainMenu) {
                $this->availableOptions = [];
                return;
            }

            // 店舗の全メニューから条件に合うものをオプションとして表示
            // show_in_upsell=trueのメニュー（アップセル用メニュー = オプション）
            // または is_option=true のメニュー
            // サブスクメニューは除外
            $this->availableOptions = \App\Models\Menu::where('is_available', true)
                ->where('store_id', $mainMenu->store_id)
                ->where('id', '!=', $menuId)
                ->where('is_subscription', false) // サブスクメニューを除外
                ->where(function($q) {
                    $q->where('show_in_upsell', true)  // アップセル用メニュー = オプション
                      ->orWhere('is_option', true);    // または明示的にオプション設定されたもの
                })
                ->orderBy('price')
                ->get()
                ->toArray();

            \Log::info('Loaded available options', [
                'menu_id' => $menuId,
                'options_count' => count($this->availableOptions),
                'option_names' => array_column($this->availableOptions, 'name')
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to load available options', [
                'menu_id' => $menuId,
                'error' => $e->getMessage()
            ]);
            $this->availableOptions = [];
        }
    }

    /**
     * オプションメニューを追加
     */
    public function addOption($optionId)
    {
        // 既に追加されているかチェック
        if (!in_array($optionId, $this->newReservation['option_menu_ids'])) {
            $this->newReservation['option_menu_ids'][] = $optionId;

            // 選択されたオプションの詳細を取得して保持
            $option = \App\Models\Menu::find($optionId);
            if ($option) {
                $this->selectedOptions[$optionId] = [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price' => $option->price,
                    'duration_minutes' => $option->duration_minutes ?? 0
                ];
            }

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('追加完了')
                ->body('オプションを追加しました')
                ->send();
        }
    }

    /**
     * オプションメニューを削除
     */
    public function removeOption($optionId)
    {
        $this->newReservation['option_menu_ids'] = array_values(
            array_filter($this->newReservation['option_menu_ids'], function($id) use ($optionId) {
                return $id != $optionId;
            })
        );

        unset($this->selectedOptions[$optionId]);

        \Filament\Notifications\Notification::make()
            ->info()
            ->title('削除完了')
            ->body('オプションを削除しました')
            ->send();
    }

    /**
     * オプションの合計金額を計算
     */
    public function getOptionsTotalPrice()
    {
        return collect($this->selectedOptions)->sum('price');
    }

    /**
     * オプションの合計時間を計算
     */
    public function getOptionsTotalDuration()
    {
        return collect($this->selectedOptions)->sum('duration_minutes');
    }

    /**
     * 新規予約作成時に利用可能なスタッフ一覧を取得
     */
    public function getAvailableStaff()
    {
        if (!$this->selectedStore || !$this->selectedDate) {
            return collect();
        }

        $store = Store::find($this->selectedStore);
        if (!$store || !$store->use_staff_assignment) {
            return collect();
        }

        $date = Carbon::parse($this->selectedDate);

        // その日のシフトデータを取得
        $shifts = \App\Models\Shift::where('store_id', $this->selectedStore)
            ->whereDate('shift_date', $date)
            ->where('status', 'scheduled')
            ->where('is_available_for_reservation', true)
            ->with('user')
            ->get();

        return $shifts->map(function($shift) {
            return [
                'id' => $shift->user_id,
                'name' => $shift->user->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time
            ];
        });
    }

    /**
     * 特定の時間スロットで予約が可能かどうかを判定（両モード対応）
     */
    public function canReserveAtTimeSlot($startTime, $endTime, $store = null, $date = null, $lineType = null, $seatNumber = null): array
    {
        if (!$store) {
            $store = Store::find($this->selectedStore);
        }
        if (!$date) {
            $date = Carbon::parse($this->selectedDate);
        }

        $result = [
            'can_reserve' => false,
            'available_slots' => 0,
            'total_capacity' => 0,
            'existing_reservations' => 0,
            'reason' => '',
            'mode' => $store->use_staff_assignment ? 'staff_shift' : 'business_hours',
            'line_type' => $lineType  // 追加：チェック対象のライン
        ];

        // 営業時間チェック（スタッフシフトモードではスキップ）
        if (!$store->use_staff_assignment && !$this->isWithinBusinessHours($startTime, $endTime, $store, $date)) {
            $result['reason'] = '営業時間外です';
            return $result;
        }

        // 既存予約を取得（サブ枠は別扱い）
        $existingReservations = Reservation::where('store_id', $store->id)
            ->whereDate('reservation_date', $date->format('Y-m-d'))
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->where(function ($q) use ($startTime, $endTime) {
                // 時間重複チェック（境界を含まない: 10:00-10:30と10:30-11:00は重複しない）
                // 時刻フォーマット統一のためtime()関数を使用（'15:00:00' と '15:00' の比較を正しく処理）
                $q->whereRaw('time(start_time) < time(?)', [$endTime])
                  ->whereRaw('time(end_time) > time(?)', [$startTime]);
            })
            ->get();

        // デバッグログ
        \Log::debug("🔍 canReserveAtTimeSlot called", [
            'startTime' => $startTime,
            'endTime' => $endTime,
            'lineType' => $lineType,
            'existingReservations_count' => $existingReservations->count(),
            'reservations' => $existingReservations->map(fn($r) => [
                'id' => $r->id,
                'start' => $r->start_time,
                'end' => $r->end_time,
                'line_type' => $r->line_type ?? 'null',
                'is_sub' => $r->is_sub
            ])
        ]);

        // スタッフシフトモードの場合、サブ枠を除外
        if ($store->use_staff_assignment) {
            $mainReservations = $existingReservations->where('is_sub', false)->where('line_type', '!=', 'sub');
            $result['existing_reservations'] = $mainReservations->count();
        } else {
            $result['existing_reservations'] = $existingReservations->count();
        }

        if ($store->use_staff_assignment) {
            // スタッフシフトモード
            return $this->checkStaffShiftModeAvailability($startTime, $endTime, $store, $date, $existingReservations, $result);
        } else {
            // 営業時間ベースモード
            return $this->checkBusinessHoursModeAvailability($startTime, $endTime, $store, $date, $existingReservations, $result, $lineType, $seatNumber);
        }
    }

    /**
     * スタッフシフトモードでの予約可能性チェック
     */
    private function checkStaffShiftModeAvailability($startTime, $endTime, $store, $date, $existingReservations, $result): array
    {
        // ブロックされた時間帯を取得
        $blockedPeriods = \App\Models\BlockedTimePeriod::where('store_id', $store->id)
            ->whereDate('blocked_date', $date->format('Y-m-d'))
            ->get();

        // 全体ブロック（line_type=null, staff_id=null）のチェック
        $hasGlobalBlock = $blockedPeriods->contains(function ($block) use ($startTime, $endTime, $date) {
            if ($block->line_type !== null || $block->staff_id !== null) {
                return false;
            }

            $blockStart = Carbon::parse($date->format('Y-m-d') . ' ' . $block->start_time);
            $blockEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $block->end_time);
            $slotStart = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
            $slotEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);

            return (
                ($slotStart->gte($blockStart) && $slotStart->lt($blockEnd)) ||
                ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                ($slotStart->lte($blockStart) && $slotEnd->gte($blockEnd))
            );
        });

        if ($hasGlobalBlock) {
            $result['reason'] = 'この時間帯はブロックされています';
            return $result;
        }

        // その時間帯に勤務可能なスタッフ数を取得（ブロック除外）
        $shifts = \App\Models\Shift::where('store_id', $store->id)
            ->whereDate('shift_date', $date->format('Y-m-d'))
            ->where('status', 'scheduled')
            ->where('is_available_for_reservation', true)
            ->get();

        $availableStaffCount = 0;
        foreach ($shifts as $shift) {
            $shiftStart = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->end_time);
            $slotStart = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
            $slotEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);

            \Log::debug('🔍 シフトチェック', [
                'staff_id' => $shift->user_id,
                'shift' => $shift->start_time . '-' . $shift->end_time,
                'slot' => $startTime . '-' . $endTime,
                'shiftStart' => $shiftStart->format('Y-m-d H:i'),
                'shiftEnd' => $shiftEnd->format('Y-m-d H:i'),
                'slotStart' => $slotStart->format('Y-m-d H:i'),
                'slotEnd' => $slotEnd->format('Y-m-d H:i'),
                'fits' => $slotStart->gte($shiftStart) && $slotEnd->lte($shiftEnd)
            ]);

            // 予約時間がシフト時間に完全に収まるかチェック
            if (!($slotStart->gte($shiftStart) && $slotEnd->lte($shiftEnd))) {
                continue;
            }

            // このスタッフがブロックされているかチェック
            $isBlocked = $blockedPeriods->contains(function ($block) use ($shift, $slotStart, $slotEnd, $date) {
                // staff_id指定のブロックのみチェック（全体ブロックは既にチェック済み）
                if (empty($block->staff_id)) {
                    return false;
                }

                // このスタッフのブロックか確認
                if ($block->staff_id != $shift->user_id) {
                    return false;
                }

                $blockStart = Carbon::parse($date->format('Y-m-d') . ' ' . $block->start_time);
                $blockEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $block->end_time);

                return (
                    ($slotStart->gte($blockStart) && $slotStart->lt($blockEnd)) ||
                    ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                    ($slotStart->lte($blockStart) && $slotEnd->gte($blockEnd))
                );
            });

            if (!$isBlocked) {
                $availableStaffCount++;
            }
        }

        \Log::debug('✅ スタッフ数チェック完了', [
            'slot' => $startTime . '-' . $endTime,
            'availableStaffCount' => $availableStaffCount,
            'existingReservations' => $result['existing_reservations']
        ]);

        if ($availableStaffCount === 0) {
            $result['reason'] = 'この時間帯には勤務可能なスタッフがいません';
            \Log::debug('❌ スタッフなしで予約不可', ['slot' => $startTime . '-' . $endTime]);
            return $result;
        }

        // 容量計算：設備台数とスタッフ数の最小値
        $equipmentCapacity = $store->shift_based_capacity ?? 1;
        $totalCapacity = min($equipmentCapacity, $availableStaffCount);

        $result['total_capacity'] = $totalCapacity;
        $result['available_slots'] = max(0, $totalCapacity - $result['existing_reservations']);
        $result['can_reserve'] = $result['available_slots'] > 0;

        \Log::debug('📊 最終判定', [
            'slot' => $startTime . '-' . $endTime,
            'can_reserve' => $result['can_reserve'],
            'available_slots' => $result['available_slots'],
            'total_capacity' => $totalCapacity
        ]);

        if (!$result['can_reserve'] && $result['available_slots'] === 0) {
            $result['reason'] = "この時間帯の予約枠は満席です（容量: {$totalCapacity}）";
        }

        return $result;
    }

    /**
     * 営業時間ベースモードでの予約可能性チェック
     */
    private function checkBusinessHoursModeAvailability($startTime, $endTime, $store, $date, $existingReservations, $result, $lineType = null, $seatNumber = null): array
    {
        $mainSeats = $store->main_lines_count ?? 3;
        $subSeats = 1; // サブライン固定1

        // メインライン容量チェック
        $mainReservations = $existingReservations->where('is_sub', false)->where('line_type', '!=', 'sub')->count();
        $availableMainSeats = max(0, $mainSeats - $mainReservations);

        // サブライン容量チェック
        $subReservations = $existingReservations->where(function($r) {
            return $r->is_sub || $r->line_type === 'sub';
        })->count();
        $availableSubSeats = max(0, $subSeats - $subReservations);

        // ライン種別が指定されている場合は、そのラインのみで判定
        if ($lineType === 'sub') {
            // サブラインのみチェック
            $result['total_capacity'] = $subSeats;
            $result['available_slots'] = $availableSubSeats;
            $result['can_reserve'] = $availableSubSeats > 0;

            if (!$result['can_reserve']) {
                $result['reason'] = "サブラインは満席です（サブ: {$subSeats}席）";
            }
        } elseif ($lineType === 'main') {
            // メインラインのみチェック
            
            // 特定の席番号が指定されている場合
            if ($seatNumber !== null) {
                // 指定された席番号での重複をチェック
                $seatConflict = $existingReservations
                    ->filter(function ($res) use ($seatNumber) {
                        return $res->seat_number == $seatNumber && 
                               $res->line_type == 'main' && 
                               !$res->is_sub;
                    })
                    ->count() > 0;
                
                if ($seatConflict) {
                    $result['can_reserve'] = false;
                    $result['reason'] = "席{$seatNumber}は既に予約済みです";
                    $result['total_capacity'] = 1;
                    $result['available_slots'] = 0;
                } else {
                    $result['can_reserve'] = true;
                    $result['total_capacity'] = 1;
                    $result['available_slots'] = 1;
                }
            } else {
                // 席番号未指定の場合は全体で判定
                $result['total_capacity'] = $mainSeats;
                $result['available_slots'] = $availableMainSeats;
                $result['can_reserve'] = $availableMainSeats > 0;

                if (!$result['can_reserve']) {
                    $result['reason'] = "メインラインは満席です（メイン: {$mainSeats}席）";
                }
            }
        } else {
            // ライン種別未指定の場合
            
            // 特定の席番号が指定されている場合は、その席の重複チェックのみ実行
            if ($seatNumber !== null) {
                // 指定された席番号での重複をチェック
                $seatConflict = $existingReservations->where('seat_number', $seatNumber)
                    ->where('is_sub', false)
                    ->where('line_type', '!=', 'sub')
                    ->exists();
                
                if ($seatConflict) {
                    $result['can_reserve'] = false;
                    $result['reason'] = "席{$seatNumber}は既に予約済みです";
                    $result['total_capacity'] = 1;
                    $result['available_slots'] = 0;
                } else {
                    $result['can_reserve'] = true;
                    $result['total_capacity'] = 1;
                    $result['available_slots'] = 1;
                }
            } else {
                // 席番号未指定の場合は全体で判定（後方互換性）
                $totalCapacity = $mainSeats + $subSeats;
                $totalAvailable = $availableMainSeats + $availableSubSeats;

                $result['total_capacity'] = $totalCapacity;
                $result['available_slots'] = $totalAvailable;
                $result['can_reserve'] = $totalAvailable > 0;

                if (!$result['can_reserve']) {
                    $result['reason'] = "この時間帯の予約枠は満席です（メイン: {$mainSeats}席、サブ: {$subSeats}席）";
                }
            }
        }

        return $result;
    }

    /**
     * 営業時間内かどうかをチェック
     */
    private function isWithinBusinessHours($startTime, $endTime, $store, $date): bool
    {
        $dayOfWeek = strtolower($date->format('l'));
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

                return Carbon::parse($startTime)->gte($openTime) && Carbon::parse($endTime)->lte($closeTime);
            }
        }

        return true; // 営業時間設定がない場合はOK
    }

    /**
     * 各タイムスロットの予約可否理由を取得
     */
    public function getSlotAvailabilityInfo(): array
    {
        if (!$this->selectedStore || !$this->selectedDate) {
            return [];
        }

        $store = Store::find($this->selectedStore);
        if (!$store) {
            return [];
        }

        $date = Carbon::parse($this->selectedDate, 'Asia/Tokyo');
        $slotInfo = [];

        // デフォルト所要時間を決定
        // 優先順位: 1. 選択メニュー所要時間 → 2. 店舗の最大メニュー所要時間
        if ($this->selectedMenuDuration) {
            $defaultDuration = $this->selectedMenuDuration + ($this->selectedOptionsDuration ?? 0);
            \Log::debug('🕒 空き判定: 選択メニュー所要時間使用', [
                'menu_duration' => $this->selectedMenuDuration,
                'options_duration' => $this->selectedOptionsDuration ?? 0,
                'total_duration' => $defaultDuration
            ]);
        } else {
            // 店舗の全メニューから最大所要時間を取得（保守的判定）
            $maxMenuDuration = \App\Models\Menu::where('store_id', $store->id)
                ->where('is_available', true)
                ->max('duration_minutes') ?? 120;

            $defaultDuration = $maxMenuDuration;
            \Log::debug('🕒 空き判定: 最大メニュー所要時間使用（保守的）', [
                'max_menu_duration' => $maxMenuDuration,
                'store_id' => $store->id
            ]);
        }

        // タイムラインのスロットごとに可否を確認
        foreach ($this->timelineData['slots'] ?? [] as $slot) {
            $startTime = $slot;
            // 実所要時間で終了時刻を計算
            $endTime = Carbon::parse($slot, 'Asia/Tokyo')
                ->addMinutes($defaultDuration)
                ->format('H:i');

            $availability = $this->canReserveAtTimeSlot($startTime, $endTime, $store, $date);

            // 理由を整形
            $reason = '';
            if (!$availability['can_reserve']) {
                if ($availability['reason']) {
                    $reason = $availability['reason'];
                } else if ($availability['available_slots'] === 0) {
                    $reason = "満席（容量: {$availability['total_capacity']}）";
                }
            } else {
                $reason = "予約可能（空き: {$availability['available_slots']}席）";
            }

            $slotInfo[$slot] = [
                'can_reserve' => $availability['can_reserve'],
                'reason' => $reason,
                'available_slots' => $availability['available_slots'],
                'total_capacity' => $availability['total_capacity']
            ];
        }

        return $slotInfo;
    }

    /**
     * 現在時刻が営業時間内かチェック
     */
    public function isCurrentlyWithinBusinessHours(): bool
    {
        if (!$this->selectedStore) {
            return true; // 店舗未選択時はデフォルト表示
        }

        $store = Store::find($this->selectedStore);
        if (!$store) {
            return true;
        }

        $now = Carbon::now('Asia/Tokyo');
        $currentTime = $now->format('H:i');
        $dayOfWeek = strtolower($now->format('l'));

        $businessHours = $store->business_hours ?? [];

        if (!is_array($businessHours)) {
            return true; // デフォルト表示
        }

        foreach ($businessHours as $hours) {
            if (isset($hours['day']) && $hours['day'] === $dayOfWeek) {
                if (isset($hours['is_closed']) && $hours['is_closed']) {
                    return false; // 定休日
                }

                $openTime = $hours['open_time'] ?? '10:00';
                $closeTime = $hours['close_time'] ?? '22:00';

                return $currentTime >= $openTime && $currentTime < $closeTime;
            }
        }

        // デフォルト営業時間（10:00-22:00）でチェック
        return $currentTime >= '10:00' && $currentTime < '22:00';
    }

    /**
     * タイムライン表示可否の判定
     */
    public function shouldShowTimeline(): bool
    {
        $selectedDate = Carbon::parse($this->selectedDate);

        // 過去日は常に表示（履歴として）
        if ($selectedDate->isPast() && !$selectedDate->isToday()) {
            return true;
        }

        // 今日の場合は営業時間で判定
        if ($selectedDate->isToday()) {
            return $this->isCurrentlyWithinBusinessHours();
        }

        // 未来日は常に表示
        return true;
    }

    /**
     * メニュー変更用：店舗のメニュー一覧を取得
     */
    public function getMenusForStore($storeId)
    {
        $menus = \App\Models\Menu::where('store_id', $storeId)
            // サブスク系は is_available に関わらず含める
            ->where(function ($q) {
                $q->where('is_available', true)
                  ->orWhere('is_subscription', true)
                  ->orWhere('is_subscription_only', true);
            })
            // オプションメニューを除外（show_in_upsell または is_option のもの）
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->where('show_in_upsell', false)->orWhereNull('show_in_upsell');
                })->where(function ($inner) {
                    $inner->where('is_option', false)->orWhereNull('is_option');
                });
            })
            ->with('menuCategory')
            ->orderByDesc('is_subscription')   // サブスクを上に（任意）
            ->orderBy('category_id')
            ->orderBy('name')
            ->get()
            ->map(function ($menu) {
                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'price' => $menu->price ?? 0,
                    'duration_minutes' => $menu->duration_minutes ?? 0,
                    'category' => $menu->menuCategory->name ?? null,
                    // 任意: バッジ表示やデバッグ用
                    'is_subscription' => (bool) $menu->is_subscription,
                    'is_subscription_only' => (bool) $menu->is_subscription_only,
                ];
            });

        return ['success' => true, 'data' => $menus];
    }

    /**
     * メニュー変更用：店舗のオプション一覧を取得
     * show_in_upsell=true または is_option=true のメニューを取得
     */
    public function getOptionsForStore($storeId)
    {
        $options = \App\Models\Menu::where('store_id', $storeId)
            ->where('is_available', true)
            ->where('is_subscription', false) // サブスクメニューを除外
            ->where(function($q) {
                $q->where('show_in_upsell', true)  // アップセル用メニュー = オプション
                  ->orWhere('is_option', true);    // または明示的にオプション設定されたもの
            })
            ->orderBy('name')
            ->get()
            ->map(function ($option) {
                return [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price' => $option->price ?? 0,
                    'duration_minutes' => $option->duration_minutes ?? 0,
                ];
            });

        return ['success' => true, 'data' => $options];
    }

    /**
     * 顧客の契約メニューID（サブスク・回数券）を取得
     */
    public function getCustomerContractsForStore($customerId, $storeId)
    {
        try {
            // サブスク（アクティブ・店舗一致）
            $subs = \App\Models\CustomerSubscription::where('customer_id', $customerId)
                ->where('store_id', $storeId)
                ->where('status', 'active')
                ->get();

            // メニューIDは subscription.menu_id 優先、無い場合は plan.menu_id を使用
            $subMenuIds = collect();
            foreach ($subs as $sub) {
                if ($sub->menu_id) {
                    $subMenuIds->push($sub->menu_id);
                } elseif ($sub->plan_id) {
                    $plan = \App\Models\SubscriptionPlan::find($sub->plan_id);
                    if ($plan && $plan->menu_id) {
                        $subMenuIds->push($plan->menu_id);
                    }
                }
            }
            $subMenuIds = $subMenuIds->filter()->unique()->values();

            // 回数券（アクティブ・残回数>0・店舗一致）
            $tickets = \App\Models\CustomerTicket::where('customer_id', $customerId)
                ->where('store_id', $storeId)
                ->where('status', 'active')
                ->where('remaining_count', '>', 0)
                ->with('ticketPlan')
                ->get();
            $ticketMenuIds = $tickets->map(function ($t) {
                return optional($t->ticketPlan)->menu_id;
            })->filter()->unique()->values();

            return [
                'success' => true,
                'data' => [
                    'sub_menu_ids' => $subMenuIds,
                    'ticket_menu_ids' => $ticketMenuIds,
                ],
            ];
        } catch (\Exception $e) {
            \Log::error('[getCustomerContractsForStore] error', ['e' => $e->getMessage()]);
            return ['success' => false, 'message' => '契約情報の取得に失敗しました'];
        }
    }

    /**
     * メニュー変更用：予約のメニューを変更
     */
    public function changeReservationMenu($reservationId, $menuId, $optionIds = [])
    {
        $reservation = Reservation::with(['menu', 'store', 'reservationOptions'])->find($reservationId);

        if (!$reservation) {
            return [
                'success' => false,
                'message' => '予約が見つかりません'
            ];
        }

        $newMenu = \App\Models\Menu::find($menuId);

        if (!$newMenu) {
            return [
                'success' => false,
                'message' => 'メニューが見つかりません'
            ];
        }

        // サブスクリプション判定
        $isSubscription = (bool)$newMenu->is_subscription;

        // 顧客とサブスクリプション情報を取得
        $customer = $reservation->customer;
        $storeId = $reservation->store_id;
        $activeSubscription = $customer ? $customer->getSubscriptionForStore($storeId) : null;

        // サブスクリプションメニューなのにアクティブサブスクがない場合の警告フラグ
        $subscriptionWarning = null;
        if ($isSubscription && !$activeSubscription) {
            $subscriptionWarning = '選択されたメニューはサブスクリプションメニューですが、この顧客にアクティブなサブスクリプションがありません';
        }

        // 回数券判定
        $isTicket = false;
        $activeTicket = null;
        $ticketWarning = null;

        if ($customer && $newMenu->id) {
            // 顧客のアクティブな回数券を取得（店舗一致・残回数>0）
            $activeTicket = \App\Models\CustomerTicket::where('customer_id', $customer->id)
                ->where('store_id', $storeId)
                ->where('status', 'active')
                ->where('remaining_count', '>', 0)
                ->whereHas('ticketPlan', function ($q) use ($newMenu) {
                    $q->where('menu_id', $newMenu->id);
                })
                ->with('ticketPlan')
                ->first();

            if ($activeTicket) {
                $isTicket = true;
            } else {
                // 回数券メニューなのにアクティブ回数券がない場合の警告
                // （回数券プランが存在するか確認）
                $ticketPlanExists = \App\Models\TicketPlan::where('menu_id', $newMenu->id)
                    ->where('store_id', $storeId)
                    ->exists();

                if ($ticketPlanExists) {
                    $ticketWarning = '選択されたメニューは回数券メニューですが、この顧客に利用可能な回数券がありません';
                }
            }
        }

        // 合計時間を計算
        $totalMinutes = $newMenu->duration_minutes;

        // オプションの時間を加算（Menuテーブルから取得）
        if (!empty($optionIds)) {
            $options = \App\Models\Menu::whereIn('id', $optionIds)->get();
            foreach ($options as $option) {
                $totalMinutes += $option->duration_minutes ?? 0;
            }
        }

        // 新しい終了時刻を計算
        $dateOnly = Carbon::parse($reservation->reservation_date)->format('Y-m-d');
        $startTime = Carbon::parse($dateOnly . ' ' . $reservation->start_time);
        $newEndTime = $startTime->copy()->addMinutes($totalMinutes);

        // 重複チェック
        $dateOnly = Carbon::parse($reservation->reservation_date)->format('Y-m-d');
        $query = Reservation::where('store_id', $reservation->store_id)
            ->where(function ($q) use ($dateOnly) {
                $q->whereDate('reservation_date', $dateOnly)
                  ->orWhere('reservation_date', 'like', $dateOnly . '%');
            })
            ->where('id', '!=', $reservation->id)
            ->whereIn('status', ['booked', 'in_progress']);

        // 座席番号がある場合は座席で絞り込み
        if (!empty($reservation->seat_number)) {
            $query->where('seat_number', $reservation->seat_number);
        }
        // スタッフIDがある場合はスタッフで絞り込み
        elseif (!empty($reservation->staff_id)) {
            $query->where('staff_id', $reservation->staff_id);
        }
        // 座席もスタッフもない場合は、店舗全体での重複をチェック
        // （同じ時間帯に他の予約があっても構わない場合はこのブロックを削除）
        else {
            // 座席・スタッフ指定なしの予約のみを対象
            $query->where(function ($q) {
                $q->whereNull('seat_number')
                  ->orWhere('seat_number', 0)
                  ->orWhere('seat_number', '');
            })
            ->where(function ($q) {
                $q->whereNull('staff_id')
                  ->orWhere('staff_id', 0)
                  ->orWhere('staff_id', '');
            });
        }

        // デバッグ: クエリSQL出力
        \Log::info('🔍 [Menu Change] クエリSQL before time check', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        // 時刻を正規化（秒まで含める）
        $startTimeStr = $startTime->format('H:i:s');
        $newEndTimeStr = $newEndTime->format('H:i:s');

        // 全ての候補を取得（time()関数で時刻フォーマットを統一）
        $allCandidates = $query->whereRaw('time(start_time) < time(?)', [$newEndTimeStr])
                              ->whereRaw('time(end_time) > time(?)', [$startTimeStr])
                              ->get();

        // 境界で接しているだけの予約を除外
        $conflictingReservations = $allCandidates->filter(function ($candidate) use ($startTimeStr, $newEndTimeStr) {
            // 相手の終了時刻が自分の開始時刻と一致 → 境界で接しているだけ
            if ($candidate->end_time === $startTimeStr) {
                return false;
            }
            // 相手の開始時刻が自分の終了時刻と一致 → 境界で接しているだけ
            if ($candidate->start_time === $newEndTimeStr) {
                return false;
            }
            // それ以外は真の重複
            return true;
        });

        // デバッグログ
        \Log::info('🔍 [Menu Change] 重複チェック', [
            'reservation_id' => $reservation->id,
            'seat_number' => $reservation->seat_number,
            'staff_id' => $reservation->staff_id,
            'date' => $reservation->reservation_date,
            'original_time' => $reservation->start_time . ' - ' . $reservation->end_time,
            'new_time' => $startTimeStr . ' - ' . $newEndTimeStr,
            'total_minutes' => $totalMinutes,
            'all_candidates_count' => $allCandidates->count(),
            'excluded_boundary_count' => $allCandidates->count() - $conflictingReservations->count(),
            'conflicting_count' => $conflictingReservations->count(),
            'all_candidates' => $allCandidates->map(function ($r) use ($startTimeStr, $newEndTimeStr) {
                $isBoundary = ($r->end_time === $startTimeStr || $r->start_time === $newEndTimeStr);
                return [
                    'id' => $r->id,
                    'time' => $r->start_time . ' - ' . $r->end_time,
                    'is_boundary' => $isBoundary ? 'YES (excluded)' : 'NO',
                ];
            })->toArray(),
            'conflicting_reservations' => $conflictingReservations->map(function ($r) {
                return [
                    'id' => $r->id,
                    'time' => $r->start_time . ' - ' . $r->end_time,
                    'seat_number' => $r->seat_number,
                    'staff_id' => $r->staff_id,
                ];
            })->toArray()
        ]);

        if ($conflictingReservations->count() > 0) {
            $conflictingTimes = $conflictingReservations->map(function ($r) {
                return $r->start_time . ' - ' . $r->end_time;
            })->join(', ');

            return [
                'success' => false,
                'message' => '新しい時間帯に予約が重複しています',
                'details' => [
                    'new_end_time' => $newEndTime->format('H:i'),
                    'conflicting_times' => $conflictingTimes,
                    'total_duration' => $totalMinutes . '分'
                ]
            ];
        }

        // トランザクション開始
        DB::beginTransaction();
        try {
            // 既存のオプションを削除（reservation_menu_optionsテーブル）
            $reservation->optionMenus()->detach();

            // 新しいオプションを追加とオプション料金の合計計算
            $totalOptionPrice = 0;
            if (!empty($optionIds)) {
                foreach ($optionIds as $optionId) {
                    $option = \App\Models\Menu::find($optionId);
                    if ($option) {
                        // サブスクリプション or 回数券予約の場合はオプション料金を0円にする
                        if (($isSubscription && $activeSubscription) || ($isTicket && $activeTicket)) {
                            $optionPrice = 0;
                        } else {
                            $optionPrice = $option->price ?? 0;
                        }
                        $totalOptionPrice += $optionPrice;

                        // optionMenusリレーション（reservation_menu_optionsテーブル）に保存
                        $reservation->optionMenus()->attach($optionId, [
                            'price' => $optionPrice,
                            'duration' => $option->duration_minutes ?? 0,
                        ]);
                    }
                }
            }

            // 合計金額の計算
            // サブスクリプション or 回数券の場合は0円、それ以外はメニュー料金 + オプション料金
            if (($isSubscription && $activeSubscription) || ($isTicket && $activeTicket)) {
                $totalAmount = 0;
            } else {
                $totalAmount = ($newMenu->price ?? 0) + $totalOptionPrice;
            }

            // 支払い方法の決定
            if ($isSubscription && $activeSubscription) {
                $paymentMethod = 'subscription';
            } elseif ($isTicket && $activeTicket) {
                $paymentMethod = 'ticket';
            } else {
                $paymentMethod = $reservation->payment_method ?? 'cash';
            }

            // 予約情報を更新
            $reservation->menu_id = $menuId;
            $reservation->end_time = $newEndTime->format('H:i:s');
            $reservation->customer_subscription_id = ($isSubscription && $activeSubscription) ? $activeSubscription->id : null;
            $reservation->customer_ticket_id = ($isTicket && $activeTicket) ? $activeTicket->id : null;
            $reservation->payment_method = $paymentMethod;
            $reservation->total_amount = $totalAmount;
            $reservation->save();

            DB::commit();

            // レスポンスメッセージの構築
            $message = 'メニューを変更しました';
            $warnings = [];

            if ($subscriptionWarning) {
                $warnings[] = $subscriptionWarning;
            }

            if ($ticketWarning) {
                $warnings[] = $ticketWarning;
            }

            if (!empty($warnings)) {
                $message .= '（警告: ' . implode('、', $warnings) . '）';
            }

            return [
                'success' => true,
                'message' => $message,
                'details' => [
                    'new_end_time' => $newEndTime->format('H:i'),
                    'total_duration' => $totalMinutes . '分',
                    'is_subscription' => $isSubscription,
                    'subscription_bound' => $activeSubscription ? true : false,
                    'is_ticket' => $isTicket,
                    'ticket_bound' => $activeTicket ? true : false,
                    'payment_method' => $paymentMethod,
                    'total_amount' => $totalAmount,
                    'warning' => !empty($warnings) ? implode('、', $warnings) : null
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Menu change error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'メニュー変更中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

}