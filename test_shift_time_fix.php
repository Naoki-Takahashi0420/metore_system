<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Store;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// テスト用Widget
class TestWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore = 2;
    public $selectedDate;

    public function __construct()
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }

    public function testReservation($startTime, $endTime)
    {
        $store = Store::find(2);
        $date = Carbon::now();
        return $this->canReserveAtTimeSlot($startTime, $endTime, $store, $date);
    }
}

$widget = new TestWidget();

echo "=== 修正後の予約可能性テスト ===\n";
echo "店舗: 新宿店（スタッフシフトモード）\n";
echo "スタッフシフト: 9:00-14:00\n";
echo "営業時間: 13:00-22:00\n\n";

$testTimes = [
    ['09:00', '10:00', 'シフト開始'],
    ['10:00', '11:00', 'シフト内'],
    ['11:00', '12:00', 'シフト内'],
    ['12:00', '13:00', 'シフト内・営業前'],
    ['13:00', '14:00', 'シフト内・営業中'],
    ['13:30', '14:30', 'シフト境界'],
    ['14:00', '15:00', 'シフト後'],
    ['15:00', '16:00', 'シフト後']
];

foreach ($testTimes as $test) {
    list($start, $end, $desc) = $test;
    $result = $widget->testReservation($start, $end);
    $status = $result['can_reserve'] ? '✅' : '❌';
    echo "{$start}-{$end} ({$desc}): {$status} ";
    echo "[";
    echo "容量:{$result['total_capacity']}, ";
    echo "既存:{$result['existing_reservations']}, ";
    echo "空き:{$result['available_slots']}] ";
    echo $result['reason'] . "\n";
}

echo "\n=== サブ枠の予約カウント確認 ===\n";
$existingReservations = \App\Models\Reservation::where('store_id', 2)
    ->whereDate('reservation_date', Carbon::now()->format('Y-m-d'))
    ->whereNotIn('status', ['cancelled', 'canceled'])
    ->where(function ($q) {
        $q->where('start_time', '<', '14:00')
          ->where('end_time', '>', '13:00');
    })
    ->get();

echo "13:00-14:00と重なる全予約数: " . $existingReservations->count() . "\n";
$mainReservations = $existingReservations->where('is_sub', false)->where('line_type', '!=', 'sub');
echo "メイン枠のみの予約数: " . $mainReservations->count() . "\n";