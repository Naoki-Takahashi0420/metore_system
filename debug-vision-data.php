<?php

/**
 * 視力推移グラフのデバッグ用スクリプト
 * カルテID 132の視力データを調査
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MedicalRecord;
use Illuminate\Support\Facades\DB;

echo "==========================================\n";
echo "視力推移グラフ デバッグレポート\n";
echo "==========================================\n\n";

// カルテID 132を取得
$medicalRecordId = 132;
$medicalRecord = MedicalRecord::find($medicalRecordId);

if (!$medicalRecord) {
    echo "❌ カルテID {$medicalRecordId} が見つかりません\n";
    exit(1);
}

echo "✅ カルテID: {$medicalRecord->id}\n";
echo "📅 施術日: {$medicalRecord->treatment_date}\n";
echo "👤 顧客ID: {$medicalRecord->customer_id}\n\n";

// 同じ顧客の全カルテを取得
$allRecords = MedicalRecord::where('customer_id', $medicalRecord->customer_id)
    ->orderBy('treatment_date', 'asc')
    ->get();

echo "📊 顧客の全カルテ数: " . $allRecords->count() . "\n\n";

// 各カルテの視力データを表示
foreach ($allRecords as $index => $record) {
    echo "─────────────────────────────────────────\n";
    echo "カルテ #{$record->id} (施術日: {$record->treatment_date})\n";
    echo "─────────────────────────────────────────\n";

    $visionRecords = $record->vision_records ?? [];

    if (empty($visionRecords)) {
        echo "  ⚠️  視力データなし\n\n";
        continue;
    }

    foreach ($visionRecords as $visionIndex => $vision) {
        echo "\n  📝 視力記録 #{$visionIndex}\n";
        echo "  ───────────────────────────\n";

        // 日付
        $date = $vision['date'] ?? $record->treatment_date?->format('Y-m-d') ?? '不明';
        echo "  日付: {$date}\n\n";

        // 裸眼視力
        echo "  【裸眼視力】\n";
        echo "    左眼（施術前）: ";
        var_export($vision['before_naked_left'] ?? null);
        echo " → 型: " . gettype($vision['before_naked_left'] ?? null) . "\n";

        echo "    左眼（施術後）: ";
        var_export($vision['after_naked_left'] ?? null);
        echo " → 型: " . gettype($vision['after_naked_left'] ?? null) . "\n";

        echo "    右眼（施術前）: ";
        var_export($vision['before_naked_right'] ?? null);
        echo " → 型: " . gettype($vision['before_naked_right'] ?? null) . "\n";

        echo "    右眼（施術後）: ";
        var_export($vision['after_naked_right'] ?? null);
        echo " → 型: " . gettype($vision['after_naked_right'] ?? null) . "\n\n";

        // 矯正視力
        echo "  【矯正視力】\n";
        echo "    左眼（施術前）: ";
        var_export($vision['before_corrected_left'] ?? null);
        echo " → 型: " . gettype($vision['before_corrected_left'] ?? null) . "\n";

        echo "    左眼（施術後）: ";
        var_export($vision['after_corrected_left'] ?? null);
        echo " → 型: " . gettype($vision['after_corrected_left'] ?? null) . "\n";

        echo "    右眼（施術前）: ";
        var_export($vision['before_corrected_right'] ?? null);
        echo " → 型: " . gettype($vision['before_corrected_right'] ?? null) . "\n";

        echo "    右眼（施術後）: ";
        var_export($vision['after_corrected_right'] ?? null);
        echo " → 型: " . gettype($vision['after_corrected_right'] ?? null) . "\n\n";

        // 変換後の値をテスト
        echo "  【変換後の値（現在のロジック）】\n";
        $leftCorrectedBefore = (isset($vision['before_corrected_left']) && $vision['before_corrected_left'] !== '' && (float)$vision['before_corrected_left'] >= 0) ? (float)$vision['before_corrected_left'] : null;
        echo "    左眼（施術前）矯正: ";
        var_export($leftCorrectedBefore);
        echo " → nullか?: " . ($leftCorrectedBefore === null ? 'YES' : 'NO') . "\n";

        // 詳細な条件チェック
        echo "\n  【条件チェック詳細】\n";
        echo "    isset: " . (isset($vision['before_corrected_left']) ? 'true' : 'false') . "\n";
        echo "    !== '': " . (isset($vision['before_corrected_left']) && $vision['before_corrected_left'] !== '' ? 'true' : 'false') . "\n";
        if (isset($vision['before_corrected_left'])) {
            echo "    (float)値: " . (float)$vision['before_corrected_left'] . "\n";
            echo "    >= 0: " . ((float)$vision['before_corrected_left'] >= 0 ? 'true' : 'false') . "\n";
        }
    }

    echo "\n";
}

echo "\n==========================================\n";
echo "データベースの生の値を確認\n";
echo "==========================================\n\n";

// データベースから直接取得
$rawData = DB::table('medical_records')
    ->where('id', $medicalRecordId)
    ->first();

echo "vision_records カラムの生の値:\n";
echo $rawData->vision_records ?? 'NULL';
echo "\n\n";

echo "==========================================\n";
echo "デバッグ完了\n";
echo "==========================================\n";
