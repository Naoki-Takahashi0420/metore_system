# AWS SES設定手順

## 1. SESでドメイン検証

### AWS CLIコマンド
```bash
# プロファイルを指定
export AWS_PROFILE=xsyumeno

# ドメインをSESに追加
aws ses verify-domain-identity --domain meno-training.com --region ap-northeast-1

# DKIM設定を有効化
aws ses put-identity-dkim-enabled \
    --identity meno-training.com \
    --dkim-enabled \
    --region ap-northeast-1

# DKIM トークンを取得
aws ses get-identity-dkim-attributes \
    --identities meno-training.com \
    --region ap-northeast-1
```

## 2. Route 53 DNS設定

### 必要なDNSレコード

#### SPFレコード（TXTレコード）
```
Name: meno-training.com
Type: TXT
Value: "v=spf1 include:amazonses.com ~all"
TTL: 300
```

#### DKIMレコード（CNAMEレコード × 3）
SESから提供される3つのトークンに対して：
```
Name: [token]._domainkey.meno-training.com
Type: CNAME
Value: [token].dkim.amazonses.com
TTL: 300
```

#### DMARCレコード（TXTレコード）
```
Name: _dmarc.meno-training.com
Type: TXT
Value: "v=DMARC1; p=quarantine; rua=mailto:admin@meno-training.com"
TTL: 300
```

#### MXレコード（受信用・オプション）
```
Name: meno-training.com
Type: MX
Value: 10 inbound-smtp.ap-northeast-1.amazonaws.com
TTL: 300
```

## 3. SES設定確認

```bash
# ドメイン検証状態を確認
aws ses get-identity-verification-attributes \
    --identities meno-training.com \
    --region ap-northeast-1

# DKIM検証状態を確認
aws ses get-identity-dkim-attributes \
    --identities meno-training.com \
    --region ap-northeast-1

# 送信統計を確認
aws ses get-send-statistics --region ap-northeast-1
```

## 4. テストメール送信

```bash
# テストメール送信
aws ses send-email \
    --from noreply@meno-training.com \
    --to your-email@example.com \
    --subject "SES Test Email" \
    --text "This is a test email from AWS SES" \
    --region ap-northeast-1
```

## 5. Laravelの.env設定

```env
# メール設定
MAIL_MAILER=ses
MAIL_HOST=email-smtp.ap-northeast-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@meno-training.com
MAIL_FROM_NAME="目のトレーニング"

# AWS設定
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=ap-northeast-1
```

## 6. サンドボックスからの移行

本番運用前に以下を実施：

1. **送信制限の解除申請**
```bash
# Service Quotas経由で申請
aws service-quotas request-service-quota-increase \
    --service-code ses \
    --quota-code L-804C8AE8 \
    --desired-value 50000 \
    --region ap-northeast-1
```

2. **バウンス・苦情処理の設定**
   - SNSトピックを作成してバウンス通知を受け取る
   - 自動的にメールリストから除外する仕組みを構築

3. **送信レピュテーション管理**
   - 送信統計のモニタリング
   - バウンス率5%以下、苦情率0.1%以下を維持

## 注意事項

- **サンドボックス中の制限**
  - 検証済みメールアドレスにのみ送信可能
  - 1日200通、1秒1通の制限
  
- **DKIM設定の重要性**
  - Gmail、Yahoo等の主要プロバイダーでの到達率向上
  - なりすまし防止
  
- **監視項目**
  - バウンス率
  - 苦情率
  - 送信レピュテーション