<?php

/**
 * 既存予約のcustomer_subscription_idを修正するスクリプト
 *
 * 問題: 予約作成時にcustomer_subscription_idが設定されていない
 * 影響: 約167件の予約が不正確なデータを持っている
 *
 * 実行方法: php database/scripts/fix-missing-subscription-ids.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Reservation;
use App\Models\CustomerSubscription;
use Illuminate\Support\Facades\DB;

echo "=== customer_subscription_id 修正スクリプト ===" . PHP_EOL;
echo "開始時刻: " . now()->format('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// customer_subscription_idがNULLかつcustomer_ticket_idもNULLの完了済み予約を取得
$reservations = Reservation::whereNull('customer_subscription_id')
    ->whereNull('customer_ticket_id')
    ->where('status', 'completed')
    ->orderBy('reservation_date', 'desc')
    ->get();

echo "対象予約数: " . $reservations->count() . "件" . PHP_EOL . PHP_EOL;

$fixedCount = 0;
$skippedCount = 0;

foreach ($reservations as $reservation) {
    // 予約日時点でアクティブなサブスク契約を検索
    // ステータスが'active'であれば有効とみなす（終了日を過ぎていても運用されているケースがあるため）
    $activeSubscription = CustomerSubscription::where('customer_id', $reservation->customer_id)
        ->where('store_id', $reservation->store_id)
        ->where('status', 'active')
        ->first();

    if ($activeSubscription) {
        // customer_subscription_idを設定
        $reservation->update(['customer_subscription_id' => $activeSubscription->id]);
        $fixedCount++;

        echo sprintf(
            "✅ 予約ID %d: customer_subscription_id = %d に設定（顧客ID: %d, 日付: %s）" . PHP_EOL,
            $reservation->id,
            $activeSubscription->id,
            $reservation->customer_id,
            $reservation->reservation_date
        );
    } else {
        $skippedCount++;

        if ($skippedCount <= 10) {
            echo sprintf(
                "⏭️  予約ID %d: サブスク契約なし（スポット予約として扱う）" . PHP_EOL,
                $reservation->id
            );
        }
    }
}

if ($skippedCount > 10) {
    echo sprintf("⏭️  その他 %d 件のスポット予約をスキップ" . PHP_EOL, $skippedCount - 10);
}

echo PHP_EOL . "=== 完了 ===" . PHP_EOL;
echo "修正件数: " . $fixedCount . "件" . PHP_EOL;
echo "スキップ: " . $skippedCount . "件（実際のスポット予約）" . PHP_EOL;
echo "終了時刻: " . now()->format('Y-m-d H:i:s') . PHP_EOL;
