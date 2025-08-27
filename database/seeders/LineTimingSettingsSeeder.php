<?php

namespace Database\Seeders;

use App\Models\LineSettings;
use Illuminate\Database\Seeder;

class LineTimingSettingsSeeder extends Seeder
{
    /**
     * 追加のタイミング設定をシード
     */
    public function run(): void
    {
        $timingSettings = [
            // リマインダー送信タイミング設定
            [
                'key' => 'reminder_send_timing',
                'name' => '予約リマインダー送信タイミング',
                'description' => '予約何時間前にリマインダーを送信するか設定します',
                'value' => ['selected' => '24'],
                'type' => 'select',
                'options' => [
                    '6' => '6時間前',
                    '12' => '12時間前',
                    '24' => '24時間前（前日）',
                    '48' => '48時間前（2日前）',
                    '72' => '72時間前（3日前）',
                ],
                'category' => 'notification',
                'sort_order' => 10,
                'is_system' => false,
            ],
            [
                'key' => 'reminder_send_time',
                'name' => 'リマインダー送信時刻',
                'description' => 'リマインダーを送信する時刻帯を設定します（24時間前の場合に適用）',
                'value' => ['selected' => '10:00'],
                'type' => 'select',
                'options' => [
                    '09:00' => '午前9時',
                    '10:00' => '午前10時',
                    '12:00' => '正午',
                    '15:00' => '午後3時',
                    '18:00' => '午後6時',
                    '20:00' => '午後8時',
                ],
                'category' => 'notification',
                'sort_order' => 11,
                'is_system' => false,
            ],
            [
                'key' => 'reminder_double_send',
                'name' => '二重リマインダー送信',
                'description' => '前日と当日の両方でリマインダーを送信します',
                'value' => ['enabled' => false],
                'type' => 'boolean',
                'category' => 'notification',
                'sort_order' => 12,
                'is_system' => false,
            ],
            
            // フォローアップメッセージ設定
            [
                'key' => 'followup_enabled',
                'name' => 'フォローアップメッセージ',
                'description' => '予約完了後のフォローアップメッセージを送信します',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'notification',
                'sort_order' => 20,
                'is_system' => false,
            ],
            [
                'key' => 'followup_timing',
                'name' => 'フォローアップ送信タイミング',
                'description' => '予約完了後いつフォローアップメッセージを送信するか',
                'value' => ['selected' => '1day'],
                'type' => 'select',
                'options' => [
                    'immediate' => '即時',
                    '1hour' => '1時間後',
                    '3hours' => '3時間後',
                    '1day' => '翌日',
                    '3days' => '3日後',
                    '1week' => '1週間後',
                ],
                'category' => 'notification',
                'sort_order' => 21,
                'is_system' => false,
            ],
            
            // キャンペーン配信詳細設定
            [
                'key' => 'campaign_frequency_limit',
                'name' => 'キャンペーン配信頻度制限',
                'description' => '同一顧客へのキャンペーン配信間隔（日数）',
                'value' => ['text' => '7'],
                'type' => 'text',
                'category' => 'campaign',
                'sort_order' => 10,
                'is_system' => false,
            ],
            [
                'key' => 'campaign_send_hours',
                'name' => 'キャンペーン配信可能時間帯',
                'description' => 'キャンペーンメッセージを配信可能な時間帯を設定',
                'value' => ['selected' => '9-21'],
                'type' => 'select',
                'options' => [
                    '9-21' => '9:00-21:00',
                    '10-20' => '10:00-20:00',
                    '11-19' => '11:00-19:00',
                    '24' => '24時間配信可能',
                ],
                'category' => 'campaign',
                'sort_order' => 11,
                'is_system' => false,
            ],
            [
                'key' => 'campaign_retry_on_failure',
                'name' => '配信失敗時の再送信',
                'description' => 'キャンペーン配信に失敗した場合、自動的に再送信を試みます',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'campaign',
                'sort_order' => 12,
                'is_system' => false,
            ],
            [
                'key' => 'campaign_retry_delay',
                'name' => '再送信までの待機時間',
                'description' => '配信失敗後、何分後に再送信を試みるか',
                'value' => ['selected' => '30'],
                'type' => 'select',
                'options' => [
                    '5' => '5分後',
                    '15' => '15分後',
                    '30' => '30分後',
                    '60' => '1時間後',
                    '120' => '2時間後',
                ],
                'category' => 'campaign',
                'sort_order' => 13,
                'is_system' => false,
            ],
            
            // 自動返信設定
            [
                'key' => 'auto_reply_enabled',
                'name' => '自動返信機能',
                'description' => '顧客からのメッセージに自動返信します',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'notification',
                'sort_order' => 30,
                'is_system' => false,
            ],
            [
                'key' => 'auto_reply_business_hours_only',
                'name' => '営業時間内のみ自動返信',
                'description' => '営業時間内のみ自動返信を有効にします',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'notification',
                'sort_order' => 31,
                'is_system' => false,
            ],
            [
                'key' => 'auto_reply_delay',
                'name' => '自動返信遅延',
                'description' => 'メッセージ受信後、自動返信までの待機時間（秒）',
                'value' => ['text' => '3'],
                'type' => 'text',
                'category' => 'notification',
                'sort_order' => 32,
                'is_system' => false,
            ],
            
            // 配信制限設定
            [
                'key' => 'daily_message_limit',
                'name' => '1日あたりの最大メッセージ数',
                'description' => '1人の顧客に1日に送信できる最大メッセージ数',
                'value' => ['text' => '5'],
                'type' => 'text',
                'category' => 'system',
                'sort_order' => 10,
                'is_system' => false,
            ],
            [
                'key' => 'quiet_hours',
                'name' => '配信禁止時間帯',
                'description' => 'メッセージ配信を行わない時間帯',
                'value' => ['selected' => '22-8'],
                'type' => 'select',
                'options' => [
                    'none' => '制限なし',
                    '22-8' => '22:00-8:00',
                    '23-7' => '23:00-7:00',
                    '0-6' => '0:00-6:00',
                ],
                'category' => 'system',
                'sort_order' => 11,
                'is_system' => false,
            ],
            [
                'key' => 'respect_customer_timezone',
                'name' => '顧客タイムゾーン考慮',
                'description' => '顧客の地域に応じて配信時刻を調整します',
                'value' => ['enabled' => false],
                'type' => 'boolean',
                'category' => 'system',
                'sort_order' => 12,
                'is_system' => false,
            ],
            
            // バッチ処理設定
            [
                'key' => 'batch_send_enabled',
                'name' => 'バッチ送信有効化',
                'description' => '大量配信時にバッチ処理を使用します',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'system',
                'sort_order' => 20,
                'is_system' => false,
            ],
            [
                'key' => 'batch_size',
                'name' => 'バッチサイズ',
                'description' => '一度に処理するメッセージ数',
                'value' => ['text' => '100'],
                'type' => 'text',
                'category' => 'system',
                'sort_order' => 21,
                'is_system' => false,
            ],
            [
                'key' => 'batch_delay',
                'name' => 'バッチ間の待機時間',
                'description' => 'バッチ処理間の待機時間（秒）',
                'value' => ['text' => '5'],
                'type' => 'text',
                'category' => 'system',
                'sort_order' => 22,
                'is_system' => false,
            ],
        ];

        foreach ($timingSettings as $setting) {
            LineSettings::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}