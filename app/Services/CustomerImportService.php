<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CustomerImportService
{
    private array $errors = [];
    private int $successCount = 0;
    private int $skipCount = 0;
    private int $errorCount = 0;
    private array $prefectures = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
    ];

    /**
     * CSVファイルをインポート
     */
    public function import(string $filePath, int $storeId): array
    {
        $this->resetCounters();
        
        // CSVファイルを読み込み（文字エンコーディングを自動判定して変換）
        $csvData = file_get_contents($filePath);

        // 文字エンコーディングを自動判定（UTF-16LEを追加）
        $encoding = mb_detect_encoding($csvData, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'SJIS-win', 'SJIS', 'EUC-JP', 'JIS'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $csvData = mb_convert_encoding($csvData, 'UTF-8', $encoding);
        }

        // BOM（Byte Order Mark）を削除
        $csvData = preg_replace('/^\xEF\xBB\xBF/', '', $csvData);

        // 一時ファイルに保存
        $tempFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempFile, $csvData);

        // CSVを解析（タブ区切りも考慮）
        $handle = fopen($tempFile, 'r');

        // 最初の行を読んでデリミタを判定
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ','; // デフォルトはカンマ
        if (strpos($firstLine, "\t") !== false) {
            $delimiter = "\t"; // タブ区切り
        }

        $header = fgetcsv($handle, 0, $delimiter);
        
        $rowNumber = 2; // ヘッダーの次の行から

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $this->processRow($row, $header, $rowNumber, $storeId);
            $rowNumber++;
        }
        
        fclose($handle);
        unlink($tempFile);
        
        return $this->getResults();
    }

    /**
     * 1行を処理
     */
    private function processRow(array $row, array $header, int $rowNumber, int $storeId): void
    {
        try {
            // 列数を合わせる（不足分は空文字で埋める）
            $headerCount = count($header);
            $rowCount = count($row);
            
            if ($rowCount < $headerCount) {
                $row = array_pad($row, $headerCount, '');
            } elseif ($rowCount > $headerCount) {
                $row = array_slice($row, 0, $headerCount);
            }
            
            // ヘッダーと値をマッピング
            $data = array_combine($header, $row);
            
            // null値を空文字に変換
            $data = array_map(function($value) {
                return $value ?? '';
            }, $data);
            
            // 必須フィールドチェック（氏名または姓・名が必須）
            $hasName = !empty($data['氏名']) || (!empty($data['姓']) || !empty($data['名']));
            if (!$hasName) {
                $this->addError($rowNumber, '必須項目（氏名または姓・名）が不足');
                $this->errorCount++;
                return;
            }
            
            // 電話番号の取得（単一カラムまたは複数カラム）
            $phoneRaw = trim($data['電話番号'] ?? $data['電話番号1'] ?? '');

            // より厳密な空チェック
            $phoneNumber = '';
            if (!empty($phoneRaw) && $phoneRaw !== '' && $phoneRaw !== '　' && $phoneRaw !== '-') {
                $phoneNumber = $phoneRaw;
            }

            // 電話番号の正規化（電話番号がある場合のみ）
            $phone = null;
            if (!empty($phoneNumber) && $phoneNumber !== '' && $phoneNumber !== '　' && $phoneNumber !== '-') {
                $phone = $this->normalizePhone($phoneNumber);
                if (!$phone) {
                    // 電話番号の形式が不正でも警告のみでインポートは継続
                    $this->addError($rowNumber, '警告: 電話番号の形式が不正です（' . $phoneNumber . '）。電話番号なしで登録します。');
                    $phone = null;
                }
            }

            // 既存顧客チェック（同一店舗内）
            if ($phone) {
                // 電話番号がある場合は電話番号で重複チェック
                $existingCustomer = Customer::where('phone', $phone)
                    ->where('store_id', $storeId)
                    ->first();

                if ($existingCustomer) {
                    $this->addError($rowNumber, '既存顧客（同一店舗・同一電話番号）のためスキップ');
                    $this->skipCount++;
                    return;
                }
            } else {
                // 電話番号がない場合は氏名または姓・名で重複チェック（同姓同名を避けるため）
                $lastName = trim($data['姓'] ?? '');
                $firstName = trim($data['名'] ?? '');

                // 氏名カラムから姓名を分割
                if (empty($lastName) && empty($firstName) && !empty($data['氏名'])) {
                    $customerName = $this->splitName($data['氏名']);
                    $lastName = $customerName['last'];
                    $firstName = $customerName['first'];
                }

                if ($lastName && $firstName) {
                    $existingCustomer = Customer::where('last_name', $lastName)
                        ->where('first_name', $firstName)
                        ->where('store_id', $storeId)
                        ->whereNull('phone') // 電話番号なしの顧客同士で比較
                        ->first();

                    if ($existingCustomer) {
                        $this->addError($rowNumber, '既存顧客（同一店舗・同姓同名・電話番号なし）のためスキップ');
                        $this->skipCount++;
                        return;
                    }
                }
            }
            
            // 顧客データを作成
            $customerData = $this->mapToCustomerData($data, $storeId);
            
            // 電話番号がnullでも許可する（削除またはコメントアウト）
            // 電話番号は必須ではなくなった
            
            // 顧客を作成
            Customer::create($customerData);
            
            $this->successCount++;
            
        } catch (\Exception $e) {
            $this->addError($rowNumber, 'エラー: ' . $e->getMessage());
            $this->errorCount++;
            Log::error('Customer import error', [
                'row' => $rowNumber,
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
        }
    }

    /**
     * CSVデータを顧客データにマッピング
     */
    private function mapToCustomerData(array $data, int $storeId): array
    {
        // 名前を取得（姓・名が直接ある場合はそれを使用、なければ氏名から分割）
        $lastName = trim($data['姓'] ?? '');
        $firstName = trim($data['名'] ?? '');

        if (empty($lastName) && empty($firstName) && !empty($data['氏名'])) {
            $nameParts = $this->splitName($data['氏名']);
            $lastName = $nameParts['last'];
            $firstName = $nameParts['first'];
        }

        // ふりがなを取得（セイ・メイが直接ある場合はそれを使用）
        $lastNameKana = trim($data['セイ'] ?? '');
        $firstNameKana = trim($data['メイ'] ?? '');

        if (empty($lastNameKana) && empty($firstNameKana) && !empty($data['ふりがな'])) {
            $kanaParts = $this->splitName($data['ふりがな']);
            $lastNameKana = $kanaParts['last'];
            $firstNameKana = $kanaParts['first'];
        }
        
        // 性別を変換
        $gender = $this->convertGender($data['性別'] ?? '');
        
        // 住所を分離
        $addressParts = $this->parseAddress($data['住所'] ?? '');
        
        // 備考欄を構築
        $notes = $this->buildNotes($data);
        
        // 日付をパース
        $birthDate = $this->parseDate($data['生年月日'] ?? $data['誕生日'] ?? '');
        $createdAt = $this->parseDateTime($data['初回登録日'] ?? $data['顧客登録日時'] ?? '');
        $updatedAt = $this->parseDateTime($data['更新日時'] ?? '');
        
        // 電話番号を取得（単一カラムまたは複数カラム）
        $phoneRaw = trim($data['電話番号'] ?? $data['電話番号1'] ?? '');

        $phoneNumber = '';
        if (!empty($phoneRaw) && $phoneRaw !== '' && $phoneRaw !== '　' && $phoneRaw !== '-') {
            $phoneNumber = $phoneRaw;
        }
        
        // 電話番号の正規化（電話番号がある場合のみ）
        $normalizedPhone = null;
        if (!empty($phoneNumber) && $phoneNumber !== '' && $phoneNumber !== '　' && $phoneNumber !== '-') {
            $normalizedPhone = $this->normalizePhone($phoneNumber);
        }

        return [
            'customer_number' => !empty($data['お客様番号']) ? $data['お客様番号'] : (!empty($data['顧客番号']) ? $data['顧客番号'] : null),
            'store_id' => $storeId,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'last_name_kana' => $this->toKatakana($lastNameKana),
            'first_name_kana' => $this->toKatakana($firstNameKana),
            'phone' => $normalizedPhone, // nullを許可
            'email' => $this->validateEmail($data['メールアドレス'] ?? ''),
            'gender' => $gender,
            'birth_date' => $birthDate,
            'postal_code' => $this->normalizePostalCode($data['郵便番号'] ?? ''),
            'prefecture' => $addressParts['prefecture'],
            'city' => $addressParts['city'],
            'address' => $addressParts['address'],
            'building' => $data['建物名'] ?? null,
            'notes' => $notes,
            'characteristics' => $data['顧客特性'] ?? null, // 顧客特性を追加
            'created_at' => $createdAt ?: now(),
            'updated_at' => $updatedAt ?: now(),
        ];
    }

    /**
     * 名前を姓名に分割
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        
        if (empty($fullName)) {
            return ['last' => '', 'first' => ''];
        }
        
        // スペースで分割
        if (strpos($fullName, '　') !== false) {
            $parts = explode('　', $fullName, 2);
        } elseif (strpos($fullName, ' ') !== false) {
            $parts = explode(' ', $fullName, 2);
        } else {
            // スペースがない場合は2文字目で分割（仮）
            if (mb_strlen($fullName) > 2) {
                $parts = [
                    mb_substr($fullName, 0, 2),
                    mb_substr($fullName, 2)
                ];
            } else {
                $parts = [$fullName, ''];
            }
        }
        
        return [
            'last' => $parts[0] ?? '',
            'first' => $parts[1] ?? ''
        ];
    }

    /**
     * ひらがなをカタカナに変換
     */
    private function toKatakana(string $text): string
    {
        return mb_convert_kana($text, 'KC', 'UTF-8');
    }

    /**
     * 性別を変換
     */
    private function convertGender(?string $gender): ?string
    {
        if (empty($gender)) {
            return null;
        }
        
        if (in_array($gender, ['男性', '男'])) {
            return 'male';
        }
        
        if (in_array($gender, ['女性', '女'])) {
            return 'female';
        }
        
        return 'other';
    }

    /**
     * 電話番号を正規化
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        
        // ハイフン、スペース、括弧を除去
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // 10-11桁チェック
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            return null;
        }
        
        return $phone;
    }

    /**
     * 郵便番号を正規化
     */
    private function normalizePostalCode(?string $postalCode): ?string
    {
        if (empty($postalCode)) {
            return null;
        }
        
        // ハイフンを除去
        $postalCode = str_replace('-', '', $postalCode);
        
        // 7桁チェック
        if (strlen($postalCode) != 7) {
            return null;
        }
        
        return $postalCode;
    }

    /**
     * メールアドレスのバリデーション
     */
    private function validateEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * 住所を都道府県、市区町村、それ以降に分離
     */
    private function parseAddress(string $address): array
    {
        $prefecture = null;
        $city = null;
        $remainAddress = $address;
        
        // 都道府県を検出
        foreach ($this->prefectures as $pref) {
            if (strpos($address, $pref) === 0) {
                $prefecture = $pref;
                $remainAddress = substr($address, strlen($pref));
                break;
            }
        }
        
        // 市区町村を検出（簡易版）
        if (preg_match('/^(.{2,}?[市区町村])/u', $remainAddress, $matches)) {
            $city = $matches[1];
            $remainAddress = substr($remainAddress, strlen($city));
        }
        
        return [
            'prefecture' => $prefecture,
            'city' => $city,
            'address' => trim($remainAddress)
        ];
    }

    /**
     * 備考欄を構築
     */
    private function buildNotes(array $data): ?string
    {
        $notes = [];

        // 引き継ぎ内容（新しいカラム）
        if (!empty($data['引き継ぎ内容'])) {
            $notes[] = "【引き継ぎ内容】" . $data['引き継ぎ内容'];
        }

        // 予約ステータス
        if (!empty($data['予約ステータス'])) {
            $notes[] = "【予約ステータス】" . $data['予約ステータス'];
        }

        // 最終来店日
        if (!empty($data['最終来店日'])) {
            $notes[] = "【最終来店日】" . $data['最終来店日'];
        }

        // 最終予約メニュー
        if (!empty($data['最終予約メニュー'])) {
            $notes[] = "【最終予約メニュー】" . $data['最終予約メニュー'];
        }

        // 記念日
        if (!empty($data['記念日'])) {
            $notes[] = "【記念日】" . $data['記念日'];
        }

        // 顧客特性
        if (!empty($data['顧客特性'])) {
            $notes[] = "【顧客特性】" . $data['顧客特性'];
        }

        // 来店区分
        if (!empty($data['来店区分'])) {
            $notes[] = "【来店区分】" . $data['来店区分'];
        }

        // 補助電話番号
        if (!empty($data['電話番号2'])) {
            $notes[] = "【電話番号2】" . $data['電話番号2'];
        }
        if (!empty($data['電話番号3'])) {
            $notes[] = "【電話番号3】" . $data['電話番号3'];
        }

        // 血液型
        if (!empty($data['血液型'])) {
            $notes[] = "【血液型】" . $data['血液型'];
        }

        // 来店動機
        if (!empty($data['来店動機'])) {
            $notes[] = "【来店動機】" . $data['来店動機'];
        }

        // 来店詳細
        if (!empty($data['来店詳細'])) {
            $notes[] = "【来店詳細】" . $data['来店詳細'];
        }

        return empty($notes) ? null : implode("\n", $notes);
    }

    /**
     * 日付をパース
     */
    private function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        
        try {
            // 様々な形式を試す
            $formats = ['Y/m/d', 'Y-m-d', 'Y年m月d日'];
            
            foreach ($formats as $format) {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed) {
                    return $parsed->format('Y-m-d');
                }
            }
        } catch (\Exception $e) {
            // パース失敗
        }
        
        return null;
    }

    /**
     * 日時をパース
     */
    private function parseDateTime(?string $datetime): ?Carbon
    {
        if (empty($datetime)) {
            return null;
        }
        
        try {
            // 様々な形式を試す
            $formats = ['Y/m/d H:i', 'Y-m-d H:i:s', 'Y年m月d日 H:i'];
            
            foreach ($formats as $format) {
                $parsed = Carbon::createFromFormat($format, $datetime);
                if ($parsed) {
                    return $parsed;
                }
            }
        } catch (\Exception $e) {
            // パース失敗
        }
        
        return null;
    }

    /**
     * エラーを追加
     */
    private function addError(int $rowNumber, string $message): void
    {
        $this->errors[] = [
            'row' => $rowNumber,
            'message' => $message
        ];
    }

    /**
     * カウンターをリセット
     */
    private function resetCounters(): void
    {
        $this->errors = [];
        $this->successCount = 0;
        $this->skipCount = 0;
        $this->errorCount = 0;
    }

    /**
     * 結果を取得
     */
    public function getResults(): array
    {
        return [
            'success_count' => $this->successCount,
            'skip_count' => $this->skipCount,
            'error_count' => $this->errorCount,
            'errors' => $this->errors
        ];
    }

    /**
     * エラーログをCSVとして出力
     */
    public function exportErrorLog(array $errors): string
    {
        $csv = "行番号,エラー内容\n";
        
        foreach ($errors as $error) {
            $csv .= "{$error['row']},\"{$error['message']}\"\n";
        }
        
        return $csv;
    }
}