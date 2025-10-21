<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\MedicalRecord;
use App\Models\PresbyopiaMeasurement;
use Carbon\Carbon;

// 電話番号で顧客を検索
$phoneNumber = '08033372305';
$customer = Customer::where('phone_number', $phoneNumber)->first();

if (!$customer) {
    echo "❌ 顧客が見つかりません: {$phoneNumber}\n";
    exit(1);
}

echo "✅ 顧客情報:\n";
echo "   ID: {$customer->id}\n";
echo "   名前: {$customer->last_name} {$customer->first_name}\n";
echo "   店舗ID: {$customer->store_id}\n";
echo "\n";

// 既存のカルテ数を確認
$existingCount = MedicalRecord::where('customer_id', $customer->id)->count();
echo "📋 既存のカルテ数: {$existingCount}\n\n";

// 12件のテストカルテを作成（1ヶ月ごと）
$recordsToCreate = 12;
echo "🔨 {$recordsToCreate}件のテストカルテを作成中...\n\n";

$baseDate = Carbon::now()->subMonths($recordsToCreate);
$createdCount = 0;

for ($i = 0; $i < $recordsToCreate; $i++) {
    $recordDate = $baseDate->copy()->addMonths($i);

    // 徐々に改善するデータを生成
    $progress = $i * 0.05; // 0.05ずつ改善

    // 裸眼視力データ（0.3から開始）
    $nakedBase = 0.3 + $progress;
    $beforeNakedLeft = round($nakedBase + (rand(-5, 5) / 100), 2);
    $afterNakedLeft = round($beforeNakedLeft + rand(5, 15) / 100, 2);
    $beforeNakedRight = round($nakedBase + (rand(-5, 5) / 100), 2);
    $afterNakedRight = round($beforeNakedRight + rand(5, 15) / 100, 2);

    // 矯正視力データ（0.8から開始）
    $correctedBase = 0.8 + $progress;
    $beforeCorrectedLeft = round($correctedBase + (rand(-5, 5) / 100), 2);
    $afterCorrectedLeft = round($beforeCorrectedLeft + rand(5, 10) / 100, 2);
    $beforeCorrectedRight = round($correctedBase + (rand(-5, 5) / 100), 2);
    $afterCorrectedRight = round($beforeCorrectedRight + rand(5, 10) / 100, 2);

    // 視力データ
    $visionRecords = [
        [
            'session' => 1,
            'date' => $recordDate->format('Y-m-d'),
            'before_naked_left' => $beforeNakedLeft,
            'after_naked_left' => $afterNakedLeft,
            'before_naked_right' => $beforeNakedRight,
            'after_naked_right' => $afterNakedRight,
            'before_corrected_left' => $beforeCorrectedLeft,
            'after_corrected_left' => $afterCorrectedLeft,
            'before_corrected_right' => $beforeCorrectedRight,
            'after_corrected_right' => $afterCorrectedRight,
            'intensity' => '中',
            'duration' => '30分',
            'public_memo' => '順調に改善しています'
        ]
    ];

    // カルテ作成
    $medicalRecord = MedicalRecord::create([
        'customer_id' => $customer->id,
        'staff_id' => 1, // 適当なスタッフID
        'record_date' => $recordDate->format('Y-m-d'),
        'treatment_date' => $recordDate->format('Y-m-d'),
        'age' => $customer->age ?? 30,
        'chief_complaint' => '視力低下、眼精疲労',
        'symptoms' => '長時間のPC作業で目が疲れやすい',
        'diagnosis' => '近視、調節緊張',
        'treatment' => 'アイトレーニング ' . ($i + 1) . '回目',
        'prescription' => '1日2回の目の体操を推奨',
        'vision_records' => $visionRecords,
        'session_number' => $i + 1,
    ]);

    // 老眼測定データ（施術前）
    // 近見距離は徐々に伸びる（改善）
    $presbyopiaBase = 20 + ($i * 2); // 20cmから開始、2cmずつ改善

    PresbyopiaMeasurement::create([
        'medical_record_id' => $medicalRecord->id,
        'status' => '施術前',
        'a_95_left' => $presbyopiaBase + rand(-2, 2),
        'a_95_right' => $presbyopiaBase + rand(-2, 2),
        'b_50_left' => $presbyopiaBase - 5 + rand(-2, 2),
        'b_50_right' => $presbyopiaBase - 5 + rand(-2, 2),
        'c_25_left' => $presbyopiaBase - 10 + rand(-2, 2),
        'c_25_right' => $presbyopiaBase - 10 + rand(-2, 2),
        'd_12_left' => $presbyopiaBase - 15 + rand(-2, 2),
        'd_12_right' => $presbyopiaBase - 15 + rand(-2, 2),
        'e_6_left' => $presbyopiaBase - 20 + rand(-2, 2),
        'e_6_right' => $presbyopiaBase - 20 + rand(-2, 2),
    ]);

    // 老眼測定データ（施術後）
    $afterImprovement = 3; // 施術後は3cm改善
    PresbyopiaMeasurement::create([
        'medical_record_id' => $medicalRecord->id,
        'status' => '施術後',
        'a_95_left' => $presbyopiaBase + $afterImprovement + rand(-1, 1),
        'a_95_right' => $presbyopiaBase + $afterImprovement + rand(-1, 1),
        'b_50_left' => $presbyopiaBase - 5 + $afterImprovement + rand(-1, 1),
        'b_50_right' => $presbyopiaBase - 5 + $afterImprovement + rand(-1, 1),
        'c_25_left' => $presbyopiaBase - 10 + $afterImprovement + rand(-1, 1),
        'c_25_right' => $presbyopiaBase - 10 + $afterImprovement + rand(-1, 1),
        'd_12_left' => $presbyopiaBase - 15 + $afterImprovement + rand(-1, 1),
        'd_12_right' => $presbyopiaBase - 15 + $afterImprovement + rand(-1, 1),
        'e_6_left' => $presbyopiaBase - 20 + $afterImprovement + rand(-1, 1),
        'e_6_right' => $presbyopiaBase - 20 + $afterImprovement + rand(-1, 1),
    ]);

    $createdCount++;
    echo "   ✓ カルテ #{$createdCount} 作成完了: {$recordDate->format('Y-m-d')}\n";
    echo "      裸眼: 左{$beforeNakedLeft}→{$afterNakedLeft} / 右{$beforeNakedRight}→{$afterNakedRight}\n";
    echo "      矯正: 左{$beforeCorrectedLeft}→{$afterCorrectedLeft} / 右{$beforeCorrectedRight}→{$afterCorrectedRight}\n";
    echo "      老眼: A95% {$presbyopiaBase}cm→" . ($presbyopiaBase + $afterImprovement) . "cm\n\n";
}

echo "\n✅ 完了！ {$createdCount}件のテストカルテを作成しました。\n";
echo "\n📊 総カルテ数: " . MedicalRecord::where('customer_id', $customer->id)->count() . "件\n";
