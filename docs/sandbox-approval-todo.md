# サンドボックス承認後のTODOリスト

## 1. SMS機能を本番化

### `/app/Services/SmsService.php`
```php
// 37-44行目のコメントを解除して、45-69行目を有効化
// 現在：ログ出力のみ
// 承認後：実際のSMS送信

// コメントアウトを解除
$result = $this->snsClient->publish([
    'Message' => $message,
    'PhoneNumber' => $phone,
    ...
]);
```

## 2. OTPをランダム化

### `/app/Services/OtpService.php`
```php
// 106行目を変更
// 現在：
return '123456';

// 承認後：
return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
```

## 3. メール送信を本番化

### `/app/Services/EmailService.php`
```php
// 39-44行目のログ出力を削除
// 47-75行目のコメントを解除して有効化

$result = $this->sesClient->sendEmail([
    'Source' => "$fromName <$fromEmail>",
    'Destination' => [
        'ToAddresses' => [$to],
    ],
    ...
]);
```

## 4. 環境変数の確認

### 本番環境の `.env`
```env
# AWS認証情報が設定されているか確認
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=ap-northeast-1

# メール設定
MAIL_FROM_ADDRESS=noreply@meno-training.com
MAIL_FROM_NAME=目のトレーニング

# SMS設定
SMS_FROM_NAME=目のトレーニング
```

## 5. テスト手順

### SMS送信テスト
```bash
# 本番環境でテスト
curl -X POST https://reservation.meno-training.com/api/auth/otp/send \
  -H "Content-Type: application/json" \
  -d '{
    "type": "sms",
    "phone": "090-1234-5678"
  }'
```

### メール送信テスト
```bash
curl -X POST https://reservation.meno-training.com/api/auth/otp/send \
  -H "Content-Type: application/json" \
  -d '{
    "type": "email",
    "email": "test@example.com"
  }'
```

## 6. 監視設定

### バウンス率・苦情率の監視
```bash
# 送信統計を確認
aws ses get-send-statistics --region ap-northeast-1 --profile xsyumeno

# アカウントの送信クォータ確認
aws ses get-send-quota --region ap-northeast-1 --profile xsyumeno
```

### CloudWatchアラーム設定（推奨）
- バウンス率 > 5% でアラート
- 苦情率 > 0.1% でアラート

## 7. デプロイ

```bash
# 変更をコミット
git add -A
git commit -m "feat: サンドボックス承認後の本番設定有効化

- SMS実送信を有効化
- OTPランダム生成を有効化
- メール実送信を有効化"

# デプロイ
git push
gh workflow run deploy-simple.yml
```

## チェックリスト

- [ ] サンドボックス承認メール受信
- [ ] SmsService.php の本番コード有効化
- [ ] OtpService.php のランダムOTP有効化
- [ ] EmailService.php の本番コード有効化
- [ ] 環境変数の確認
- [ ] SMS送信テスト
- [ ] メール送信テスト
- [ ] CloudWatch監視設定
- [ ] 本番デプロイ

## 注意事項

⚠️ **必ずサンドボックス承認を確認してから有効化すること**
⚠️ **最初は少量のテスト送信から開始**
⚠️ **バウンス率・苦情率を定期的に確認**