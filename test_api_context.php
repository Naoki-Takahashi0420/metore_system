<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReservationContextService;
use App\Models\Customer;

$contextService = new ReservationContextService();

// 電話番号08033372305の顧客を取得
$customer = Customer::where('phone', '08033372305')->first();

if (!$customer) {
    echo "顧客が見つかりません\n";
    exit;
}

echo "顧客情報:\n";
echo "ID: " . $customer->id . "\n";
echo "名前: " . $customer->full_name . "\n";
echo "電話: " . $customer->phone . "\n\n";

// 最新の予約を取得
$lastReservation = $customer->reservations()
    ->whereNotIn('status', ['cancelled', 'canceled'])
    ->orderBy('reservation_date', 'desc')
    ->orderBy('start_time', 'desc')
    ->first();

if ($lastReservation) {
    echo "最新予約:\n";
    echo "店舗ID: " . $lastReservation->store_id . "\n";
    echo "予約日: " . $lastReservation->reservation_date . "\n";

    // APIと同じロジックでコンテキストを生成
    $encryptedContext = $contextService->createMedicalRecordContext($customer->id, $lastReservation->store_id);

    echo "\n生成されたコンテキスト (暗号化済み):\n";
    echo $encryptedContext . "\n\n";

    // 復号化して確認
    $decryptedContext = $contextService->decryptContext($encryptedContext);
    echo "復号化されたコンテキスト:\n";
    print_r($decryptedContext);

} else {
    echo "予約履歴がありません\n";
}