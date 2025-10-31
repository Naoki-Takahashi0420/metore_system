<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * 店舗一覧取得
     */
    public function index()
    {
        // 有効な店舗のみ取得（is_active = true かつ status = 'active'）
        $stores = Store::where('is_active', true)
            ->where('status', 'active')
            ->select(['id', 'name', 'name_kana', 'prefecture', 'city', 'address', 'phone', 'image_path', 'opening_hours', 'business_hours', 'holidays'])
            ->get();

        return response()->json([
            'data' => $stores,
            'message' => '店舗一覧を取得しました'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * 店舗詳細取得
     */
    public function show($id)
    {
        $store = Store::find($id);

        // 有効な店舗（is_active = true かつ status = 'active'）のみ表示
        if (!$store || !$store->is_active || $store->status !== 'active') {
            return response()->json([
                'message' => '店舗が見つかりません'
            ], 404);
        }

        $store->load(['menus' => function ($query) {
            $query->where('is_available', true)
                  ->orderBy('category')
                  ->orderBy('sort_order');
        }]);

        return response()->json([
            'data' => $store,
            'message' => '店舗詳細を取得しました'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Store $store)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        //
    }
}
