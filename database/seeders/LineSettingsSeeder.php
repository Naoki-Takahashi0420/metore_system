<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LineSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // 通知設定
            [
                'key' => 'notification_priority',
                'name' => '通知優先度設定',
                'description' => 'LINE登録済み顧客への通知方法の優先順位を設定します。LINEが利用可能な場合はSMSより優先して送信されます。',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'notification',
                'sort_order' => 1,
                'is_system' => true,
            ],
            [
                'key' => 'auto_line_preference',
                'name' => 'LINE優先自動切り替え',
                'description' => '顧客がLINEに登録している場合、自動的にLINE通知を優先し、SMS通知を無効化します。',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'notification',
                'sort_order' => 2,
                'is_system' => true,
            ],
            [
                'key' => 'fallback_to_sms',
                'name' => 'SMS フォールバック',
                'description' => 'LINE送信に失敗した場合、自動的にSMSで再送信します。',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'notification',
                'sort_order' => 3,
                'is_system' => false,
            ],
            
            // キャンペーン配信設定
            [
                'key' => 'campaign_flow_tracking',
                'name' => '流入経路トラッキング',
                'description' => '顧客がどの店舗のQRコードでLINE登録したかを記録し、店舗別キャンペーン配信に活用します。',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'campaign',
                'sort_order' => 1,
                'is_system' => true,
            ],
            [
                'key' => 'campaign_auto_send',
                'name' => '自動キャンペーン配信',
                'description' => '新規LINE登録時に店舗別キャンペーンメッセージを自動送信します。',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'campaign',
                'sort_order' => 2,
                'is_system' => false,
            ],
            [
                'key' => 'campaign_send_timing',
                'name' => 'キャンペーン送信タイミング',
                'description' => '新規登録後のキャンペーン送信タイミングを設定します。',
                'value' => ['selected' => 'immediate'],
                'type' => 'select',
                'options' => [
                    'immediate' => '即座に送信',
                    '1hour' => '1時間後',
                    '24hour' => '24時間後',
                    'manual' => '手動送信のみ',
                ],
                'category' => 'campaign',
                'sort_order' => 3,
                'is_system' => false,
            ],
            
            // システム設定
            [
                'key' => 'webhook_verification',
                'name' => 'Webhook検証',
                'description' => 'LINE Webhook の署名検証を有効にします。本番環境では必須です。',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'category' => 'system',
                'sort_order' => 1,
                'is_system' => true,
            ],
            [
                'key' => 'debug_logging',
                'name' => 'デバッグログ',
                'description' => 'LINE API との通信ログを詳細に記録します。トラブルシューティング用です。',
                'value' => ['enabled' => false],
                'type' => 'boolean',
                'category' => 'system',
                'sort_order' => 2,
                'is_system' => false,
            ],
            
            // 使用方法・マニュアル
            [
                'key' => 'usage_manual',
                'name' => 'LINE Bot 使用方法',
                'description' => 'LINE Bot機能の使用方法とベストプラクティス',
                'value' => ['text' => "【LINE Bot 使用方法】\n\n■ 基本機能\n・新規顧客がLINE登録すると自動的にウェルカムメッセージを送信\n・予約リマインダーはLINE優先、SMS はフォールバックとして利用\n・店舗別QRコードで流入経路を自動追跡\n\n■ キャンペーン配信\n・店舗別にターゲットを絞ったキャンペーン配信が可能\n・登録元店舗に基づいた自動配信設定\n・手動配信も管理画面から実行可能\n\n■ メッセージテンプレート管理\n・管理画面でテンプレートの編集・追加が可能\n・変数機能で顧客名や予約情報を自動挿入\n・店舗別テンプレートの設定も対応\n\n■ 注意事項\n・LINE登録済み顧客にはSMSではなくLINEで通知\n・キャンペーン配信前には必ずテスト送信を実施\n・個人情報保護に配慮したメッセージ作成を心がけてください"],
                'type' => 'textarea',
                'category' => 'manual',
                'sort_order' => 1,
                'is_system' => false,
            ],
            [
                'key' => 'troubleshooting_guide',
                'name' => 'トラブルシューティング',
                'description' => 'よくある問題と解決方法',
                'value' => ['text' => "【よくある問題と解決方法】\n\n■ メッセージが送信されない\n・LINE Bot の友だち登録状況を確認\n・テンプレートの変数が正しく設定されているか確認\n・デバッグログを有効にして詳細を確認\n\n■ キャンペーンが配信されない\n・自動配信設定が有効になっているか確認\n・対象店舗の設定が正しいか確認\n・送信タイミング設定を確認\n\n■ QRコードが表示されない\n・完了画面のJavaScriptエラーがないか確認\n・予約情報が正しく渡されているか確認\n\n■ 管理画面でエラーが発生\n・ブラウザのキャッシュをクリア\n・設定値の形式が正しいか確認\n・システム設定項目は慎重に変更してください"],
                'type' => 'textarea',
                'category' => 'manual',
                'sort_order' => 2,
                'is_system' => false,
            ],
        ];

        foreach ($settings as $setting) {
            \App\Models\LineSettings::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
