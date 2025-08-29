<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuCategoryController extends Controller
{
    /**
     * カテゴリーの並び順を更新
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:menu_categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);
        
        DB::transaction(function () use ($validated) {
            foreach ($validated['categories'] as $category) {
                MenuCategory::where('id', $category['id'])
                    ->update(['sort_order' => $category['sort_order']]);
            }
        });
        
        return response()->json([
            'success' => true,
            'message' => 'カテゴリーの並び順を更新しました',
        ]);
    }
}