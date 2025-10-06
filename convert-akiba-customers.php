<?php
/**
 * AKIBA末広町店顧客データ変換スクリプト
 * txtファイル（UTF-16LE、タブ区切り）をCSV形式に変換
 */

$inputFile = __DIR__ . '/2025.9.22AKIBA末広町店顧客.txt';
$outputFile = __DIR__ . '/2025.9.22AKIBA末広町店顧客_converted.csv';

if (!file_exists($inputFile)) {
    die("エラー: 入力ファイルが見つかりません: {$inputFile}\n");
}

echo "変換開始...\n";
echo "入力: {$inputFile}\n";
echo "出力: {$outputFile}\n\n";

// ファイルを読み込み（UTF-16LE → UTF-8に変換）
$content = file_get_contents($inputFile);
$content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');

// BOMを削除
$content = preg_replace('/^\x{FEFF}/u', '', $content);

// 行に分割（CR, LF, CRLF に対応）
$lines = preg_split('/\r\n|\r|\n/', $content);

// CSVヘッダー（インポート機能が期待する形式）
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

// CSV出力用の配列
$csvData = [];
$csvData[] = $csvHeader;

$successCount = 0;
$skipCount = 0;
$errorCount = 0;

// 1行目はヘッダーなのでスキップ
for ($i = 1; $i < count($lines); $i++) {
    $line = trim($lines[$i]);

    // 空行をスキップ
    if (empty($line)) {
        continue;
    }

    // タブで分割（末尾の空列も保持）
    $columns = str_getcsv($line, "\t", '"', '');

    // 列数チェック
    if (count($columns) < 22) {
        echo "警告: 行 " . ($i + 1) . " の列数が不足しています（" . count($columns) . "列）\n";
        $errorCount++;
        continue;
    }

    // 列のマッピング（0から始まるインデックス）
    $name = $columns[0]; // 氏名（フルネーム）
    $lastName = trim($columns[1]); // 姓
    $firstName = trim($columns[2]); // 名
    $lastNameKana = trim($columns[3]); // セイ
    $firstNameKana = trim($columns[4]); // メイ
    $phone = trim($columns[5]); // 電話番号
    $email = trim($columns[6]); // メールアドレス
    $registeredAt = trim($columns[7]); // 初回登録日
    $line = trim($columns[8]); // LINE
    $lastStaff = trim($columns[9]); // 最終選択スタッフ
    $lastMenu = trim($columns[10]); // 最終選択メニュー
    $lastOption = trim($columns[11]); // 最終選択オプション
    $reservationStatus = trim($columns[12]); // 予約ステータス
    $nextVisitDate = trim($columns[13]); // 予約日（次回来店日）
    $lastVisitDate = trim($columns[14]); // 最終来店日
    $reservationCount = trim($columns[15]); // 予約回数
    $cancelCount = trim($columns[16]); // キャンセル回数
    $visitFrequency = trim($columns[17]); // 来店頻度
    $lastReservationMenu = trim($columns[18]); // 最終予約メニュー
    $customerNumber = trim($columns[19]); // お客様番号
    $birthDate = trim($columns[20]); // 生年月日
    $notes = trim($columns[21]); // 引き継ぎ内容

    // 電話番号から'を削除
    $phone = str_replace("'", '', $phone);

    // 顧客名を作成（姓 + 名）
    $fullName = $lastName;
    if (!empty($firstName)) {
        $fullName .= ' ' . $firstName;
    }

    // ふりがなを作成（セイ + メイ）
    $fullNameKana = $lastNameKana;
    if (!empty($firstNameKana)) {
        $fullNameKana .= ' ' . $firstNameKana;
    }

    // 来店詳細を構築（追加情報をまとめる）
    $visitDetails = [];
    if (!empty($line)) {
        $visitDetails[] = "LINE: {$line}";
    }
    if (!empty($lastStaff)) {
        $visitDetails[] = "最終選択スタッフ: {$lastStaff}";
    }
    if (!empty($lastMenu)) {
        $visitDetails[] = "最終選択メニュー: {$lastMenu}";
    }
    if (!empty($lastOption)) {
        $visitDetails[] = "最終選択オプション: {$lastOption}";
    }
    if (!empty($nextVisitDate) && $nextVisitDate !== '-') {
        $visitDetails[] = "予約日: {$nextVisitDate}";
    }
    if (!empty($lastVisitDate) && $lastVisitDate !== '-') {
        $visitDetails[] = "最終来店日: {$lastVisitDate}";
    }
    if (!empty($reservationCount)) {
        $visitDetails[] = "予約回数: {$reservationCount}回";
    }
    if (!empty($cancelCount)) {
        $visitDetails[] = "キャンセル回数: {$cancelCount}回";
    }
    if (!empty($visitFrequency) && $visitFrequency !== '-') {
        $visitDetails[] = "来店頻度: {$visitFrequency}";
    }
    if (!empty($lastReservationMenu) && $lastReservationMenu !== '-') {
        $visitDetails[] = "最終予約メニュー: {$lastReservationMenu}";
    }
    $visitDetailsStr = implode(', ', $visitDetails);

    // CSV行を作成
    $csvRow = [
        $customerNumber, // 顧客番号
        $fullName, // 顧客名
        $fullNameKana, // ふりがな
        $email, // メールアドレス
        '', // 性別（空）
        $phone, // 電話番号1
        '', // 電話番号2（空）
        '', // 電話番号3（空）
        $birthDate, // 誕生日
        '', // 郵便番号（空）
        '', // 住所（空）
        '', // 建物名（空）
        '', // 記念日（空）
        $notes, // 顧客特性（引き継ぎ内容）
        $reservationStatus, // 来店区分
        '', // 血液型（空）
        '', // 来店動機（空）
        $visitDetailsStr, // 来店詳細
        $registeredAt, // 顧客登録日時
        $registeredAt // 更新日時
    ];

    $csvData[] = $csvRow;
    $successCount++;
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
