<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MedicalRecord;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class MedicalRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ja_JP');
        
        // スタッフユーザーを取得
        $staffUsers = User::whereIn('role', ['staff', 'admin', 'manager'])->get();
        
        if ($staffUsers->isEmpty()) {
            $this->command->warn('No staff users found. Skipping medical records seeding.');
            return;
        }
        
        // 完了済みの予約を取得
        $completedReservations = Reservation::whereIn('status', ['completed', 'in_progress'])->get();
        
        if ($completedReservations->isEmpty()) {
            $this->command->warn('No completed reservations found. Skipping medical records seeding.');
            return;
        }
        
        // 各完了済み予約に対してカルテを作成（50%の確率）
        foreach ($completedReservations as $reservation) {
            if (rand(0, 1) === 1) {
                $this->createMedicalRecord($reservation, $staffUsers, $faker);
            }
        }
        
        // 追加で過去のカルテを作成（予約と関連しない）
        $customers = Customer::inRandomOrder()->limit(10)->get();
        foreach ($customers as $customer) {
            $recordCount = rand(1, 3);
            for ($i = 0; $i < $recordCount; $i++) {
                $this->createHistoricalMedicalRecord($customer, $staffUsers, $faker);
            }
        }
    }
    
    private function createMedicalRecord($reservation, $staffUsers, $faker)
    {
        $chiefComplaints = [
            'シミが気になる',
            'しわを改善したい',
            'たるみが気になる',
            '毛穴の開きが気になる',
            'ニキビ跡を治したい',
            '肌のくすみを改善したい',
            'ほうれい線を薄くしたい',
            '肌の乾燥が気になる',
        ];
        
        $symptoms = [
            '頬に直径5mmほどのシミが3箇所確認',
            '目尻に小じわが複数見られる',
            'フェイスラインのたるみが軽度認められる',
            'Tゾーンの毛穴開きが目立つ',
            '両頬にニキビ跡が点在',
            '全体的に肌のトーンが暗い',
            'ほうれい線が深くなってきている',
            '頬と口周りの乾燥が顕著',
        ];
        
        $diagnoses = [
            '老人性色素斑（日光性黒子）',
            '表情じわ（動的しわ）',
            '皮膚弛緩症（軽度）',
            '脂性肌による毛穴開き',
            '炎症後色素沈着',
            'メラニン色素の過剰生成',
            '真皮層のコラーゲン減少',
            '皮脂分泌量の低下による乾燥肌',
        ];
        
        $treatments = [
            'Qスイッチレーザー照射（3箇所）',
            'ボトックス注射（目尻）',
            'HIFU治療（全顔）',
            'ケミカルピーリング＋イオン導入',
            'フラクショナルレーザー治療',
            'フォトフェイシャル（IPL）照射',
            'ヒアルロン酸注入（ほうれい線）',
            '高濃度ビタミンC点滴＋保湿パック',
        ];
        
        $prescriptions = [
            'ハイドロキノンクリーム4% 朝晩塗布。日焼け止めSPF50+を必ず使用',
            '保湿クリームを1日2回使用。表情筋のストレッチを指導',
            'レチノールクリーム0.1% 夜のみ使用。マッサージ方法を指導',
            'ビタミンCローション朝晩使用。週2回のクレイマスク',
            'トレチノインクリーム0.05% 夜のみ。保湿を十分に',
            'トラネキサム酸内服 1日3回。美白美容液の使用',
            'コラーゲンサプリメント摂取。顔ヨガエクササイズ',
            'セラミド配合保湿剤を朝晩使用。加湿器の使用を推奨',
        ];
        
        $index = array_rand($chiefComplaints);
        
        MedicalRecord::create([
            'customer_id' => $reservation->customer_id,
            'staff_id' => $reservation->staff_id ?? $staffUsers->random()->id,
            'reservation_id' => $reservation->id,
            'record_date' => $reservation->reservation_date,
            'chief_complaint' => $chiefComplaints[$index],
            'symptoms' => $symptoms[$index],
            'diagnosis' => $diagnoses[$index],
            'treatment' => $treatments[$index],
            'prescription' => $prescriptions[$index],
            'medications' => $faker->randomElement([
                ['トラネキサム酸錠', 'ビタミンC錠'],
                ['ハイドロキノンクリーム4%'],
                ['トレチノイン軟膏0.05%', 'ヘパリン類似物質クリーム'],
                null
            ]),
            'medical_history' => $faker->randomElement([
                'アトピー性皮膚炎（幼少期）',
                '特記事項なし',
                '花粉症（スギ）',
                '金属アレルギー（ニッケル）',
                null
            ]),
            'notes' => $faker->randomElement([
                '次回は2週間後に経過観察',
                '肌の状態良好。現在の治療を継続',
                '少し赤みが出たら使用を中止するよう指導',
                null
            ]),
            'next_visit_date' => $faker->optional(0.7)->dateTimeBetween('now', '+3 months'),
            'created_by' => $staffUsers->random()->id,
        ]);
    }
    
    private function createHistoricalMedicalRecord($customer, $staffUsers, $faker)
    {
        $recordDate = $faker->dateTimeBetween('-2 years', '-1 month');
        
        MedicalRecord::create([
            'customer_id' => $customer->id,
            'staff_id' => $staffUsers->random()->id,
            'reservation_id' => null,
            'record_date' => $recordDate,
            'chief_complaint' => $faker->randomElement([
                'アンチエイジング相談',
                '美肌治療希望',
                '定期メンテナンス',
                'シミ・そばかす相談',
            ]),
            'symptoms' => $faker->randomElement([
                '全体的な肌質改善を希望',
                '定期的なメンテナンス来院',
                '前回治療の経過良好',
                '新たなシミの出現',
            ]),
            'diagnosis' => $faker->randomElement([
                '加齢による肌質変化',
                '光老化の進行',
                '肌状態安定',
                'メラニン活性の亢進',
            ]),
            'treatment' => $faker->randomElement([
                'フォトフェイシャル＋ビタミン導入',
                'ボトックス＋ヒアルロン酸',
                'ピーリング＋保湿パック',
                'レーザートーニング',
            ]),
            'prescription' => $faker->randomElement([
                'ホームケア製品の継続使用',
                '日焼け止めの徹底',
                '保湿ケアの強化',
                '美白化粧品の使用',
            ]),
            'created_by' => $staffUsers->random()->id,
        ]);
    }
}