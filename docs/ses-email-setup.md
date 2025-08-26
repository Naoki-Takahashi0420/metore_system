# AWS SES メール設定手順

## ステップ1: メールアドレスの検証

### 推奨メールアドレス
以下のメールアドレスから選択、または追加してください：

1. **管理用メール**
   - `admin@meno-training.com`
   - `naoki@meno-training.com`

2. **システム用メール**
   - `noreply@meno-training.com` （送信専用）
   - `support@meno-training.com` （サポート用）
   - `info@meno-training.com` （一般問い合わせ）

### AWS CLIでメールアドレスを検証

```bash
# プロファイルを設定
export AWS_PROFILE=xsyumeno

# メールアドレスを検証（例：admin@meno-training.com）
aws ses verify-email-identity \
    --email-address admin@meno-training.com \
    --region ap-northeast-1

# 複数のメールアドレスを一括検証
for email in admin@meno-training.com noreply@meno-training.com support@meno-training.com; do
    aws ses verify-email-identity \
        --email-address $email \
        --region ap-northeast-1
    echo "検証メールを送信: $email"
done

# 検証状態を確認
aws ses list-verified-email-addresses --region ap-northeast-1
```

## ステップ2: ドメインの追加と検証

```bash
# ドメインを追加
aws ses verify-domain-identity \
    --domain meno-training.com \
    --region ap-northeast-1

# 結果例：
# {
#     "VerificationToken": "TXT_RECORD_VALUE_HERE"
# }
```

### Route 53にTXTレコードを追加

```json
{
  "Changes": [{
    "Action": "CREATE",
    "ResourceRecordSet": {
      "Name": "_amazonses.meno-training.com",
      "Type": "TXT",
      "TTL": 300,
      "ResourceRecords": [{
        "Value": "\"VERIFICATION_TOKEN_HERE\""
      }]
    }
  }]
}
```

## ステップ3: DKIM設定（配信性能の強化）

```bash
# DKIMを有効化
aws ses put-identity-dkim-enabled \
    --identity meno-training.com \
    --dkim-enabled \
    --region ap-northeast-1

# DKIMトークンを取得
aws ses get-identity-dkim-attributes \
    --identities meno-training.com \
    --region ap-northeast-1

# 結果例：
# {
#     "DkimAttributes": {
#         "meno-training.com": {
#             "DkimEnabled": true,
#             "DkimVerificationStatus": "Pending",
#             "DkimTokens": [
#                 "token1",
#                 "token2",
#                 "token3"
#             ]
#         }
#     }
# }
```

### Route 53にDKIMレコードを追加

各トークンに対してCNAMEレコードを作成：

```json
{
  "Changes": [
    {
      "Action": "CREATE",
      "ResourceRecordSet": {
        "Name": "token1._domainkey.meno-training.com",
        "Type": "CNAME",
        "TTL": 300,
        "ResourceRecords": [{
          "Value": "token1.dkim.amazonses.com"
        }]
      }
    },
    {
      "Action": "CREATE",
      "ResourceRecordSet": {
        "Name": "token2._domainkey.meno-training.com",
        "Type": "CNAME",
        "TTL": 300,
        "ResourceRecords": [{
          "Value": "token2.dkim.amazonses.com"
        }]
      }
    },
    {
      "Action": "CREATE",
      "ResourceRecordSet": {
        "Name": "token3._domainkey.meno-training.com",
        "Type": "CNAME",
        "TTL": 300,
        "ResourceRecords": [{
          "Value": "token3.dkim.amazonses.com"
        }]
      }
    }
  ]
}
```

## ステップ4: SPFレコードの設定

```bash
# Route 53でSPFレコードを設定
aws route53 change-resource-record-sets \
    --hosted-zone-id YOUR_ZONE_ID \
    --change-batch file://spf-record.json
```

spf-record.json:
```json
{
  "Changes": [{
    "Action": "CREATE",
    "ResourceRecordSet": {
      "Name": "meno-training.com",
      "Type": "TXT",
      "TTL": 300,
      "ResourceRecords": [{
        "Value": "\"v=spf1 include:amazonses.com ~all\""
      }]
    }
  }]
}
```

## ステップ5: 検証確認

```bash
# メールアドレスの検証状態
aws ses get-identity-verification-attributes \
    --identities admin@meno-training.com \
    --region ap-northeast-1

# ドメインの検証状態
aws ses get-identity-verification-attributes \
    --identities meno-training.com \
    --region ap-northeast-1

# DKIM検証状態
aws ses get-identity-dkim-attributes \
    --identities meno-training.com \
    --region ap-northeast-1
```

## テストメール送信

```bash
# CLIでテスト送信
aws ses send-email \
    --from admin@meno-training.com \
    --to your-test-email@example.com \
    --subject "SES設定テスト" \
    --text "AWS SESからのテストメールです。" \
    --region ap-northeast-1
```

## Laravel設定（.env）

```env
# メール基本設定
MAIL_MAILER=ses
MAIL_HOST=email-smtp.ap-northeast-1.amazonaws.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls

# 送信元設定
MAIL_FROM_ADDRESS=noreply@meno-training.com
MAIL_FROM_NAME="目のトレーニング"

# AWS認証情報
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=ap-northeast-1
```

## 注意事項

1. **検証メールの確認**
   - 各メールアドレスに送信される検証メールのリンクをクリック
   - 24時間以内に検証を完了

2. **サンドボックス制限**
   - 初期状態では検証済みアドレスにのみ送信可能
   - 本番運用前に制限解除申請が必要

3. **推奨設定**
   - バウンス処理の自動化
   - 苦情処理の設定
   - 送信レートの監視