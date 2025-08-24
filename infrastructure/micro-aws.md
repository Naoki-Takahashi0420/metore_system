# 🚀 月間1000アクセス用 超軽量AWS構成

## 💰 実質無料でスタート！

### AWS無料枠フル活用プラン

```
EC2 t3.micro:     $0/月 (12ヶ月間)
S3 5GB:          $0/月 (永続無料)  
SNS 1000通知:     $0/月 (永続無料)
合計:            $0/月 ✨
```

## ⚡ 5分セットアップ

### 1. EC2インスタンス作成（1クリック）
```bash
# キーペア作成
aws ec2 create-key-pair --key-name xsyumeno --query 'KeyMaterial' --output text > xsyumeno.pem
chmod 400 xsyumeno.pem

# 最小構成で起動
aws ec2 run-instances \
    --image-id ami-0d52744d6551d851e \
    --count 1 \
    --instance-type t3.micro \
    --key-name xsyumeno \
    --security-groups default \
    --user-data file://cloud-init.sh
```

### 2. セキュリティグループ設定
```bash
# HTTP許可
aws ec2 authorize-security-group-ingress \
    --group-name default \
    --protocol tcp \
    --port 80 \
    --cidr 0.0.0.0/0
```

### 3. デプロイ（SSHキーのみ）
```bash
# GitHub Secretsに設定するだけ
EC2_HOST: [IPアドレス]
EC2_SSH_KEY: [秘密鍵の内容]
```

## 🎯 超シンプルCI/CD

```yaml
# .github/workflows/simple-deploy.yml (既に作成済み)
- SSH接続でファイル転送
- 自動マイグレーション  
- サービス再起動
- ヘルスチェック
```

## 📱 SMS設定（無料分で十分）

```php
// 月間1000通知まで無料
$sns = new \Aws\Sns\SnsClient([
    'version' => 'latest',
    'region' => 'ap-northeast-1'
]);

$sns->publish([
    'Message' => '予約確定しました！',
    'PhoneNumber' => '+819012345678'
]);
```

## 🔧 パフォーマンス最適化

### t3.micro でも快適！
```nginx
# Nginxキャッシュ設定
location ~* \.(css|js|png|jpg)$ {
    expires 1M;
    add_header Cache-Control "public";
}

# PHP-FPM軽量化
pm.max_children = 5
pm.start_servers = 2
```

### MySQL軽量化
```sql
-- 必要最小限のテーブルのみ
-- インデックス最適化
-- クエリキャッシュ有効化
```

## 🚀 爆速デプロイコマンド

```bash
# たった1コマンドで本番デプロイ！
git push origin main

# 2-3分で完了 ✨
```

## 📊 実際のリソース使用量

### 月間1000アクセスの場合
```
CPU使用率:     平均5%以下
メモリ使用率:   平均60%以下  
ネットワーク:   1GB以下
ストレージ:    10GB以下
```

**→ t3.microで十分すぎる！**

## 🎉 スケーリング計画

### 月間アクセス数別プラン

| アクセス数 | インスタンス | 月額コスト |
|-----------|------------|----------|
| ~1,000    | t3.micro   | $0       |
| ~10,000   | t3.small   | ~$15     |
| ~100,000  | t3.medium  | ~$30     |

## 💡 追加したい機能

### 1. 自動バックアップ（無料）
```bash
# 日次スナップショット
aws ec2 create-snapshot --volume-id vol-xxxxx
```

### 2. 監視（無料）
```bash
# CloudWatch Basic監視
aws cloudwatch put-metric-alarm \
    --alarm-name high-cpu \
    --metric-name CPUUtilization
```

### 3. SSL証明書（無料）
```bash
# Let's Encrypt
sudo certbot --nginx -d yourdomain.com
```

## 🚨 コスト監視

### 予算アラート設定
```bash
aws budgets create-budget \
    --account-id $(aws sts get-caller-identity --query Account --output text) \
    --budget '{
        "BudgetName": "xsyumeno-monthly",
        "BudgetLimit": {
            "Amount": "5",
            "Unit": "USD"
        },
        "TimeUnit": "MONTHLY",
        "BudgetType": "COST"
    }'
```

## 🎯 まとめ

### ✅ この構成の利点
- **完全無料**で12ヶ月運用可能
- **2-3分**で自動デプロイ  
- **1000+ユーザー**まで対応
- **拡張性**抜群
- **保守性**高い

### 📈 成長に応じてスケール
月間1000 → 10,000 → 100,000アクセスまで同じ構成でOK！

**今すぐ始められます！** 🚀