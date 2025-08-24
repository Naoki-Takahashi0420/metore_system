# デプロイ手順書

## 作成済みのAWSリソース

- **EC2インスタンス**: 13.115.38.179
- **RDSデータベース**: xsyumeno-db.cbq0ywo44b0p.ap-northeast-1.rds.amazonaws.com
- **データベース名**: xsyumenodb
- **データベースユーザー**: admin
- **データベースパスワード**: bms3sH5CS2qtPdTKP7Vi

## EC2への手動アクセス方法

### SSHキーの権限設定
```bash
chmod 400 xsyumeno-key.pem
```

### SSH接続
```bash
ssh -i xsyumeno-key.pem ec2-user@13.115.38.179
```

## EC2での初期設定（手動で行う場合）

1. EC2にSSH接続後、以下を実行：

```bash
# セットアップスクリプトをコピー
cat > setup.sh << 'EOF'
[scripts/setup-ec2.shの内容をコピー]
EOF

# 実行権限を付与
chmod +x setup.sh

# 実行
sudo ./setup.sh
```

2. Laravelのアプリケーションキーを生成：

```bash
cd /var/www/html
php artisan key:generate --show
```

3. 生成されたキーを`.env.production`のAPP_KEYに設定

## GitHub Actionsでの自動デプロイ

### 必要なGitHub Secrets

すでに設定済み：
- AWS_ACCESS_KEY_ID
- AWS_SECRET_ACCESS_KEY  
- EC2_HOST
- EC2_USER
- EC2_SSH_KEY

### デプロイ方法

1. mainブランチにプッシュ
2. GitHub Actionsが自動的に実行
3. デプロイ完了

## トラブルシューティング

### SSH接続できない場合

1. キーファイルの権限を確認：
```bash
ls -la xsyumeno-key.pem
# -r-------- である必要があります
```

2. EC2のパブリックIPを確認：
```bash
aws ec2 describe-instances --instance-ids i-077118408a6816fdf \
  --profile xsyumeno \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text
```

### デプロイが失敗する場合

1. GitHub Actionsのログを確認
2. EC2の`/var/www/html`ディレクトリが存在するか確認
3. `.env.production`ファイルが正しく配置されているか確認

### データベース接続エラー

1. RDSのステータスを確認：
```bash
aws rds describe-db-instances --db-instance-identifier xsyumeno-db \
  --profile xsyumeno \
  --query 'DBInstances[0].DBInstanceStatus' \
  --output text
```

2. セキュリティグループを確認（EC2からRDSへのMySQL接続が許可されているか）

## コスト情報

月額予想費用：
- EC2 t3.micro: 約1,000円
- RDS t3.micro: 約1,500円
- 合計: 約2,500円 + SMS送信料

## 連絡先

問題が発生した場合は、このドキュメントと共にエラーログを確認してください。