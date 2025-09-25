<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ReservationTimelineWidgetのテストインスタンス
class TestSubFrameWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore = 2; // 新宿店
    public $selectedDate;

    public function __construct()
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }

    // canMoveToSubメソッドをテストするためのメソッド
    public function testCanMoveToSub($reservationId)
    {
        return $this->canMoveToSub($reservationId);
    }

    public function testCanMoveToMain($reservationId, $seatNumber)
    {
        return $this->canMoveToMain($reservationId, $seatNumber);
    }

    public function testMoveToSub($reservationId)
    {
        $this->moveToSub($reservationId);
    }

    public function testMoveToMain($reservationId, $seatNumber)
    {
        $this->moveToMain($reservationId, $seatNumber);
    }
}

$widget = new TestSubFrameWidget();
$store = Store::find(2);

echo "=== サブ枠移動機能のテスト ===\n";
echo "店舗: {$store->name}\n";
echo "サブライン数: " . ($store->sub_lines_count ?? 0) . "\n";
echo "メインライン数: " . ($store->main_lines_count ?? 3) . "\n\n";

// 既存の予約を取得
$reservations = Reservation::where('store_id', 2)
    ->whereDate('reservation_date', Carbon::now()->format('Y-m-d'))
    ->orderBy('start_time')
    ->get();

echo "=== 本日の予約一覧 ===\n";
foreach ($reservations as $reservation) {
    $seatInfo = $reservation->is_sub ? 'サブ枠' : '席' . $reservation->seat_number;
    echo "予約ID: {$reservation->id}, {$reservation->start_time}-{$reservation->end_time}, {$seatInfo}, スタッフID: " . ($reservation->staff_id ?: 'null') . "\n";
}
echo "\n";

// 各予約についてサブ枠移動の可能性をテスト
echo "=== サブ枠移動可能性テスト ===\n";
foreach ($reservations as $reservation) {
    $seatInfo = $reservation->is_sub ? 'サブ枠' : '席' . $reservation->seat_number;

    echo "--- 予約ID {$reservation->id} ({$seatInfo}) ---\n";
    echo "時間: {$reservation->start_time} - {$reservation->end_time}\n";

    if (!$reservation->is_sub) {
        // メイン席からサブ枠への移動可能性
        $canMoveToSub = $widget->testCanMoveToSub($reservation->id);
        echo "サブ枠への移動: " . ($canMoveToSub ? "✅ 可能" : "❌ 不可") . "\n";

        if ($canMoveToSub) {
            echo "  → 実際に移動テストを実行\n";
            try {
                // 移動前の状態を記録
                $beforeState = [
                    'is_sub' => $reservation->is_sub,
                    'seat_number' => $reservation->seat_number
                ];

                $widget->testMoveToSub($reservation->id);

                // 移動後の状態を確認
                $reservation->refresh();
                echo "  → 移動成功: サブ枠=" . ($reservation->is_sub ? 'true' : 'false') . "\n";

                // 元に戻す（テストなので）
                $reservation->is_sub = $beforeState['is_sub'];
                $reservation->seat_number = $beforeState['seat_number'];
                $reservation->line_type = 'main';
                $reservation->line_number = $beforeState['seat_number'];
                $reservation->save();
                echo "  → 元の状態に復元\n";

            } catch (\Exception $e) {
                echo "  → 移動エラー: " . $e->getMessage() . "\n";
            }
        }
    } else {
        // サブ枠からメイン席への移動可能性
        echo "現在サブ枠にいます。メイン席への移動可能性:\n";

        for ($seatNum = 1; $seatNum <= ($store->main_lines_count ?? 3); $seatNum++) {
            $canMoveToMain = $widget->testCanMoveToMain($reservation->id, $seatNum);
            echo "  席{$seatNum}への移動: " . ($canMoveToMain ? "✅ 可能" : "❌ 不可") . "\n";

            if ($canMoveToMain) {
                echo "    → 実際に移動テストを実行\n";
                try {
                    // 移動前の状態を記録
                    $beforeState = [
                        'is_sub' => $reservation->is_sub,
                        'seat_number' => $reservation->seat_number
                    ];

                    $widget->testMoveToMain($reservation->id, $seatNum);

                    // 移動後の状態を確認
                    $reservation->refresh();
                    echo "    → 移動成功: 席番号=" . $reservation->seat_number . "\n";

                    // 元に戻す（テストなので）
                    $reservation->is_sub = $beforeState['is_sub'];
                    $reservation->seat_number = $beforeState['seat_number'];
                    $reservation->line_type = 'sub';
                    $reservation->line_number = 1;
                    $reservation->save();
                    echo "    → 元の状態に復元\n";
                    break; // 1つ成功したら次へ

                } catch (\Exception $e) {
                    echo "    → 移動エラー: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    echo "\n";
}

echo "=== サブ枠移動機能の問題診断 ===\n";

// 店舗設定の確認
echo "店舗設定:\n";
echo "  - sub_lines_count: " . ($store->sub_lines_count ?? '未設定') . "\n";
echo "  - main_lines_count: " . ($store->main_lines_count ?? '未設定') . "\n";
echo "  - use_staff_assignment: " . ($store->use_staff_assignment ? 'true' : 'false') . "\n\n";

// checkAvailabilityメソッドの動作確認
if ($reservations->count() > 0) {
    $testReservation = $reservations->first();
    echo "checkAvailabilityメソッドの動作確認:\n";

    // 元の予約の複製を作成してサブ枠設定をテスト
    $tempReservation = clone $testReservation;
    $tempReservation->is_sub = true;
    $tempReservation->seat_number = null;

    try {
        $result = Reservation::checkAvailability($tempReservation);
        echo "  サブ枠への仮移動可否: " . ($result ? "✅ 可能" : "❌ 不可") . "\n";
    } catch (\Exception $e) {
        echo "  checkAvailabilityエラー: " . $e->getMessage() . "\n";
    }
}

echo "\nテスト完了\n";