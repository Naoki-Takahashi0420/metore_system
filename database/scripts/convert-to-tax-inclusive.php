#!/usr/bin/env php
<?php

/**
 * 売上データを外税計算から内税計算に変換するスクリプト
 *
 * 目的:
 *   既存の売上データ（外税計算：税抜+税額=税込）を
 *   内税計算（入力価格=税込）に変換する
 *
 * 影響:
 *   - sale_items.tax_amount を 0 に設定
 *   - sales.total_amount を税抜金額（税込 - 税額合計）に更新
 *
 * 実行方法:
 *   php database/scripts/convert-to-tax-inclusive.php
 *
 * 本番環境での実行:
 *   ssh ubuntu@54.64.54.226
 *   cd /var/www/html
 *   php database/scripts/convert-to-tax-inclusive.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== 売上データの内税変換スクリプト ===\n\n";

// ステップ1: 影響を受けるデータを確認
echo "📊 調整対象の売上データを確認中...\n\n";

$affectedSales = DB::table('sales as s')
    ->leftJoin('sale_items as si', 's.id', '=', 'si.sale_id')
    ->select(
        's.id as sale_id',
        's.sale_date',
        's.total_amount',
        DB::raw('COALESCE(SUM(si.tax_amount), 0) as total_tax')
    )
    ->whereNotNull('si.tax_amount')
    ->where('si.tax_amount', '>', 0)
    ->groupBy('s.id', 's.sale_date', 's.total_amount')
    ->orderBy('s.sale_date', 'desc')
    ->get();

if ($affectedSales->isEmpty()) {
    echo "✅ 調整が必要な売上データはありません。\n";
    exit(0);
}

echo "調整対象: " . $affectedSales->count() . "件\n\n";
echo "| sale_id | 日付       | 現在の金額 | 税額合計 | 調整後の金額 |\n";
echo "|---------|------------|-----------|---------|-------------|\n";

foreach ($affectedSales as $sale) {
    $newTotal = $sale->total_amount - $sale->total_tax;
    printf(
        "| %-7d | %-10s | ¥%-8s | ¥%-6s | ¥%-10s |\n",
        $sale->sale_id,
        substr($sale->sale_date, 0, 10),
        number_format($sale->total_amount),
        number_format($sale->total_tax),
        number_format($newTotal)
    );
}

echo "\n";
echo "⚠️  この操作は以下の変更を行います:\n";
echo "   1. sale_itemsの税額を0に設定\n";
echo "   2. salesの合計金額を税抜金額に更新\n\n";

// 本番環境では自動実行しない
if (app()->environment('production')) {
    echo "⚠️  本番環境では手動確認が必要です。\n";
    echo "続行しますか？ (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line !== 'yes') {
        echo "キャンセルしました。\n";
        exit(0);
    }
}

// ステップ2: トランザクション開始
DB::beginTransaction();

try {
    echo "🔄 データを更新中...\n\n";

    // ステップ2-1: sale_itemsの税額を0に更新
    $updatedItems = DB::table('sale_items')
        ->where('tax_amount', '>', 0)
        ->update([
            'tax_amount' => 0,
            'updated_at' => now()
        ]);

    echo "   ✓ sale_items更新: {$updatedItems}件\n";

    // ステップ2-2: 各売上の合計金額を更新
    $updatedSales = 0;
    foreach ($affectedSales as $sale) {
        $newTotal = $sale->total_amount - $sale->total_tax;

        DB::table('sales')
            ->where('id', $sale->sale_id)
            ->update([
                'total_amount' => $newTotal,
                'updated_at' => now()
            ]);

        $updatedSales++;
    }

    echo "   ✓ sales更新: {$updatedSales}件\n\n";

    // ステップ3: 更新結果を確認
    $remainingTax = DB::table('sale_items')
        ->where('tax_amount', '>', 0)
        ->count();

    if ($remainingTax > 0) {
        throw new Exception("税額が0になっていない明細が{$remainingTax}件残っています");
    }

    // コミット
    DB::commit();

    echo "✅ 売上データの内税変換が完了しました！\n\n";

    // 更新後のデータを表示
    echo "📊 更新後の売上データ:\n\n";

    $updatedSalesData = DB::table('sales')
        ->whereIn('id', $affectedSales->pluck('sale_id'))
        ->orderBy('sale_date', 'desc')
        ->get();

    foreach ($updatedSalesData as $sale) {
        echo sprintf(
            "  - ID: %d | 日付: %s | 金額: ¥%s | 支払: %s\n",
            $sale->id,
            substr($sale->sale_date, 0, 10),
            number_format($sale->total_amount),
            $sale->payment_method
        );
    }

    echo "\n✅ すべての処理が正常に完了しました。\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ エラーが発生しました: " . $e->getMessage() . "\n";
    echo "   変更はロールバックされました。\n";
    exit(1);
}
