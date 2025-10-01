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

    // リアルタイム更新のためのポーリング間隔（30秒）
    protected static ?string $pollingInterval = '30s';
    
    public $selectedStore = null;
    public $selectedDate = null;
    public $stores = [];
    public $timelineData = [];
    public $categories = [];
    public $selectedReservation = null;
    
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
    public $newReservation = [
        'date' => '',
        'start_time' => '',
        'duration' => 60,
        'menu_id' => '',
        'line_type' => 'main',
        'line_number' => 1,
        'staff_id' => '',
        'notes' => '電話予約',
        'option_menu_ids' => [] // オプションメニューID配列
    ];
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
                'label' => 'サブ',
                'type' => 'sub',
                'reservations' => []
            ];
        } else {
            // 営業時間ベースモードの場合は固定席数
            for ($seat = 1; $seat <= $mainSeats; $seat++) {
                $timeline['seat_' . $seat] = [
                    'label' => '席' . $seat,
                    'type' => 'main',
                    'reservations' => []
                ];
            }

            // サブ枠（固定1席）
            $timeline['sub_1'] = [
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
                
                // 時間スロットのインデックスを計算（店舗設定の時間刻み）
                $slotsPerHour = 60 / $slotDuration;
                $startSlot = max(0, ($blockStart->hour - $startHour) * $slotsPerHour + ($blockStart->minute / $slotDuration));
                $endSlot = min(count($slots), ($blockEnd->hour - $startHour) * $slotsPerHour + ($blockEnd->minute / $slotDuration));
                
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

            // 時間スロットのインデックスを計算（店舗設定の時間刻み）
            $slotsPerHour = 60 / $slotDuration; // 1時間あたりのスロット数
            $startSlot = ($startTime->hour - $startHour) * $slotsPerHour + ($startTime->minute / $slotDuration);
            $span = $duration / $slotDuration; // slotDurationを1単位とする

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
            // optionMenusを安全に読み込み
            if ($this->selectedReservation) {
                $this->selectedReservation->load('optionMenus');
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
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '過去の予約の席移動はできません'
                ]);
                return;
            }
            // サブ枠に既に予約があるかチェック
            // サブ枠の重複チェックのみを直接実施（営業時間チェックは不要）
            $hasConflict = Reservation::where('store_id', $reservation->store_id)
                ->whereDate('reservation_date', $reservation->reservation_date)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->where('id', '!=', $reservation->id)
                ->where(function($q) use ($reservation) {
                    $q->where('start_time', '<', $reservation->end_time)
                      ->where('end_time', '>', $reservation->start_time);
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
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'サブ枠は既に予約が入っています'
                ]);
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
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'サブ枠に移動しました'
            ]);
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
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '過去の予約の席移動はできません'
            ]);
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
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => ($staff ? $staff->name : 'スタッフ') . 'はこの日シフトがありません'
            ]);
            return;
        }

        // シフト時間内かチェック
        $startTime = Carbon::parse($reservation->start_time);
        $endTime = Carbon::parse($reservation->end_time);
        $shiftStart = Carbon::parse($shift->start_time);
        $shiftEnd = Carbon::parse($shift->end_time);

        if ($startTime->lt($shiftStart) || $endTime->gt($shiftEnd)) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '予約時間がスタッフのシフト時間外です（' . $shift->start_time . '-' . $shift->end_time . '）'
            ]);
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
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => ($staff ? $staff->name : 'スタッフ') . 'に割り当てました'
        ]);
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
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '過去の予約の席移動はできません'
                ]);
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

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '未指定ラインに移動しました'
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

            $store = Store::find($reservation->store_id);

            // スタッフシフトモードでは使用しない
            if ($store && $store->use_staff_assignment) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'スタッフシフトモードではこの操作は利用できません'
                ]);
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
                        $q->where('start_time', '<', $reservation->end_time)
                          ->where('end_time', '>', $reservation->start_time);
                    })
                    ->exists();

                if ($hasConflict) {
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => '席' . $seatNumber . 'は既に予約が入っています'
                    ]);
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

            // 予約ブロックを作成
            \App\Models\BlockedTimePeriod::create([
                'store_id' => $this->selectedStore,
                'blocked_date' => $this->blockSettings['date'],
                'start_time' => $this->blockSettings['start_time'],
                'end_time' => $this->blockSettings['end_time'],
                'is_all_day' => false,
                'reason' => $this->blockSettings['reason'],
                'is_recurring' => false,
            ]);

            // モーダルを閉じて、データをリロード
            $this->closeNewReservationModal();
            $this->loadTimelineData();

            // 成功通知
            session()->flash('success', '予約ブロックを設定しました。');

        } catch (\Exception $e) {
            \Log::error('Failed to create blocked time:', [
                'error' => $e->getMessage(),
                'blockSettings' => $this->blockSettings
            ]);
            session()->flash('error', '予約ブロックの設定に失敗しました。');
        }
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

                $this->searchResults = \App\Models\Customer::where(function($query) use ($search, $dbDriver) {
                        $query->where('phone', 'LIKE', '%' . $search . '%')
                              ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                              ->orWhere('first_name', 'LIKE', '%' . $search . '%')
                              ->orWhere('last_name_kana', 'LIKE', '%' . $search . '%')
                              ->orWhere('first_name_kana', 'LIKE', '%' . $search . '%');

                        // SQLite: last_name || first_name, MySQL: CONCAT(last_name, first_name)
                        if ($dbDriver === 'sqlite') {
                            $query->orWhereRaw('(last_name || first_name) LIKE ?', ['%' . $search . '%'])
                                  ->orWhereRaw('(last_name_kana || first_name_kana) LIKE ?', ['%' . $search . '%']);
                        } else {
                            $query->orWhereRaw('CONCAT(last_name, first_name) LIKE ?', ['%' . $search . '%'])
                                  ->orWhereRaw('CONCAT(last_name_kana, first_name_kana) LIKE ?', ['%' . $search . '%']);
                        }
                    })
                    // whereHas を削除して、全ての顧客を検索対象に
                    ->withCount(['reservations' => function($query) use ($storeId) {
                        // この店舗での予約回数をカウント（0件でもOK）
                        $query->where('store_id', $storeId);
                    }])
                    ->with(['reservations' => function($query) use ($storeId) {
                        // この店舗での最新予約を取得（なくてもOK）
                        $query->where('store_id', $storeId)
                              ->latest('reservation_date')
                              ->limit(1);
                    }])
                    ->limit(10)
                    ->get()
                    ->map(function($customer) {
                        $lastReservation = $customer->reservations->first();
                        $customer->last_visit_date = $lastReservation ? $lastReservation->reservation_date : null;
                        return $customer;
                    });

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
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '顧客検索中にエラーが発生しました: ' . $e->getMessage()
            ]);
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
        
        // 新規顧客を作成（重複チェック強化）
        try {
            $customer = \App\Models\Customer::create([
                'last_name' => $this->newCustomer['last_name'],
                'first_name' => $this->newCustomer['first_name'],
                'last_name_kana' => '',  // カナは空で設定
                'first_name_kana' => '', // カナは空で設定
                'email' => $this->newCustomer['email'],
                'phone' => $this->newCustomer['phone'],
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // メールアドレス重複の場合、既存顧客を使用
            $existingCustomer = \App\Models\Customer::where('email', $this->newCustomer['email'])->first();
            if ($existingCustomer) {
                $this->selectedCustomer = $existingCustomer;
                $this->reservationStep = 3;
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '既存のお客様でした（' . $existingCustomer->last_name . ' ' . $existingCustomer->first_name . '様）。予約詳細を入力してください。'
                ]);
                return;
            } else {
                throw $e; // 他の原因の場合はエラーを再throw
            }
        }
        
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
        try {
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

            // 店舗情報取得
            $store = \App\Models\Store::find($this->selectedStore);

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
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => $availabilityResult['reason'] ?: 'この時間帯は予約できません'
                    ]);
                    return;
                }
            } else {
                // 営業時間ベースモードの場合、営業時間チェック（終了時刻ベース）
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
            }

            // 予約番号を生成
            $reservationNumber = 'R' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // スタッフシフトモードかどうか確認
            $store = Store::find($this->selectedStore);
            $useStaffAssignment = $store->use_staff_assignment ?? false;

            // 予約データを準備
            $reservationData = [
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
                'notes' => $this->newReservation['notes'],
                'total_amount' => $menu->price ?? 0,
                'deposit_amount' => 0,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
            ];

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

            // 成功通知（オプション数を含める）
            $optionCount = count($this->newReservation['option_menu_ids']);
            $message = '予約を作成しました（予約番号: ' . $reservationNumber;
            if ($optionCount > 0) {
                $message .= '、オプション' . $optionCount . '件追加';
            }
            $message .= '）';

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message
            ]);
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

        // オプションメニューを読み込む
        $this->loadAvailableOptions($menuId);

        // 検索フィールドをクリア & ドロップダウンを閉じる
        $this->menuSearch = '';
        $this->showAllMenus = false;
    }

    /**
     * 選択可能なオプションメニューを読み込む
     */
    public function loadAvailableOptions($menuId)
    {
        try {
            // 選択されたメニューと同じ店舗のオプションメニューを取得
            $mainMenu = \App\Models\Menu::find($menuId);
            if (!$mainMenu) {
                $this->availableOptions = [];
                return;
            }

            // オプションとして選択可能なメニュー（is_optionがtrueまたは小額メニュー）
            $this->availableOptions = \App\Models\Menu::where('is_available', true)
                ->where('store_id', $mainMenu->store_id)
                ->where('id', '!=', $menuId)
                ->where(function($q) {
                    $q->where('is_option', true)
                      ->orWhere('price', '<=', 3000); // 3000円以下はオプションとして選択可能
                })
                ->orderBy('price')
                ->get()
                ->toArray();

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

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'オプションを追加しました'
            ]);
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

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'オプションを削除しました'
        ]);
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
    public function canReserveAtTimeSlot($startTime, $endTime, $store = null, $date = null): array
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
            'mode' => $store->use_staff_assignment ? 'staff_shift' : 'business_hours'
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
                // 時間重複チェック
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->get();

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
            return $this->checkBusinessHoursModeAvailability($startTime, $endTime, $store, $date, $existingReservations, $result);
        }
    }

    /**
     * スタッフシフトモードでの予約可能性チェック
     */
    private function checkStaffShiftModeAvailability($startTime, $endTime, $store, $date, $existingReservations, $result): array
    {
        // その時間帯に勤務可能なスタッフ数を取得
        $shifts = \App\Models\Shift::where('store_id', $store->id)
            ->whereDate('shift_date', $date->format('Y-m-d'))
            ->where('status', 'scheduled')
            ->where('is_available_for_reservation', true)
            ->get();

        $availableStaffCount = 0;
        foreach ($shifts as $shift) {
            $shiftStart = Carbon::parse($shift->start_time);
            $shiftEnd = Carbon::parse($shift->end_time);

            // 予約時間とシフト時間が重なっているかチェック
            if (Carbon::parse($startTime)->lt($shiftEnd) && Carbon::parse($endTime)->gt($shiftStart)) {
                $availableStaffCount++;
            }
        }

        if ($availableStaffCount === 0) {
            $result['reason'] = 'この時間帯には勤務可能なスタッフがいません';
            return $result;
        }

        // 容量計算：設備台数とスタッフ数の最小値
        $equipmentCapacity = $store->shift_based_capacity ?? 1;
        $totalCapacity = min($equipmentCapacity, $availableStaffCount);

        $result['total_capacity'] = $totalCapacity;
        $result['available_slots'] = max(0, $totalCapacity - $result['existing_reservations']);
        $result['can_reserve'] = $result['available_slots'] > 0;

        if (!$result['can_reserve'] && $result['available_slots'] === 0) {
            $result['reason'] = "この時間帯の予約枠は満席です（容量: {$totalCapacity}）";
        }

        return $result;
    }

    /**
     * 営業時間ベースモードでの予約可能性チェック
     */
    private function checkBusinessHoursModeAvailability($startTime, $endTime, $store, $date, $existingReservations, $result): array
    {
        $mainSeats = $store->main_lines_count ?? 3;
        $subSeats = 1; // サブライン固定1

        // メインライン容量チェック
        $mainReservations = $existingReservations->where('is_sub', false)->count();
        $availableMainSeats = max(0, $mainSeats - $mainReservations);

        // サブライン容量チェック
        $subReservations = $existingReservations->where('is_sub', true)->count();
        $availableSubSeats = max(0, $subSeats - $subReservations);

        $totalCapacity = $mainSeats + $subSeats;
        $totalAvailable = $availableMainSeats + $availableSubSeats;

        $result['total_capacity'] = $totalCapacity;
        $result['available_slots'] = $totalAvailable;
        $result['can_reserve'] = $totalAvailable > 0;

        if (!$result['can_reserve']) {
            $result['reason'] = "この時間帯の予約枠は満席です（メイン: {$mainSeats}席、サブ: {$subSeats}席）";
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

        $date = Carbon::parse($this->selectedDate);
        $slotInfo = [];

        // タイムラインのスロットごとに可否を確認
        foreach ($this->timelineData['slots'] ?? [] as $slot) {
            $startTime = $slot;
            $endTime = Carbon::parse($slot)->addMinutes($store->reservation_slot_duration ?? 30)->format('H:i');

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

}