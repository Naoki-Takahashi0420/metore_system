<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class TodayReservationTimelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.today-reservation-timeline-widget';
    
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';

    #[Url]
    public string $selectedDate = '';

    #[Url]
    public ?int $selectedStoreId = null;

    public ?int $selectedReservationId = null;
    public bool $showReservationModal = false;
    
    // キャッシュされたデータ
    public ?array $cachedData = null;
    public ?Collection $cachedTimeSlots = null;

    public function mount(): void
    {
        // 明確にこのウィジェットが使用されていることを示す
        logger('🔴 TodayReservationTimelineWidget が使用されています');

        if (empty($this->selectedDate)) {
            $this->selectedDate = Carbon::today()->format('Y-m-d');
        }

        // 初期データをキャッシュ
        $this->refreshData();
    }
    
    public function refreshData(): void
    {
        $this->cachedData = null;
        $this->cachedTimeSlots = null;
    }

    public function getData(): array
    {
        // キャッシュされたデータがある場合はそれを返す
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }
        
        $selectedDate = Carbon::parse($this->selectedDate);
        $user = auth()->user();
        
        // 店舗クエリを構築（ロール制限 + 選択店舗フィルタ適用）
        $storesQuery = Store::where('is_active', true);

        // 選択された店舗がある場合は、その店舗のみ
        if ($this->selectedStoreId) {
            $storesQuery->where('id', $this->selectedStoreId);
        } elseif ($user && !$user->hasRole('super_admin') && $user->store_id) {
            // 非スーパーアドミンの場合は自店舗のみ
            $storesQuery->where('id', $user->store_id);
        }

        $stores = $storesQuery->get();
        
        // 予約クエリを構築（ロール制限 + 選択店舗フィルタ適用）
        $reservationsQuery = Reservation::with(['customer', 'menu.menuCategory', 'store'])
            ->whereDate('reservation_date', $selectedDate)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('store_id')
            ->orderBy('start_time');

        // 選択された店舗がある場合は、その店舗のみ
        if ($this->selectedStoreId) {
            $reservationsQuery->where('store_id', $this->selectedStoreId);
        } elseif ($user && !$user->hasRole('super_admin') && $user->store_id) {
            // 非スーパーアドミンの場合は自店舗のみ
            $reservationsQuery->where('store_id', $user->store_id);
        }
        
        $reservations = $reservationsQuery->get();
        
        // 新規顧客の判定（シンプル版：顧客の初回予約=新規）
        $reservations->transform(function ($reservation) {
            $reservation->is_new_customer = $reservation->customer->isFirstReservation($reservation);
            // カテゴリー別の色クラスを設定
            $categoryId = $reservation->menu ? $reservation->menu->category_id : null;
            $reservation->category_color_class = $this->getCategoryColorClass($categoryId);
            return $reservation;
        });
        
        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$selectedDate->dayOfWeek];
        
        // データをキャッシュ
        $this->cachedData = [
            'reservations' => $reservations,
            'stores' => $stores,
            'selectedDate' => $selectedDate,
            'todayDate' => $selectedDate->format('Y年n月j日') . '（' . $dayOfWeek . '）',
            'isToday' => $selectedDate->isToday(),
            'canNavigateBack' => $selectedDate->gt(Carbon::today()->subDays(30)),
            'canNavigateForward' => $selectedDate->lt(Carbon::today()->addDays(60)),
            'timeSlots' => $this->getTimeSlots(),
        ];
        
        return $this->cachedData;
    }
    
    public function getTimeSlots(): Collection
    {
        if ($this->cachedTimeSlots !== null) {
            return $this->cachedTimeSlots;
        }
        
        $this->cachedTimeSlots = $this->generateTimeSlots();
        return $this->cachedTimeSlots;
    }

    public function goToPreviousDay()
    {
        $currentDate = Carbon::parse($this->selectedDate);
        if ($currentDate->gt(Carbon::today()->subDays(30))) {
            $this->selectedDate = $currentDate->subDay()->format('Y-m-d');
            $this->refreshData();
        }
    }

    public function goToNextDay()
    {
        $currentDate = Carbon::parse($this->selectedDate);
        if ($currentDate->lt(Carbon::today()->addDays(60))) {
            $this->selectedDate = $currentDate->addDay()->format('Y-m-d');
            $this->refreshData();
        }
    }

    public function goToToday()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->refreshData();
    }

    public function updatedSelectedDate()
    {
        $this->refreshData();
    }

    public function updatedSelectedStoreId()
    {
        $this->refreshData();
    }

    public function getAvailableStores(): Collection
    {
        $user = auth()->user();
        $storesQuery = Store::where('is_active', true);

        // スーパーアドミン以外は自店舗のみ
        if ($user && !$user->hasRole('super_admin') && $user->store_id) {
            $storesQuery->where('id', $user->store_id);
        }

        return $storesQuery->orderBy('name')->get();
    }
    
    public function openReservationModal(int $reservationId): void
    {
        $this->selectedReservationId = $reservationId;
        $this->showReservationModal = true;
    }
    
    public function closeReservationModal(): void
    {
        $this->selectedReservationId = null;
        $this->showReservationModal = false;
        
        // refreshData()を削除 - キャッシュクリアしない（色変更防止）
        // $this->refreshData();
    }
    
    public function getSelectedReservation(): ?Reservation
    {
        if (!$this->selectedReservationId) {
            return null;
        }

        // キャッシュされた予約データから取得（色が変わらない）
        $cachedReservations = $this->getData()['reservations'];
        $cachedReservation = $cachedReservations->where('id', $this->selectedReservationId)->first();

        if ($cachedReservation) {
            return $cachedReservation;
        }

        // フォールバック（通常は使われない）
        $reservation = Reservation::with(['customer', 'menu', 'store'])
            ->find($this->selectedReservationId);

        if ($reservation) {
            $reservation->is_new_customer = $reservation->customer->isFirstReservation($reservation);
            $reservation->category_color_class = $this->getCategoryColorClass($reservation->menu->category_id ?? null);
        }

        return $reservation;
    }

    /**
     * カテゴリーIDから色クラスを取得
     */
    private function getCategoryColorClass($categoryId): string
    {
        // カテゴリーIDがnullの場合はデフォルトを返す
        if (!$categoryId) {
            return 'default';
        }

        // カテゴリー情報を取得してname-based の色クラスを生成
        $category = \App\Models\MenuCategory::find($categoryId);
        if (!$category) {
            return 'default';
        }

        // カテゴリーIDをベースにした統一の色クラスを生成（getCategoryColors()と一致）
        return $categoryId;
    }

    /**
     * すべてのカテゴリー色情報を取得（実際に使用されているもの + 利用可能なメニューがあるもの）
     */
    public function getCategoryColors(): array
    {
        // 今日の予約で使用されているカテゴリーIDを取得
        $selectedDate = Carbon::parse($this->selectedDate);
        $user = auth()->user();

        $reservationsQuery = \App\Models\Reservation::with(['menu'])
            ->whereDate('reservation_date', $selectedDate)
            ->whereNotIn('status', ['cancelled', 'canceled']);

        // 選択された店舗がある場合は、その店舗のみ
        if ($this->selectedStoreId) {
            $reservationsQuery->where('store_id', $this->selectedStoreId);
        } elseif ($user && !$user->hasRole('super_admin') && $user->store_id) {
            // 非スーパーアドミンの場合は自店舗のみ
            $reservationsQuery->where('store_id', $user->store_id);
        }

        $usedCategoryIds = $reservationsQuery->get()
            ->pluck('menu.category_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // 利用可能なメニューがあるカテゴリーIDも取得（店舗フィルタリング適用）
        $availableMenusQuery = \App\Models\Menu::where('is_available', true)
            ->whereNotNull('category_id');

        // 権限に応じた店舗フィルタリング
        if ($this->selectedStoreId) {
            $availableMenusQuery->where('store_id', $this->selectedStoreId);
        } elseif ($user && !$user->hasRole('super_admin') && $user->store_id) {
            $availableMenusQuery->where('store_id', $user->store_id);
        }

        $availableCategoryIds = $availableMenusQuery
            ->pluck('category_id')
            ->unique()
            ->values()
            ->toArray();

        // 今日使用されているカテゴリー + 利用可能なメニューがあるカテゴリーを結合
        $allCategoryIds = array_unique(array_merge($usedCategoryIds, $availableCategoryIds));

        // カテゴリーを取得（店舗フィルタリング適用）
        $categoriesQuery = \App\Models\MenuCategory::whereIn('id', $allCategoryIds)
            ->where('is_active', true);

        // 権限に応じた店舗フィルタリング
        if ($this->selectedStoreId) {
            $categoriesQuery->where('store_id', $this->selectedStoreId);
        } elseif ($user && !$user->hasRole('super_admin') && $user->store_id) {
            $categoriesQuery->where('store_id', $user->store_id);
        }

        $categories = $categoriesQuery->orderBy('id')->get();

        // フォールバック用のカラーパターン
        $fallbackColors = [
            '#3b82f6',  // 青系
            '#8b5cf6',  // 紫系
            '#f97316',  // オレンジ系
            '#22c55e',  // 緑系
            '#ef4444',  // 赤系
            '#eab308',  // 黄系
        ];

        $result = [];
        $seenNames = []; // 重複チェック用
        $nameToColorClass = []; // 名前とカラークラスのマッピング

        foreach ($categories as $index => $category) {
            // 同じ名前のカテゴリーが既に追加されている場合はスキップ
            if (in_array($category->name, $seenNames)) {
                continue;
            }
            $seenNames[] = $category->name;

            // データベースの色を優先、なければフォールバック色を使用
            $colorHex = $category->color ?: $fallbackColors[$index % count($fallbackColors)];

            // カラークラス名を生成（category-{id}形式で統一）
            $colorClass = $category->id;
            $nameToColorClass[$category->name] = $colorClass;

            $result[] = [
                'id' => $category->id,
                'name' => $category->name,
                'colorClass' => $colorClass,
                'colorHex' => $colorHex,
                'initial' => mb_substr($category->name, 0, 1)
            ];
        }

        return $result;
    }

    private function generateTimeSlots(): Collection
    {
        $selectedDate = Carbon::parse($this->selectedDate);
        $dayOfWeek = strtolower($selectedDate->format('l'));
        
        // 店舗データを取得（キャッシュがない場合は直接取得）
        $stores = $this->cachedData['stores'] ?? Store::where('is_active', true)->get();
        
        $earliestOpen = null;
        $latestClose = null;
        
        // 各店舗の営業時間をチェック
        foreach ($stores as $store) {
            if ($store->business_hours) {
                // business_hoursが文字列の場合はデコード
                $businessHours = is_string($store->business_hours)
                    ? json_decode($store->business_hours, true)
                    : $store->business_hours;

                if (!is_array($businessHours)) {
                    continue;
                }

                foreach ($businessHours as $hours) {
                    // $hoursが配列でない場合はスキップ
                    if (!is_array($hours)) {
                        continue;
                    }

                    if (isset($hours['day']) && $hours['day'] === $dayOfWeek &&
                        (!isset($hours['is_closed']) || !$hours['is_closed']) &&
                        isset($hours['open_time']) && isset($hours['close_time']) &&
                        $hours['open_time'] && $hours['close_time']) {
                        
                        try {
                            // H:i:sフォーマットの場合もH:iフォーマットの場合も対応
                            $openTimeStr = substr($hours['open_time'], 0, 5); // HH:MM部分のみ取得
                            $closeTimeStr = substr($hours['close_time'], 0, 5);

                            $openTime = Carbon::createFromFormat('H:i', $openTimeStr);
                            $closeTime = Carbon::createFromFormat('H:i', $closeTimeStr);
                            
                            if ($earliestOpen === null || $openTime->lt($earliestOpen)) {
                                $earliestOpen = $openTime;
                            }
                            if ($latestClose === null || $closeTime->gt($latestClose)) {
                                $latestClose = $closeTime;
                            }
                        } catch (\Exception $e) {
                            // エラー時はスキップ
                            continue;
                        }
                        break;
                    }
                }
            }
        }
        
        // デフォルトの営業時間（9:00-23:30 銀座店に合わせて拡張）
        if ($earliestOpen === null) {
            $earliestOpen = Carbon::createFromTime(9, 0);
        }
        if ($latestClose === null) {
            $latestClose = Carbon::createFromTime(23, 30);
        }
        
        // 表示対象店舗の最小予約間隔を取得
        $minSlotInterval = 30; // デフォルト
        if ($stores->isNotEmpty()) {
            $minSlotInterval = $stores->min('reservation_slot_duration') ?? 30;
        }

        // 営業時間のフルレンジを表示
        $start = $earliestOpen->copy();
        $end = $latestClose->copy();

        $slots = collect();
        while ($start <= $end) {
            $slots->push($start->format('H:i'));
            $start->addMinutes($minSlotInterval);
        }

        return $slots;
    }
    
    /**
     * 店舗の営業時間を取得
     */
    public function getStoreBusinessHours($store): array
    {
        $selectedDate = Carbon::parse($this->selectedDate);
        $dayOfWeek = strtolower($selectedDate->format('l')); // monday, tuesday, etc.
        
        if ($store->business_hours) {
            // business_hoursが文字列の場合はデコード
            $businessHours = is_string($store->business_hours)
                ? json_decode($store->business_hours, true)
                : $store->business_hours;

            if (is_array($businessHours)) {
                foreach ($businessHours as $hours) {
                    // $hoursが配列でない場合はスキップ
                    if (!is_array($hours)) {
                        continue;
                    }

                    if (isset($hours['day']) && $hours['day'] === $dayOfWeek) {
                    // 休業日チェック（is_closedまたはopen_time/close_timeがnull）
                    if ((isset($hours['is_closed']) && $hours['is_closed']) || 
                        !isset($hours['open_time']) || !isset($hours['close_time']) ||
                        !$hours['open_time'] || !$hours['close_time']) {
                        return [
                            'open' => null,
                            'close' => null,
                            'is_open' => false
                        ];
                    }
                    
                    return [
                        'open' => $hours['open_time'],
                        'close' => $hours['close_time'],
                        'is_open' => true
                    ];
                }
            }
            }
        }
        
        // デフォルトの営業時間（データが見つからない場合）
        return [
            'open' => '09:00',
            'close' => '18:00',
            'is_open' => true
        ];
    }
    
    /**
     * 予約の時間スロットでの開始位置と期間を計算
     */
    public function getReservationTimeSlotInfo($reservation): array
    {
        $timeSlots = $this->generateTimeSlots();
        
        // 時間フォーマットの正規化
        try {
            if (is_string($reservation->start_time)) {
                if (strlen($reservation->start_time) === 5) {
                    // 既にH:i形式
                    $startTime = $reservation->start_time;
                    $endTime = $reservation->end_time;
                } else {
                    // H:i:s形式からH:iに変換
                    $startTime = Carbon::createFromFormat('H:i:s', $reservation->start_time)->format('H:i');
                    $endTime = Carbon::createFromFormat('H:i:s', $reservation->end_time)->format('H:i');
                }
            } else {
                // Carbonインスタンスの場合
                $startTime = $reservation->start_time->format('H:i');
                $endTime = $reservation->end_time->format('H:i');
            }
        } catch (\Exception $e) {
            // エラー時のフォールバック
            $startTime = substr(strval($reservation->start_time), 0, 5);
            $endTime = substr(strval($reservation->end_time), 0, 5);
        }
        
        // 開始時刻のスロットインデックスを取得
        $startSlotIndex = $timeSlots->search($startTime);
        if ($startSlotIndex === false) {
            // 完全一致しない場合は最も近いスロットを探す
            $startSlotIndex = $timeSlots->search(function($slot) use ($startTime) {
                return $slot >= $startTime;
            });
            if ($startSlotIndex === false) $startSlotIndex = 0;
        }
        
        // 終了時刻のスロットインデックスを取得
        $endSlotIndex = $timeSlots->search(function($slot) use ($endTime) {
            return $slot >= $endTime;
        });
        if ($endSlotIndex === false) $endSlotIndex = count($timeSlots);
        
        $duration = max(1, $endSlotIndex - $startSlotIndex);
        
        return [
            'startSlotIndex' => $startSlotIndex,
            'duration' => $duration,
            'startTime' => $startTime,
            'endTime' => $endTime
        ];
    }

    public function getReservationAtTime(string $time, ?int $storeId = null): ?Reservation
    {
        $reservations = $this->getData()['reservations'];
        
        return $reservations->first(function ($reservation) use ($time, $storeId) {
            if ($storeId && $reservation->store_id !== $storeId) {
                return false;
            }
            
            // 時刻を安全に正規化
            try {
                if (is_string($reservation->start_time)) {
                    if (strlen($reservation->start_time) === 5) {
                        $startTime = $reservation->start_time;
                        $endTime = $reservation->end_time;
                    } else {
                        $startTime = Carbon::createFromFormat('H:i:s', $reservation->start_time)->format('H:i');
                        $endTime = Carbon::createFromFormat('H:i:s', $reservation->end_time)->format('H:i');
                    }
                } else {
                    $startTime = $reservation->start_time->format('H:i');
                    $endTime = $reservation->end_time->format('H:i');
                }
            } catch (\Exception $e) {
                $startTime = substr(strval($reservation->start_time), 0, 5);
                $endTime = substr(strval($reservation->end_time), 0, 5);
            }
            
            // 文字列での時間比較
            return ($time >= $startTime && $time < $endTime);
        });
    }

    /**
     * 現在時刻が営業時間内かチェック
     */
    public function isCurrentlyWithinBusinessHours(): bool
    {
        $now = Carbon::now('Asia/Tokyo');
        $currentTime = $now->format('H:i');
        $dayOfWeek = strtolower($now->format('l'));

        foreach ($this->stores as $store) {
            $businessHours = $this->getStoreBusinessHours($store);

            if ($businessHours['is_open'] &&
                $currentTime >= $businessHours['open'] &&
                $currentTime < $businessHours['close']) {
                return true;
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