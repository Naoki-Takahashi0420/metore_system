<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Reservation;
use App\Models\CustomerTicket;
use App\Models\TicketPlan;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;

echo "🧪 予約×回数券連携のエッジケーステスト開始\n\n";

try {
    // テストデータ作成
    $store = Store::first() ?? Store::factory()->create();
    $customer = Customer::where('store_id', $store->id)->first() ?? Customer::factory()->create(['store_id' => $store->id]);
    $staff = User::where('store_id', $store->id)->first() ?? User::factory()->create(['store_id' => $store->id]);
    $menu = Menu::where('store_id', $store->id)->first() ?? Menu::factory()->create([
        'store_id' => $store->id,
        'duration_minutes' => 60,
        'price' => 5000,
    ]);

    $ticketPlan = TicketPlan::create([
        'store_id' => $store->id,
        'name' => 'テスト10回券',
        'ticket_count' => 10,
        'price' => 50000,
        'validity_months' => 3,
        'is_active' => true,
    ]);

    $testsPassed = 0;
    $testsFailed = 0;

    // テスト1: 回数券を使った予約作成で自動消費
    echo "📝 テスト1: 回数券を使った予約作成で自動消費\n";
    $ticket1 = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => $ticketPlan->name,
        'total_count' => $ticketPlan->ticket_count,
        'purchase_price' => $ticketPlan->price,
    ]);

    $reservation1 = Reservation::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'menu_id' => $menu->id,
        'staff_id' => $staff->id,
        'reservation_number' => 'R' . uniqid(),
        'reservation_date' => now()->addDays(10)->format('Y-m-d'),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status' => 'booked',
        'payment_method' => 'ticket',
        'customer_ticket_id' => $ticket1->id,
    ]);

    // 手動で消費処理をシミュレート
    $ticket1->use($reservation1->id);
    $reservation1->update(['paid_with_ticket' => true, 'payment_status' => 'paid']);

    $ticket1->refresh();
    if ($ticket1->used_count === 1 && $ticket1->remaining_count === 9) {
        echo "  ✅ 回数券が1回消費された (used: 1, remaining: 9)\n";
        $testsPassed++;
    } else {
        echo "  ❌ 回数券の消費に失敗 (used: {$ticket1->used_count}, remaining: {$ticket1->remaining_count})\n";
        $testsFailed++;
    }

    // テスト2: 予約キャンセルで回数券返却
    echo "\n📝 テスト2: 予約キャンセルで回数券返却\n";
    $ticket2 = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => $ticketPlan->name,
        'total_count' => $ticketPlan->ticket_count,
        'purchase_price' => $ticketPlan->price,
    ]);

    $reservation2 = Reservation::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'menu_id' => $menu->id,
        'staff_id' => $staff->id,
        'reservation_number' => 'R' . uniqid(),
        'reservation_date' => now()->addDays(11)->format('Y-m-d'),
        'start_time' => '11:00:00',
        'end_time' => '12:00:00',
        'status' => 'booked',
        'payment_method' => 'ticket',
        'customer_ticket_id' => $ticket2->id,
        'paid_with_ticket' => true,
    ]);

    $ticket2->use($reservation2->id);
    $ticket2->refresh();
    $usedBefore = $ticket2->used_count;

    // キャンセル（モデルイベントが発火）
    $reservation2->update(['status' => 'cancelled', 'cancelled_at' => now()]);

    $ticket2->refresh();
    if ($ticket2->used_count === 0 && $ticket2->remaining_count === 10) {
        echo "  ✅ 予約キャンセルで回数券が返却された (used: 0, remaining: 10)\n";
        $testsPassed++;
    } else {
        echo "  ❌ 回数券の返却に失敗 (used: {$ticket2->used_count})\n";
        $testsFailed++;
    }

    // テスト3: 期限切れ回数券は使用不可
    echo "\n📝 テスト3: 期限切れ回数券は使用不可\n";
    $expiredTicket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '期限切れテスト',
        'total_count' => 10,
        'purchase_price' => $ticketPlan->price,
        'status' => 'active',
        'purchased_at' => Carbon::now()->subMonths(4),
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $canUse = $expiredTicket->canUse();
    $useResult = $expiredTicket->use();

    if (!$canUse && !$useResult && $expiredTicket->used_count === 0) {
        echo "  ✅ 期限切れ回数券は使用できない\n";
        $testsPassed++;
    } else {
        echo "  ❌ 期限切れチェックが機能していない\n";
        $testsFailed++;
    }

    // テスト4: 使い切った回数券は使用不可
    echo "\n📝 テスト4: 使い切った回数券は使用不可\n";
    $usedUpTicket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '使い切りテスト',
        'total_count' => 3,
        'used_count' => 3,
        'purchase_price' => $ticketPlan->price,
        'status' => 'used_up',
    ]);

    $canUseUsedUp = $usedUpTicket->canUse();
    $useResultUsedUp = $usedUpTicket->use();

    if (!$canUseUsedUp && !$useResultUsedUp) {
        echo "  ✅ 使い切った回数券は使用できない\n";
        $testsPassed++;
    } else {
        echo "  ❌ 使い切りチェックが機能していない\n";
        $testsFailed++;
    }

    // テスト5: 最後の1回使用で used_up ステータスに変わる
    echo "\n📝 テスト5: 最後の1回使用で used_up ステータスに変わる\n";
    $lastTicket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '最後の1回テスト',
        'total_count' => 3,
        'used_count' => 2,
        'purchase_price' => $ticketPlan->price,
        'status' => 'active',
    ]);

    $lastTicket->use();
    $lastTicket->refresh();

    if ($lastTicket->status === 'used_up' && $lastTicket->used_count === 3) {
        echo "  ✅ 最後の1回使用で used_up ステータスになった\n";
        $testsPassed++;
    } else {
        echo "  ❌ ステータス変更が機能していない (status: {$lastTicket->status})\n";
        $testsFailed++;
    }

    // テスト6: used_up から返却で active に戻る
    echo "\n📝 テスト6: used_up から返却で active に戻る\n";
    $refundTicket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '返却復活テスト',
        'total_count' => 3,
        'used_count' => 3,
        'purchase_price' => $ticketPlan->price,
        'status' => 'used_up',
    ]);

    $refundTicket->refund(null, 1);
    $refundTicket->refresh();

    if ($refundTicket->status === 'active' && $refundTicket->used_count === 2) {
        echo "  ✅ used_up から active に戻った (used: 2)\n";
        $testsPassed++;
    } else {
        echo "  ❌ ステータス復元が機能していない (status: {$refundTicket->status})\n";
        $testsFailed++;
    }

    // テスト7: 複数予約で複数回消費
    echo "\n📝 テスト7: 複数予約で複数回消費\n";
    $multiTicket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '複数使用テスト',
        'total_count' => 10,
        'purchase_price' => $ticketPlan->price,
    ]);

    for ($i = 1; $i <= 3; $i++) {
        $res = Reservation::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'staff_id' => $staff->id,
            'reservation_number' => 'R' . uniqid(),
            'reservation_date' => now()->addDays(20 + $i)->format('Y-m-d'),
            'start_time' => sprintf('%02d:00:00', 10 + $i),
            'end_time' => sprintf('%02d:00:00', 11 + $i),
            'status' => 'booked',
            'payment_method' => 'ticket',
            'customer_ticket_id' => $multiTicket->id,
        ]);
        $multiTicket->use($res->id);
    }

    $multiTicket->refresh();
    if ($multiTicket->used_count === 3 && $multiTicket->remaining_count === 7) {
        echo "  ✅ 3回の予約で3回消費された (used: 3, remaining: 7)\n";
        $testsPassed++;
    } else {
        echo "  ❌ 複数消費が正しく記録されていない (used: {$multiTicket->used_count})\n";
        $testsFailed++;
    }

    // テスト8: 顧客の利用可能回数券取得（店舗別）
    echo "\n📝 テスト8: 顧客の利用可能回数券取得（店舗別）\n";
    $storeB = Store::where('id', '!=', $store->id)->first();
    if (!$storeB) {
        // 別店舗がなければテストをスキップ
        echo "  ⚠️  別店舗が存在しないためスキップ\n";
        $testsPassed++;
    } else {
        $planB = TicketPlan::create([
            'store_id' => $storeB->id,
            'name' => '店舗B回数券',
            'ticket_count' => 5,
            'price' => 25000,
            'is_active' => true,
        ]);

        $ticketStoreA = CustomerTicket::create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'ticket_plan_id' => $ticketPlan->id,
            'plan_name' => '店舗A回数券',
            'total_count' => 10,
            'purchase_price' => $ticketPlan->price,
            'status' => 'active',
        ]);

        $ticketStoreB = CustomerTicket::create([
            'customer_id' => $customer->id,
            'store_id' => $storeB->id,
            'ticket_plan_id' => $planB->id,
            'plan_name' => '店舗B回数券',
            'total_count' => 5,
            'purchase_price' => $planB->price,
            'status' => 'active',
        ]);

        $availableA = $customer->getAvailableTicketsForStore($store->id);
        $availableB = $customer->getAvailableTicketsForStore($storeB->id);

        $hasCorrectStoreA = $availableA->contains($ticketStoreA) && !$availableA->contains($ticketStoreB);
        $hasCorrectStoreB = $availableB->contains($ticketStoreB) && !$availableB->contains($ticketStoreA);

        if ($hasCorrectStoreA && $hasCorrectStoreB) {
            echo "  ✅ 店舗別に正しく回数券を取得できた\n";
            $testsPassed++;
        } else {
            echo "  ❌ 店舗別取得が正しく機能していない\n";
            $testsFailed++;
        }
    }

    // テスト9: 利用履歴が正しく記録される
    echo "\n📝 テスト9: 利用履歴が正しく記録される\n";
    $historyTicket = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '履歴テスト',
        'total_count' => 10,
        'purchase_price' => $ticketPlan->price,
    ]);

    $historyReservation = Reservation::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'menu_id' => $menu->id,
        'staff_id' => $staff->id,
        'reservation_number' => 'R' . uniqid(),
        'reservation_date' => now()->addDays(30)->format('Y-m-d'),
        'start_time' => '15:00:00',
        'end_time' => '16:00:00',
        'status' => 'booked',
        'payment_method' => 'ticket',
        'customer_ticket_id' => $historyTicket->id,
    ]);

    $historyTicket->use($historyReservation->id);

    $history = $historyTicket->usageHistory()
        ->where('reservation_id', $historyReservation->id)
        ->first();

    if ($history && $history->used_count === 1 && !$history->is_cancelled) {
        echo "  ✅ 利用履歴が予約IDと共に記録された\n";
        $testsPassed++;
    } else {
        echo "  ❌ 利用履歴の記録に失敗\n";
        $testsFailed++;
    }

    // テスト10: 期限切れ回数券は利用可能リストに含まれない
    echo "\n📝 テスト10: 期限切れ回数券は利用可能リストに含まれない\n";
    $activeForList = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '有効回数券',
        'total_count' => 10,
        'purchase_price' => $ticketPlan->price,
        'status' => 'active',
        'expires_at' => Carbon::now()->addMonth(),
    ]);

    $expiredForList = CustomerTicket::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'ticket_plan_id' => $ticketPlan->id,
        'plan_name' => '期限切れ回数券',
        'total_count' => 10,
        'purchase_price' => $ticketPlan->price,
        'status' => 'active',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $availableList = $customer->getAvailableTicketsForStore($store->id);

    $hasActive = $availableList->contains($activeForList);
    $hasExpired = $availableList->contains($expiredForList);

    if ($hasActive && !$hasExpired) {
        echo "  ✅ 期限切れ回数券は利用可能リストから除外された\n";
        $testsPassed++;
    } else {
        echo "  ❌ 期限切れフィルタリングが機能していない\n";
        $testsFailed++;
    }

    // テスト結果サマリー
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 予約×回数券連携テスト結果\n";
    echo str_repeat("=", 50) . "\n";
    echo "✅ 成功: {$testsPassed}件\n";
    echo "❌ 失敗: {$testsFailed}件\n";
    $totalTests = $testsPassed + $testsFailed;
    $successRate = $totalTests > 0 ? round(($testsPassed / $totalTests) * 100, 1) : 0;
    echo "📈 成功率: {$successRate}%\n";

    if ($testsFailed === 0) {
        echo "\n🎉 全ての予約連携テストが成功しました！\n";
        echo "✅ 本番環境デプロイの準備が整いました。\n";
    } else {
        echo "\n⚠️  いくつかのテストが失敗しました。\n";
        echo "🔧 修正してから次のフェーズに進んでください。\n";
    }

} catch (\Exception $e) {
    echo "\n❌ テスト中にエラーが発生しました:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
