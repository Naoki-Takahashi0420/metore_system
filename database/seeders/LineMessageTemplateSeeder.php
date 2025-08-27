<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LineMessageTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => 'welcome',
                'name' => 'ウェルカムメッセージ',
                'category' => 'welcome',
                'message' => "{{customer_name}}様、友だち追加ありがとうございます！🎉\n\n目のトレーニングをLINEでもっと便利に✨\n\n📅 予約確認・変更\n🔔 リマインダー通知\n💰 お得なキャンペーン情報\n📍 店舗情報\n\nメニューからお気軽にご利用ください！",
                'variables' => [
                    'customer_name' => '顧客名',
                    'store_name' => '店舗名'
                ],
                'description' => '新規友だち追加時に自動送信されるメッセージ'
            ],
            [
                'key' => 'reminder',
                'name' => '予約リマインダー',
                'category' => 'reminder',
                'message' => "【予約リマインダー】\n\n{{customer_name}}様\n明日のご予約をお忘れではありませんか？\n\n📅 日時: {{reservation_date}} {{start_time}}\n🏪 店舗: {{store_name}}\n💆 メニュー: {{menu_name}}\n🎫 予約番号: {{reservation_number}}\n\nお気をつけてお越しください。\n変更・キャンセルはお気軽にご連絡ください。",
                'variables' => [
                    'customer_name' => '顧客名',
                    'reservation_date' => '予約日',
                    'start_time' => '開始時間',
                    'store_name' => '店舗名',
                    'menu_name' => 'メニュー名',
                    'reservation_number' => '予約番号'
                ],
                'description' => '予約前日に自動送信されるリマインダーメッセージ'
            ],
            [
                'key' => 'campaign_welcome',
                'name' => '新規顧客向けキャンペーン',
                'category' => 'campaign',
                'message' => "🎉 {{customer_name}}様限定！初回特別キャンペーン\n\n{{store_name}}にご予約いただいた方限定✨\n\n👁️ 目のトレーニング体験\n通常 ¥5,000 → ¥3,000\n\n📱 LINEでご予約で更に500円OFF！\n\nご予約はこちらから\n👉 https://reservation.meno-training.com\n\n※期間限定・先着順",
                'variables' => [
                    'customer_name' => '顧客名',
                    'store_name' => '店舗名'
                ],
                'description' => '予約完了画面からのLINE登録者向けキャンペーン'
            ]
        ];

        foreach ($templates as $template) {
            \App\Models\LineMessageTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}
