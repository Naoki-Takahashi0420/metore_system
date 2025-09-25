<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Shift;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// タイムラインウィジェットのテストクラス
class TestBothModesWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore;
    public $selectedDate;

    public function __construct($storeId)
    {
        $this->selectedStore = $storeId;
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }

    public function testCanReserveAtTimeSlot($startTime, $endTime)
    {
        $store = Store::find($this->selectedStore);
        $date = Carbon::parse($this->selectedDate);
        return $this->canReserveAtTimeSlot($startTime, $endTime, $store, $date);
    }
}

echo "=== 営業時間ベース vs スタッフシフトベース 総合比較テスト ===\n\n";

// テスト対象店舗
$staffShiftStore = Store::find(2); // 新宿店（スタッフシフトモード）
$businessHoursStore = Store::find(3); // 横浜店（営業時間ベース）

echo "【スタッフシフトモード店舗】\n";
echo "店舗: {$staffShiftStore->name}\n";
echo "営業時間: 13:00-22:00\n";
echo "容量: {$staffShiftStore->shift_based_capacity}\n";
echo "メインライン: {$staffShiftStore->main_lines_count}\n";
echo "サブライン: {$staffShiftStore->sub_lines_count}\n\n";

echo "【営業時間ベースモード店舗】\n";
echo "店舗: {$businessHoursStore->name}\n";
echo "営業時間: 10:00-21:00\n";
echo "容量: {$businessHoursStore->shift_based_capacity}\n";
echo "メインライン: {$businessHoursStore->main_lines_count}\n";
echo "サブライン: {$businessHoursStore->sub_lines_count}\n\n";

// スタッフシフト情報表示
$today = Carbon::now()->format('Y-m-d');
$shifts = Shift::where('store_id', 2)
    ->whereDate('shift_date', $today)
    ->where('status', 'scheduled')
    ->get();

echo "【スタッフシフト情報（新宿店）】\n";
foreach ($shifts as $shift) {
    echo "スタッフID {$shift->user_id}: {$shift->start_time} - {$shift->end_time}\n";
}
echo "\n";

// 既存予約情報
echo "【既存予約（新宿店）】\n";
$existingReservationsStaff = Reservation::where('store_id', 2)
    ->whereDate('reservation_date', $today)
    ->orderBy('start_time')
    ->get();

foreach ($existingReservationsStaff as $r) {
    $seatInfo = $r->is_sub ? 'サブ枠' : '席' . $r->seat_number;
    echo "予約ID: {$r->id}, {$r->start_time}-{$r->end_time}, {$seatInfo}, スタッフID: " . ($r->staff_id ?: 'null') . "\n";
}
echo "\n";

echo "【既存予約（横浜店）】\n";
$existingReservationsBusiness = Reservation::where('store_id', 3)
    ->whereDate('reservation_date', $today)
    ->orderBy('start_time')
    ->get();

foreach ($existingReservationsBusiness as $r) {
    $seatInfo = $r->is_sub ? 'サブ枠' : '席' . $r->seat_number;
    echo "予約ID: {$r->id}, {$r->start_time}-{$r->end_time}, {$seatInfo}, スタッフID: " . ($r->staff_id ?: 'null') . "\n";
}

if ($existingReservationsBusiness->count() == 0) {
    echo "既存予約なし\n";
}
echo "\n";

// 比較テストケース
$testCases = [
    ['11:00', '12:00', '営業時間内（シフト前・営業時間内）'],
    ['13:00', '14:00', '営業時間内（シフト内）'],
    ['15:00', '16:00', '営業時間内（シフト外）'],
    ['18:00', '19:00', '営業時間内（夜間）'],
    ['09:00', '10:00', '営業時間外（朝）'],
    ['22:00', '23:00', '営業時間外（夜）'],
];

echo "=== 予約可能性比較テスト ===\n\n";

$staffWidget = new TestBothModesWidget(2);
$businessWidget = new TestBothModesWidget(3);

foreach ($testCases as $index => $testCase) {
    list($startTime, $endTime, $description) = $testCase;

    echo "--- テスト " . ($index + 1) . ": {$description} ---\n";
    echo "時間帯: {$startTime} - {$endTime}\n\n";

    // スタッフシフトモードテスト
    echo "【スタッフシフトモード結果】\n";
    try {
        $staffResult = $staffWidget->testCanReserveAtTimeSlot($startTime, $endTime);
        $staffStatus = $staffResult['can_reserve'] ? "✅ 予約可能" : "❌ 予約不可";
        echo "結果: {$staffStatus}\n";
        echo "理由: {$staffResult['reason']}\n";
        echo "容量: {$staffResult['total_capacity']} / 既存: {$staffResult['existing_reservations']} / 空き: {$staffResult['available_slots']}\n";
        echo "モード: {$staffResult['mode']}\n";
    } catch (\Exception $e) {
        echo "結果: ❌ エラー - " . $e->getMessage() . "\n";
    }

    echo "\n【営業時間ベースモード結果】\n";
    try {
        $businessResult = $businessWidget->testCanReserveAtTimeSlot($startTime, $endTime);
        $businessStatus = $businessResult['can_reserve'] ? "✅ 予約可能" : "❌ 予約不可";
        echo "結果: {$businessStatus}\n";
        echo "理由: {$businessResult['reason']}\n";
        echo "容量: {$businessResult['total_capacity']} / 既存: {$businessResult['existing_reservations']} / 空き: {$businessResult['available_slots']}\n";
        echo "モード: {$businessResult['mode']}\n";
    } catch (\Exception $e) {
        echo "結果: ❌ エラー - " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n\n";
}

echo "=== サブ枠移動テスト比較 ===\n\n";

// 仮の予約を作成して移動テスト
echo "【サブ枠独立性テスト】\n";
echo "両モードでサブ枠がスタッフシフトに依存しないかテスト\n\n";

// スタッフシフトモードのサブ枠テスト
echo "スタッフシフトモード - サブ枠への15:00-16:00移動（シフト外時間）:\n";
$testReservation = new Reservation();
$testReservation->store_id = 2;
$testReservation->reservation_date = Carbon::now()->format('Y-m-d');
$testReservation->start_time = '15:00';
$testReservation->end_time = '16:00';
$testReservation->line_type = 'sub';
$testReservation->is_sub = true;
$testReservation->seat_number = null;

try {
    $canReserveSubStaff = Reservation::checkAvailability($testReservation);
    echo $canReserveSubStaff ? "✅ サブ枠は独立してアクセス可能" : "❌ サブ枠がスタッフシフトに依存";
} catch (\Exception $e) {
    echo "❌ エラー: " . $e->getMessage();
}
echo "\n\n";

// 営業時間ベースモードのサブ枠テスト
echo "営業時間ベースモード - サブ枠への15:00-16:00移動:\n";
$testReservation->store_id = 3;

try {
    $canReserveSubBusiness = Reservation::checkAvailability($testReservation);
    echo $canReserveSubBusiness ? "✅ サブ枠は営業時間内でアクセス可能" : "❌ サブ枠がアクセス不可";
} catch (\Exception $e) {
    echo "❌ エラー: " . $e->getMessage();
}
echo "\n\n";

echo "=== 容量制限テスト比較 ===\n\n";

// 容量制限のテスト
echo "13:30-14:30の時間帯での容量制限テスト:\n";
echo "（既存予約がある時間帯で新規予約が拒否されるかテスト）\n\n";

echo "【スタッフシフトモード】\n";
try {
    $capacityTestStaff = $staffWidget->testCanReserveAtTimeSlot('13:30', '14:30');
    $staffCapacityStatus = $capacityTestStaff['can_reserve'] ? "✅ 予約可能" : "❌ 予約不可（容量満席）";
    echo "結果: {$staffCapacityStatus}\n";
    echo "詳細: {$capacityTestStaff['reason']}\n";
    echo "容量情報: {$capacityTestStaff['available_slots']}/{$capacityTestStaff['total_capacity']}\n";
} catch (\Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}

echo "\n【営業時間ベースモード】\n";
try {
    $capacityTestBusiness = $businessWidget->testCanReserveAtTimeSlot('13:30', '14:30');
    $businessCapacityStatus = $capacityTestBusiness['can_reserve'] ? "✅ 予約可能" : "❌ 予約不可（容量満席）";
    echo "結果: {$businessCapacityStatus}\n";
    echo "詳細: {$capacityTestBusiness['reason']}\n";
    echo "容量情報: {$capacityTestBusiness['available_slots']}/{$capacityTestBusiness['total_capacity']}\n";
} catch (\Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}

echo "\n=== テスト完了 ===\n";
echo "両モードの動作比較が完了しました。\n";
echo "スタッフシフトモード: スタッフの勤務時間に基づく制限 + 営業時間制限\n";
echo "営業時間ベースモード: 営業時間のみの制限\n";
echo "サブ枠: 両モードで営業時間内であれば独立してアクセス可能\n";