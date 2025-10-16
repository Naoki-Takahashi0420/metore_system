<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Store;
use App\Services\ReservationContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //
    }

    /**
     * 顧客のカルテ（医療記録）取得
     */
    public function getMedicalRecords(Request $request)
    {
        try {
            $customer = $request->user();

            \Log::info('📋 getMedicalRecords called', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->last_name . ' ' . $customer->first_name,
                'store_id' => $customer->store_id
            ]);

            // 同じ電話番号を持つ全顧客IDを取得
            $customerIds = Customer::where('phone', $customer->phone)
                ->pluck('id')
                ->toArray();

            // カルテを取得（店舗IDがある場合のみフィルタリング）
            $query = \App\Models\MedicalRecord::whereIn('customer_id', $customerIds);

            // リクエストヘッダーまたはクエリパラメータから店舗IDを取得
            $filterStoreId = $request->header('X-Store-Id') ?? $request->input('store_id') ?? $customer->store_id;

            // 店舗IDが設定されている場合のみ店舗でフィルタリング
            if ($filterStoreId) {
                $query->whereHas('reservation', function ($q) use ($filterStoreId) {
                    $q->where('store_id', $filterStoreId);
                });
            }

            $medicalRecords = $query
                ->with([
                    'reservation.store',
                    'reservation.menu',
                    'createdBy',
                    'visibleImages', // 顧客に表示可能な画像のみ取得
                    'presbyopiaMeasurements' // 老眼詳細測定データ
                ])
                ->orderBy('record_date', 'desc')
                ->get();

            \Log::info('✅ getMedicalRecords success', [
                'customer_id' => $customer->id,
                'records_count' => $medicalRecords->count()
            ]);

            // 顧客APIでは年齢フィールドを除外
            $medicalRecords->each(function ($record) {
                $record->makeHidden(['age']);
            });

            return response()->json([
                'message' => 'カルテを取得しました',
                'data' => $medicalRecords
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ getMedicalRecords error', [
                'customer_id' => $request->user()?->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'カルテの取得に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * カルテからの予約用のコンテキストを生成
     */
    public function createMedicalRecordContext(Request $request, ReservationContextService $contextService)
    {
        $customer = $request->user();

        $validator = Validator::make($request->all(), [
            'store_id' => 'nullable|exists:stores,id',
            'source' => 'nullable|string|in:mypage,medical_record'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors()
            ], 422);
        }

        $storeId = $request->input('store_id');
        $source = $request->input('source', 'mypage'); // デフォルトはmypage

        // 店舗IDの自動取得を削除: 複数店舗で予約可能にするため、顧客に明示的に選択させる

        // ログ出力: 受信した店舗ID
        \Log::info('[createMedicalRecordContext] 受信したパラメータ', [
            'customer_id' => $customer->id,
            'store_id' => $storeId,
            'source' => $source
        ]);

        // 店舗IDが取得できた場合は、店舗選択済みのコンテキストを生成
        if ($storeId) {
            // 店舗IDが指定されている場合は、店舗選択済みのコンテキストを生成
            $store = Store::find($storeId);
            if (!$store || !$store->is_active) {
                return response()->json([
                    'message' => '指定された店舗が見つからないか、利用できません'
                ], 404);
            }

            // sourceパラメータを追加して呼び出し
            $context = [
                'type' => 'medical_record',
                'customer_id' => $customer->id,
                'store_id' => $storeId,
                'is_existing_customer' => true,
                'source' => $source
            ];
            $encryptedContext = $contextService->encryptContext($context);
        } else {
            // 店舗が見つからない場合は、通常の新規予約コンテキストを生成
            $context = [
                'type' => 'medical_record',
                'customer_id' => $customer->id,
                'is_existing_customer' => true,
                'source' => $source
            ];
            $encryptedContext = $contextService->encryptContext($context);
        }

        return response()->json([
            'message' => 'コンテキストを生成しました',
            'data' => [
                'encrypted_context' => $encryptedContext
            ]
        ]);
    }

    /**
     * サブスク予約用のコンテキストを生成
     */
    public function createSubscriptionContext(Request $request, ReservationContextService $contextService)
    {
        $customer = $request->user();

        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|exists:customer_subscriptions,id',
            'store_id' => 'required|exists:stores,id',
            'menu_id' => 'required|exists:menus,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors()
            ], 422);
        }

        // サブスクリプションが顧客のものか確認
        $subscription = $customer->subscriptions()
            ->where('id', $request->input('subscription_id'))
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'アクティブなサブスクリプションが見つかりません'
            ], 404);
        }

        // サブスク予約用のコンテキストを生成
        $context = [
            'type' => 'subscription',
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'store_id' => $request->input('store_id'),
            'menu_id' => $request->input('menu_id'),
            'is_subscription' => true,
            'is_existing_customer' => true,
            'source' => 'mypage_subscription'
        ];

        $encryptedContext = $contextService->encryptContext($context);

        \Log::info('[createSubscriptionContext] サブスク予約コンテキスト生成', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'store_id' => $request->input('store_id'),
            'menu_id' => $request->input('menu_id')
        ]);

        return response()->json([
            'message' => 'サブスク予約コンテキストを生成しました',
            'data' => [
                'encrypted_context' => $encryptedContext
            ]
        ]);
    }

    /**
     * 顧客の画像一覧を取得
     */
    public function getImages(Request $request)
    {
        $customer = $request->user();

        $images = $customer->images()
            ->where('is_visible_to_customer', true)
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    }

    /**
     * 同じ電話番号を持つ全店舗の顧客情報を取得（店舗切替用）
     *
     * 条件: インポートされた店舗も含めて全て表示
     */
    public function getAvailableStores(Request $request)
    {
        $customer = $request->user();
        $availableStores = collect();

        // 同じ電話番号を持つ全顧客のIDを取得
        $customerIds = Customer::where('phone', $customer->phone)->pluck('id');

        // 条件1: インポートされた顧客レコードから店舗を取得
        $customerRecords = Customer::where('phone', $customer->phone)
            ->whereNotNull('store_id')
            ->with('store:id,name')
            ->get();

        foreach ($customerRecords as $c) {
            if ($c->store) {
                $availableStores->push([
                    'customer_id' => $c->id,
                    'store_id' => $c->store_id,
                    'store_name' => $c->store->name,
                    'source' => 'customer_record'
                ]);
            }
        }

        // 条件2: 予約履歴がある店舗を取得（顧客レコードにない店舗）
        $reservationStores = \DB::table('reservations')
            ->join('stores', 'reservations.store_id', '=', 'stores.id')
            ->join('customers', 'reservations.customer_id', '=', 'customers.id')
            ->whereIn('reservations.customer_id', $customerIds)
            ->whereNotIn('reservations.status', ['cancelled', 'canceled'])
            ->select(
                'customers.id as customer_id',
                'stores.id as store_id',
                'stores.name as store_name'
            )
            ->distinct()
            ->get();

        foreach ($reservationStores as $rs) {
            // すでに顧客レコードから追加されていない店舗のみ追加
            if (!$availableStores->contains('store_id', $rs->store_id)) {
                $availableStores->push([
                    'customer_id' => $rs->customer_id,
                    'store_id' => $rs->store_id,
                    'store_name' => $rs->store_name,
                    'source' => 'reservation_history'
                ]);
            }
        }

        // 店舗IDでユニーク化
        $stores = $availableStores->unique('store_id')->values();

        return response()->json([
            'success' => true,
            'stores' => $stores,
            'current_customer_id' => $customer->id,
            'current_store_id' => $customer->store_id
        ]);
    }

    /**
     * 認証済み顧客情報を取得
     */
    public function me(Request $request)
    {
        $customer = $request->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => '認証されていません'
                ]
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'last_name' => $customer->last_name,
                    'first_name' => $customer->first_name,
                    'full_name' => $customer->full_name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'store_id' => $customer->store_id,
                    'store_name' => $customer->store?->name ?? null,
                ]
            ]
        ]);
    }
}
