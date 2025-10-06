<?php
/**
 * AKIBA末広町店顧客データ変換スクリプト（簡易版）
 * UTF-8変換済みのtxtファイルをCSV形式に変換
 */

$inputFile = __DIR__ . '/2025.9.22AKIBA末広町店顧客_utf8.txt';
$outputFile = __DIR__ . '/2025.9.22AKIBA末広町店顧客_converted.csv';

if (!file_exists($inputFile)) {
    die("エラー: 入力ファイルが見つかりません: {$inputFile}\n");
}

echo "変換開始...\n";
echo "入力: {$inputFile}\n";
echo "出力: {$outputFile}\n\n";

// ファイルを読み込み
$content = file_get_contents($inputFile);

// BOMを削除
$content = preg_replace('/^\x{FEFF}/u', '', $content);

// 行に分割
$lines = preg_split('/\r\n|\r|\n/', $content);

// CSVヘッダー
$csvHeader = [
    '顧客番号',
    '顧客名',
    'ふりがな',
    'メールアドレス',
    '性別',
    '電話番号1',
    '電話番号2',
    '電話番号3',
    '誕生日',
    '郵便番号',
    '住所',
    '建物名',
    '記念日',
    '顧客特性',
    '来店区分',
    '血液型',
    '来店動機',
    '来店詳細',
    '顧客登録日時',
    '更新日時'
];

$csvData = [];
$csvData[] = $csvHeader;

$successCount = 0;
$errorCount = 0;

// 1行目はヘッダーなのでスキップ
for ($i = 1; $i < count($lines); $i++) {
    $line = trim($lines[$i]);

    if (empty($line)) {
        continue;
    }

    // タブで分割
    $columns = explode("\t", $line);

    // 22列にパディング（不足分は空文字）
    while (count($columns) < 22) {
        $columns[] = '';
    }

    try {
        // 列を取得
        $lastName = trim($columns[1]);
        $firstName = trim($columns[2]);
        $lastNameKana = trim($columns[3]);
        $firstNameKana = trim($columns[4]);
        $phone = str_replace("'", '', trim($columns[5]));
        $email = trim($columns[6]);
        $registeredAt = trim($columns[7]);
        $line_data = trim($columns[8]);
        $lastStaff = trim($columns[9]);
        $lastMenu = trim($columns[10]);
        $lastOption = trim($columns[11]);
        $reservationStatus = trim($columns[12]);
        $nextVisitDate = trim($columns[13]);
        $lastVisitDate = trim($columns[14]);
        $reservationCount = trim($columns[15]);
        $cancelCount = trim($columns[16]);
        $visitFrequency = trim($columns[17]);
        $lastReservationMenu = trim($columns[18]);
        $customerNumber = trim($columns[19]);
        $birthDate = trim($columns[20]);
        $notes = trim($columns[21]);

        // 顧客名
        $fullName = $lastName;
        if (!empty($firstName)) {
            $fullName .= ' ' . $firstName;
        }

        // ふりがな
        $fullNameKana = $lastNameKana;
        if (!empty($firstNameKana)) {
            $fullNameKana .= ' ' . $firstNameKana;
        }

        // 来店詳細
        $visitDetails = [];
        if (!empty($line_data)) $visitDetails[] = "LINE: {$line_data}";
        if (!empty($lastStaff)) $visitDetails[] = "最終選択スタッフ: {$lastStaff}";
        if (!empty($lastMenu)) $visitDetails[] = "最終選択メニュー: {$lastMenu}";
        if (!empty($lastOption)) $visitDetails[] = "最終選択オプション: {$lastOption}";
        if (!empty($nextVisitDate) && $nextVisitDate !== '-') $visitDetails[] = "予約日: {$nextVisitDate}";
        if (!empty($lastVisitDate) && $lastVisitDate !== '-') $visitDetails[] = "最終来店日: {$lastVisitDate}";
        if (!empty($reservationCount)) $visitDetails[] = "予約回数: {$reservationCount}回";
        if (!empty($cancelCount)) $visitDetails[] = "キャンセル回数: {$cancelCount}回";
        if (!empty($visitFrequency) && $visitFrequency !== '-') $visitDetails[] = "来店頻度: {$visitFrequency}";
        if (!empty($lastReservationMenu) && $lastReservationMenu !== '-') $visitDetails[] = "最終予約メニュー: {$lastReservationMenu}";
        $visitDetailsStr = implode(', ', $visitDetails);

        // CSV行
        $csvRow = [
            $customerNumber,
            $fullName,
            $fullNameKana,
            $email,
            '',
            $phone,
            '',
            '',
            $birthDate,
            '',
            '',
            '',
            '',
            $notes,
            $reservationStatus,
            '',
            '',
            $visitDetailsStr,
            $registeredAt,
            $registeredAt
        ];

        $csvData[] = $csvRow;
        $successCount++;

    } catch (\Exception $e) {
        echo "エラー: 行 " . ($i + 1) . " - " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

// CSVファイルに書き込み（Shift-JIS形式）
$fp = fopen($outputFile, 'w');

foreach ($csvData as $row) {
    // Shift-JISに変換
    $row = array_map(function($value) {
        return mb_convert_encoding($value, 'SJIS-win', 'UTF-8');
    }, $row);

    fputcsv($fp, $row, ',', '"', '');
}

fclose($fp);

echo "\n変換完了！\n";
echo "成功: {$successCount}件\n";
echo "エラー: {$errorCount}件\n";
echo "出力ファイル: {$outputFile}\n";
