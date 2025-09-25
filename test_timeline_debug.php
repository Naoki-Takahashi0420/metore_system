<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Store;
use App\Models\Reservation;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== タイムライン読み込みデバッグ ===\n\n";

// Widget経由での表示確認
class DebugWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore = 2;
    public $selectedDate;

    public function __construct()
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }

    public function debugLoadTimeline()
    {
        $store = \App\Models\Store::find($this->selectedStore);
        echo "店舗: {$store->name}\n";
        echo "モード: " . ($store->use_staff_assignment ? "スタッフシフト" : "営業時間") . "\n\n";

        $date = Carbon::parse($this->selectedDate);

        // 予約データを取得
        $reservations = Reservation::with(['customer', 'menu', 'staff'])
            ->where('store_id', $this->selectedStore)
            ->whereDate('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('start_time')
            ->get();

        echo "予約数: " . $reservations->count() . "\n";

        foreach ($reservations as $r) {
            echo "\n予約ID {$r->id}:\n";
            echo "  顧客: {$r->customer->last_name} {$r->customer->first_name}\n";
            echo "  時間: {$r->start_time} - {$r->end_time}\n";
            echo "  スタッフID: " . ($r->staff_id ?? 'null') . "\n";
            echo "  line_type: {$r->line_type}\n";
            echo "  line_number: " . ($r->line_number ?? 'null') . "\n";
            echo "  is_sub: " . ($r->is_sub ? 'true' : 'false') . "\n";
        }

        // タイムラインを読み込み
        $this->loadTimelineData();

        echo "\n=== タイムライン構造 ===\n";
        foreach ($this->timelineData['timeline'] as $key => $line) {
            echo "\nキー: {$key}\n";
            echo "  ラベル: {$line['label']}\n";
            echo "  タイプ: {$line['type']}\n";
            echo "  予約数: " . count($line['reservations']) . "\n";

            if (count($line['reservations']) > 0) {
                foreach ($line['reservations'] as $res) {
                    echo "    - ID {$res['id']}: {$res['customer_name']}\n";
                }
            }
        }

        return $this->timelineData;
    }
}

$widget = new DebugWidget();
$timelineData = $widget->debugLoadTimeline();