<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Store;
use App\Models\Reservation;
use Carbon\Carbon;

// Laravelアプリケーションの初期化
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== getBaseQuery テスト ===\n\n";

// Widget経由でのテスト
class QueryWidget extends \App\Filament\Widgets\ReservationTimelineWidget
{
    public $selectedStore = 2;
    public $selectedDate;

    public function __construct()
    {
        $this->selectedDate = Carbon::now()->format('Y-m-d');
    }

    public function testQuery()
    {
        $date = Carbon::parse($this->selectedDate);

        // getBaseQuery経由
        echo "=== getBaseQuery経由 ===\n";
        $reservations1 = $this->getBaseQuery()
            ->with(['customer', 'menu', 'staff'])
            ->where('store_id', $this->selectedStore)
            ->whereDate('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('start_time')
            ->get();

        echo "予約数: " . $reservations1->count() . "\n";
        foreach ($reservations1 as $r) {
            echo "  ID {$r->id}: {$r->customer->last_name} {$r->customer->first_name}\n";
        }

        // 直接Reservation::query()経由
        echo "\n=== 直接Reservation::query()経由 ===\n";
        $reservations2 = Reservation::query()
            ->with(['customer', 'menu', 'staff'])
            ->where('store_id', $this->selectedStore)
            ->whereDate('reservation_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('start_time')
            ->get();

        echo "予約数: " . $reservations2->count() . "\n";
        foreach ($reservations2 as $r) {
            echo "  ID {$r->id}: {$r->customer->last_name} {$r->customer->first_name}\n";
        }

        // loadTimelineDataの実際の処理を確認
        echo "\n=== loadTimelineData内部処理 ===\n";
        $this->loadTimelineData();

        // timelineDataの中身を確認
        if (isset($this->timelineData['timeline'])) {
            $totalReservations = 0;
            foreach ($this->timelineData['timeline'] as $key => $line) {
                $count = count($line['reservations']);
                $totalReservations += $count;
                if ($count > 0) {
                    echo "  {$key}: {$count} 件の予約\n";
                }
            }
            echo "合計予約数: {$totalReservations}\n";
        } else {
            echo "timelineDataが設定されていません\n";
        }
    }
}

// ユーザー権限を模擬
auth()->loginUsingId(1); // 管理者として実行

$widget = new QueryWidget();
$widget->testQuery();