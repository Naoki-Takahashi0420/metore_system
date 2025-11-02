<?php

/**
 * 既存予約のcustomer_subscription_idを修正するスクリプト
 *
 * 問題: 予約作成時にcustomer_subscription_idが設定されていない
 * 影響: 約766件の予約が不正確なデータを持っている
 *
 * 実行方法: php database/scripts/fix-missing-subscription-ids.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Reservation;
use App\Services\ReservationSubscriptionBinder;
use Illuminate\Support\Facades\DB;

echo "=== customer_subscription_id 修正スクリプト ===" . PHP_EOL;
echo "開始時刻: " . now()->format('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// 共通サービスを使用
$binder = app(ReservationSubscriptionBinder::class);

// customer_subscription_idがNULLかつcustomer_ticket_idもNULLの予約を取得
// 完了済みだけでなく、booked、confirmedも対象
$reservations = Reservation::whereNull('customer_subscription_id')
    ->whereNull('customer_ticket_id')
    ->whereIn('status', ['booked', 'confirmed', 'completed'])
    ->orderBy('reservation_date', 'desc')
    ->get();

echo "対象予約数: " . $reservations->count() . "件" . PHP_EOL . PHP_EOL;

$fixedCount = 0;
$skippedCount = 0;

foreach ($reservations as $reservation) {
    // 共通サービスを使用してサブスクIDを設定
    $result = $binder->bindModel($reservation);

    if ($result) {
        $fixedCount++;

        echo sprintf(
            "✅ 予約ID %d: customer_subscription_id = %d に設定（顧客ID: %d, 日付: %s, メニュー: %s）" . PHP_EOL,
            $reservation->id,
            $reservation->fresh()->customer_subscription_id,
            $reservation->customer_id,
            $reservation->reservation_date,
            $reservation->menu->name ?? 'N/A'
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
