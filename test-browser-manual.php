<?php

// 管理画面の動作確認用スクリプト
echo "=== サブスクリプション管理機能のテスト ===\n\n";

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Laravel アプリケーションを起動
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CustomerSubscription;
use App\Models\Customer;
use App\Models\Store;

echo "1. 要対応顧客の確認\n";
echo "-------------------\n";

$subscriptions = CustomerSubscription::where(function($q) {
    $q->where('payment_failed', true)
      ->orWhere('is_paused', true)
      ->orWhere(function ($subQuery) {
          $subQuery->whereNotNull('end_date')
                   ->whereDate('end_date', '<=', now()->addDays(30))
                   ->whereDate('end_date', '>', now());
      });
})->with(['customer', 'store'])->get();

echo "要対応顧客数: " . $subscriptions->count() . "件\n\n";

foreach ($subscriptions as $sub) {
    $type = '';
    if ($sub->payment_failed) $type = '🔴 決済失敗';
    elseif ($sub->is_paused) $type = '⏸️ 休止中';
    elseif ($sub->isEndingSoon()) $type = '⚠️ 終了間近';
    
    $storeName = $sub->store ? $sub->store->name : '店舗未設定';
    echo $type . " - " . $sub->customer->last_name . " " . $sub->customer->first_name . " (" . $storeName . ")\n";
}

echo "\n2. 高橋直希様のサブスク詳細\n";
echo "-------------------------\n";

$takahashi = CustomerSubscription::whereHas('customer', function($q) {
    $q->where('last_name', '高橋')->where('first_name', '直希');
})->first();

if ($takahashi) {
    echo "ID: " . $takahashi->id . "\n";
    echo "プラン: " . $takahashi->plan_name . "\n";
    echo "ステータス: " . $takahashi->status . "\n";
    echo "決済失敗: " . ($takahashi->payment_failed ? 'はい' : 'いいえ') . "\n";
    echo "休止中: " . ($takahashi->is_paused ? 'はい' : 'いいえ') . "\n";
    
    if ($takahashi->payment_failed_at) {
        echo "決済失敗日時: " . $takahashi->payment_failed_at . "\n";
        echo "失敗理由: " . $takahashi->payment_failed_reason_display . "\n";
    }
    
    if ($takahashi->is_paused) {
        echo "休止期間: " . $takahashi->pause_start_date . " ～ " . $takahashi->pause_end_date . "\n";
    }
} else {
    echo "高橋直希様のサブスクが見つかりません。\n";
}

echo "\n3. 休止履歴の確認\n";
echo "---------------\n";

$pauseHistories = \App\Models\SubscriptionPauseHistory::with(['customerSubscription.customer'])->get();
echo "休止履歴件数: " . $pauseHistories->count() . "件\n";

foreach ($pauseHistories as $history) {
    echo "- " . $history->customerSubscription->customer->last_name . " " . $history->customerSubscription->customer->first_name . "\n";
    echo "  休止期間: " . $history->pause_start_date . " ～ " . $history->pause_end_date . "\n";
    echo "  キャンセル予約数: " . $history->cancelled_reservations_count . "件\n";
    echo "  再開: " . ($history->resumed_at ? $history->resumed_at . " (" . $history->resume_type . ")" : '未再開') . "\n\n";
}

echo "\n✅ テスト完了！\n";
echo "\n管理画面の手動確認手順:\n";
echo "1. http://localhost:8002/admin/login でログイン\n";
echo "2. ダッシュボードで要対応顧客ウィジェットを確認\n";
echo "3. 'サブスク契約管理' ページで高橋直希様のレコードを確認\n";
echo "4. 決済復旧・休止ボタンの動作を確認\n";
?>