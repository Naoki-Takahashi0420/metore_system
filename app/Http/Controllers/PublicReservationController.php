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
use App\Models\User;
use App\Events\ReservationCreated;
use App\Events\ReservationCancelled;
use App\Events\ReservationChanged;
use App\Jobs\SendReservationConfirmationWithFallback;
use App\Services\ReservationContextService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PublicReservationController extends Controller
{
    public function selectStore(Request $request, ReservationContextService $contextService)
    {
        // パラメータベース: 暗号化されたコンテキストを取得
        $context = $contextService->extractContextFromRequest($request);

        // レガシートークン処理（後方互換性のため残しておく）
        if ($token = $request->get('token')) {
            $accessToken = CustomerAccessToken::where('token', $token)
                ->with(['customer', 'store'])
                ->first();

            if ($accessToken && $accessToken->isValid()) {
                // トークンを使用してコンテキストを生成
                $context = [
                    'type' => 'medical_record',
                    'customer_id' => $accessToken->customer_id,
                    'store_id' => $accessToken->store_id,
                    'is_existing_customer' => true,
                    'source' => 'medical_record_legacy'
                ];

                // トークン使用を記録
                $accessToken->recordUsage();

                // 店舗が指定されている場合は直接カテゴリー選択へ
                if ($accessToken->store_id) {
                    $encryptedContext = $contextService->encryptContext($context);
                    return redirect()->route('reservation.select-category', ['ctx' => $encryptedContext]);
                }
            }
        }

        // デバッグ: コンテキストの内容を確認
        \Log::info('[/stores] 受信したコンテキスト', [
            'context' => $context,
            'has_store_id' => isset($context['store_id']),
            'store_id' => $context['store_id'] ?? null
        ]);

        // コンテキストにstore_idが含まれている場合は直接カテゴリ選択へリダイレクト
        if ($context && isset($context['store_id'])) {
            \Log::info('[/stores] 店舗選択をスキップしてカテゴリ選択へリダイレクト', [
                'store_id' => $context['store_id']
            ]);
            $encryptedContext = $contextService->encryptContext($context);
            return redirect()->route('reservation.select-category', ['ctx' => $encryptedContext]);
        }

        // 新規予約の場合、デフォルトコンテキストを作成
        if (!$context) {
            $context = [
                'type' => 'new_reservation',
                'is_existing_customer' => false,
                'source' => 'public'
            ];
        }

        $stores = Store::where('is_active', true)->get();
        $encryptedContext = $contextService->encryptContext($context);

        // レガシーサポート: 古いパラメータ形式も一時的にサポート
        $source = null;
        $customerId = null;

        if (isset($context['source'])) {
            $source = $context['source'] === 'medical_record' ? 'medical' : $context['source'];
        }

        if (isset($context['customer_id'])) {
            $customerId = $context['customer_id'];
        }

        \Log::info('[/stores] パラメータベースアクセス', [
            'context_type' => $context['type'] ?? null,
            'source' => $source,
            'customer_id' => $customerId,
            'encrypted_context' => $encryptedContext
        ]);

        return view('stores.index', compact('stores', 'encryptedContext', 'source', 'customerId'));
    }
    
    public function storeStoreSelection(Request $request, ReservationContextService $contextService)
    {
        \Log::info('[storeStoreSelection] リクエスト受信', [
            'all_data' => $request->all(),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'url' => $request->url()
        ]);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'ctx' => 'nullable|string'
        ]);

        \Log::info('[storeStoreSelection] バリデーション完了', $validated);

        // コンテキストがある場合は既存のフローを継続
        if (isset($validated['ctx']) && !empty($validated['ctx'])) {
            \Log::info('[storeStoreSelection] 既存コンテキストあり', ['ctx_length' => strlen($validated['ctx'])]);

            $context = $contextService->decryptContext($validated['ctx']);
            if (!$context) {
                \Log::error('[storeStoreSelection] コンテキスト復号化失敗');
                return redirect()->route('stores')->withErrors(['error' => '不正なリクエストです']);
            }

            \Log::info('[storeStoreSelection] コンテキスト復号化成功', $context);

            // 店舗IDをコンテキストに追加
            $context['store_id'] = $validated['store_id'];
            // 新しいコンテキストを暗号化してリダイレクト
            $encryptedContext = $contextService->encryptContext($context);

            \Log::info('[storeStoreSelection] 既存顧客フロー：カテゴリ選択へリダイレクト', [
                'redirect_url' => route('reservation.select-category', ['ctx' => $encryptedContext])
            ]);

            return redirect()->route('reservation.select-category', ['ctx' => $encryptedContext]);
        }

        // 新規顧客の場合：新しいコンテキストを作成
        \Log::info('[storeStoreSelection] 新規顧客：新しいコンテキストを作成');

        $context = [
            'type' => 'new_reservation',
            'store_id' => $validated['store_id'],
            'is_existing_customer' => false,
            'source' => 'public'
        ];

        \Log::info('[storeStoreSelection] 新規コンテキスト作成', $context);

        $encryptedContext = $contextService->encryptContext($context);

        \Log::info('[storeStoreSelection] 新規顧客フロー：カテゴリ選択へリダイレクト', [
            'redirect_url' => route('reservation.select-category', ['ctx' => $encryptedContext])
        ]);

        return redirect()->route('reservation.select-category', ['ctx' => $encryptedContext]);
    }
    
    /**
     * メニューカテゴリー選択
     */
    public function selectCategory(Request $request, ReservationContextService $contextService)
    {
        // パラメータベース: 暗号化されたコンテキストを取得
        $context = $contextService->extractContextFromRequest($request);

        if (!$context) {
            // コンテキストがない場合は店舗選択へ
            return redirect()->route('stores')->withErrors(['error' => '予約情報が見つかりません']);
        }

        // 店舗IDが設定されているかチェック
        if (!isset($context['store_id'])) {
            // 店舗が選択されていない場合は店舗選択へ
            $encryptedContext = $contextService->encryptContext($context);
            return redirect()->route('stores', ['ctx' => $encryptedContext]);
        }

        $store = Store::find($context['store_id']);

        if (!$store || !$store->is_active) {
            return redirect()->route('stores')->withErrors(['error' => '指定された店舗が見つかりません']);
        }

        // コンテキストから必要な情報を取得
        $source = $context['source'] ?? null;
        $customerId = $context['customer_id'] ?? null;
        $storeId = $context['store_id'];

        // sourceに基づいて既存顧客かどうか判定
        $isExistingCustomer = isset($context['is_existing_customer']) ? $context['is_existing_customer'] : false;

        // medical_recordソースの場合は既存顧客として扱う
        if ($context['type'] === 'medical_record' || $source === 'medical_record') {
            $isExistingCustomer = true;
        }

        \Log::info('[selectCategory] 顧客タイプ判定', [
            'context_type' => $context['type'] ?? null,
            'source' => $source,
            'customer_id' => $customerId,
            'is_existing_customer' => $isExistingCustomer,
            'store_id' => $storeId
        ]);

        // アクティブなカテゴリーを取得
        $categoriesQuery = MenuCategory::where('store_id', $storeId)
            ->where('is_active', true);

        // 既存顧客の場合、new_onlyメニューを持つカテゴリーを除外
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

        // ビューに必要なデータを渡す
        $encryptedContext = $contextService->encryptContext($context);

        return view('reservation.category-select', [
            'categories' => $categories,
            'store' => $store,
            'source' => $source,
            'customer_id' => $customerId,
            'encryptedContext' => $encryptedContext
        ]);
    }
    
    /**
     * 時間・料金選択
     */
    public function selectTime(Request $request, ReservationContextService $contextService)
    {
        // GETリクエストの場合とPOSTリクエストの場合で処理を分ける
        if ($request->isMethod('get')) {
            // GETリクエスト: コンテキストからカテゴリIDを取得
            $validated = $request->validate([
                'ctx' => 'required|string'
            ]);

            $context = $contextService->decryptContext($validated['ctx']);
            if (!$context || !isset($context['category_id'])) {
                return redirect()->route('stores')->withErrors(['error' => '予約情報が見つかりません']);
            }

            $categoryId = $context['category_id'];
        } else {
            // POSTリクエスト: フォームからカテゴリIDを取得
            $validated = $request->validate([
                'category_id' => 'required|exists:menu_categories,id',
                'ctx' => 'required|string'
            ]);

            $context = $contextService->decryptContext($validated['ctx']);
            if (!$context) {
                return redirect()->route('stores')->withErrors(['error' => '予約情報が見つかりません']);
            }

            // カテゴリIDをコンテキストに追加
            $context['category_id'] = $validated['category_id'];
            $categoryId = $validated['category_id'];
        }

        $storeId = $context['store_id'] ?? null;
        if (!$storeId) {
            return redirect()->route('stores')->withErrors(['error' => '店舗が選択されていません']);
        }

        $store = Store::find($storeId);
        $category = MenuCategory::find($categoryId);

        // コンテキストから情報を取得
        $source = $context['source'] ?? null;
        $customerId = $context['customer_id'] ?? null;

        // デバッグログ
        \Log::info('[selectTime] 予約ソース確認', [
            'source' => $source,
            'customer_id' => $customerId,
            'all_request_data' => $request->all()
        ]);

        // 顧客のサブスクリプション状態を確認
        $hasSubscription = false;
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $hasSubscription = $customer->hasActiveSubscription();
            }
        }
        
        // 新規・既存判定（パラメータベース）
        $isNewCustomer = true;

        // コンテキストに既存顧客フラグがある場合はそれを使用
        if (isset($context['is_existing_customer'])) {
            $isNewCustomer = !$context['is_existing_customer'];
        } elseif ($context['type'] === 'medical_record' || $source === 'medical_record') {
            // カルテからは必ず既存顧客
            $isNewCustomer = false;
        } elseif ($customerId) {
            // customer_idがある場合は既存予約をチェック
            $existingReservations = Reservation::where('customer_id', $customerId)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->count();
            $isNewCustomer = $existingReservations === 0;
        }
        
        // カテゴリーに属するメニューを時間別に取得
        \Log::info('Fetching menus', [
            'store_id' => $storeId,
            'category_id' => $categoryId,
            'category_name' => $category->name ?? 'Unknown'
        ]);

        $menusQuery = Menu::where('store_id', $storeId)
            ->where('category_id', $categoryId)
            ->where('is_available', true)
            ->where('is_visible_to_customer', true);
            
        // サブスク限定メニューのフィルタリング
        if (!$hasSubscription) {
            $menusQuery->where('is_subscription_only', false);
        }
        
        // メニューフィルタリング（パラメータベース）
        if ($context['type'] === 'medical_record' || $source === 'medical_record') {
            // カルテから: 既存向け＋カルテ専用OK
            $menusQuery->whereIn('customer_type_restriction', ['all', 'existing']);
            // medical_record_onlyの制限なし（カルテ専用メニューも表示）
            \Log::info('[selectTime] カルテからの予約');
        } elseif ($isNewCustomer) {
            // 新規顧客: 新規向けのみ
            $menusQuery->whereIn('customer_type_restriction', ['all', 'new', 'new_only']);
            $menusQuery->where('medical_record_only', 0);
            \Log::info('[selectTime] 新規顧客');
        } else {
            // 既存顧客（通常予約）: 既存向けのみ（カルテ専用は除外）
            $menusQuery->whereIn('customer_type_restriction', ['all', 'existing']);
            $menusQuery->where('medical_record_only', 0);
            \Log::info('[selectTime] 既存顧客（通常予約）');
        }

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

        // コンテキストを暗号化
        $encryptedContext = $contextService->encryptContext($context);

        // POSTリクエストの場合、GETリダイレクトしてURLにコンテキストを表示
        if ($request->isMethod('post')) {
            return redirect()->route('reservation.select-time', ['ctx' => $encryptedContext]);
        }

        // GETリクエストの場合、ビューを表示
        return view('reservation.time-select', [
            'menusByDuration' => $menusByDuration,
            'sortedMenus' => $sortedMenus,
            'store' => $store,
            'category' => $category,
            'hasSubscription' => $hasSubscription,
            'source' => $source,
            'customer_id' => $customerId,
            'encryptedContext' => $encryptedContext
        ]);
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
            return redirect('/stores')->with('error', '指定された店舗が見つかりません。');
        }
        
        // セッションに店舗IDを保存
        Session::put('selected_store_id', $storeId);
        
        // パラメータベースで予約タイプを判定
        $source = $request->get('source');
        $isFromMedicalRecord = ($source === 'medical' || $source === 'mypage');
        $customerId = $request->get('customer_id');

        // 顧客が新規か既存かを判定
        $isNewCustomer = true;

        // マイページまたはカルテからの場合は既存顧客
        if ($source === 'mypage' || $source === 'medical') {
            $isNewCustomer = false;
        } elseif ($customerId) {
            // 通常予約で顧客IDがある場合はDBをチェック
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
    
    public function storeMenu(Request $request, ReservationContextService $contextService)
    {
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'option_ids' => 'nullable|string',
            'ctx' => 'required|string'
        ]);

        // コンテキストを復号化
        $context = $contextService->decryptContext($validated['ctx']);
        if (!$context) {
            return redirect()->route('stores')->withErrors(['error' => '予約情報が見つかりません']);
        }

        // メニュー情報をコンテキストに追加
        $menu = Menu::find($validated['menu_id']);
        $context['menu_id'] = $menu->id;

        // オプション情報をコンテキストに追加
        $optionIds = [];
        if (!empty($validated['option_ids'])) {
            $optionIds = array_filter(explode(',', $validated['option_ids']));
            $context['option_ids'] = $optionIds;
        }

        // セッションにも一時保存（レガシー互換性のため）
        Session::put('reservation_menu', $menu);
        if (!empty($optionIds)) {
            $selectedOptions = Menu::whereIn('id', $optionIds)
                ->where('is_available', true)
                ->where('show_in_upsell', true)
                ->get();
            Session::put('reservation_options', $selectedOptions);
        }

        // 店舗ID取得
        $storeId = $context['store_id'] ?? Session::get('selected_store_id');
        $store = Store::find($storeId);

        // コンテキストを暗号化
        $encryptedContext = $contextService->encryptContext($context);

        if ($store && $store->use_staff_assignment && $menu->requires_staff) {
            // スタッフ指定が必要な場合はスタッフ選択ページへ
            return redirect()->route('reservation.select-staff', ['ctx' => $encryptedContext]);
        }

        // スタッフ指定が不要な場合はカレンダーページへ
        Session::forget('selected_staff_id');

        // セッションも設定（レガシー互換性）
        if (isset($context['store_id'])) {
            Session::put('selected_store_id', $context['store_id']);
        }
        if (isset($context['category_id'])) {
            Session::put('selected_category_id', $context['category_id']);
        }

        return redirect()->route('reservation.index', ['ctx' => $encryptedContext]);
    }

    /**
     * スタッフ選択画面
     */
    public function selectStaff(Request $request)
    {
        // セッションから必要な情報を取得
        $storeId = Session::get('selected_store_id');
        $menu = Session::get('reservation_menu');

        // 必要な情報がない場合はメニュー選択へリダイレクト
        if (!$storeId || !$menu) {
            return redirect()->route('reservation.select-category');
        }

        $store = Store::find($storeId);
        if (!$store || !$store->use_staff_assignment || !$menu->requires_staff) {
            // スタッフ指定が不要な場合はカレンダーへ
            return redirect()->route('reservation.index');
        }

        // 利用可能なスタッフを取得
        $staffs = User::where('store_id', $storeId)
            ->where('is_active_staff', true)
            ->get();

        // カテゴリ情報も取得
        $category = null;
        if ($menu->category_id) {
            $category = MenuCategory::find($menu->category_id);
        }

        return view('reservation.staff-select', compact('staffs', 'store', 'menu', 'category'));
    }

    /**
     * スタッフ選択処理
     */
    public function storeStaff(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:users,id'
        ]);

        // スタッフが該当店舗のアクティブなスタッフかチェック
        $storeId = Session::get('selected_store_id');
        $staff = User::where('id', $validated['staff_id'])
            ->where('store_id', $storeId)
            ->where('is_active_staff', true)
            ->first();

        if (!$staff) {
            return back()->withErrors(['staff_id' => '選択されたスタッフが無効です。']);
        }

        // セッションにスタッフIDを保存
        Session::put('selected_staff_id', $validated['staff_id']);
        Session::put('selected_staff', $staff);

        // カレンダー選択へ
        return redirect()->route('reservation.index');
    }

    public function index(Request $request, ReservationContextService $contextService)
    {
        // パラメータベース：コンテキストを取得
        $context = $contextService->extractContextFromRequest($request);

        // コンテキストがない場合はセッションからフォールバック（レガシー互換性）
        if (!$context) {
            // URLパラメータでサブスク予約かチェック
            $isSubscriptionFromUrl = $request->get('type') === 'subscription';

            if ($isSubscriptionFromUrl) {
                \Log::info('URLパラメータでサブスク予約を検知');
                Session::put('is_subscription_booking', true);

                // 電話番号から顧客IDを取得
                $phone = $request->get('phone');
                if ($phone) {
                    $customer = \App\Models\Customer::where('phone', $phone)->first();
                    if ($customer) {
                        Session::put('customer_id', $customer->id);
                        Session::put('existing_customer_id', $customer->id);
                        \Log::info('電話番号から顧客情報を設定', [
                            'phone' => $phone,
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->full_name
                        ]);
                    }
                }
            }

            // セッションから情報を取得
            $selectedMenu = Session::get('reservation_menu');
            $selectedOptions = Session::get('reservation_options', collect());
            $selectedStoreId = Session::get('selected_store_id');
        } else {
            // コンテキストから情報を取得
            \Log::info('パラメータベース予約カレンダーアクセス', [
                'context' => $context
            ]);

            $selectedStoreId = $context['store_id'] ?? null;
            $selectedMenu = isset($context['menu_id']) ? Menu::find($context['menu_id']) : null;
            $selectedOptions = isset($context['option_ids']) ?
                Menu::whereIn('id', $context['option_ids'])->get() :
                collect();

            // セッションにも保存（レガシー互換性）
            if ($selectedMenu) {
                Session::put('reservation_menu', $selectedMenu);
            }
            if ($selectedOptions->isNotEmpty()) {
                Session::put('reservation_options', $selectedOptions);
            }
            if ($selectedStoreId) {
                Session::put('selected_store_id', $selectedStoreId);
            }

            // サブスク予約判定
            $isSubscriptionFromUrl = isset($context['is_subscription']) && $context['is_subscription'];
        }

        // 共通処理
        \Log::info('予約カレンダーアクセス', [
            'is_subscription_booking' => $isSubscriptionFromUrl,
            'menu_id' => $selectedMenu ? $selectedMenu->id : null,
            'store_id' => $selectedStoreId
        ]);

        // サブスク予約の場合、必要な情報を設定
        if (Session::get('is_subscription_booking') || $isSubscriptionFromUrl) {
            $customerId = Session::get('customer_id');
            $subscriptionId = Session::get('subscription_id');

            \Log::info('サブスク予約モード処理', [
                'customer_id' => $customerId,
                'subscription_id' => $subscriptionId,
                'has_menu' => !!$selectedMenu,
                'has_store' => !!$selectedStoreId,
                'from_url' => $isSubscriptionFromUrl
            ]);

            // サブスク情報が不足している場合、アクティブなサブスクリプションから取得
            if (!$selectedMenu || !$selectedStoreId) {
                // 最初にセッションストレージからの情報を確認（JavaScript側で設定されている可能性）
                $requestCustomerId = $request->header('X-Customer-ID');

                // 現在ログイン中の顧客のアクティブサブスクリプションを取得
                $subscriptions = \App\Models\CustomerSubscription::where('status', 'active')
                    ->where('payment_failed', false)
                    ->where('is_paused', false)
                    ->with(['plan'])
                    ->get();

                if ($subscriptions->isNotEmpty()) {
                    $subscription = $subscriptions->first();
                    \Log::info('アクティブサブスクリプション発見', [
                        'subscription_id' => $subscription->id,
                        'customer_id' => $subscription->customer_id,
                        'store_id' => $subscription->store_id,
                        'menu_id' => $subscription->menu_id
                    ]);

                    // 顧客IDを設定
                    if (!$customerId) {
                        Session::put('customer_id', $subscription->customer_id);
                        $customerId = $subscription->customer_id;
                    }

                    // メニューを設定
                    if (!$selectedMenu && $subscription->menu_id) {
                        $menu = \App\Models\Menu::find($subscription->menu_id);
                        if ($menu) {
                            Session::put('reservation_menu', $menu);
                            $selectedMenu = $menu;
                            \Log::info('サブスクメニューを設定', ['menu_id' => $menu->id, 'menu_name' => $menu->name]);
                        }
                    }

                    // 店舗を設定
                    if (!$selectedStoreId && $subscription->store_id) {
                        Session::put('selected_store_id', $subscription->store_id);
                        $selectedStoreId = $subscription->store_id;
                        \Log::info('サブスク店舗を設定', ['store_id' => $subscription->store_id]);
                    }

                    // サブスクリプションIDを設定
                    Session::put('subscription_id', $subscription->id);
                    Session::put('from_mypage', true);
                }
            }
        }

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
        // 顧客IDの設定
        $customerId = null;

        // パラメータベース：コンテキストから顧客IDを取得
        if ($context) {
            // 既存顧客の場合のみ顧客IDを設定
            if (isset($context['is_existing_customer']) && $context['is_existing_customer'] === true) {
                $customerId = $context['customer_id'] ?? null;
                \Log::info('パラメータベース：既存顧客の顧客ID設定', [
                    'customer_id' => $customerId,
                    'context_type' => $context['type'] ?? 'unknown'
                ]);
            } else {
                // 新規顧客の場合はサブスク関連セッションをクリア
                Session::forget('is_subscription_booking');
                Session::forget('customer_id');
                Session::forget('existing_customer_id');
                \Log::info('パラメータベース：新規顧客のためセッションクリア', [
                    'is_existing_customer' => $context['is_existing_customer'] ?? 'not_set',
                    'context_type' => $context['type'] ?? 'unknown'
                ]);
            }
        }
        // レガシー：セッションベース（サブスク予約の場合のみ）
        else if (Session::get('is_subscription_booking')) {
            // existing_customer_id または customer_id を取得
            $customerId = Session::get('existing_customer_id') ?? Session::get('customer_id');

            \Log::info('レガシー：サブスク予約の顧客ID確認', [
                'existing_customer_id' => Session::get('existing_customer_id'),
                'customer_id' => Session::get('customer_id'),
                'final_customer_id' => $customerId
            ]);
        }
        $availability = $this->getAvailability($selectedStoreId, $selectedStore, $startDate, $dates, $totalDuration, $customerId);

        // 既存顧客情報を取得
        $existingCustomer = null;
        $isExistingCustomer = false;
        if ($context && isset($context['customer_id'])) {
            $existingCustomer = Customer::find($context['customer_id']);
            $isExistingCustomer = true;
        }

        return view('reservation.public.index', compact(
            'stores',
            'selectedMenu',
            'selectedOptions',
            'selectedStore',
            'dates',
            'timeSlots',
            'availability',
            'weekOffset',
            'maxWeeks',
            'existingCustomer',
            'isExistingCustomer'
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
    
    private function getAvailability($storeId, $store, $startDate, $dates, $menuDuration = 60, $customerId = null)
    {
        $availability = [];
        $endDate = $startDate->copy()->addDays(6);

        // サブスク予約の場合、既存予約を取得して5日間隔制限用に準備
        $existingReservationDates = [];
        $isSubscriptionBooking = Session::get('is_subscription_booking', false);

        // 新規顧客の場合は5日間制限を完全に無効化
        if ($isSubscriptionBooking && $customerId) {
            \Log::info('既存予約取得開始', [
                'customer_id' => $customerId,
                'customer_id_type' => gettype($customerId),
                'is_subscription' => $isSubscriptionBooking
            ]);

            $existingReservations = Reservation::where('customer_id', $customerId)
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->get();

            \Log::info('既存予約クエリ結果', [
                'customer_id' => $customerId,
                'reservations_count' => $existingReservations->count(),
                'reservations' => $existingReservations->map(function($r) {
                    return [
                        'id' => $r->id,
                        'customer_id' => $r->customer_id,
                        'reservation_date' => $r->reservation_date,
                        'status' => $r->status
                    ];
                })->toArray()
            ]);

            $existingReservationDates = $existingReservations
                ->pluck('reservation_date')
                ->map(function($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->unique()
                ->values()
                ->toArray();

            \Log::info('サブスク予約の5日間隔チェック', [
                'customer_id' => $customerId,
                'existing_dates' => $existingReservationDates,
                'is_subscription' => $isSubscriptionBooking
            ]);
        }

        \Log::info('getAvailability開始', [
            'store_id' => $storeId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'menu_duration' => $menuDuration
        ]);
        
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
        
        // シフト情報を取得（スタッフシフトベースの場合、または指名スタッフがいる場合）
        $shifts = collect();
        $selectedStaffId = Session::get('selected_staff_id');

        // 指名スタッフがいる場合、またはスタッフシフトベースの場合
        if ($selectedStaffId || $store->use_staff_assignment) {
            $shiftsQuery = Shift::where('store_id', $storeId)
                ->whereDate('shift_date', '>=', $startDate->format('Y-m-d'))
                ->whereDate('shift_date', '<=', $endDate->format('Y-m-d'))
                ->where('is_available_for_reservation', true)
                ->whereHas('user', function($query) {
                    $query->where('is_active_staff', true);
                });

            // 指名スタッフがいる場合はそのスタッフのシフトのみ取得
            if ($selectedStaffId) {
                $shiftsQuery->where('user_id', $selectedStaffId);
            }

            $shifts = $shiftsQuery->get()
                ->groupBy(function($shift) {
                    return Carbon::parse($shift->shift_date)->format('Y-m-d');
                });
        }
        
        foreach ($dates as $dateInfo) {
            $date = $dateInfo['date'];
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = strtolower($date->format('l'));
            $dayReservations = $existingReservations->get($dateStr, collect());
            $dayBlocks = $blockedPeriods->get($dateStr, collect());

            \Log::info("日付処理: $dateStr ($dayOfWeek)", [
                'existing_reservations' => $dayReservations->count(),
                'blocked_periods' => $dayBlocks->count()
            ]);

            // その日の営業時間に基づいて時間枠を生成
            $timeSlots = $this->generateTimeSlotsForDay($store, $dayOfWeek);

            \Log::info("タイムスロット生成: $dateStr", [
                'time_slots_count' => count($timeSlots),
                'time_slots' => array_slice($timeSlots, 0, 5) // 最初の5個だけログ
            ]);
            
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

                $isAvailable = true;
                $reason = null;

                // 過去の日付は予約不可
                if ($date->lt(Carbon::today())) {
                    $isAvailable = false;
                    $reason = 'past_date';
                }

                // 当日の過去時間は予約不可
                elseif ($date->isToday() && $slotTime->lt(now()->addHours($minBookingHours))) {
                    $isAvailable = false;
                    $reason = 'past_time_today';
                }

                if (!$isAvailable) {
                    $availability[$dateStr][$slot] = false;
                    if ($slot === '10:00') { // 10:00の判定結果のみログ出力
                        \Log::info("時間判定: $dateStr $slot = false", [
                            'reason' => $reason,
                            'is_today' => $date->isToday(),
                            'slot_time' => $slotTime->format('Y-m-d H:i:s'),
                            'current_time' => now()->format('Y-m-d H:i:s'),
                            'min_booking_hours' => $minBookingHours
                        ]);
                    }
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
                
                // シフトチェック：スタッフシフトベースの場合、または指名スタッフがいる場合
                if ($store->use_staff_assignment || $selectedStaffId) {
                    $dayShifts = $shifts->get($dateStr, collect());
                    $availableStaffCount = $dayShifts->filter(function ($shift) use ($slotTime, $slotEnd) {
                        $shiftStart = Carbon::parse($shift->shift_date->format('Y-m-d') . ' ' . $shift->start_time);
                        $shiftEnd = Carbon::parse($shift->shift_date->format('Y-m-d') . ' ' . $shift->end_time);

                        // 予約時間がシフト時間に収まるかチェック（休憩時間は考慮しない）
                        return $slotTime->gte($shiftStart) && $slotEnd->lte($shiftEnd);
                    })->count();

                    // 指名スタッフがいる場合
                    if ($selectedStaffId) {
                        // 指名スタッフのシフトがある場合のみ1、なければ0
                        $maxConcurrent = $availableStaffCount > 0 ? 1 : 0;


                        if ($maxConcurrent <= 0) {
                            $availability[$dateStr][$slot] = false;
                            continue;
                        }
                    } else {
                        // シフトモード：min(席数, スタッフ数×設備台数) でキャパシティを決定
                        $seatsCapacity = $store->main_lines_count ?? 1;  // 席数
                        $equipmentCapacity = $store->shift_based_capacity ?? 1;  // 1スタッフあたりの設備台数
                        $staffCapacity = $availableStaffCount * $equipmentCapacity;  // スタッフ×設備台数
                        $maxConcurrent = min($seatsCapacity, $staffCapacity);

                        if ($maxConcurrent <= 0) {
                            $availability[$dateStr][$slot] = false;
                            continue;
                        }
                    }
                }
                // 営業時間ベースの場合はシフトチェックをスキップ
                
                // 予約が重複していないかチェック
                $overlappingCount = $dayReservations->filter(function ($reservation) use ($slotTime, $slotEnd, $selectedStaffId) {
                    // 指名スタッフがいる場合は、そのスタッフの予約のみをカウント
                    if ($selectedStaffId) {
                        // 指名スタッフの予約以外は除外
                        if ($reservation->staff_id != $selectedStaffId) {
                            return false;
                        }
                    } else {
                        // 指名スタッフがいない場合は従来通り（サブラインを除外）
                        if ($reservation->line_type === 'sub' || $reservation->is_sub == true) {
                            return false;
                        }
                    }

                    // 時間をH:i形式に統一して比較
                    $slotTimeStr = $slotTime->format('H:i');
                    $slotEndStr = $slotEnd->format('H:i');

                    // DBの時間形式を正規化（H:i または H:i:s → H:i）
                    $reservationStart = substr($reservation->start_time, 0, 5);  // "10:00:00" → "10:00"
                    $reservationEnd = substr($reservation->end_time, 0, 5);      // "11:00:00" → "11:00"

                    // 時間が重なっているかチェック
                    return (
                        ($slotTimeStr >= $reservationStart && $slotTimeStr < $reservationEnd) ||
                        ($slotEndStr > $reservationStart && $slotEndStr <= $reservationEnd) ||
                        ($slotTimeStr <= $reservationStart && $slotEndStr >= $reservationEnd)
                    );
                })->count();
                
                // 最終的な予約可否を判定（$maxConcurrentは既に上で適切に設定済み）
                $finalAvailability = $overlappingCount < $maxConcurrent;

                // サブスク予約の5日間隔制限チェック
                if ($finalAvailability && $isSubscriptionBooking && !empty($existingReservationDates)) {
                    $currentDate = Carbon::parse($dateStr);

                    \Log::info('5日間制限チェック開始', [
                        'target_date' => $dateStr,
                        'slot' => $slot,
                        'existing_dates' => $existingReservationDates,
                        'is_subscription' => $isSubscriptionBooking,
                        'initial_availability' => $finalAvailability
                    ]);

                    foreach ($existingReservationDates as $existingDateStr) {
                        $existingDate = Carbon::parse($existingDateStr);
                        $daysDiff = $currentDate->diffInDays($existingDate, false); // 符号付きで取得

                        // 5日間隔制限: 予約間に最低5日間空ける必要がある
                        // つまり、既存予約日から5日以内は予約不可
                        // 例: 19日の予約がある場合、20,21,22,23,24日は不可、25日から可
                        if (abs($daysDiff) < 6) {
                            \Log::info('5日間隔制限により予約不可', [
                                'target_date' => $dateStr,
                                'existing_date' => $existingDateStr,
                                'days_diff' => $daysDiff,
                                'abs_days_diff' => abs($daysDiff),
                                'slot' => $slot
                            ]);
                            $finalAvailability = false;
                            break;
                        } else {
                            \Log::info('5日間隔制限OK', [
                                'target_date' => $dateStr,
                                'existing_date' => $existingDateStr,
                                'days_diff' => $daysDiff,
                                'abs_days_diff' => abs($daysDiff),
                                'slot' => $slot
                            ]);
                        }
                    }
                } else {
                    \Log::info('5日間制限チェックをスキップ', [
                        'target_date' => $dateStr,
                        'slot' => $slot,
                        'final_availability' => $finalAvailability,
                        'is_subscription' => $isSubscriptionBooking,
                        'has_existing_dates' => !empty($existingReservationDates),
                        'existing_dates_count' => count($existingReservationDates ?? [])
                    ]);
                }

                // サブスク予約の5日間制限内かどうかの情報も保存
                $withinFiveDays = false;
                if ($isSubscriptionBooking && !empty($existingReservationDates)) {
                    $currentDate = Carbon::parse($dateStr);
                    foreach ($existingReservationDates as $existingDateStr) {
                        $existingDate = Carbon::parse($existingDateStr);
                        $daysDiff = $currentDate->diffInDays($existingDate, false);
                        if (abs($daysDiff) < 6) {
                            $withinFiveDays = true;
                            break;
                        }
                    }
                }

                $availability[$dateStr][$slot] = [
                    'available' => $finalAvailability,
                    'within_five_days' => $withinFiveDays,
                    'is_subscription' => $isSubscriptionBooking
                ];

                if ($slot === '10:00') { // 10:00の最終判定のみログ出力
                    \Log::info("最終判定: $dateStr $slot = " . ($finalAvailability ? 'true' : 'false'), [
                        'overlapping_count' => $overlappingCount,
                        'max_concurrent' => $maxConcurrent,
                        'close_time' => $closeTime ? $closeTime->format('H:i') : 'null'
                    ]);
                }
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

        // マイページからの予約用コンテキストを生成
        $contextService = new \App\Services\ReservationContextService();
        $customer = \App\Models\Customer::find($validated['customer_id']);

        $contextData = [
            'customer_id' => $customer->id,
            'is_existing_customer' => true,
            'type' => 'subscription',
            'source' => 'mypage',
            'store_id' => $validated['store_id'],
            'menu_id' => $validated['menu_id'],
            'subscription_id' => $subscription->id
        ];

        $encryptedContext = $contextService->encryptContext($contextData);

        // サブスク予約では店舗・メニューが確定しているので、コンテキスト付きでカレンダーページへ
        return redirect('/reservation/calendar?ctx=' . urlencode($encryptedContext));
    }
    
    public function store(Request $request, ReservationContextService $contextService)
    {
        // パラメータベースでコンテキストを取得
        $context = $contextService->extractContextFromRequest($request);

        \Log::info('コンテキスト取得結果', [
            'context' => $context,
            'has_customer_id' => isset($context['customer_id']),
            'is_existing_customer' => $context['is_existing_customer'] ?? 'not_set',
            'source' => $context['source'] ?? 'not_set',
            'type' => $context['type'] ?? 'not_set',
            'raw_ctx' => $request->get('ctx')
        ]);

        // 既存顧客の判定（カルテまたはマイページからの予約）
        $isExistingCustomer = false;
        $existingCustomer = null;
        $isFromMyPage = $context && isset($context['source']) && $context['source'] === 'mypage';
        $isFromMedicalRecord = $context && isset($context['source']) && in_array($context['source'], ['medical_record', 'medical_record_legacy']);

        // マイページまたはカルテからの予約は既存顧客として扱う
        if (($isFromMyPage || $isFromMedicalRecord) && $context && isset($context['customer_id'])) {
            $existingCustomer = Customer::find($context['customer_id']);
            $isExistingCustomer = true;
            \Log::info('マイページ/カルテからの予約として既存顧客設定', [
                'customer_id' => $context['customer_id'],
                'source' => $context['source']
            ]);
        }
        // それ以外のコンテキストから顧客IDがある場合
        else if ($context && isset($context['customer_id'])) {
            $existingCustomer = Customer::find($context['customer_id']);
            $isExistingCustomer = true;
        }

        \Log::info('予約ソースの判定', [
            'is_from_mypage' => $isFromMyPage,
            'is_from_medical_record' => $isFromMedicalRecord,
            'source' => $context['source'] ?? null,
            'is_existing_customer' => $isExistingCustomer,
            'customer_id' => $context['customer_id'] ?? null
        ]);

        // 新規顧客の場合は5日間制限に関連するセッションを完全クリア
        if (!$context || !isset($context['customer_id']) || !isset($context['is_existing_customer']) || $context['is_existing_customer'] !== true) {
            Session::forget('is_subscription_booking');
            Session::forget('customer_id');
            Session::forget('existing_customer_id');
            \Log::info('新規顧客のためサブスク関連セッションを完全クリア');
        }

        // バリデーションルール（既存顧客の場合は顧客情報を除外）
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'menu_id' => 'required|exists:menus,id',
            'date' => 'required|date',
            'time' => 'required',
            'notes' => 'nullable|string|max:500',
        ];

        // 新規顧客の場合のみ顧客情報を必須にする
        if (!$isExistingCustomer) {
            $rules['last_name'] = 'required|string|max:50';
            $rules['first_name'] = 'required|string|max:50';
            $rules['phone'] = 'required|string|max:20';
            $rules['email'] = 'nullable|email|max:255';
        }

        // スタッフ指名が必要な場合の追加バリデーション
        $store = Store::find($request->store_id);
        $menu = Menu::find($request->menu_id);

        if ($store && $store->use_staff_assignment && $menu && $menu->requires_staff) {
            $rules['staff_id'] = 'required|exists:users,id';

            // セッションからスタッフIDを取得（フォームで送信されていない場合）
            if (!$request->has('staff_id') && Session::has('selected_staff_id')) {
                $request->merge(['staff_id' => Session::get('selected_staff_id')]);
            }
        }

        $validated = $request->validate($rules);
        
        // 既存顧客のサブスク予約の場合のみ、5日間隔制限をチェック
        // 新規顧客の場合は完全にスキップ
        $isExistingCustomer = isset($customer) && $customer;
        if ($isExistingCustomer && Session::has('is_subscription_booking') && Session::get('is_subscription_booking') === true) {
            $customerId = Session::get('customer_id');
            \Log::info('既存顧客のサブスク予約での5日間隔チェック開始', [
                'customer_id' => $customerId,
                'target_date' => $validated['date'],
                'is_subscription_booking' => Session::get('is_subscription_booking'),
                'is_existing_customer' => true
            ]);

            if ($customerId) {
                $this->validateFiveDayInterval($customerId, $validated['date']);
            } else {
                \Log::warning('サブスク予約なのにcustomer_idがセッションに設定されていません');
            }
        } else {
            \Log::info('5日間隔制限をスキップ', [
                'is_subscription_booking' => Session::get('is_subscription_booking'),
                'is_existing_customer' => $isExistingCustomer,
                'reason' => $isExistingCustomer ? '通常予約' : '新規顧客'
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
        
        // 顧客情報の処理
        $customer = null;

        if ($isExistingCustomer && $existingCustomer) {
            // 既存顧客の場合（マイページからの予約）
            $customer = $existingCustomer;

            // バリデーション済みデータに顧客情報を追加（レガシー互換性のため）
            $validated['last_name'] = $customer->last_name;
            $validated['first_name'] = $customer->first_name;
            $validated['phone'] = $customer->phone;
            $validated['email'] = $customer->email;

            \Log::info('既存顧客による予約', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'phone' => $customer->phone
            ]);

            // 既存顧客の場合、複雑なチェックをスキップして直接予約作成へ
            // マイページからの予約の場合は常に5日間隔制限をチェック
            if ($isFromMyPage) {
                \Log::info('マイページからの予約: 5日間隔制限をチェック', [
                    'customer_id' => $customer->id,
                    'date' => $validated['date']
                ]);
                $this->validateFiveDayInterval($customer->id, $validated['date']);
            }

            // 直接予約作成処理へ進む（customerは設定済み）
            // 既存顧客処理をスキップして予約作成へ
            \Log::info('マイページからの予約: 既存顧客チェックをスキップして予約作成へ', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name
            ]);
        }

        // 新規顧客の場合の処理（マイページからの予約の場合はこの部分をスキップ）
        else if (!$isExistingCustomer && isset($validated['phone'])) {
            \Log::info('新規顧客ルート: 電話番号による既存顧客チェック開始', [
                'is_existing_customer' => $isExistingCustomer,
                'phone' => $validated['phone']
            ]);
            // 新規顧客の場合、電話番号で既存顧客をチェック
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
            
            // データベースに顧客情報がある場合は既存顧客として扱う（CSVインポート顧客も含む）
            if (!$hasActiveSubscription) {
                // 最新の予約（完了済みも含む）を取得
                $latestReservation = Reservation::where('customer_id', $existingCustomerByPhone->id)
                    ->whereIn('status', ['pending', 'confirmed', 'booked', 'completed'])
                    ->orderBy('reservation_date', 'desc')
                    ->orderBy('start_time', 'desc')
                    ->first();
                    
                // 5日間の制限チェックを削除（新規予約ルートでは適用しない）
                // ユーザー要求：「新規のルートではこのアラートが出てしまうのは問題なので、最後に電話番号で弾いて、マイページに誘導の流れだけでいい」
                
                // 過去の予約履歴チェック（一度でも予約したことがある顧客）
                $pastReservations = Reservation::where('customer_id', $existingCustomerByPhone->id)
                    ->whereIn('status', ['completed', 'pending', 'confirmed', 'booked'])
                    ->count();

                // マイページまたはカルテからの予約の場合はモーダルを出さない
                $isFromMyPageOrMedical = $context && isset($context['source']) &&
                    in_array($context['source'], ['mypage', 'medical_record', 'medical_record_legacy']);

                \Log::info('モーダル表示判定', [
                    'past_reservations' => $pastReservations,
                    'isFromMyPageOrMedical' => $isFromMyPageOrMedical,
                    'context_source' => $context['source'] ?? 'none',
                    'will_show_modal' => ($pastReservations > 0 && !$isFromMyPageOrMedical)
                ]);

                if ($pastReservations > 0 && !$isFromMyPageOrMedical) {
                    \Log::info('過去の予約履歴あり、マイページへ誘導', [
                        'customer_id' => $existingCustomerByPhone->id,
                        'past_reservations' => $pastReservations,
                        'phone' => $existingCustomerByPhone->phone,
                        'context' => $context,
                        'is_existing_from_context' => $context && isset($context['is_existing_customer']) ? $context['is_existing_customer'] : false
                    ]);
                    return back()->with([
                        'show_mypage_modal' => true,
                        'customer_phone' => $existingCustomerByPhone->phone
                    ]);
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
            $customer = $existingCustomerByPhone;
        }
        }

        // 予約作成処理（既存顧客・新規顧客共通）
        DB::beginTransaction();
        try {
            // $customerが設定されていない場合（新規顧客作成）
            if (!$customer) {
                // まず電話番号で既存顧客を検索
                $customer = Customer::where('phone', $validated['phone'])->first();

                if (!$customer) {
                    // 電話番号で見つからない場合、メールアドレスでも検索
                    $customer = Customer::where('email', $validated['email'])->first();
                }

                if ($customer) {
                    // 既存顧客が見つかった場合、情報を更新
                    $customer->update([
                        'last_name' => $validated['last_name'],
                        'first_name' => $validated['first_name'],
                        'phone' => $validated['phone'],
                        'email' => $validated['email'],
                    ]);
                } else {
                    // 新規顧客として作成
                    $customer = Customer::create([
                        'phone' => $validated['phone'],
                        'last_name' => $validated['last_name'],
                        'first_name' => $validated['first_name'],
                        'last_name_kana' => '', // カナは空文字で保存
                        'first_name_kana' => '', // カナは空文字で保存
                        'email' => $validated['email'],
                        'customer_number' => Customer::generateCustomerNumber(),
                    ]);
                }
            }
            
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

                // 特定のスタッフが選択されている場合は、そのスタッフの可用性をチェック
                if (isset($validated['staff_id'])) {
                    \Log::info('スタッフシフトチェック', [
                        'staff_id' => $validated['staff_id'],
                        'date' => $validated['date'],
                        'time' => $validated['time'],
                        'end_time' => $endTime->format('H:i')
                    ]);

                    // デバッグ：該当スタッフのシフトを確認
                    $debugShifts = Shift::where('store_id', $validated['store_id'])
                        ->where('user_id', $validated['staff_id'])
                        ->whereDate('shift_date', $validated['date'])
                        ->get();

                    \Log::info('該当日のスタッフシフト', [
                        'shifts' => $debugShifts->toArray()
                    ]);

                    $specificStaffAvailable = Shift::where('store_id', $validated['store_id'])
                        ->where('user_id', $validated['staff_id'])
                        ->whereDate('shift_date', $validated['date'])  // whereDateを使用
                        ->where('start_time', '<=', $validated['time'])
                        ->where('end_time', '>=', $endTime->format('H:i'))
                        ->where('is_available_for_reservation', true)
                        ->whereHas('user', function($query) {
                            $query->where('is_active_staff', true);
                        })
                        ->exists();

                    if (!$specificStaffAvailable) {
                        DB::rollback();
                        return back()->with('error', '申し訳ございません。選択されたスタッフは指定の時間帯にご対応できません。別の時間帯をお選びください。');
                    }
                } else {
                    // スタッフが選択されていない場合は、一般的な可用性をチェック
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
            }
            // 営業時間ベースの場合はシフトチェックをスキップ
            
            // 予約を作成
            $reservationData = [
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
            ];

            // スタッフIDが指定されている場合は追加
            if (isset($validated['staff_id'])) {
                $reservationData['staff_id'] = $validated['staff_id'];
            }

            $reservation = Reservation::create($reservationData);
            
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
                    // 統一的なフラグ設定（ReservationConfirmationService::markConfirmationSentを使用）
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
        Session::forget(['reservation_menu', 'reservation_options', 'selected_store_id', 'selected_staff_id']);
            
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

    /**
     * 顧客の最後に訪問した店舗IDを取得
     */
    public function getLastVisitedStore(Request $request)
    {
        $customerId = $request->get('customer_id');

        if (!$customerId) {
            return response()->json(['store_id' => null]);
        }

        // 顧客の最新の予約から店舗IDを取得
        $lastReservation = Reservation::where('customer_id', $customerId)
            ->whereNotNull('store_id')
            ->orderBy('reservation_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->first();

        if ($lastReservation && $lastReservation->store_id) {
            // 店舗がアクティブかチェック
            $store = Store::where('id', $lastReservation->store_id)
                ->where('is_active', true)
                ->first();

            if ($store) {
                return response()->json(['store_id' => $store->id]);
            }
        }

        return response()->json(['store_id' => null]);
    }

    /**
     * 特定の時間枠の予約可能性をチェック
     */
    public function checkAvailability(Request $request)
    {
        \Log::info('checkAvailability called', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'menu_id' => 'required|exists:menus,id',
            'date' => 'required|date',
            'time' => 'required',
            'customer_id' => 'nullable|exists:customers,id'  // customer_idを追加（オプション）
        ]);

        $store = Store::find($validated['store_id']);
        $menu = Menu::find($validated['menu_id']);
        $date = Carbon::parse($validated['date']);
        $time = $validated['time'];
        $duration = $menu->duration_minutes ?? 60;
        $customerId = $validated['customer_id'] ?? null;

        \Log::info('checkAvailability processing', [
            'customer_id' => $customerId,
            'menu_id' => $menu->id,
            'menu_is_subscription' => $menu->is_subscription,
            'date' => $validated['date'],
            'time' => $time
        ]);

        // 時間枠の開始と終了を計算
        $startDateTime = Carbon::parse($validated['date'] . ' ' . $time);
        $endDateTime = $startDateTime->copy()->addMinutes($duration);

        // 過去の時間をチェック
        if ($startDateTime <= now()) {
            return response()->json(['available' => false, 'reason' => 'past_time']);
        }

        // 営業時間をチェック
        $dayOfWeek = strtolower($date->format('l'));
        $businessHours = collect($store->business_hours ?? [])->firstWhere('day', $dayOfWeek);

        if (!$businessHours || ($businessHours['is_closed'] ?? false)) {
            return response()->json(['available' => false, 'reason' => 'closed']);
        }

        $openTime = Carbon::parse($validated['date'] . ' ' . ($businessHours['open_time'] ?? '10:00'));
        $closeTime = Carbon::parse($validated['date'] . ' ' . ($businessHours['close_time'] ?? '18:00'));

        if ($startDateTime < $openTime || $endDateTime > $closeTime) {
            return response()->json(['available' => false, 'reason' => 'outside_hours']);
        }

        // 既存の予約との重複をチェック
        $overlappingReservations = Reservation::where('store_id', $validated['store_id'])
            ->whereDate('reservation_date', $validated['date'])
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->where(function($query) use ($time, $duration) {
                $startTime = $time;
                $endTime = Carbon::parse($time)->addMinutes($duration)->format('H:i:s');

                $query->where(function($q) use ($startTime, $endTime) {
                    // 既存予約の開始時間が新しい予約の時間範囲内
                    $q->where('start_time', '>=', $startTime)
                      ->where('start_time', '<', $endTime);
                })->orWhere(function($q) use ($startTime, $endTime) {
                    // 既存予約の終了時間が新しい予約の時間範囲内
                    $q->where('end_time', '>', $startTime)
                      ->where('end_time', '<=', $endTime);
                })->orWhere(function($q) use ($startTime, $endTime) {
                    // 既存予約が新しい予約を完全に包含
                    $q->where('start_time', '<=', $startTime)
                      ->where('end_time', '>=', $endTime);
                });
            })
            ->count();

        $capacity = $store->main_lines_count ?? 1;
        $available = $overlappingReservations < $capacity;

        // サブスク予約の5日間制限チェック
        if ($available && $customerId && $menu->is_subscription) {
            $customer = Customer::find($customerId);
            if ($customer) {
                // 顧客の既存予約を取得
                $existingReservations = $customer->reservations()
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->whereDate('reservation_date', '!=', $validated['date'])
                    ->get();

                // 5日間隔制限のチェック
                foreach ($existingReservations as $reservation) {
                    $existingDate = Carbon::parse($reservation->reservation_date);
                    $daysDiff = $existingDate->diffInDays($date, false);

                    if (abs($daysDiff) < 6) {
                        \Log::info('5日間隔制限により予約不可 (checkAvailability)', [
                            'customer_id' => $customerId,
                            'check_date' => $validated['date'],
                            'existing_date' => $reservation->reservation_date,
                            'days_diff' => abs($daysDiff)
                        ]);

                        return response()->json([
                            'available' => false,
                            'reason' => '5days_restriction',
                            'message' => '前後の予約から5日以上空ける必要があります',
                            'existing_date' => $reservation->reservation_date,
                            'days_diff' => abs($daysDiff)
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'available' => $available,
            'reason' => $available ? null : 'fully_booked',
            'capacity' => $capacity,
            'current_bookings' => $overlappingReservations
        ]);
    }
}