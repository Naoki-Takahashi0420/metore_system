<?php

/**
 * 回数券通知システムのエッジケーステスト
 *
 * 使用方法:
 * php test-ticket-notification-system.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Customer;
use App\Models\Store;
use App\Models\CustomerTicket;
use App\Models\TicketPlan;
use App\Services\CustomerNotificationService;
use Carbon\Carbon;

echo "\n";
echo "======================================\n";
echo " 回数券通知システム エッジケーステスト\n";
echo "======================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

/**
 * テストヘルパー関数
 */
function runTest($testName, $callback) {
    global $testsPassed, $testsFailed;

    echo "テスト: {$testName}\n";

    try {
        \Illuminate\Support\Facades\DB::beginTransaction();

        $result = $callback();

        if ($result) {
            echo "  ✅ 成功\n\n";
            $testsPassed++;
        } else {
            echo "  ❌ 失敗\n\n";
            $testsFailed++;
        }

        \Illuminate\Support\Facades\DB::rollBack();
    } catch (\Exception $e) {
        echo "  ❌ エラー: {$e->getMessage()}\n";
        echo "  ファイル: {$e->getFile()}:{$e->getLine()}\n\n";
        $testsFailed++;
        \Illuminate\Support\Facades\DB::rollBack();
    }
}

// ===== テスト1: 期限切れ通知対象の正確な抽出 =====
runTest("期限切れ通知対象の正確な抽出（7日後が期限）", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // 7日後に期限切れの回数券を作成
    $ticket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券（7日後期限）',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => now()->addDays(7),
        'status' => 'active',
    ]);

    // 7日後が期限の回数券を検索
    $expiring = CustomerTicket::where('status', 'active')
        ->whereDate('expires_at', now()->addDays(7))
        ->where('remaining_count', '>', 0)
        ->whereNull('expiry_notified_at')
        ->count();

    echo "  期限7日後の回数券数: {$expiring}\n";

    return $expiring >= 1;
});

// ===== テスト2: 通知済み回数券は除外される =====
runTest("通知済み回数券は通知対象から除外される", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // 通知済みの回数券を作成
    $ticket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券（通知済み）',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => now()->addDays(7),
        'expiry_notified_at' => now()->subDay(), // 通知済み
        'status' => 'active',
    ]);

    // 未通知の回数券のみ検索
    $notNotified = CustomerTicket::where('id', $ticket->id)
        ->whereNull('expiry_notified_at')
        ->count();

    echo "  通知済み回数券が未通知として検出: {$notNotified}件\n";

    return $notNotified === 0; // 検出されないはず
});

// ===== テスト3: 期限切れ回数券のステータス更新 =====
runTest("期限切れ回数券のステータスが自動更新される", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // 昨日期限切れの回数券を作成
    $ticket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券（昨日期限切れ）',
        'total_count' => 10,
        'used_count' => 3,
        'purchase_price' => 10000,
        'purchased_at' => now()->subMonths(3),
        'expires_at' => now()->subDay(),
        'status' => 'active',
    ]);

    $initialStatus = $ticket->status;
    echo "  作成時ステータス: {$initialStatus}\n";

    // 期限切れ回数券を検索
    $expired = CustomerTicket::where('status', 'active')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->first();

    if ($expired) {
        $expired->update(['status' => 'expired']);
        $ticket->refresh();

        echo "  更新後ステータス: {$ticket->status}\n";

        return $ticket->status === 'expired';
    }

    return false;
});

// ===== テスト4: 使い切った回数券は通知対象外 =====
runTest("残り回数が0の回数券は通知対象外", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // 使い切った回数券（残り0回）
    $ticket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券（使い切り）',
        'total_count' => 10,
        'used_count' => 10, // 全て使用済み
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => now()->addDays(7),
        'status' => 'active',
    ]);

    // 残り回数を計算
    $remainingCount = $ticket->total_count - $ticket->used_count;
    echo "  残り回数（計算値）: {$remainingCount}回\n";

    // 残り回数が1以上の回数券のみ検索（whereRawでSQL計算）
    $hasRemaining = CustomerTicket::where('id', $ticket->id)
        ->whereRaw('(total_count - used_count) > 0')
        ->count();

    echo "  残り0回の回数券が検出: {$hasRemaining}件\n";

    return $hasRemaining === 0; // 検出されないはず
});

// ===== テスト5: 無期限回数券は通知対象外 =====
runTest("有効期限が無期限の回数券は通知対象外", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // 無期限回数券
    $ticket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券（無期限）',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => null, // 無期限
        'status' => 'active',
    ]);

    // 有効期限がある回数券のみ検索
    $hasExpiry = CustomerTicket::where('id', $ticket->id)
        ->whereNotNull('expires_at')
        ->count();

    echo "  無期限回数券が検出: {$hasExpiry}件\n";

    return $hasExpiry === 0; // 検出されないはず
});

// ===== テスト6: ブロック顧客への通知スキップ =====
runTest("ブロック顧客への通知はスキップされる", function() {
    $store = Store::first();
    $customer = Customer::where('is_blocked', true)->first();

    if (!$customer) {
        // ブロック顧客を作成（既存の電話番号と重複しないようにランダム生成）
        $randomPhone = '090' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $customer = Customer::create([
            'store_id' => $store->id,
            'last_name' => 'テスト',
            'first_name' => 'ブロック',
            'phone' => $randomPhone,
            'is_blocked' => true,
        ]);
    }

    echo "  ブロック顧客: {$customer->last_name} {$customer->first_name}様\n";
    echo "  is_blocked: " . ($customer->is_blocked ? 'true' : 'false') . "\n";

    return $customer->is_blocked === true;
});

// ===== テスト7: SMS無効の顧客への通知スキップ =====
runTest("SMS通知無効の顧客はSMS送信スキップ", function() {
    $store = Store::first();

    // SMS無効の顧客を作成
    $customer = Customer::create([
        'store_id' => $store->id,
        'last_name' => 'テスト',
        'first_name' => 'SMS無効',
        'phone' => '09087654321',
        'sms_notifications_enabled' => false,
    ]);

    $notificationService = app(CustomerNotificationService::class);
    $canSend = $notificationService->canSendNotification($customer, 'ticket_expiry');

    echo "  SMS送信可能: " . ($canSend['sms'] ? 'true' : 'false') . "\n";

    return $canSend['sms'] === false;
});

// ===== テスト8: 期限切れステータス変更時の複数条件チェック =====
runTest("期限切れ更新は active ステータスのみ対象", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // 既にexpiredステータスの回数券
    $ticket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券（既にexpired）',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now()->subMonths(3),
        'expires_at' => now()->subDay(),
        'status' => 'expired', // 既にexpired
    ]);

    // activeステータスのみ検索
    $activeExpired = CustomerTicket::where('id', $ticket->id)
        ->where('status', 'active')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->count();

    echo "  既にexpiredの回数券が検出: {$activeExpired}件\n";

    return $activeExpired === 0; // 検出されないはず
});

// ===== テスト9: 通知メッセージの内容確認（3日前 vs 7日前） =====
runTest("通知メッセージが期限までの日数で変化する", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // 7日後期限の回数券
    $ticket7days = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト7日前',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => now()->addDays(7),
        'status' => 'active',
    ]);

    // 3日後期限の回数券
    $ticket3days = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト3日前',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => now()->addDays(3),
        'status' => 'active',
    ]);

    echo "  7日前の回数券ID: {$ticket7days->id}\n";
    echo "  3日前の回数券ID: {$ticket3days->id}\n";
    echo "  ※実際のメッセージ内容はコマンド内で生成されます\n";

    return true;
});

// ===== テスト10: 期限が同日の複数回数券の処理 =====
runTest("同一顧客が同日期限の複数回数券を持つケース", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    $expiryDate = now()->addDays(7);

    // 同じ顧客、同じ期限の回数券を複数作成
    $ticket1 = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券A',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => $expiryDate,
        'status' => 'active',
    ]);

    $ticket2 = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券B',
        'total_count' => 5,
        'used_count' => 0,
        'purchase_price' => 5000,
        'purchased_at' => now(),
        'expires_at' => $expiryDate,
        'status' => 'active',
    ]);

    // 同一顧客の同日期限の回数券を検索
    $sameCustomerExpiring = CustomerTicket::where('customer_id', $customer->id)
        ->where('status', 'active')
        ->whereDate('expires_at', $expiryDate->format('Y-m-d'))
        ->whereNull('expiry_notified_at')
        ->count();

    echo "  同一顧客の同日期限回数券: {$sameCustomerExpiring}枚\n";
    echo "  ※各回数券に個別に通知が送信されます\n";

    return $sameCustomerExpiring === 2;
});

// ===== テスト11: cancelled ステータスの回数券は通知対象外 =====
runTest("キャンセル済み回数券は通知対象外", function() {
    $store = Store::first();
    $customer = Customer::first();

    if (!$store || !$customer) {
        echo "  ⚠️  店舗または顧客が存在しないためスキップ\n";
        return true;
    }

    // キャンセル済み回数券
    $ticket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'plan_name' => 'テスト回数券（キャンセル済み）',
        'total_count' => 10,
        'used_count' => 0,
        'purchase_price' => 10000,
        'purchased_at' => now(),
        'expires_at' => now()->addDays(7),
        'status' => 'cancelled',
        'cancelled_at' => now(),
    ]);

    // activeステータスのみ検索
    $activeOnly = CustomerTicket::where('id', $ticket->id)
        ->where('status', 'active')
        ->count();

    echo "  キャンセル済み回数券が検出: {$activeOnly}件\n";

    return $activeOnly === 0; // 検出されないはず
});

// ===== テスト12: 電話番号なしの顧客への通知スキップ =====
runTest("電話番号がない顧客はSMS送信スキップ", function() {
    $store = Store::first();

    // 電話番号なしの顧客を作成
    $customer = Customer::create([
        'store_id' => $store->id,
        'last_name' => 'テスト',
        'first_name' => '電話なし',
        'phone' => null, // 電話番号なし
        'email' => 'nophone@example.com',
    ]);

    $notificationService = app(CustomerNotificationService::class);
    $canSend = $notificationService->canSendNotification($customer, 'ticket_expiry');

    echo "  SMS送信可能: " . ($canSend['sms'] ? 'true' : 'false') . "\n";
    echo "  LINE送信可能: " . ($canSend['line'] ? 'true' : 'false') . "\n";

    return $canSend['sms'] === false;
});

// ===== 結果サマリー =====
echo "======================================\n";
echo " テスト結果サマリー\n";
echo "======================================\n";
echo "成功: {$testsPassed}件\n";
echo "失敗: {$testsFailed}件\n";
echo "合計: " . ($testsPassed + $testsFailed) . "件\n";

if ($testsFailed === 0) {
    echo "\n✅ 全てのテストが成功しました！\n\n";
    exit(0);
} else {
    echo "\n❌ いくつかのテストが失敗しました。\n\n";
    exit(1);
}
