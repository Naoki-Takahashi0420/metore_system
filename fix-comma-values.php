<?php

/**
 * カンマ区切り視力データを修正するスクリプト
 *
 * 使い方:
 *   php fix-comma-values.php --dry-run  # プレビューのみ
 *   php fix-comma-values.php            # 実際に修正
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MedicalRecord;
use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv);

echo "==========================================\n";
echo "カンマ区切り視力データの修正\n";
echo "==========================================\n\n";

if ($dryRun) {
    echo "🔍 DRY RUN モード (プレビューのみ)\n\n";
} else {
    echo "⚠️  実際に修正します。本番環境では必ずバックアップを取ってください！\n\n";
    echo "続行しますか? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line !== 'y' && $line !== 'Y') {
        echo "キャンセルしました。\n";
        exit(0);
    }
    fclose($handle);
    echo "\n";
}

// 全カルテを取得
$records = MedicalRecord::whereNotNull('vision_records')->get();

echo "検査対象カルテ数: " . $records->count() . "\n\n";

$fixedCount = 0;
$fixedRecords = [];

DB::beginTransaction();

try {
    foreach ($records as $record) {
        $visionRecords = $record->vision_records ?? [];

        if (empty($visionRecords)) {
            continue;
        }

        $modified = false;

        foreach ($visionRecords as $visionIndex => &$vision) {
            // 各視力フィールドをチェック・修正
            $fieldsToCheck = [
                'before_naked_left',
                'before_naked_right',
                'after_naked_left',
                'after_naked_right',
                'before_corrected_left',
                'before_corrected_right',
                'after_corrected_left',
                'after_corrected_right',
            ];

            foreach ($fieldsToCheck as $field) {
                $value = $vision[$field] ?? null;

                // カンマを含む文字列をチェック
                if (is_string($value) && strpos($value, ',') !== false) {
                    $oldValue = $value;
                    $newValue = str_replace(',', '.', $value);

                    echo "カルテID {$record->id} - 視力記録 #{$visionIndex} - {$field}:\n";
                    echo "  変更前: '{$oldValue}'\n";
                    echo "  変更後: '{$newValue}'\n\n";

                    $vision[$field] = $newValue;
                    $modified = true;
                    $fixedCount++;
                }
            }
        }

        // 変更があった場合は保存
        if ($modified) {
            $fixedRecords[] = $record->id;

            if (!$dryRun) {
                $record->vision_records = $visionRecords;
                $record->save();
                echo "✅ カルテID {$record->id} を保存しました\n\n";
            } else {
                echo "🔍 (DRY RUN) カルテID {$record->id} の変更内容をプレビュー\n\n";
            }
        }
    }

    if (!$dryRun) {
        DB::commit();
        echo "✅ トランザクションをコミットしました\n\n";
    } else {
        DB::rollBack();
        echo "🔍 (DRY RUN) ロールバックしました\n\n";
    }
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ エラーが発生しました: {$e->getMessage()}\n";
    echo "トランザクションをロールバックしました\n";
    exit(1);
}

echo "==========================================\n";
echo "修正サマリー\n";
echo "==========================================\n\n";

if ($fixedCount === 0) {
    echo "✅ 修正が必要なデータは見つかりませんでした。\n";
} else {
    echo "修正された項目数: {$fixedCount}\n";
    echo "修正されたカルテ数: " . count($fixedRecords) . "\n";
    echo "カルテID: " . implode(', ', $fixedRecords) . "\n\n";

    if ($dryRun) {
        echo "💡 実際に修正する場合は、以下のコマンドを実行してください:\n";
        echo "   php fix-comma-values.php\n";
    } else {
        echo "✅ 修正が完了しました。\n";
    }
}

echo "\n==========================================\n";
echo "完了\n";
echo "==========================================\n";
