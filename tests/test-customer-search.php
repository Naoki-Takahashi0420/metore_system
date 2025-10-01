<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$search = '高橋';
$storeId = 1;

try {
    // SQLiteとMySQLの互換性対応
    $dbDriver = DB::connection()->getDriverName();
    echo "DB Driver: $dbDriver\n";

    $results = \App\Models\Customer::where(function($query) use ($search, $dbDriver) {
            $query->where('phone', 'LIKE', '%' . $search . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('first_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('last_name_kana', 'LIKE', '%' . $search . '%')
                  ->orWhere('first_name_kana', 'LIKE', '%' . $search . '%');

            // SQLite: last_name || first_name, MySQL: CONCAT(last_name, first_name)
            if ($dbDriver === 'sqlite') {
                $query->orWhereRaw('(last_name || first_name) LIKE ?', ['%' . $search . '%'])
                      ->orWhereRaw('(last_name_kana || first_name_kana) LIKE ?', ['%' . $search . '%']);
            } else {
                $query->orWhereRaw('CONCAT(last_name, first_name) LIKE ?', ['%' . $search . '%'])
                      ->orWhereRaw('CONCAT(last_name_kana, first_name_kana) LIKE ?', ['%' . $search . '%']);
            }
        })
        ->withCount(['reservations' => function($query) use ($storeId) {
            $query->where('store_id', $storeId);
        }])
        ->with(['reservations' => function($query) use ($storeId) {
            $query->where('store_id', $storeId)
                  ->latest('reservation_date')
                  ->limit(1);
        }])
        ->limit(10)
        ->get();

    echo "✅ クエリ成功！\n";
    echo "検索結果: " . $results->count() . "件\n";

    foreach ($results as $customer) {
        echo "\n顧客: {$customer->last_name} {$customer->first_name}\n";
        echo "予約回数: {$customer->reservations_count}\n";
        $lastReservation = $customer->reservations->first();
        echo "最終予約: " . ($lastReservation ? $lastReservation->reservation_date : 'なし') . "\n";
    }
} catch (\Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
