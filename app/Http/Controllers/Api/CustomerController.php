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
        $customer = $request->user();
        
        $medicalRecords = $customer->medicalRecords()
            ->with([
                'reservation.store',
                'reservation.menu',
                'createdBy',
                'visibleImages', // 顧客に表示可能な画像のみ取得
                'presbyopiaMeasurements' // 老眼詳細測定データ
            ])
            ->orderBy('record_date', 'desc')
            ->get();

        return response()->json([
            'message' => 'カルテを取得しました',
            'data' => $medicalRecords
        ]);
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
        $lastReservation = null;

        // 店舗IDが指定されていない場合は、最後に予約した店舗を取得
        if (!$storeId) {
            // 最新の予約から店舗IDを取得
            $lastReservation = $customer->reservations()
                ->whereNotIn('status', ['cancelled', 'canceled'])
                ->orderBy('reservation_date', 'desc')
                ->orderBy('start_time', 'desc')
                ->first();

            if ($lastReservation) {
                $storeId = $lastReservation->store_id;
            } else {
                // 予約履歴がない場合は、デフォルトの店舗を取得
                $defaultStore = Store::where('is_active', true)->first();
                if ($defaultStore) {
                    $storeId = $defaultStore->id;
                }
            }
        }

        // ログ出力: 取得した店舗ID
        \Log::info('[createMedicalRecordContext] 店舗ID取得結果', [
            'customer_id' => $customer->id,
            'store_id' => $storeId,
            'last_reservation' => $lastReservation ? [
                'id' => $lastReservation->id,
                'store_id' => $lastReservation->store_id,
                'reservation_date' => $lastReservation->reservation_date,
                'status' => $lastReservation->status
            ] : null
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
}
