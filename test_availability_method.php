<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Store;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ReservationTimelineWidgetのインスタンスを作成（テスト用）
class TestTimelineWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore = 2; // 新宿店
    public $selectedDate;

    public function __construct()
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }
}

$widget = new TestTimelineWidget();
$store = Store::find(2);
$date = Carbon::now();

echo "=== 統合的予約可能性判定メソッドのテスト ===\n";
echo "店舗: {$store->name}\n";
echo "モード: " . ($store->use_staff_assignment ? 'スタッフシフト' : '営業時間ベース') . "\n";
echo "容量: {$store->shift_based_capacity}\n";
echo "日付: {$date->format('Y-m-d')}\n\n";

// テストケース
$testCases = [
    ['13:00', '14:00', '既存予約と重複（13:30-15:00）'],
    ['13:30', '14:30', '既存予約と完全重複（13:30-15:00）'],
    ['14:00', '15:00', '既存予約と重複（13:30-15:00）'],
    ['15:30', '16:30', '既存予約と重複（15:00-16:30）'],
    ['16:30', '17:30', '既存予約と重複なし'],
    ['10:00', '11:00', 'シフト時間内（9:00-14:00）'],
    ['18:00', '19:00', 'シフト外・営業時間内'],
];

foreach ($testCases as $index => $case) {
    list($startTime, $endTime, $description) = $case;

    echo "--- テスト " . ($index + 1) . ": {$description} ---\n";
    echo "時間: {$startTime} - {$endTime}\n";

    try {
        $result = $widget->canReserveAtTimeSlot($startTime, $endTime, $store, $date);

        echo "結果: " . ($result['can_reserve'] ? "✅ 予約可能" : "❌ 予約不可") . "\n";
        echo "理由: {$result['reason']}\n";
        echo "容量: {$result['total_capacity']} / 既存: {$result['existing_reservations']} / 空き: {$result['available_slots']}\n";
        echo "モード: {$result['mode']}\n";

    } catch (\Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "テスト完了\n";