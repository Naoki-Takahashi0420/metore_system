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
        
        // CSVファイルを読み込み（Shift-JISからUTF-8に変換）
        $csvData = file_get_contents($filePath);
        $csvData = mb_convert_encoding($csvData, 'UTF-8', 'SJIS-win');
        
        // 一時ファイルに保存
        $tempFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempFile, $csvData);
        
        // CSVを解析
        $handle = fopen($tempFile, 'r');
        $header = fgetcsv($handle);
        
        $rowNumber = 2; // ヘッダーの次の行から
        
        while (($row = fgetcsv($handle)) !== false) {
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
            
            // 必須フィールドチェック
            if (empty($data['顧客番号']) || empty($data['顧客名'])) {
                $this->addError($rowNumber, '必須項目（顧客番号または顧客名）が不足');
                $this->errorCount++;
                return;
            }
            
            // 電話番号の取得（電話番号1が優先、なければ電話番号2）
            $phoneNumber1 = trim($data['電話番号1'] ?? '');
            $phoneNumber2 = trim($data['電話番号2'] ?? '');
            
            // より厳密な空チェック
            $phoneNumber = '';
            if (!empty($phoneNumber1) && $phoneNumber1 !== '' && $phoneNumber1 !== '　' && $phoneNumber1 !== '-') {
                $phoneNumber = $phoneNumber1;
            } elseif (!empty($phoneNumber2) && $phoneNumber2 !== '' && $phoneNumber2 !== '　' && $phoneNumber2 !== '-') {
                $phoneNumber = $phoneNumber2;
            }
            
            // 電話番号がない場合はエラー（より厳密な判定）
            if (empty($phoneNumber) || $phoneNumber === '' || $phoneNumber === '　' || $phoneNumber === '-' || strlen(trim($phoneNumber)) === 0) {
                $this->addError($rowNumber, '電話番号が必須です（電話番号1または電話番号2のいずれかが必要）');
                $this->errorCount++;
                return;
            }
            
            // 電話番号の正規化
            $phone = $this->normalizePhone($phoneNumber);
            if (!$phone) {
                $this->addError($rowNumber, '電話番号の形式が不正: ' . $phoneNumber);
                $this->errorCount++;
                return;
            }
            
            // 既存顧客チェック（同一店舗内）- 電話番号で判定
            $existingCustomer = Customer::where('phone', $phone)
                ->where('store_id', $storeId)
                ->first();
            
            if ($existingCustomer) {
                $this->addError($rowNumber, '既存顧客（同一店舗）のためスキップ');
                $this->skipCount++;
                return;
            }
            
            // 顧客データを作成
            $customerData = $this->mapToCustomerData($data, $storeId);
            
            // 最終安全チェック：電話番号が確実に設定されているか
            if (empty($customerData['phone']) || $customerData['phone'] === null || $customerData['phone'] === '') {
                $this->addError($rowNumber, '最終チェック: 電話番号が設定されていません');
                $this->errorCount++;
                return;
            }
            
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
        // 名前を分割
        $nameParts = $this->splitName($data['顧客名'] ?? '');
        
        // ふりがなを分割
        $kanaParts = $this->splitName($data['ふりがな'] ?? '');
        
        // 性別を変換
        $gender = $this->convertGender($data['性別'] ?? '');
        
        // 住所を分離
        $addressParts = $this->parseAddress($data['住所'] ?? '');
        
        // 備考欄を構築
        $notes = $this->buildNotes($data);
        
        // 日付をパース
        $birthDate = $this->parseDate($data['誕生日'] ?? '');
        $createdAt = $this->parseDateTime($data['顧客登録日時'] ?? '');
        $updatedAt = $this->parseDateTime($data['更新日時'] ?? '');
        
        // 電話番号を取得（優先順位: 電話番号1 > 電話番号2）
        $phoneNumber1 = trim($data['電話番号1'] ?? '');
        $phoneNumber2 = trim($data['電話番号2'] ?? '');
        
        $phoneNumber = '';
        if (!empty($phoneNumber1) && $phoneNumber1 !== '' && $phoneNumber1 !== '　' && $phoneNumber1 !== '-') {
            $phoneNumber = $phoneNumber1;
        } elseif (!empty($phoneNumber2) && $phoneNumber2 !== '' && $phoneNumber2 !== '　' && $phoneNumber2 !== '-') {
            $phoneNumber = $phoneNumber2;
        }
        
        return [
            'customer_number' => $data['顧客番号'],
            'store_id' => $storeId,
            'last_name' => $nameParts['last'],
            'first_name' => $nameParts['first'],
            'last_name_kana' => $this->toKatakana($kanaParts['last']),
            'first_name_kana' => $this->toKatakana($kanaParts['first']),
            'phone' => $this->normalizePhone($phoneNumber),
            'email' => $this->validateEmail($data['メールアドレス'] ?? ''),
            'gender' => $gender,
            'birth_date' => $birthDate,
            'postal_code' => $this->normalizePostalCode($data['郵便番号'] ?? ''),
            'prefecture' => $addressParts['prefecture'],
            'city' => $addressParts['city'],
            'address' => $addressParts['address'],
            'building' => $data['建物名'] ?? null,
            'notes' => $notes,
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