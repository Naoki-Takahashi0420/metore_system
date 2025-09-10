<?php

use App\Models\User;
use App\Models\Reservation;
use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\CustomerResource;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=== Filamentリソースの権限テスト ===\n\n";

$testUsers = [
    ['email' => 'admin@eye-training.com', 'role' => 'super_admin'],
    ['email' => 'owner@test.com', 'role' => 'owner'],
    ['email' => 'manager1@eye-training.jp', 'role' => 'manager（銀座本店）'],
    ['email' => 'staff0_1@eye-training.jp', 'role' => 'staff（銀座本店）'],
    ['email' => 'manager2@eye-training.jp', 'role' => 'manager（新宿店）'],
];

foreach ($testUsers as $testUser) {
    $user = User::where('email', $testUser['email'])->first();
    if (!$user) continue;
    
    // Filamentの認証コンテキストを設定
    \Filament\Facades\Filament::auth()->login($user);
    auth()->login($user);
    
    echo "【{$testUser['role']}】\n";
    
    // ReservationResourceのクエリを取得
    $query = ReservationResource::getEloquentQuery();
    $count = $query->count();
    $stores = $query->with('store')->get()->pluck('store.name')->unique()->filter();
    
    echo "  予約アクセス可能数: {$count}\n";
    echo "  表示される店舗: " . ($stores->isEmpty() ? 'なし' : $stores->implode(', ')) . "\n";
    
    // CustomerResourceのクエリを取得
    $customerQuery = CustomerResource::getEloquentQuery();
    $customerCount = $customerQuery->count();
    
    echo "  顧客アクセス可能数: {$customerCount}\n";
    
    // 権限チェック
    echo "  予約作成可能: " . (ReservationResource::canCreate() ? '○' : '×') . "\n";
    echo "  顧客作成可能: " . (CustomerResource::canCreate() ? '○' : '×') . "\n";
    
    echo "\n";
}

echo "=== テスト完了 ===\n";