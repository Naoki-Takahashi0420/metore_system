<?php

/**
 * 視力推移グラフ問題の分析スクリプト
 * 本番環境から取得したデータを元に問題を再現
 */

echo "==========================================\n";
echo "視力推移グラフ問題の分析\n";
echo "==========================================\n\n";

// 本番環境のデータ（デバッグログから抽出）
$vision = [
    'session' => 1,
    'date' => '2025-10-08',
    'intensity' => '31',
    'duration' => 80,
    'before_naked_left' => null,
    'before_naked_right' => null,
    'before_corrected_left' => '1.5',
    'before_corrected_right' => '1,5',  // カンマ区切り！
    'after_naked_left' => null,
    'after_naked_right' => null,
    'after_corrected_left' => '1.5',
    'after_corrected_right' => '1.5',
    'public_memo' => null,
];

echo "📝 元のデータ:\n";
echo "  左眼（施術前）矯正: ";
var_export($vision['before_corrected_left']);
echo " → 型: " . gettype($vision['before_corrected_left']) . "\n";
echo "  右眼（施術前）矯正: ";
var_export($vision['before_corrected_right']);
echo " → 型: " . gettype($vision['before_corrected_right']) . "\n\n";

echo "─────────────────────────────────────────\n";
echo "現在のロジックでの変換\n";
echo "─────────────────────────────────────────\n\n";

// 現在のロジック（vision-chart.blade.phpの32-41行目）
$leftCorrectedBefore = (isset($vision['before_corrected_left']) && $vision['before_corrected_left'] !== '' && (float)$vision['before_corrected_left'] >= 0) ? (float)$vision['before_corrected_left'] : null;
$rightCorrectedBefore = (isset($vision['before_corrected_right']) && $vision['before_corrected_right'] !== '' && (float)$vision['before_corrected_right'] >= 0) ? (float)$vision['before_corrected_right'] : null;

echo "左眼（施術前）矯正:\n";
echo "  isset: " . (isset($vision['before_corrected_left']) ? 'true' : 'false') . "\n";
echo "  !== '': " . ($vision['before_corrected_left'] !== '' ? 'true' : 'false') . "\n";
echo "  (float)値: " . (float)$vision['before_corrected_left'] . "\n";
echo "  >= 0: " . ((float)$vision['before_corrected_left'] >= 0 ? 'true' : 'false') . "\n";
echo "  → 変換後: ";
var_export($leftCorrectedBefore);
echo "\n\n";

echo "右眼（施術前）矯正 (カンマ区切り '1,5'):\n";
echo "  isset: " . (isset($vision['before_corrected_right']) ? 'true' : 'false') . "\n";
echo "  !== '': " . ($vision['before_corrected_right'] !== '' ? 'true' : 'false') . "\n";
echo "  (float)値: " . (float)$vision['before_corrected_right'] . "\n";
echo "  >= 0: " . ((float)$vision['before_corrected_right'] >= 0 ? 'true' : 'false') . "\n";
echo "  → 変換後: ";
var_export($rightCorrectedBefore);
echo "\n\n";

echo "⚠️  重要な発見:\n";
echo "  '1,5' を (float) でキャストすると → " . (float)'1,5' . " になります！\n";
echo "  PHPはカンマを無視して最初の数字だけを取るため、'1,5' → 1.0 に変換されます。\n\n";

echo "─────────────────────────────────────────\n";
echo "問題の根本原因\n";
echo "─────────────────────────────────────────\n\n";

echo "1. データベースに保存されている値: '1,5' (カンマ区切り)\n";
echo "2. PHPの (float) キャストでは、カンマは小数点として認識されない\n";
echo "3. 結果として '1,5' → 1.0 に変換される\n";
echo "4. グラフには 1.0 として表示される\n\n";

echo "─────────────────────────────────────────\n";
echo "他のテストケース\n";
echo "─────────────────────────────────────────\n\n";

$testCases = [
    '-1.5' => '-1.5 (マイナス値)',
    '' => '空文字列',
    null => 'null',
    '0.5' => '0.5 (正常)',
    '1.5' => '1.5 (正常)',
    '1,5' => '1,5 (カンマ区切り)',
    '2,0' => '2,0 (カンマ区切り)',
    '0,3' => '0,3 (カンマ区切り)',
    'abc' => 'abc (文字列)',
    '1.5abc' => '1.5abc (数字+文字)',
];

foreach ($testCases as $input => $label) {
    $result = (isset($input) && $input !== '' && (float)$input >= 0) ? (float)$input : null;
    echo sprintf(
        "  %-20s → (float): %-6s → 条件判定: %-5s → 結果: %s\n",
        $label,
        var_export((float)$input, true),
        ((float)$input >= 0 ? 'true' : 'false'),
        var_export($result, true)
    );
}

echo "\n";
echo "─────────────────────────────────────────\n";
echo "修正方法の提案\n";
echo "─────────────────────────────────────────\n\n";

echo "方法1: str_replace でカンマをドットに変換してからキャスト\n";
echo "  \$value = str_replace(',', '.', \$vision['before_corrected_left']);\n";
echo "  \$result = (isset(\$value) && \$value !== '' && (float)\$value >= 0) ? (float)\$value : null;\n\n";

$testValue = '1,5';
$corrected = str_replace(',', '.', $testValue);
$result = (isset($corrected) && $corrected !== '' && (float)$corrected >= 0) ? (float)$corrected : null;
echo "  テスト: '1,5' → '{$corrected}' → " . var_export($result, true) . " ✅\n\n";

echo "方法2: is_numeric チェックを追加（ただしカンマは認識されない）\n";
echo "  この方法ではカンマ区切りの数値は弾かれてしまうため不適切\n\n";

echo "推奨: 方法1 (str_replace) を使用\n\n";

echo "==========================================\n";
echo "分析完了\n";
echo "==========================================\n";
