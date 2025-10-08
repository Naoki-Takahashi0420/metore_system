<?php

/**
 * データベース内のカンマ区切り視力データを検出するスクリプト
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MedicalRecord;

echo "==========================================\n";
echo "カンマ区切り視力データの検出\n";
echo "==========================================\n\n";

// 全カルテを取得
$records = MedicalRecord::whereNotNull('vision_records')->get();

echo "検査対象カルテ数: " . $records->count() . "\n\n";

$issuesFound = [];
$totalIssues = 0;

foreach ($records as $record) {
    $visionRecords = $record->vision_records ?? [];

    if (empty($visionRecords)) {
        continue;
    }

    foreach ($visionRecords as $visionIndex => $vision) {
        $recordIssues = [];

        // 各視力フィールドをチェック
        $fieldsToCheck = [
            'before_naked_left' => '左眼（施術前）裸眼',
            'before_naked_right' => '右眼（施術前）裸眼',
            'after_naked_left' => '左眼（施術後）裸眼',
            'after_naked_right' => '右眼（施術後）裸眼',
            'before_corrected_left' => '左眼（施術前）矯正',
            'before_corrected_right' => '右眼（施術前）矯正',
            'after_corrected_left' => '左眼（施術後）矯正',
            'after_corrected_right' => '右眼（施術後）矯正',
        ];

        foreach ($fieldsToCheck as $field => $label) {
            $value = $vision[$field] ?? null;

            // カンマを含む文字列をチェック
            if (is_string($value) && strpos($value, ',') !== false) {
                $recordIssues[] = [
                    'field' => $field,
                    'label' => $label,
                    'value' => $value,
                    'converted' => (float)str_replace(',', '.', $value),
                ];
                $totalIssues++;
            }
        }

        if (!empty($recordIssues)) {
            $issuesFound[] = [
                'record_id' => $record->id,
                'customer_id' => $record->customer_id,
                'treatment_date' => $record->treatment_date?->format('Y-m-d'),
                'vision_index' => $visionIndex,
                'issues' => $recordIssues,
            ];
        }
    }
}

if (empty($issuesFound)) {
    echo "✅ カンマ区切りのデータは見つかりませんでした。\n";
} else {
    echo "⚠️  カンマ区切りのデータが見つかりました: " . count($issuesFound) . " カルテ, {$totalIssues} 件\n\n";

    foreach ($issuesFound as $issue) {
        echo "─────────────────────────────────────────\n";
        echo "カルテID: {$issue['record_id']}\n";
        echo "顧客ID: {$issue['customer_id']}\n";
        echo "施術日: {$issue['treatment_date']}\n";
        echo "視力記録 #{$issue['vision_index']}\n";
        echo "─────────────────────────────────────────\n";

        foreach ($issue['issues'] as $problem) {
            echo "  {$problem['label']}: '{$problem['value']}' → {$problem['converted']}\n";
        }

        echo "\n";
    }

    echo "==========================================\n";
    echo "修正用SQLスクリプト（参考）\n";
    echo "==========================================\n\n";

    echo "-- 以下のSQLを実行して一括修正できます\n";
    echo "-- ただし、本番環境では必ずバックアップを取ってから実行してください！\n\n";

    foreach ($issuesFound as $issue) {
        $recordId = $issue['record_id'];
        echo "-- カルテID {$recordId} の修正\n";
        echo "-- TODO: PHPスクリプトでの修正を推奨（JSON操作が必要）\n\n";
    }
}

echo "\n==========================================\n";
echo "検査完了\n";
echo "==========================================\n";

// 修正スクリプトの生成
if (!empty($issuesFound)) {
    echo "\n💡 修正スクリプトを生成する場合は、以下のコマンドを実行してください:\n";
    echo "   php fix-comma-values.php --dry-run  # プレビューのみ\n";
    echo "   php fix-comma-values.php            # 実際に修正\n";
}
