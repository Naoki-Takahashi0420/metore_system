<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ImportNagoyaCustomers extends Seeder
{
    public function run()
    {
        DB::beginTransaction();
        
        try {
            // 1. 名古屋駅前店を作成（既存なら取得）
            $store = Store::firstOrCreate(
                ['name' => '目のトレーニング名古屋駅前店'],
                [
                    'name_kana' => 'メノトレーニングナゴヤエキマエテン',
                    'phone' => '052-xxx-xxxx',
                    'email' => 'nagoya@eye-training.com',
                    'postal_code' => '450-0002',
                    'prefecture' => '愛知県',
                    'city' => '名古屋市中村区',
                    'address' => '名駅1-1-1',
                    'is_active' => true,
                    'opening_hours' => [
                        'monday' => ['open' => '10:00', 'close' => '20:00'],
                        'tuesday' => ['open' => '10:00', 'close' => '20:00'],
                        'wednesday' => ['open' => '10:00', 'close' => '20:00'],
                        'thursday' => ['open' => '10:00', 'close' => '20:00'],
                        'friday' => ['open' => '10:00', 'close' => '20:00'],
                        'saturday' => ['open' => '10:00', 'close' => '18:00'],
                        'sunday' => ['open' => '10:00', 'close' => '18:00'],
                    ],
                    'holidays' => [],
                    'capacity' => 3,
                    'settings' => [],
                    'reservation_settings' => [
                        'slot_duration' => 30,
                        'min_booking_hours' => 1,
                        'allow_same_day' => true
                    ],
                ]
            );
            
            echo "店舗作成/取得完了: {$store->name} (ID: {$store->id})\n";
            
            // 2. CSVファイルを読み込み（Shift-JISからUTF-8へ変換）
            $csvPath = '/Users/naoki.t/Downloads/20250902143328-359.csv';
            if (!file_exists($csvPath)) {
                throw new \Exception("CSVファイルが見つかりません: {$csvPath}");
            }
            
            // Shift-JISからUTF-8に変換して読み込み
            $csvContent = file_get_contents($csvPath);
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'SJIS-win');
            
            // 一時ファイルに保存
            $tempFile = tempnam(sys_get_temp_dir(), 'customers_');
            file_put_contents($tempFile, $csvContent);
            
            // CSVを読み込み
            $handle = fopen($tempFile, 'r');
            $headers = fgetcsv($handle); // ヘッダー行をスキップ
            
            $imported = 0;
            $skipped = 0;
            $errors = [];
            
            while (($data = fgetcsv($handle)) !== false) {
                try {
                    // データのマッピング
                    $customerNumber = $data[1] ?? null;  // 顧客番号
                    $lastName = $data[3] ?? '';          // 顧客名（姓名分割が必要）
                    $lastNameKana = $data[4] ?? '';      // ふりがな
                    $email = $data[5] ?? null;           // メールアドレス
                    $gender = $data[6] ?? null;          // 性別
                    $birthday = $data[9] ?? null;        // 誕生日
                    $phone = $data[19] ?? $data[18] ?? $data[20] ?? null; // 電話番号（優先順位：1→2→3）
                    $postalCode = $data[15] ?? null;     // 郵便番号
                    $address = $data[16] ?? null;        // 住所
                    $building = $data[17] ?? null;       // 建物名
                    $customerType = $data[23] ?? null;   // 顧客特性（新規/失客）
                    $registeredAt = $data[27] ?? null;   // 顧客登録日時
                    
                    // 電話番号がない場合はスキップ
                    if (empty($phone)) {
                        $skipped++;
                        continue;
                    }
                    
                    // 電話番号の正規化（数字のみ抽出）
                    $phone = preg_replace('/[^0-9]/', '', $phone);
                    if (substr($phone, 0, 1) !== '0') {
                        $phone = '0' . $phone;
                    }
                    
                    // 名前の分割（スペースで分割、なければ全て姓に）
                    $nameParts = preg_split('/[\s　]+/', trim($lastName), 2);
                    $lastNameClean = $nameParts[0] ?? '';
                    $firstName = $nameParts[1] ?? '';
                    
                    // かなの分割
                    $kanaParts = preg_split('/[\s　]+/', trim($lastNameKana), 2);
                    $lastNameKanaClean = $kanaParts[0] ?? '';
                    $firstNameKana = $kanaParts[1] ?? '';
                    
                    // 性別の変換
                    $genderValue = null;
                    if ($gender === '男性') $genderValue = 'male';
                    elseif ($gender === '女性') $genderValue = 'female';
                    
                    // 誕生日の変換
                    $birthDateValue = null;
                    if (!empty($birthday)) {
                        try {
                            $birthDateValue = Carbon::parse($birthday)->format('Y-m-d');
                        } catch (\Exception $e) {
                            // 日付解析エラーは無視
                        }
                    }
                    
                    // 登録日時の変換
                    $registeredAtValue = null;
                    if (!empty($registeredAt)) {
                        try {
                            $registeredAtValue = Carbon::parse($registeredAt);
                        } catch (\Exception $e) {
                            $registeredAtValue = now();
                        }
                    }
                    
                    // 顧客の作成または更新（電話番号で重複チェック）
                    $customer = Customer::updateOrCreate(
                        ['phone' => $phone],
                        [
                            'customer_number' => $customerNumber ?? Customer::generateCustomerNumber(),
                            'last_name' => $lastNameClean ?: '未設定',
                            'first_name' => $firstName ?: '',
                            'last_name_kana' => $lastNameKanaClean ?: '',
                            'first_name_kana' => $firstNameKana ?: '',
                            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                            'gender' => $genderValue,
                            'birth_date' => $birthDateValue,
                            'postal_code' => $postalCode,
                            'prefecture' => '愛知県', // 名古屋なので固定
                            'city' => $address,
                            'address' => $address,
                            'building' => $building,
                            'notes' => "顧客タイプ: {$customerType}",
                            'created_at' => $registeredAtValue ?? now(),
                        ]
                    );
                    
                    // 店舗との紐付け（多対多の場合）
                    // ※ customers テーブルに store_id がある場合は以下のように直接更新
                    // $customer->update(['store_id' => $store->id]);
                    
                    $imported++;
                    
                    if ($imported % 50 === 0) {
                        echo "処理中... {$imported}件完了\n";
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "行 " . ($imported + $skipped + 2) . ": " . $e->getMessage();
                }
            }
            
            fclose($handle);
            unlink($tempFile);
            
            DB::commit();
            
            echo "\n=== 移行完了 ===\n";
            echo "成功: {$imported}件\n";
            echo "スキップ: {$skipped}件\n";
            
            if (!empty($errors)) {
                echo "\nエラー:\n";
                foreach (array_slice($errors, 0, 10) as $error) {
                    echo "- {$error}\n";
                }
                if (count($errors) > 10) {
                    echo "... 他 " . (count($errors) - 10) . " 件のエラー\n";
                }
            }
            
        } catch (\Exception $e) {
            DB::rollback();
            echo "エラー: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}