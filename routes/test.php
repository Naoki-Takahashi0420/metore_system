<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use App\Models\Menu;
use App\Models\Store;

Route::get('/test-calendar', function() {
    // テスト用にセッションを設定
    $menu = Menu::where('is_available', true)->first();
    $store = Store::where('is_active', true)->first();
    
    if (!$menu || !$store) {
        return 'No menu or store available';
    }
    
    Session::put('reservation_menu', $menu);
    Session::put('selected_store_id', $store->id);
    Session::put('reservation_options', collect());
    
    return redirect('/reservation/calendar');
});