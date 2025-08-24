<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PublicReservationController extends Controller
{
    public function selectStore()
    {
        $stores = Store::where('is_active', true)->get();
        return view('reservation.store-select', compact('stores'));
    }
    
    public function storeStoreSelection(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id'
        ]);
        
        Session::put('selected_store_id', $validated['store_id']);
        return redirect()->route('reservation.menu');
    }
    
    public function selectMenu(Request $request)
    {
        // 店舗が選択されていない場合は店舗選択へ
        $storeId = Session::get('selected_store_id');
        if (!$storeId) {
            return redirect()->route('reservation.select-store');
        }
        
        $store = Store::find($storeId);
        
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
            'option_ids' => 'nullable|json'
        ]);
        
        // メニュー情報をセッションに保存
        $menu = Menu::find($validated['menu_id']);
        Session::put('reservation_menu', $menu);
        
        // オプション情報をセッションに保存
        $selectedOptions = [];
        if (!empty($validated['option_ids'])) {
            $optionIds = json_decode($validated['option_ids'], true);
            if (is_array($optionIds) && !empty($optionIds)) {
                $selectedOptions = Menu::whereIn('id', $optionIds)
                    ->where('is_available', true)
                    ->where('show_in_upsell', true)
                    ->get();
            }
        }
        Session::put('reservation_options', $selectedOptions);
        
        return redirect('/reservation/datetime');
    }

    public function showUpsell()
    {
        // セッションからメニュー情報を取得
        $selectedMenu = Session::get('reservation_menu');
        
        if (!$selectedMenu) {
            return redirect()->route('reservation.menu');
        }
        
        // アップセルメニューを取得
        $upsellMenus = Menu::where('is_available', true)
            ->where('show_in_upsell', true)
            ->where('id', '!=', $selectedMenu->id)
            ->orderBy('display_order')
            ->get();
        
        return view('reservation.upsell', compact('selectedMenu', 'upsellMenus'));
    }
    
    public function index(Request $request)
    {
        // セッションから情報を取得
        $selectedMenu = Session::get('reservation_menu');
        $selectedOptions = Session::get('reservation_options', collect());
        $selectedStoreId = Session::get('selected_store_id');
        
        // メニューが選択されていない場合はメニュー選択ページへリダイレクト
        if (!$selectedMenu) {
            return redirect()->route('reservation.menu');
        }
        
        // 店舗が選択されていない場合は店舗選択ページへリダイレクト
        if (!$selectedStoreId) {
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
        // 今日から始まる7日間を表示（月曜始まりではなく）
        $startDate = Carbon::today()->addWeeks($weekOffset);
        
        // 1週間分の日付を生成
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dates[] = [
                'date' => $date,
                'formatted' => $date->format('n月j日'),
                'day' => $date->format('(D)'),
                'day_jp' => $this->getDayInJapanese($date->dayOfWeek),
                'is_today' => $date->isToday(),
                'is_past' => $date->isPast(),
            ];
        }
        
        // 時間枠を生成（10:00〜17:30まで30分刻み）
        $timeSlots = $this->generateTimeSlots($selectedStore);
        
        // 予約状況を取得
        $availability = $this->getAvailability($selectedStoreId, $startDate, $timeSlots);
        
        return view('reservation.public.index', compact(
            'stores',
            'selectedMenu',
            'selectedOptions',
            'selectedStore',
            'dates',
            'timeSlots',
            'availability',
            'weekOffset'
        ));
    }
    
    private function generateTimeSlots($store)
    {
        $slots = [];
        $start = Carbon::createFromTime(10, 0);
        $end = Carbon::createFromTime(17, 30);
        
        while ($start <= $end) {
            $slots[] = $start->format('H:i');
            $start->addMinutes(30);
        }
        
        return $slots;
    }
    
    private function getAvailability($storeId, $startDate, $timeSlots)
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
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateStr = $date->format('Y-m-d');
            $dayReservations = $existingReservations->get($dateStr, collect());
            
            foreach ($timeSlots as $slot) {
                $slotTime = Carbon::parse($dateStr . ' ' . $slot);
                
                // 日曜は休み
                if ($date->dayOfWeek == 0) {
                    $availability[$dateStr][$slot] = false;
                    continue;
                }
                
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
                
                // 予約が重複していないかチェック
                $isBooked = $dayReservations->contains(function ($reservation) use ($slot) {
                    $reservationStart = Carbon::parse($reservation->start_time);
                    $reservationEnd = Carbon::parse($reservation->end_time);
                    $checkTime = Carbon::parse($slot);
                    
                    return $checkTime->between($reservationStart, $reservationEnd->subMinute());
                });
                
                $availability[$dateStr][$slot] = !$isBooked;
            }
        }
        
        return $availability;
    }
    
    private function getDayInJapanese($dayOfWeek)
    {
        $days = ['日', '月', '火', '水', '木', '金', '土'];
        return $days[$dayOfWeek];
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
        
        // まず最初に電話番号で既存予約をチェック（新規顧客作成前）
        $existingCustomerByPhone = Customer::where('phone', $validated['phone'])->first();
        if ($existingCustomerByPhone) {
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
            
            // セッションをクリア
            Session::forget(['reservation_menu', 'reservation_options']);
            
            DB::commit();
            
            return redirect()->route('reservation.complete', $reservation->reservation_number);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Reservation creation failed: ' . $e->getMessage());
            return back()->with('error', '予約の作成に失敗しました: ' . $e->getMessage());
        }
    }
    
    public function complete($reservationNumber)
    {
        $reservation = Reservation::with(['store', 'customer', 'menu', 'optionMenus'])
            ->where('reservation_number', $reservationNumber)
            ->firstOrFail();
            
        return view('reservation.public.complete', compact('reservation'));
    }
}