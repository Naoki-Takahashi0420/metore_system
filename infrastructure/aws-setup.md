# AWS EC2 + SNS インフラ構築ガイド

## 📋 必要なAWSリソース

### 1. IAM設定

#### GitHub Actions用IAMユーザー
```bash
# IAMユーザー作成
aws iam create-user --user-name github-actions-xsyumeno

# 必要なポリシー作成
cat << EOF > github-actions-policy.json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": [
                "arn:aws:s3:::xsyumeno-deployments/*"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "ssm:SendCommand",
                "ssm:GetCommandInvocation"
            ],
            "Resource": [
                "arn:aws:ec2:*:*:instance/*",
                "arn:aws:ssm:*:*:document/AWS-RunShellScript"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "sns:Publish"
            ],
            "Resource": "arn:aws:sns:*:*:xsyumeno-notifications"
        }
    ]
}
EOF

aws iam create-policy \
    --policy-name GitHubActionsXsyumenoPolicy \
    --policy-document file://github-actions-policy.json

aws iam attach-user-policy \
    --user-name github-actions-xsyumeno \
    --policy-arn arn:aws:iam::YOUR_ACCOUNT_ID:policy/GitHubActionsXsyumenoPolicy

# アクセスキー作成
aws iam create-access-key --user-name github-actions-xsyumeno
```

### 2. S3バケット作成

```bash
# デプロイメント用S3バケット
aws s3 mb s3://xsyumeno-deployments-$(date +%s)

# バケットポリシー設定
cat << EOF > s3-bucket-policy.json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::YOUR_ACCOUNT_ID:user/github-actions-xsyumeno"
            },
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::YOUR_BUCKET_NAME/*"
        }
    ]
}
EOF
```

### 3. EC2インスタンス

#### 推奨スペック
- **インスタンスタイプ**: t3.medium (2 vCPU, 4GB RAM)
- **OS**: Ubuntu 22.04 LTS
- **ストレージ**: 20GB gp3
- **セキュリティグループ**: HTTP(80), HTTPS(443), SSH(22)

#### EC2作成コマンド
```bash
# キーペア作成
aws ec2 create-key-pair --key-name xsyumeno-key --query 'KeyMaterial' --output text > xsyumeno-key.pem
chmod 400 xsyumeno-key.pem

# セキュリティグループ作成
aws ec2 create-security-group \
    --group-name xsyumeno-web \
    --description "Xsyumeno web application security group"

# インバウンドルール追加
aws ec2 authorize-security-group-ingress \
    --group-name xsyumeno-web \
    --protocol tcp \
    --port 80 \
    --cidr 0.0.0.0/0

aws ec2 authorize-security-group-ingress \
    --group-name xsyumeno-web \
    --protocol tcp \
    --port 443 \
    --cidr 0.0.0.0/0

aws ec2 authorize-security-group-ingress \
    --group-name xsyumeno-web \
    --protocol tcp \
    --port 22 \
    --cidr 0.0.0.0/0
```

#### IAMロール（EC2用）
```bash
cat << EOF > ec2-trust-policy.json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF

aws iam create-role \
    --role-name XsyumenoEC2Role \
    --assume-role-policy-document file://ec2-trust-policy.json

# 必要なポリシーをアタッチ
aws iam attach-role-policy \
    --role-name XsyumenoEC2Role \
    --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore

aws iam attach-role-policy \
    --role-name XsyumenoEC2Role \
    --policy-arn arn:aws:iam::aws:policy/AmazonS3ReadOnlyAccess

# インスタンスプロファイル作成
aws iam create-instance-profile --instance-profile-name XsyumenoEC2Profile
aws iam add-role-to-instance-profile \
    --instance-profile-name XsyumenoEC2Profile \
    --role-name XsyumenoEC2Role
```

### 4. SNS設定

```bash
# SNSトピック作成
aws sns create-topic --name xsyumeno-notifications

# SMS送信用の電話番号登録（手動）
aws sns subscribe \
    --topic-arn arn:aws:sns:ap-northeast-1:YOUR_ACCOUNT_ID:xsyumeno-notifications \
    --protocol sms \
    --notification-endpoint +819012345678
```

### 5. CloudWatch設定（オプション）

```bash
# ログストリーム作成
aws logs create-log-group --log-group-name xsyumeno-application
aws logs create-log-stream \
    --log-group-name xsyumeno-application \
    --log-stream-name web-server
```

## 🔧 GitHub Secrets設定

Repository → Settings → Secrets and variables → Actions

```
AWS_ACCESS_KEY_ID: [GitHubActions用IAMユーザーのアクセスキー]
AWS_SECRET_ACCESS_KEY: [GitHubActions用IAMユーザーのシークレットキー]
AWS_REGION: ap-northeast-1
S3_BUCKET: [S3バケット名]
EC2_INSTANCE_ID: [EC2インスタンスID]
EC2_PUBLIC_IP: [EC2パブリックIP]
SNS_TOPIC_ARN: [SNSトピックARN]
```

## 📱 SMS通知設定例

### Laravel側の設定
```php
// config/services.php
'sns' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('SNS_REGION', 'ap-northeast-1'),
],
```

### 通知サービス作成
```php
// app/Services/SmsNotificationService.php
<?php
namespace App\Services;

use Aws\Sns\SnsClient;

class SmsNotificationService
{
    private $snsClient;
    
    public function __construct()
    {
        $this->snsClient = new SnsClient([
            'version' => 'latest',
            'region' => config('services.sns.region'),
            'credentials' => [
                'key' => config('services.sns.key'),
                'secret' => config('services.sns.secret'),
            ],
        ]);
    }
    
    public function sendReservationConfirmation($phoneNumber, $reservation)
    {
        $message = "【Xsyumeno】予約確定\n";
        $message .= "日時: {$reservation->reservation_date} {$reservation->start_time}\n";
        $message .= "メニュー: {$reservation->menu->name}\n";
        $message .= "予約番号: {$reservation->reservation_number}";
        
        return $this->snsClient->publish([
            'Message' => $message,
            'PhoneNumber' => $this->formatPhoneNumber($phoneNumber),
            'MessageAttributes' => [
                'AWS.SNS.SMS.SMSType' => [
                    'DataType' => 'String',
                    'StringValue' => 'Transactional'
                ]
            ]
        ]);
    }
    
    private function formatPhoneNumber($phone)
    {
        // 日本の電話番号を国際形式に変換
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            return '+81' . substr($phone, 1);
        }
        return '+81' . $phone;
    }
}
```

## 🚀 初回デプロイ手順

1. **EC2インスタンス起動**
2. **セットアップスクリプト実行**
   ```bash
   scp -i xsyumeno-key.pem scripts/ec2-setup.sh ubuntu@EC2_IP:~
   ssh -i xsyumeno-key.pem ubuntu@EC2_IP
   chmod +x ec2-setup.sh
   ./ec2-setup.sh
   ```

3. **GitHub Secrets設定**
4. **最初のデプロイ実行**
   - mainブランチにプッシュするとCI/CDが開始
   
## 💰 概算コスト（東京リージョン）

- **EC2 t3.medium**: 約 $30/月
- **S3ストレージ**: 約 $1/月
- **SNS SMS**: 約 ¥7/件
- **合計**: 約 $35/月 + SMS使用料

## 🔒 セキュリティ考慮事項

1. **定期的なパッケージ更新**
2. **SSL証明書の設定**（Let's Encrypt推奨）
3. **ファイアウォール設定**
4. **アクセスキーのローテーション**
5. **ログ監視**
6. **バックアップ設定**

## 📞 SMS送信制限

- **日本**: 1日あたり200件
- **料金**: 約¥7/件
- **文字制限**: 160文字（全角70文字）

この構成でコスト効率的で拡張可能なCI/CD環境が構築できます！