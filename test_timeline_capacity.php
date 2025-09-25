<?php

require_once __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// タイムラインウィジェットのテスト
class TestTimelineWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore = 2; // 新宿店
    public $selectedDate;

    public function __construct()
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }

    // canReserveAtTimeSlotメソッドをパブリックにアクセス可能にする
    public function testCanReserveAtTimeSlot($startTime, $endTime)
    {
        $store = \App\Models\Store::find($this->selectedStore);
        $date = Carbon::parse($this->selectedDate);

        return $this->canReserveAtTimeSlot($startTime, $endTime, $store, $date);
    }
}

$widget = new TestTimelineWidget();

echo "=== タイムラインの容量チェック実装テスト ===\n";
echo "店舗: 新宿店\n";
echo "日付: " . Carbon::now()->format('Y-m-d') . "\n\n";

// 重要なテストケース
$testTimes = [
    ['13:00', '14:00', '13:30の既存予約と重複'],
    ['13:30', '14:30', '13:30の既存予約と完全重複'],
    ['14:30', '15:30', '15:00の既存予約と重複'],
    ['16:00', '17:00', 'シフト時間外（営業時間内）'],
    ['10:00', '11:00', 'シフト時間内・営業時間外'],
];

foreach ($testTimes as $index => $testCase) {
    list($startTime, $endTime, $description) = $testCase;

    echo "--- テスト " . ($index + 1) . ": {$description} ---\n";
    echo "時間: {$startTime} - {$endTime}\n";

    try {
        $result = $widget->testCanReserveAtTimeSlot($startTime, $endTime);

        $status = $result['can_reserve'] ? "✅ 予約可能" : "❌ 予約不可";
        echo "結果: {$status}\n";
        echo "理由: {$result['reason']}\n";
        echo "容量情報: {$result['total_capacity']}/{$result['existing_reservations']}/{$result['available_slots']}\n";
        echo "モード: {$result['mode']}\n";

        // タイムライン表示でのクリック可能性を判定
        $isClickableInTimeline = $result['can_reserve'];
        echo "タイムライン表示: " . ($isClickableInTimeline ? "クリック可能" : "クリック不可") . "\n";

    } catch (\Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "=== タイムライン表示の期待される動作 ===\n";
echo "• 13:00-14:30の時間帯: 容量満席のためクリック不可\n";
echo "• 14:30-15:30の時間帯: スタッフシフト時間外のためクリック不可\n";
echo "• 16:00以降: スタッフシフト時間外のためクリック不可\n";
echo "• 10:00-11:00: 営業時間外のためクリック不可\n";

echo "\nテスト完了\n";