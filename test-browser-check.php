<?php
// ブラウザで実際の動作を確認するためのテストページ

use App\Models\User;
use App\Models\Reservation;
use App\Models\Customer;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// テストユーザーでログイン
$testEmail = $_GET['user'] ?? 'staff0_1@eye-training.jp';
$user = User::where('email', $testEmail)->first();

if (!$user) {
    die("ユーザーが見つかりません: {$testEmail}");
}

// Filamentの認証コンテキストを設定
\Filament\Facades\Filament::auth()->login($user);
auth()->login($user);

// ReservationResourceのクエリを実行
$reservationQuery = \App\Filament\Resources\ReservationResource::getEloquentQuery();
$reservations = $reservationQuery->with(['store', 'customer'])->limit(10)->get();

// CustomerResourceのクエリを実行
$customerQuery = \App\Filament\Resources\CustomerResource::getEloquentQuery();
$customers = $customerQuery->limit(10)->get();

?>
<!DOCTYPE html>
<html>
<head>
    <title>権限テスト結果</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .user-info { background: #e8f4f8; padding: 10px; margin-bottom: 20px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="user-info">
        <h2>ログインユーザー情報</h2>
        <p><strong>名前:</strong> <?= htmlspecialchars($user->name) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user->email) ?></p>
        <p><strong>ロール:</strong> <?= htmlspecialchars($user->roles->first()->name ?? 'なし') ?></p>
        <p><strong>所属店舗:</strong> <?= htmlspecialchars($user->store->name ?? 'なし') ?> (ID: <?= $user->store_id ?? 'なし' ?>)</p>
    </div>

    <h2>アクセス可能な予約データ（最大10件）</h2>
    <?php if ($reservations->isEmpty()): ?>
        <p class="error">予約データがありません</p>
    <?php else: ?>
        <table>
            <tr>
                <th>予約番号</th>
                <th>店舗</th>
                <th>顧客名</th>
                <th>予約日</th>
            </tr>
            <?php foreach ($reservations as $reservation): ?>
            <tr>
                <td><?= htmlspecialchars($reservation->reservation_number) ?></td>
                <td><?= htmlspecialchars($reservation->store->name ?? 'なし') ?></td>
                <td><?= htmlspecialchars(($reservation->customer->last_name ?? '') . ' ' . ($reservation->customer->first_name ?? '')) ?></td>
                <td><?= htmlspecialchars($reservation->reservation_date) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="success">合計 <?= $reservationQuery->count() ?> 件の予約にアクセス可能</p>
    <?php endif; ?>

    <h2>アクセス可能な顧客データ（最大10件）</h2>
    <?php if ($customers->isEmpty()): ?>
        <p class="error">顧客データがありません</p>
    <?php else: ?>
        <table>
            <tr>
                <th>顧客名</th>
                <th>電話番号</th>
                <th>メール</th>
            </tr>
            <?php foreach ($customers as $customer): ?>
            <tr>
                <td><?= htmlspecialchars($customer->last_name . ' ' . $customer->first_name) ?></td>
                <td><?= htmlspecialchars($customer->phone ?? 'なし') ?></td>
                <td><?= htmlspecialchars($customer->email ?? 'なし') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="success">合計 <?= $customerQuery->count() ?> 件の顧客にアクセス可能</p>
    <?php endif; ?>

    <h2>他のユーザーでテスト</h2>
    <ul>
        <li><a href="?user=admin@eye-training.com">super_admin でテスト</a></li>
        <li><a href="?user=owner@test.com">owner でテスト</a></li>
        <li><a href="?user=manager1@eye-training.jp">manager（銀座本店）でテスト</a></li>
        <li><a href="?user=staff0_1@eye-training.jp">staff（銀座本店）でテスト</a></li>
        <li><a href="?user=manager2@eye-training.jp">manager（新宿店）でテスト</a></li>
        <li><a href="?user=staff1_1@eye-training.jp">staff（新宿店）でテスト</a></li>
    </ul>
</body>
</html>