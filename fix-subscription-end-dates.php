<?php
/**
 * サブスクリプション契約終了日修正スクリプト
 *
 * 問題: 6ヶ月コースが12ヶ月契約になっていた
 * 原因: CustomerSubscriptionResource.phpで間違ったプロパティ名を参照していた
 *
 * このスクリプトは既存の間違ったデータを修正します。
 *
 * 実行方法: php fix-subscription-end-dates.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CustomerSubscription;
use Carbon\Carbon;

echo "=== サブスクリプション契約終了日修正スクリプト ===" . PHP_EOL;
echo PHP_EOL;

// 6ヶ月コースメニューの既存サブスクリプションを取得
$subscriptions = CustomerSubscription::with(['menu', 'customer'])
    ->whereHas('menu', function($q) {
        $q->where('contract_months', 6);
    })
    ->get();

echo "対象契約数: " . $subscriptions->count() . PHP_EOL;
echo PHP_EOL;

if ($subscriptions->isEmpty()) {
    echo "修正対象のデータがありません。" . PHP_EOL;
    exit(0);
}

echo "=== 修正内容プレビュー ===" . PHP_EOL;
echo PHP_EOL;

$updates = [];

foreach ($subscriptions as $sub) {
    $expectedEnd = Carbon::parse($sub->service_start_date)
        ->addMonths($sub->menu->contract_months)
        ->subDay();

    $actualEnd = Carbon::parse($sub->end_date);

    $customerName = $sub->customer
        ? $sub->customer->last_name . ' ' . $sub->customer->first_name
        : 'N/A';

    echo "【ID: {$sub->id}】{$customerName}" . PHP_EOL;
    echo "  メニュー: {$sub->menu->name}" . PHP_EOL;
    echo "  サービス開始日: {$sub->service_start_date->format('Y-m-d')}" . PHP_EOL;
    echo "  現在の終了日: {$actualEnd->format('Y-m-d')} (contract_months: {$sub->contract_months})" . PHP_EOL;
    echo "  正しい終了日: {$expectedEnd->format('Y-m-d')} (contract_months: {$sub->menu->contract_months})" . PHP_EOL;
    echo PHP_EOL;

    $updates[] = [
        'id' => $sub->id,
        'customer_name' => $customerName,
        'old_end_date' => $actualEnd->format('Y-m-d'),
        'new_end_date' => $expectedEnd->format('Y-m-d'),
        'old_contract_months' => $sub->contract_months,
        'new_contract_months' => $sub->menu->contract_months,
    ];
}

// ユーザー確認
echo "上記の内容で修正しますか？ (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));

if (strtolower($line) !== 'yes') {
    echo "修正をキャンセルしました。" . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
echo "=== 修正実行中 ===" . PHP_EOL;
echo PHP_EOL;

$updatedCount = 0;

foreach ($updates as $update) {
    $subscription = CustomerSubscription::find($update['id']);

    if ($subscription) {
        $subscription->update([
            'end_date' => $update['new_end_date'],
            'contract_months' => $update['new_contract_months'],
        ]);

        echo "✅ ID: {$update['id']} ({$update['customer_name']}) を修正しました" . PHP_EOL;
        echo "   終了日: {$update['old_end_date']} → {$update['new_end_date']}" . PHP_EOL;
        echo "   契約期間: {$update['old_contract_months']}ヶ月 → {$update['new_contract_months']}ヶ月" . PHP_EOL;
        echo PHP_EOL;

        $updatedCount++;
    }
}

echo PHP_EOL;
echo "=== 修正完了 ===" . PHP_EOL;
echo "修正件数: {$updatedCount} / " . count($updates) . PHP_EOL;
echo PHP_EOL;

// 修正後の確認
echo "=== 修正後の確認 ===" . PHP_EOL;
echo PHP_EOL;

$afterCheck = CustomerSubscription::with(['menu', 'customer'])
    ->whereHas('menu', function($q) {
        $q->where('contract_months', 6);
    })
    ->get();

foreach ($afterCheck as $sub) {
    $expectedEnd = Carbon::parse($sub->service_start_date)
        ->addMonths($sub->menu->contract_months)
        ->subDay();

    $actualEnd = Carbon::parse($sub->end_date);

    $customerName = $sub->customer
        ? $sub->customer->last_name . ' ' . $sub->customer->first_name
        : 'N/A';

    if ($expectedEnd->eq($actualEnd)) {
        echo "✅ ID: {$sub->id} ({$customerName}) - 正しく修正されました" . PHP_EOL;
    } else {
        echo "❌ ID: {$sub->id} ({$customerName}) - まだ間違っています" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "処理完了" . PHP_EOL;
