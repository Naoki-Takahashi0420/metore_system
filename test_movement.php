<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Store;
use App\Models\Reservation;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 予約移動テスト ===\n\n";

// テスト対象の予約を取得（最新の未指定予約）
$reservation = Reservation::where('store_id', 2)
    ->whereDate('reservation_date', Carbon::now())
    ->where(function($q) {
        $q->whereNull('staff_id')
          ->orWhere('line_type', 'unassigned');
    })
    ->orderBy('id', 'desc')
    ->first();

if (!$reservation) {
    echo "テスト対象の予約が見つかりません。\n";
    exit;
}

echo "対象予約:\n";
echo "  ID: {$reservation->id}\n";
echo "  顧客: {$reservation->customer->last_name} {$reservation->customer->first_name}\n";
echo "  時間: {$reservation->start_time} - {$reservation->end_time}\n";
echo "  現在のスタッフID: " . ($reservation->staff_id ?? 'null') . "\n";
echo "  現在のline_type: {$reservation->line_type}\n";
echo "  現在のline_number: " . ($reservation->line_number ?? 'null') . "\n\n";

// スタッフ2に移動
echo "スタッフID 2 に移動中...\n";
$updateResult = DB::table('reservations')
    ->where('id', $reservation->id)
    ->update([
        'is_sub' => false,
        'line_type' => 'staff',
        'line_number' => 1,
        'seat_number' => null,
        'staff_id' => 2,
        'updated_at' => now()
    ]);

echo "更新結果: " . ($updateResult ? "成功" : "失敗") . "\n";

// 更新後の状態を確認
$updatedReservation = Reservation::find($reservation->id);
echo "\n更新後の状態:\n";
echo "  スタッフID: " . ($updatedReservation->staff_id ?? 'null') . "\n";
echo "  line_type: {$updatedReservation->line_type}\n";
echo "  line_number: " . ($updatedReservation->line_number ?? 'null') . "\n\n";

// Widget経由での表示確認
class TestWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore = 2;
    public $selectedDate;

    public function __construct()
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }

    public function testLoadTimeline()
    {
        $this->loadTimelineData();
        return $this->timelineData;
    }
}

$widget = new TestWidget();
$timelineData = $widget->testLoadTimeline();

echo "=== タイムライン表示確認 ===\n";
foreach ($timelineData['timeline'] as $key => $line) {
    echo "{$line['label']}:\n";
    foreach ($line['reservations'] as $res) {
        if ($res['id'] == $reservation->id) {
            echo "  ★ 予約ID {$res['id']}: {$res['customer_name']} (移動後の予約)\n";
        } else {
            echo "  - 予約ID {$res['id']}: {$res['customer_name']}\n";
        }
    }
}

// 未指定に戻す
echo "\n未指定に戻す...\n";
DB::table('reservations')
    ->where('id', $reservation->id)
    ->update([
        'is_sub' => false,
        'line_type' => 'unassigned',
        'line_number' => 1,
        'seat_number' => null,
        'staff_id' => null,
        'updated_at' => now()
    ]);

$finalReservation = Reservation::find($reservation->id);
echo "最終状態:\n";
echo "  スタッフID: " . ($finalReservation->staff_id ?? 'null') . "\n";
echo "  line_type: {$finalReservation->line_type}\n";