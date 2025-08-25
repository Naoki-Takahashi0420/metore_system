# AWS SNS 本番環境SMS送信ガイド

## 🚨 重要：本番利用までのステップ

### Step 1: IAMユーザー作成（即日可能）

```bash
# SMS送信専用IAMユーザー作成
aws iam create-user --user-name xsyumeno-sns-user

# SMS送信ポリシー作成
cat << EOF > sns-sms-policy.json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "sns:Publish",
                "sns:SetSMSAttributes",
                "sns:GetSMSAttributes"
            ],
            "Resource": "*"
        }
    ]
}
EOF

aws iam create-policy \
    --policy-name XsyumenoSNSSMSPolicy \
    --policy-document file://sns-sms-policy.json

# ポリシーアタッチ
aws iam attach-user-policy \
    --user-name xsyumeno-sns-user \
    --policy-arn arn:aws:iam::YOUR_ACCOUNT_ID:policy/XsyumenoSNSSMSPolicy

# アクセスキー作成
aws iam create-access-key --user-name xsyumeno-sns-user
```

### Step 2: サンドボックス解除申請（1-3営業日）

#### 申請が必要な理由
- デフォルトではテスト環境（サンドボックス）
- 制限：事前登録した番号のみ送信可能
- 本番利用には解除が必須

#### 申請手順

1. **AWSサポートセンターへアクセス**
   - https://console.aws.amazon.com/support/home
   - 「Create case」をクリック

2. **申請内容記入**
```
Case type: Service limit increase
Service: SNS
Limit type: SMS Sandbox Mode
Region: Asia Pacific (Tokyo) [ap-northeast-1]

Use case description:
-----------------------
【サービス概要】
美容サロン「Xsyumeno」の予約管理システムで、顧客への予約確認・リマインダー通知に使用

【送信メッセージ種別】
- 予約確認通知
- 来店前日リマインダー
- 次回メンテナンス推奨日の通知

【送信対象】
- 事前同意を得た既存顧客のみ
- オプトアウト機能実装済み

【予想送信量】
- 月間: 約1,000-2,000通
- 日次最大: 約100通
- ピーク時: 約20通/時間

【メッセージサンプル】
【Xsyumeno】
山田様
ご予約を承りました。
日時: 1月15日 14:00
メニュー: アイケアコース
ご来店をお待ちしております。
-----------------------
```

3. **追加で必要な情報**
   - 会社のWebサイトURL
   - プライバシーポリシーURL
   - オプトアウト方法の説明

### Step 3: 送信制限緩和申請（必要に応じて）

初期制限:
- 送信レート: 1通/秒
- 日次制限: 200通

制限緩和申請:
```
必要な送信レート: 5通/秒
必要な日次制限: 2,000通
理由: 予約確認の即時送信と朝のリマインダー一括送信のため
```

### Step 4: 本番環境設定

#### .env設定
```env
# AWS SNS本番設定
AWS_ACCESS_KEY_ID=AKIAXXXXXXXXXX
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxx
AWS_DEFAULT_REGION=ap-northeast-1
SMS_FROM_NAME=Xsyumeno

# SMS送信設定
SMS_SANDBOX_MODE=false  # 本番時はfalse
SMS_DRY_RUN=false       # テスト時はtrue
```

#### 本番用設定ファイル作成
```php
// config/sms.php
<?php

return [
    'sandbox_mode' => env('SMS_SANDBOX_MODE', true),
    'dry_run' => env('SMS_DRY_RUN', false),
    'daily_limit' => env('SMS_DAILY_LIMIT', 200),
    'rate_limit' => env('SMS_RATE_LIMIT', 1), // 通/秒
    
    // 送信可能時間帯（日本時間）
    'send_hours' => [
        'start' => 9,  // 9:00
        'end' => 20,   // 20:00
    ],
    
    // メッセージテンプレート
    'templates' => [
        'reservation_confirmation' => '【{store_name}】\n{customer_name}様\nご予約を承りました。\n日時: {date} {time}\nメニュー: {menu}\nご来店をお待ちしております。',
        'reminder' => '【{store_name}】\n{customer_name}様\n明日 {time} にご予約をいただいております。\nご来店をお待ちしております。',
        'maintenance' => '【{store_name}】\n{customer_name}様\n前回お伝えした次回メンテナンス推奨日（{date}頃）が近づいてまいりました。\nご予約はお電話または公式サイトから承っております。',
    ],
];
```

### Step 5: 送信前チェックリスト

#### 必須確認項目
- [ ] サンドボックス解除承認済み
- [ ] IAMユーザーの権限設定完了
- [ ] 環境変数設定完了
- [ ] エラーハンドリング実装
- [ ] 送信ログ記録機能実装
- [ ] オプトアウト機能実装
- [ ] 送信時間制限実装（9:00-20:00）
- [ ] レート制限実装

#### コンプライアンス
- [ ] 個人情報保護方針への記載
- [ ] 事前同意取得フロー実装
- [ ] 配信停止方法の明記

## 💰 料金計算

### SMS送信料金（日本向け）
- 1通あたり: $0.07451（約11円）
- 月1,000通: 約11,000円
- 月2,000通: 約22,000円

### 追加コスト
- SNS利用料: 基本無料
- CloudWatchログ: 約$0.5/月

## ⚠️ トラブルシューティング

### よくあるエラー

1. **サンドボックスエラー**
```
Error: SMS message failed to send because your account is in the SMS sandbox
解決: サンドボックス解除申請を行う
```

2. **レート制限エラー**
```
Error: Rate exceeded
解決: 送信間隔を調整（sleep追加）
```

3. **無効な電話番号**
```
Error: Invalid parameter: PhoneNumber
解決: 電話番号フォーマット確認（+81形式）
```

### デバッグ用コード
```php
// app/Console/Commands/TestSms.php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Sms\SmsService;

class TestSms extends Command
{
    protected $signature = 'sms:test {phone}';
    protected $description = 'Test SMS sending';
    
    public function handle(SmsService $smsService)
    {
        $phone = $this->argument('phone');
        $message = "テストメッセージ from Xsyumeno\n" . now()->format('Y-m-d H:i:s');
        
        try {
            $result = $smsService->sendSms($phone, $message);
            if ($result) {
                $this->info('SMS sent successfully');
            } else {
                $this->error('SMS sending failed');
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
```

実行:
```bash
php artisan sms:test 09012345678
```

## 📊 監視設定

### CloudWatchアラーム設定
```bash
# SMS送信失敗アラート
aws cloudwatch put-metric-alarm \
    --alarm-name sms-send-failures \
    --alarm-description "Alert when SMS send failures exceed threshold" \
    --metric-name NumberOfMessagesFailed \
    --namespace AWS/SNS \
    --statistic Sum \
    --period 300 \
    --threshold 5 \
    --comparison-operator GreaterThanThreshold
```

### ログ確認
```bash
# Laravel側のログ
tail -f storage/logs/laravel.log | grep SMS

# CloudWatchログ
aws logs tail /aws/sns/sms --follow
```

## 🚀 本番移行チェックリスト

### 移行前
- [ ] ステージング環境でテスト完了
- [ ] サンドボックス解除承認
- [ ] 本番IAMクレデンシャル準備
- [ ] バックアップ取得

### 移行時
- [ ] 環境変数更新
- [ ] 少数の番号でテスト送信
- [ ] ログ監視開始

### 移行後
- [ ] 送信成功率確認
- [ ] エラー率監視
- [ ] コスト監視

## 📞 サポート連絡先

- AWSサポート: https://console.aws.amazon.com/support/
- 技術的な質問: AWS Developer Forums
- 緊急時: AWS Premium Support（要契約）

---

このガイドに従って設定を進めることで、本番環境でのSMS送信が可能になります。
サンドボックス解除には1-3営業日かかるため、早めの申請をお勧めします。