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
                'visibleImages' // 顧客に表示可能な画像のみ取得
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
            'store_id' => 'nullable|exists:stores,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors()
            ], 422);
        }

        $storeId = $request->input('store_id');

        // 店舗IDが指定されていない場合は、通常の新規予約コンテキストを生成
        if (!$storeId) {
            $context = [
                'type' => 'medical_record',
                'customer_id' => $customer->id,
                'is_existing_customer' => true,
                'source' => 'medical_record'
            ];
            $encryptedContext = $contextService->encryptContext($context);
        } else {
            // 店舗IDが指定されている場合は、店舗選択済みのコンテキストを生成
            $store = Store::find($storeId);
            if (!$store || !$store->is_active) {
                return response()->json([
                    'message' => '指定された店舗が見つからないか、利用できません'
                ], 404);
            }

            $encryptedContext = $contextService->createMedicalRecordContext($customer->id, $storeId);
        }

        return response()->json([
            'message' => 'コンテキストを生成しました',
            'data' => [
                'encrypted_context' => $encryptedContext
            ]
        ]);
    }
}
