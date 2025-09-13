<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreLiffController extends Controller
{
    /**
     * 店舗のLIFF IDを取得
     */
    public function getLiffId($storeId)
    {
        $store = Store::findOrFail($storeId);
        
        return response()->json([
            'liff_id' => $store->line_liff_id,
            'store_name' => $store->name,
        ]);
    }
}