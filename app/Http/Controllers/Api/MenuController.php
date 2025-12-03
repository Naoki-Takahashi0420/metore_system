<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * メニュー一覧取得
     */
    public function index(Request $request)
    {
        $query = Menu::with('store')
            ->where('is_available', true);
        
        // 店舗IDでフィルター
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        
        // カテゴリーでフィルター
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        $menus = $query->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $menus,
            'message' => 'メニュー一覧を取得しました'
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
     * メニュー詳細取得
     */
    public function show(Menu $menu)
    {
        if (!$menu->is_available) {
            return response()->json([
                'message' => 'メニューが見つかりません'
            ], 404);
        }

        $menu->load('store');

        return response()->json([
            'data' => $menu,
            'message' => 'メニュー詳細を取得しました'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Menu $menu)
    {
        //
    }

    /**
     * アップセル用メニュー取得
     */
    public function upsell(Request $request)
    {
        $query = Menu::where('is_available', true)
            ->where('show_in_upsell', true);

        // 除外するメニューID
        if ($request->has('exclude')) {
            $excludeIds = is_array($request->exclude) ? $request->exclude : [$request->exclude];
            $query->whereNotIn('id', $excludeIds);
        }

        // 店舗IDでフィルター
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $upsellMenus = $query->orderBy('sort_order')
            ->get(['id', 'name', 'description', 'upsell_description', 'price', 'duration_minutes', 'category', 'image_path', 'subscription_plan_ids']);

        // 選択されたメニューIDがある場合、そのメニューに紐づいたオプションのみを表示
        if ($request->has('menu_id')) {
            $selectedMenu = Menu::find($request->menu_id);

            if ($selectedMenu) {
                $upsellMenus = $upsellMenus->filter(function ($menu) use ($selectedMenu) {
                    // オプションメニューのsubscription_plan_ids（実際は紐付けメニューID配列）を確認
                    $linkedMenuIds = is_string($menu->subscription_plan_ids)
                        ? json_decode($menu->subscription_plan_ids, true)
                        : $menu->subscription_plan_ids;

                    if (empty($linkedMenuIds)) {
                        return false; // 紐付けなしの場合は表示しない（厳密フィルタリング）
                    }

                    return in_array($selectedMenu->id, $linkedMenuIds);
                })->values();
            }
        }

        return response()->json($upsellMenus);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Menu $menu)
    {
        //
    }
}
