<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerAccessToken;
use App\Models\BlockedTimePeriod;
use App\Models\Shift;
use App\Events\ReservationCreated;
use App\Events\ReservationCancelled;
use App\Events\ReservationChanged;
use App\Jobs\SendReservationConfirmationWithFallback;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PublicReservationController extends Controller
{
    public function selectStore(Request $request)
    {
        // トークンがある場合の処理
        if ($token = $request->get('token')) {
            $accessToken = CustomerAccessToken::where('token', $token)
                ->with(['customer', 'store'])
                ->first();
                
            if ($accessToken && $accessToken->isValid()) {
                // トークン使用を記録
                $accessToken->recordUsage();
                
                // 顧客情報をセッションに保存
                Session::put('customer_id', $accessToken->customer_id);
                Session::put('is_existing_customer', true);
                Session::put('access_token_id', $accessToken->id);
                
                // 店舗が指定されている場合は直接カテゴリー選択へ
                if ($accessToken->store_id) {
                    Session::put('selected_store_id', $accessToken->store_id);
                    return redirect()->route('reservation.select-category');
                }
            }
        }
        
        $stores = Store::where('is_active', true)->get();
        return view('reservation.store-select', compact('stores'));
    }
    
    public function storeStoreSelection(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id'
        ]);
        
        Session::put('selected_store_id', $validated['store_id']);
        // 新フロー：カテゴリー選択へ
        return redirect()->route('reservation.select-category');
    }
    
    /**
     * メニューカテゴリー選択
     */
    public function selectCategory(Request $request)
    {
        // 店舗が選択されていない場合は店舗選択へ
        $storeId = Session::get('selected_store_id');
        if (!$storeId) {
            return redirect()->route('stores');
        }
        
        $store = Store::find($storeId);
        
        // マイページからの予約かチェック
        $fromMypage = $request->get('from_mypage', false);
        $existingCustomerId = $request->get('existing_customer_id');
        
        // 既存顧客の判定
        $isExistingCustomer = $fromMypage || $existingCustomerId;
        
        // アクティブなカテゴリーを取得（sort_order優先）
        $categoriesQuery = MenuCategory::where('store_id', $storeId)
            ->where('is_active', true);
            
        // 既存顧客の場合、メニューの制限を適用
        if ($isExistingCustomer) {
            $categoriesQuery->whereHas('menus', function($query) {
                $query->where('is_available', true)
                      ->where('customer_type_restriction', '!=', 'new_only');
            });
        }
        
        $categories = $categoriesQuery
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
            
        return view('reservation.category-select', compact('categories', 'store'));
    }
    
    /**
     * 時間・料金選択
     */
    public function selectTime(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:menu_categories,id'
        ]);
        
        Session::put('selected_category_id', $validated['category_id']);
        
        $storeId = Session::get('selected_store_id');
        $store = Store::find($storeId);
        $category = MenuCategory::find($validated['category_id']);
        
        // カルテからの予約かチェック
        $isFromMedicalRecord = $request->get('from_medical_record', false);
        $customerId = $request->get('customer_id');
        
        // マイページから予約の場合（JavaScriptでsessionStorageに保存されたデータを使用）
        $fromMypage = $request->get('from_mypage', false);
        if (!$customerId && $fromMypage) {
            $customerId = $request->get('existing_customer_id');
        }
        
        // 顧客のサブスクリプション状態を確認
        $hasSubscription = false;
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $hasSubscription = $customer->hasActiveSubscription();
            }
        }
        
        // 新規・既存判定
        $isNewCustomer = true;
        if ($customerId) {
            $existingReservations = Reservation::where('customer_id', $customerId)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->count();
            $isNewCustomer = $existingReservations === 0;
        }
        
        // マイページからの場合は必ず既存顧客として扱う
        if ($fromMypage) {
            $isNewCustomer = false;
        }
        
        // カテゴリーに属するメニューを時間別に取得
        \Log::info('Fetching menus', [
            'store_id' => $storeId, 
            'category_id' => $validated['category_id'],
            'category_name' => $category->name ?? 'Unknown'
        ]);
        
        $menusQuery = Menu::where('store_id', $storeId)
            ->where('category_id', $validated['category_id'])
            ->where('is_available', true)
            ->where('is_visible_to_customer', true);
            
        // サブスク限定メニューのフィルタリング
        if (!$hasSubscription) {
            $menusQuery->where('is_subscription_only', false);
        }
        
        // 顧客タイプ制限の適用
        if ($isNewCustomer) {
            $menusQuery->whereIn('customer_type_restriction', ['all', 'new_only']);
        } else {
            $menusQuery->whereIn('customer_type_restriction', ['all', 'existing']);
        }
        
        // medical_record_only機能は削除済み - customer_type_restrictionで代替
        
        // SQLクエリをログに出力
        $sql = $menusQuery->toSql();
        $bindings = $menusQuery->getBindings();
        \Log::info('SQL Query', ['sql' => $sql, 'bindings' => $bindings]);
        
        $menus = $menusQuery->orderBy('sort_order')
            ->orderBy('duration_minutes')
            ->orderBy('price')
            ->get();
            
        \Log::info('Found menus', [
            'total_count' => $menus->count(),
            'menus' => $menus->map(function($m) {
                return ['id' => $m->id, 'name' => $m->name, 'duration' => $m->duration_minutes];
            })
        ]);
            
        // オプションメニューは除外して、sort_order順にそのまま渡す
        $sortedMenus = $menus->where('duration_minutes', '>', 0);
        
        // 互換性のため、時間別グループ化も残す（ただし表示はsortedMenusを使う）
        $menusByDuration = $sortedMenus->groupBy('duration_minutes')->sortKeys();
        
        return view('reservation.time-select', compact('menusByDuration', 'sortedMenus', 'store', 'category', 'hasSubscription'));
    }
    
    /**
     * 旧メニュー選択（互換性保持）
     */
    public function selectMenu(Request $request)
    {
        return $this->selectCategory($request);
    }
    
    public function selectMenuWithStore($storeId, Request $request)
    {
        // 指定された店舗IDが有効かチェック
        $store = Store::where('id', $storeId)->where('is_active', true)->first();
        if (!$store) {
            return redirect()->route('reservation.select-store')->with('error', '指定された店舗が見つかりません。');
        }
        
        // セッションに店舗IDを保存
        Session::put('selected_store_id', $storeId);
        
        // カルテからの予約かチェック
        $isFromMedicalRecord = $request->get('from_medical_record', false);
        $customerId = $request->get('customer_id');
        
        // 顧客が新規か既存かを判定
        $isNewCustomer = true;
        if ($customerId) {
            $existingReservations = Reservation::where('customer_id', $customerId)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->count();
            $isNewCustomer = $existingReservations === 0;
        }
        
        // 適切なメニューを取得
        $menusQuery = Menu::where('store_id', $storeId)
            ->where('is_available', true)
            ->where('show_in_upsell', false);  // メインメニューのみ
            
        // forCustomerTypeスコープが存在する場合のみ適用
        if (method_exists(Menu::class, 'scopeForCustomerType')) {
            $menusQuery->forCustomerType($isNewCustomer, $isFromMedicalRecord);
        }
        
        $menus = $menusQuery->orderBy('display_order')
            ->orderBy('id')
            ->get();
            
        return view('reservation.menu-select', compact('menus', 'store'));
    }
    
    public function storeMenu(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'option_ids' => 'nullable|string'
        ]);
        
        // メニュー情報をセッションに保存
        $menu = Menu::find($validated['menu_id']);
        Session::put('reservation_menu', $menu);
        
        // オプション情報をセッションに保存
        $selectedOptions = [];
        if (!empty($validated['option_ids'])) {
            // カンマ区切り文字列を配列に変換
            $optionIds = array_filter(explode(',', $validated['option_ids']));
            if (!empty($optionIds)) {
                $selectedOptions = Menu::whereIn('id', $optionIds)
                    ->where('is_available', true)
                    ->where('show_in_upsell', true)
                    ->get();
            }
        }
        Session::put('reservation_options', $selectedOptions);
        
        // ○×形式のカレンダーページへリダイレクト
        return redirect()->route('reservation.index');
    }

    public function index(Request $request)
    {
        // 通常予約の場合、サブスク関連のセッション情報をクリア
        if (!Session::get('is_subscription_booking')) {
            Session::forget(['customer_id', 'subscription_id', 'from_mypage']);
            \Log::info('通常予約のため、サブスク関連セッション情報をクリア');
        }
        
        // セッションから情報を取得
        $selectedMenu = Session::get('reservation_menu');
        $selectedOptions = Session::get('reservation_options', collect());
        $selectedStoreId = Session::get('selected_store_id');
        
        // サブスク予約以外で、メニューが選択されていない場合はメニュー選択ページへリダイレクト
        if (!$selectedMenu && !Session::get('is_subscription_booking')) {
            return redirect()->route('reservation.menu');
        }
        
        // サブスク予約以外で、店舗が選択されていない場合は店舗選択ページへリダイレクト
        if (!$selectedStoreId && !Session::get('is_subscription_booking')) {
            return redirect('/stores');
        }
        
        $selectedStore = Store::find($selectedStoreId);
        
        // 店舗が見つからない場合
        if (!$selectedStore || !$selectedStore->is_active) {
            Session::forget('selected_store_id');
            return redirect('/stores')->with('error', '選択された店舗が見つかりません。');
        }
        
        $stores = Store::where('is_active', true)->get();
        
        // 選択された週を取得（デフォルトは今週）
        $weekOffset = (int) $request->get('week', 0);
        
        // 店舗の最大予約可能日数を取得（デフォルト30日）
        $maxAdvanceDays = $selectedStore->max_advance_days ?? 30;
        
        // 最大週数を計算（最大日数を7で割って切り上げ）
        $maxWeeks = ceil($maxAdvanceDays / 7);
        
        // 週オフセットが最大値を超えないように制限
        if ($weekOffset >= $maxWeeks) {
            $weekOffset = $maxWeeks - 1;
        }
        
        // 今日から始まる7日間を表示（月曜始まりではなく）
        $startDate = Carbon::today()->addWeeks($weekOffset);
        
        // 1週間分の日付を生成
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dates[] = [
                'date' => $date,
                'formatted' => $date->format('j'),  // 日付のみ（例: 2, 3, 4）
                'day' => $date->format('(D)'),
                'day_jp' => $this->getDayInJapanese($date->dayOfWeek),
                'is_today' => $date->isToday(),
                'is_past' => $date->isPast(),
            ];
        }
        
        // 店舗の営業時間範囲内のすべての時間枠を生成
        $timeSlots = $this->generateAllTimeSlots($selectedStore);
        
        // 選択されたメニューとオプションの合計時間を計算
        $totalDuration = $selectedMenu->duration ?? 60;
        foreach ($selectedOptions as $option) {
            $totalDuration += $option->duration ?? 0;
        }
        
        // 各日の営業時間を取得して予約状況を生成
        $availability = $this->getAvailability($selectedStoreId, $selectedStore, $startDate, $dates, $totalDuration);
        
        return view('reservation.public.index', compact(
            'stores',
            'selectedMenu',
            'selectedOptions',
            'selectedStore',
            'dates',
            'timeSlots',
            'availability',
            'weekOffset',
            'maxWeeks'
        ));
    }
    
    /**
     * オプション選択画面
     */
    public function selectOptions(Request $request, Menu $menu)
    {
        $menu->load(['options' => function($query) {
            $query->where('is_active', true)
                  ->orderBy('sort_order')
                  ->orderBy('name');
        }]);
        
        return view('reservation.option-select', compact('menu'));
    }
    
    /**
     * オプション保存して次へ
     */
    public function storeOptions(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'options' => 'array',
            'options.*.selected' => 'sometimes|boolean',
            'options.*.quantity' => 'sometimes|integer|min:1',
        ]);
        
        $menu = Menu::find($validated['menu_id']);
        $selectedOptions = [];
        $totalOptionPrice = 0;
        $totalOptionDuration = 0;
        
        if (isset($validated['options'])) {
            foreach ($validated['options'] as $optionId => $optionData) {
                if (isset($optionData['selected']) && $optionData['selected']) {
                    $option = MenuOption::find($optionId);
                    if ($option && $option->menu_id == $menu->id) {
                        $quantity = $optionData['quantity'] ?? 1;
                        $selectedOptions[] = [
                            'id' => $option->id,
                            'name' => $option->name,
                            'quantity' => $quantity,
                            'price' => $option->price,
                            'duration' => $option->duration_minutes,
                        ];
                        $totalOptionPrice += $option->price * $quantity;
                        $totalOptionDuration += $option->duration_minutes * $quantity;
                    }
                }
            }
        }
        
        // セッションに保存
        Session::put('selected_menu_id', $menu->id);
        Session::put('selected_options', $selectedOptions);
        Session::put('total_option_price', $totalOptionPrice);
        Session::put('total_option_duration', $totalOptionDuration);
        
        // カレンダー選択へ
        return redirect()->route('reservation.index');
    }
    
    private function generateAllTimeSlots($store)
    {
        $slots = [];
        
        // 店舗の営業時間から最小・最大時間を取得
        $businessHours = collect($store->business_hours ?? []);
        $minTime = null;
        $maxTime = null;
        
        foreach ($businessHours as $dayHours) {
            if (!($dayHours['is_closed'] ?? false) && !empty($dayHours['open_time']) && !empty($dayHours['close_time'])) {
                $openTime = Carbon::createFromTimeString($dayHours['open_time']);
                $closeTime = Carbon::createFromTimeString($dayHours['close_time']);
                
                if ($minTime === null || $openTime->lt($minTime)) {
                    $minTime = $openTime;
                }
                if ($maxTime === null || $closeTime->gt($maxTime)) {
                    $maxTime = $closeTime;
                }
            }
        }
        
        // デフォルト値
        if ($minTime === null) {
            $minTime = Carbon::createFromTime(10, 0);
        }
        if ($maxTime === null) {
            $maxTime = Carbon::createFromTime(18, 0);
        }
        
        // 店舗の予約間隔を取得（デフォルト30分）
        $interval = $store->reservation_slot_duration ?? 30;
        
        // スロットを生成
        $current = $minTime->copy();
        while ($current <= $maxTime) {
            $slots[] = $current->format('H:i');
            $current->addMinutes($interval);
        }
        
        return $slots;
    }
    
    private function generateTimeSlotsForDay($store, $dayOfWeek)
    {
        $slots = [];
        
        // 店舗の営業時間を取得
        $businessHours = collect($store->business_hours ?? []);
        $dayHours = $businessHours->firstWhere('day', $dayOfWeek);
        
        // 休業日の場合は空配列を返す
        if (!$dayHours || ($dayHours['is_closed'] ?? false)) {
            return [];
        }
        
        // 営業時間から開始・終了時刻を取得
        $openTime = $dayHours['open_time'] ?? '10:00';
        $closeTime = $dayHours['close_time'] ?? '23:30';
        
        // 店舗の予約間隔を取得（デフォルト30分）
        $interval = $store->reservation_slot_duration ?? 30;
        
        $start = Carbon::createFromTimeString($openTime);
        $end = Carbon::createFromTimeString($closeTime);
        
        while ($start <= $end) {
            $slots[] = $start->format('H:i');
            $start->addMinutes($interval);
        }
        
        return $slots;
    }
    
    private function getAvailability($storeId, $store, $startDate, $dates, $menuDuration = 60)
    {
        $availability = [];
        $endDate = $startDate->copy()->addDays(6);
        
        // 店舗の予約設定を取得
        $store = Store::find($storeId);
        $minBookingHours = $store->min_booking_hours ?? 1;
        $allowSameDayBooking = $store->allow_same_day_booking ?? true;
        
        // 既存の予約を取得（キャンセル以外のすべての予約を対象）
        $existingReservations = Reservation::where('store_id', $storeId)
            ->whereDate('reservation_date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('reservation_date', '<=', $endDate->format('Y-m-d'))
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->get()
            ->groupBy(function($reservation) {
                return Carbon::parse($reservation->reservation_date)->format('Y-m-d');
            });
        
        // ブロックされた時間帯を取得
        $blockedPeriods = BlockedTimePeriod::where('store_id', $storeId)
            ->whereDate('blocked_date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('blocked_date', '<=', $endDate->format('Y-m-d'))
            ->get()
            ->groupBy(function($block) {
                return Carbon::parse($block->blocked_date)->format('Y-m-d');
            });
        
        // シフト情報を取得
        $shifts = Shift::where('store_id', $storeId)
            ->whereDate('shift_date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('shift_date', '<=', $endDate->format('Y-m-d'))
            ->where('is_available_for_reservation', true)
            ->whereHas('user', function($query) {
                $query->where('is_active_staff', true);
            })
            ->get()
            ->groupBy(function($shift) {
                return Carbon::parse($shift->shift_date)->format('Y-m-d');
            });
        
        foreach ($dates as $dateInfo) {
            $date = $dateInfo['date'];
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = strtolower($date->format('l'));
            $dayReservations = $existingReservations->get($dateStr, collect());
            $dayBlocks = $blockedPeriods->get($dateStr, collect());
            
            // その日の営業時間に基づいて時間枠を生成
            $timeSlots = $this->generateTimeSlotsForDay($store, $dayOfWeek);
            
            // 休業日の場合はその店舗の通常時間枠をすべてfalseに
            if (empty($timeSlots)) {
                // メインのtimeSlotsを使用
                foreach ($this->generateAllTimeSlots($store) as $slot) {
                    $availability[$dateStr][$slot] = false;
                }
                continue;
            }
            
            // その日の営業時間を取得
            $dayBusinessHours = collect($store->business_hours ?? [])->firstWhere('day', $dayOfWeek);
            $closeTime = null;
            if ($dayBusinessHours && !($dayBusinessHours['is_closed'] ?? false)) {
                $closeTime = Carbon::parse($dateStr . ' ' . $dayBusinessHours['close_time']);
            }
            
            foreach ($timeSlots as $slot) {
                $slotTime = Carbon::parse($dateStr . ' ' . $slot);
                $slotEnd = $slotTime->copy()->addMinutes($menuDuration);
                
                // 過去の日付は予約不可
                if ($date->lt(Carbon::today())) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }
                
                // 当日の過去時間は予約不可
                if ($date->isToday() && $slotTime->lt(now()->addHours($minBookingHours))) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }
                
                // 施術終了時刻が営業終了時刻を超える場合は予約不可
                if ($closeTime && $slotEnd->gt($closeTime)) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }
                
                // ブロックされた時間帯との重複チェック
                $isBlocked = $dayBlocks->contains(function ($block) use ($slotTime, $slotEnd) {
                    $blockStart = Carbon::parse($block->start_time);
                    $blockEnd = Carbon::parse($block->end_time);
                    
                    return (
                        ($slotTime->gte($blockStart) && $slotTime->lt($blockEnd)) ||
                        ($slotEnd->gt($blockStart) && $slotEnd->lte($blockEnd)) ||
                        ($slotTime->lte($blockStart) && $slotEnd->gte($blockEnd))
                    );
                });
                
                if ($isBlocked) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }
                
                // 店舗の同時予約可能数を初期化
                $maxConcurrent = $store->main_lines_count ?? 1;
                
                // シフトチェック：スタッフシフトベースの場合のみチェック
                if ($store->use_staff_assignment) {
                    $dayShifts = $shifts->get($dateStr, collect());
                    $availableStaffCount = $dayShifts->filter(function ($shift) use ($slotTime, $slotEnd) {
                        $shiftStart = Carbon::parse($shift->shift_date->format('Y-m-d') . ' ' . $shift->start_time);
                        $shiftEnd = Carbon::parse($shift->shift_date->format('Y-m-d') . ' ' . $shift->end_time);
                        
                        // 予約時間がシフト時間に収まるかチェック（休憩時間は考慮しない）
                        return $slotTime->gte($shiftStart) && $slotEnd->lte($shiftEnd);
                    })->count();
                    
                    // min(設備台数, スタッフ数) でキャパシティを決定
                    $equipmentCapacity = $store->shift_based_capacity ?? 1;
                    $maxConcurrent = min($equipmentCapacity, $availableStaffCount);
                    
                    if ($maxConcurrent <= 0) {
                        $availability[$dateStr][$slot] = false;
                        continue;
                    }
                }
                // 営業時間ベースの場合はシフトチェックをスキップ
                
                // 予約が重複していないかチェック（席数を考慮）
                $overlappingCount = $dayReservations->filter(function ($reservation) use ($slotTime, $slotEnd) {
                    $reservationStart = Carbon::parse($reservation->start_time);
                    $reservationEnd = Carbon::parse($reservation->end_time);
                    
                    // 時間が重なっているかチェック
                    return (
                        ($slotTime->gte($reservationStart) && $slotTime->lt($reservationEnd)) ||
                        ($slotEnd->gt($reservationStart) && $slotEnd->lte($reservationEnd)) ||
                        ($slotTime->lte($reservationStart) && $slotEnd->gte($reservationEnd))
                    );
                })->count();
                
                // 店舗の同時予約可能数を確認
                if ($store->use_staff_assignment) {
                    // シフトベースの場合は既に計算済みのslotCapacityを使用
                    $maxConcurrent = $slotCapacity;
                } else {
                    // 営業時間ベースの場合はmain_lines_countを使用
                    $maxConcurrent = $store->main_lines_count ?? 1;
                }
                $availability[$dateStr][$slot] = $overlappingCount < $maxConcurrent;
            }
        }
        
        return $availability;
    }
    
    private function getDayInJapanese($dayOfWeek)
    {
        $days = ['日', '月', '火', '水', '木', '金', '土'];
        return $days[$dayOfWeek];
    }
    
    /**
     * サブスク予約の準備（セッションに店舗とメニューを設定してカレンダーへ）
     */
    public function prepareSubscriptionReservation(Request $request)
    {
        \Log::info('サブスク予約準備開始', $request->all());
        
        try {
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'subscription_id' => 'required|exists:customer_subscriptions,id',
                'store_id' => 'required|exists:stores,id',
                'menu_id' => 'required|exists:menus,id',
                'store_name' => 'required|string',
                'plan_name' => 'required|string'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('バリデーションエラー', ['errors' => $e->errors()]);
            return redirect('/customer/dashboard')->with('error', 'サブスク予約の準備に失敗しました。入力データを確認してください。');
        }
        
        // サブスク契約の確認
        $subscription = CustomerSubscription::where('id', $validated['subscription_id'])
            ->where('customer_id', $validated['customer_id'])
            ->where('status', 'active')
            ->where('payment_failed', false)
            ->where('is_paused', false)
            ->first();
            
        if (!$subscription) {
            \Log::error('サブスクリプションが見つかりません', ['subscription_id' => $validated['subscription_id']]);
            return redirect('/customer/dashboard')->with('error', 'アクティブなサブスクリプションが見つかりません。');
        }
        
        // 利用回数チェック
        if ($subscription->hasReachedLimit()) {
            \Log::info('利用上限に達しています', ['subscription_id' => $subscription->id]);
            return redirect('/customer/dashboard')->with('error', '今月の利用上限に達しています。');
        }
        
        // メニュー情報を取得
        $menu = Menu::find($validated['menu_id']);
        if (!$menu) {
            \Log::error('メニューが見つかりません', ['menu_id' => $validated['menu_id']]);
            return redirect('/customer/dashboard')->with('error', 'メニュー情報が見つかりません。');
        }
        
        // セッションに必要な情報を保存
        Session::put('selected_store_id', $validated['store_id']);
        Session::put('reservation_menu', $menu);
        Session::put('is_subscription_booking', true);
        Session::put('subscription_id', $subscription->id);
        Session::put('customer_id', $validated['customer_id']);
        Session::put('from_mypage', true);
        
        \Log::info('サブスク予約準備完了、カレンダーへリダイレクト');
        
        // サブスク予約では店舗・メニューが確定しているので、直接カレンダーページへ
        return redirect('/reservation/calendar');
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'menu_id' => 'required|exists:menus,id',
            'date' => 'required|date',
            'time' => 'required',
            'last_name' => 'required|string|max:50',
            'first_name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:500',
        ]);
        
        // サブスク予約の場合のみ、5日間隔制限をチェック
        if (Session::has('is_subscription_booking') && Session::get('is_subscription_booking') === true) {
            $customerId = Session::get('customer_id');
            \Log::info('サブスク予約での5日間隔チェック開始', [
                'customer_id' => $customerId,
                'target_date' => $validated['date'],
                'is_subscription_booking' => Session::get('is_subscription_booking')
            ]);
            
            if ($customerId) {
                $this->validateFiveDayInterval($customerId, $validated['date']);
            } else {
                \Log::warning('サブスク予約なのにcustomer_idがセッションに設定されていません');
            }
        } else {
            \Log::info('通常予約のため5日間隔制限をスキップ', [
                'is_subscription_booking' => Session::get('is_subscription_booking')
            ]);
        }
        
        // 日程変更の場合の処理
        if (Session::has('change_reservation_id')) {
            $reservationId = Session::get('change_reservation_id');
            $existingReservation = Reservation::find($reservationId);
            
            if ($existingReservation) {
                // 既存予約を更新
                $menu = Menu::find($validated['menu_id']);
                $startTime = Carbon::parse($validated['date'] . ' ' . $validated['time']);
                $endTime = $startTime->copy()->addMinutes($menu->duration ?? 60);
                
                $existingReservation->update([
                    'reservation_date' => $validated['date'],
                    'start_time' => $validated['time'],
                    'end_time' => $endTime->format('H:i:s'),
                    'store_id' => $validated['store_id'],
                    'menu_id' => $validated['menu_id'],
                ]);
                
                // セッションをクリア
                Session::forget('change_reservation_id');
                Session::forget('is_reservation_change');
                Session::forget('original_reservation_date');
                Session::forget('original_reservation_time');
                
                // 予約変更完了ページへ
                return redirect()->route('reservation.complete', $existingReservation->reservation_number)
                    ->with('success', '予約日時を変更しました');
            }
        }
        
        // まず最初に電話番号で既存予約をチェック（新規顧客作成前）
        $existingCustomerByPhone = Customer::where('phone', $validated['phone'])->first();
        if ($existingCustomerByPhone) {
            // サブスクリプション会員かチェック
            $hasActiveSubscription = $existingCustomerByPhone->hasActiveSubscription();
            
            // デバッグログ
            \Log::info('Subscription check for customer', [
                'customer_id' => $existingCustomerByPhone->id,
                'phone' => $existingCustomerByPhone->phone,
                'has_active_subscription' => $hasActiveSubscription,
                'store_id' => $validated['store_id'] ?? null
            ]);
            
            // サブスク会員でない場合のみ制限をチェック
            if (!$hasActiveSubscription) {
                // 最新の予約（完了済みも含む）を取得
                $latestReservation = Reservation::where('customer_id', $existingCustomerByPhone->id)
                    ->whereIn('status', ['pending', 'confirmed', 'booked', 'completed'])
                    ->orderBy('reservation_date', 'desc')
                    ->orderBy('start_time', 'desc')
                    ->first();
                    
                // 5日間の制限チェック（予約日が送信されている場合のみ）
                if ($latestReservation && isset($validated['date'])) {
                    $lastVisitDate = Carbon::parse($latestReservation->reservation_date);
                    $requestedDate = Carbon::parse($validated['date']);
                    $daysDiff = $lastVisitDate->diffInDays($requestedDate, false);
                    
                    if ($daysDiff < 5) {
                        $nextAvailableDate = $lastVisitDate->addDays(5)->format('Y年m月d日');
                        return back()->with('error', "前回のご予約から最低5日間空ける必要があります。次回予約可能日: {$nextAvailableDate}以降");
                    }
                }
                
                // 既存の未来予約チェック（重複防止）
                $futureReservations = Reservation::where('customer_id', $existingCustomerByPhone->id)
                    ->whereIn('status', ['pending', 'confirmed', 'booked'])
                    ->where('reservation_date', '>=', today())
                    ->orderBy('reservation_date')
                    ->orderBy('start_time')
                    ->with(['store', 'menu'])
                    ->get();
                    
                if ($futureReservations->isNotEmpty()) {
                    return back()->with('error', 'この電話番号で既にご予約があります。2回目以降のお客様は、管理画面から予約の変更・追加を行ってください。');
                }
            } else {
                // サブスク会員の場合、月の利用回数をチェック
                $activeSubscription = $existingCustomerByPhone->activeSubscription()->first();
                if ($activeSubscription && $activeSubscription->monthly_limit) {
                    $currentMonthReservations = Reservation::where('customer_id', $existingCustomerByPhone->id)
                        ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
                        ->whereMonth('reservation_date', now()->month)
                        ->whereYear('reservation_date', now()->year)
                        ->count();
                    
                    if ($currentMonthReservations >= $activeSubscription->monthly_limit) {
                        return back()->with('error', "今月のサブスクリプション利用回数（{$activeSubscription->monthly_limit}回）に達しています。");
                    }
                }
            }
        }
        
        DB::beginTransaction();
        try {
            // 顧客を作成または取得
            $customer = Customer::firstOrCreate(
                ['phone' => $validated['phone']],
                [
                    'last_name' => $validated['last_name'],
                    'first_name' => $validated['first_name'],
                    'last_name_kana' => '', // カナは空文字で保存
                    'first_name_kana' => '', // カナは空文字で保存
                    'email' => $validated['email'],
                    'customer_number' => Customer::generateCustomerNumber(),
                ]
            );
            
            // メニュー情報を取得
            $menu = Menu::find($validated['menu_id']);
            $selectedOptions = Session::get('reservation_options', collect());
            
            // 合計金額と時間を計算
            $totalAmount = $menu->price ?? 0;
            $totalDuration = $menu->duration ?? 60;
            
            foreach ($selectedOptions as $option) {
                $totalAmount += $option->price;
                $totalDuration += $option->duration;
            }
            
            // 店舗設定を取得
            $store = Store::find($validated['store_id']);
            
            // シフトチェック: スタッフシフトベースの場合のみチェック
            if ($store->use_staff_assignment) {
                $reservationDateTime = Carbon::parse($validated['date'] . ' ' . $validated['time']);
                $endTime = $reservationDateTime->copy()->addMinutes($totalDuration);
                
                $availableStaff = Shift::where('store_id', $validated['store_id'])
                    ->where('shift_date', $validated['date'])
                    ->where('start_time', '<=', $validated['time'])
                    ->where('end_time', '>=', $endTime->format('H:i'))
                    ->where('is_available_for_reservation', true)
                    ->whereHas('user', function($query) {
                        $query->where('is_active_staff', true);
                    })
                    ->exists();
                
                if (!$availableStaff) {
                    DB::rollback();
                    return back()->with('error', '申し訳ございません。選択された時間帯に対応可能なスタッフがおりません。別の時間帯をお選びください。');
                }
            }
            // 営業時間ベースの場合はシフトチェックをスキップ
            
            // 予約を作成
            $reservation = Reservation::create([
                'reservation_number' => Reservation::generateReservationNumber(),
                'store_id' => $validated['store_id'],
                'customer_id' => $customer->id,
                'menu_id' => $validated['menu_id'],
                'reservation_date' => $validated['date'],
                'start_time' => $validated['time'],
                'end_time' => Carbon::parse($validated['time'])->addMinutes($totalDuration)->format('H:i'),
                'status' => 'booked',
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'],
                'source' => 'online',
            ]);
            
            // オプションメニューを関連付け
            foreach ($selectedOptions as $option) {
                $reservation->optionMenus()->attach($option->id, [
                    'price' => $option->price,
                    'duration' => $option->duration,
                ]);
            }
            
            // 予約関連のセッションをクリア（完了画面表示後にクリアする）
            // ここではクリアしない - 完了画面表示後にクリアする
            \Log::info('予約作成完了時のセッション', [
                'selected_store_id' => Session::get('selected_store_id'),
                'reservation_menu' => Session::has('reservation_menu'),
                'reservation_options' => Session::has('reservation_options')
            ]);
            
            DB::commit();
            
            // 新規予約通知を送信
            event(new ReservationCreated($reservation));
            
            // LINE連携チェックと通知送信
            if ($customer->line_user_id) {
                // LINE連携済みの場合は即時確認通知を試行
                \Log::info('LINE連携済み顧客：即時確認通知を試行', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'line_user_id' => $customer->line_user_id
                ]);
                
                // 即時LINE送信を試行
                $confirmationService = app(\App\Services\ReservationConfirmationService::class);
                if ($confirmationService->sendLineConfirmation($reservation)) {
                    $confirmationService->markConfirmationSent($reservation, 'line');
                    
                    \Log::info('即時LINE確認通知送信成功', [
                        'reservation_id' => $reservation->id,
                        'customer_id' => $customer->id
                    ]);
                } else {
                    \Log::warning('即時LINE確認通知送信失敗、フォールバック予約', [
                        'reservation_id' => $reservation->id,
                        'customer_id' => $customer->id
                    ]);
                }
            } else if ($store->line_enabled && $store->line_liff_id) {
                // LINE未連携だが、店舗のLINE設定が有効な場合は連携案内を送信
                \Log::info('LINE未連携顧客：連携案内を送信予定', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'store_id' => $store->id
                ]);
                
                // LINE連携用トークンを生成
                $accessToken = \App\Models\CustomerAccessToken::create([
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'token' => \Illuminate\Support\Str::random(32),
                    'purpose' => 'line_linking',
                    'expires_at' => now()->addDays(7),
                    'metadata' => [
                        'reservation_id' => $reservation->id,
                        'reservation_number' => $reservation->reservation_number,
                        'linking_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                        'from_reservation' => true
                    ]
                ]);
                
                \Log::info('LINE連携トークン生成完了', [
                    'token' => $accessToken->token,
                    'linking_code' => $accessToken->metadata['linking_code']
                ]);
                
                // 既存友達の可能性をチェック - LINE User IDが不明でも店舗のLINEから連携ボタンを送信試行
                $this->tryLinkingForPotentialFriend($customer, $store, $accessToken);
            }
            
            // 5分遅延フォールバック確認通知をスケジュール（二重送信防止チェック付き）
            $delayMinutes = config('reservation.fallback_delay_minutes', 5);
            SendReservationConfirmationWithFallback::dispatch($reservation)
                ->delay(now()->addMinutes($delayMinutes));
            
            \Log::info('予約確認通知フォールバックジョブをスケジュール', [
                'reservation_id' => $reservation->id,
                'delay_minutes' => $delayMinutes,
                'scheduled_at' => now()->addMinutes($delayMinutes)->toISOString()
            ]);
            
            \Log::info('予約完了、リダイレクト処理', [
                'reservation_number' => $reservation->reservation_number,
                'route' => route('reservation.complete', $reservation->reservation_number)
            ]);
            
            return redirect()->route('reservation.complete', $reservation->reservation_number);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Reservation creation failed: ' . $e->getMessage());
            return back()->with('error', '予約の作成に失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 予約変更準備（セッションに情報を保存してカレンダーへリダイレクト）
     */
    public function prepareChange(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|integer',
            'store_id' => 'required|integer',
            'menu_id' => 'required|integer',
            'store_name' => 'nullable|string',
            'menu_name' => 'nullable|string',
            'menu_price' => 'nullable|numeric',
            'menu_duration' => 'nullable|integer'
        ]);
        
        // メニュー情報を取得またはリクエストから作成
        $menu = Menu::find($validated['menu_id']);
        if (!$menu) {
            // メニューが見つからない場合はリクエストデータから作成
            $menu = new Menu();
            $menu->id = $validated['menu_id'];
            $menu->name = $validated['menu_name'] ?? 'メニュー';
            $menu->price = $validated['menu_price'] ?? 0;
            $menu->duration = $validated['menu_duration'] ?? 60;
        }
        
        // 元の予約情報を取得
        $originalReservation = Reservation::find($validated['reservation_id']);
        
        // セッションに保存
        Session::put('reservation_menu', $menu);
        Session::put('selected_store_id', $validated['store_id']);
        Session::put('is_reservation_change', true);
        Session::put('change_reservation_id', $validated['reservation_id']);
        
        // 元の予約日時も保存（カレンダーで強調表示用）
        if ($originalReservation) {
            Session::put('original_reservation_date', $originalReservation->reservation_date);
            Session::put('original_reservation_time', $originalReservation->start_time);
        }
        
        // カレンダーページへリダイレクト
        return redirect()->route('reservation.index');
    }
    
    public function complete($reservationNumber)
    {
        \Log::info('予約完了画面表示開始', ['reservation_number' => $reservationNumber]);
        
        $reservation = Reservation::with(['store', 'customer', 'menu', 'optionMenus'])
            ->where('reservation_number', $reservationNumber)
            ->firstOrFail();

        // LINE QRコード用トークンを生成
        $lineToken = null;
        $lineQrCodeUrl = null;
        $customerToken = null;
        
        // LINE連携用トークンを生成（未連携の場合）
        if (!$reservation->customer->line_user_id && $reservation->store->line_enabled) {
            // LINE連携用アクセストークンを生成
            $accessToken = \App\Models\CustomerAccessToken::create([
                'customer_id' => $reservation->customer->id,
                'store_id' => $reservation->store->id,
                'token' => \Illuminate\Support\Str::random(32),
                'purpose' => 'line_linking',
                'expires_at' => now()->addDays(30),
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'linking_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                ]
            ]);
            
            $customerToken = $accessToken->token;
            
            // QRコード用URL（友達追加用）
            if ($reservation->store->line_add_friend_url) {
                $lineQrCodeUrl = $reservation->store->line_add_friend_url;
            }
        }
        
        // 完了画面表示時にセッションをクリア
        Session::forget(['reservation_menu', 'reservation_options', 'selected_store_id']);
            
        return view('reservation.public.complete', compact('reservation', 'lineToken', 'lineQrCodeUrl', 'customerToken'));
    }
    
    /**
     * 5日間隔制限をバリデーション
     */
    private function validateFiveDayInterval($customerId, $targetDate)
    {
        \Log::info('5日間隔バリデーション実行', [
            'customer_id' => $customerId,
            'target_date' => $targetDate
        ]);
        
        // 顧客の既存予約を取得（キャンセル済みを除く）
        $existingReservations = Reservation::where('customer_id', $customerId)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->get();
            
        \Log::info('既存予約確認', [
            'customer_id' => $customerId,
            'existing_reservations_count' => $existingReservations->count(),
            'reservations' => $existingReservations->pluck('reservation_date', 'id')->toArray()
        ]);
            
        $targetDateTime = Carbon::parse($targetDate);
        
        foreach ($existingReservations as $reservation) {
            $reservationDate = Carbon::parse($reservation->reservation_date);
            $daysDiff = abs($targetDateTime->diffInDays($reservationDate));
            
            \Log::info('予約日間隔チェック', [
                'reservation_id' => $reservation->id,
                'reservation_date' => $reservationDate->format('Y-m-d'),
                'target_date' => $targetDateTime->format('Y-m-d'),
                'days_diff' => $daysDiff
            ]);
            
            // 同じ日は除外、1-5日以内をチェック
            if ($daysDiff > 0 && $daysDiff <= 5) {
                \Log::warning('5日間隔制限違反', [
                    'customer_id' => $customerId,
                    'conflicting_reservation_date' => $reservationDate->format('Y-m-d'),
                    'target_date' => $targetDateTime->format('Y-m-d'),
                    'days_diff' => $daysDiff
                ]);
                
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'date' => '前回の予約から5日以内のため、この日は予約できません。最後の予約日: ' . $reservationDate->format('Y年m月d日')
                ]);
            }
        }
        
        \Log::info('5日間隔制限チェック完了（問題なし）');
    }
    
    /**
     * 既存友達の可能性をチェックして連携ボタンを送信
     */
    private function tryLinkingForPotentialFriend($customer, $store, $accessToken)
    {
        try {
            \Log::info('既存友達チェック開始', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'store_id' => $store->id
            ]);
            
            // LINE連携URLを生成
            $linkingUrl = route('line.link') . '?token=' . $accessToken->token . '&store_id=' . $store->id;
            
            \Log::info('連携URL生成完了', [
                'linking_url' => $linkingUrl,
                'token' => $accessToken->token
            ]);
            
            // 可能な LINE User ID のパターンを試す
            $potentialLineUserIds = $this->generatePotentialLineUserIds($customer);
            
            if (empty($potentialLineUserIds)) {
                \Log::info('潜在的LINE User ID見つからず', [
                    'customer_id' => $customer->id
                ]);
                return false;
            }
            
            $lineMessageService = app(\App\Services\LineMessageService::class);
            
            foreach ($potentialLineUserIds as $lineUserId) {
                \Log::info('LINE User ID試行中', [
                    'potential_line_user_id' => $lineUserId,
                    'customer_id' => $customer->id
                ]);
                
                // 送信を試行 - 成功した場合、その User ID を保存
                if ($lineMessageService->sendLinkingButton($lineUserId, $linkingUrl, $store)) {
                    // 成功した場合は LINE User ID を顧客に紐づけ
                    $customer->update(['line_user_id' => $lineUserId]);
                    
                    \Log::info('既存友達発見・連携ボタン送信成功', [
                        'customer_id' => $customer->id,
                        'line_user_id' => $lineUserId
                    ]);
                    return true;
                }
                
                // 失敗した場合は次を試行
                \Log::info('LINE User ID送信失敗', [
                    'potential_line_user_id' => $lineUserId
                ]);
            }
            
            \Log::info('既存友達見つからず', [
                'customer_id' => $customer->id,
                'tried_ids' => count($potentialLineUserIds)
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            \Log::error('既存友達チェック中エラー', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * 顧客の情報から潜在的なLINE User IDを生成
     */
    private function generatePotentialLineUserIds($customer)
    {
        $potentialIds = [];
        
        try {
            // 1. 同じ電話番号で過去に連携されたアカウントを検索
            $existingLinks = \Illuminate\Support\Facades\DB::table('customers')
                ->where('phone', $customer->phone)
                ->whereNotNull('line_user_id')
                ->where('id', '!=', $customer->id)
                ->pluck('line_user_id')
                ->toArray();
                
            $potentialIds = array_merge($potentialIds, $existingLinks);
            
            \Log::info('潜在LINE User ID検索結果', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'found_existing_links' => count($existingLinks),
                'total_potential_ids' => count($potentialIds)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('潜在LINE User ID検索エラー', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
        }
        
        // 重複を除去
        return array_unique($potentialIds);
    }
}