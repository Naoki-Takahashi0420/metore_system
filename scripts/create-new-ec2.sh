#!/bin/bash
# 新しいEC2インスタンスを安全に作成

set -e
set -u

export AWS_PROFILE=xsyumeno

echo "=== 新EC2インスタンス作成 ==="

# 1. 新しいキーペアを作成
KEY_NAME="xsyumeno-$(date +%Y%m%d-%H%M%S)"
aws ec2 create-key-pair --key-name $KEY_NAME \
  --query 'KeyMaterial' --output text > ~/.ssh/${KEY_NAME}.pem
chmod 600 ~/.ssh/${KEY_NAME}.pem
echo "Created key: $KEY_NAME"

# 2. セキュリティグループ（既存を使用または新規作成）
SG_ID=$(aws ec2 describe-security-groups \
  --filters "Name=group-name,Values=xsyumeno-web-sg" \
  --query 'SecurityGroups[0].GroupId' --output text 2>/dev/null || echo "")

if [ -z "$SG_ID" ] || [ "$SG_ID" = "None" ]; then
  echo "Creating security group..."
  SG_ID=$(aws ec2 create-security-group \
    --group-name xsyumeno-web-sg \
    --description "Xsyumeno Web Server Security Group" \
    --query 'GroupId' --output text)
  
  # SSH, HTTP, HTTPS許可
  aws ec2 authorize-security-group-ingress --group-id $SG_ID \
    --protocol tcp --port 22 --cidr 0.0.0.0/0
  aws ec2 authorize-security-group-ingress --group-id $SG_ID \
    --protocol tcp --port 80 --cidr 0.0.0.0/0
  aws ec2 authorize-security-group-ingress --group-id $SG_ID \
    --protocol tcp --port 443 --cidr 0.0.0.0/0
fi

echo "Security Group: $SG_ID"

# 3. UserDataスクリプト（初期セットアップ）
cat > /tmp/userdata.sh << 'USERDATA'
#!/bin/bash
apt-get update
apt-get install -y nginx php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip git

# Nginx設定
cat > /etc/nginx/sites-available/default << 'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    root /var/www/html/public;
    index index.php index.html;
    
    server_name _;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
NGINX

systemctl restart nginx php8.1-fpm

# Composer インストール
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# アプリケーション準備
mkdir -p /var/www/html
cd /var/www/html
echo "<?php phpinfo();" > public/index.php
chown -R www-data:www-data /var/www/html
USERDATA

# 4. EC2インスタンス起動
INSTANCE_ID=$(aws ec2 run-instances \
  --image-id ami-0d03c6e00b9077e44 \
  --instance-type t2.micro \
  --key-name $KEY_NAME \
  --security-group-ids $SG_ID \
  --user-data file:///tmp/userdata.sh \
  --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=xsyumeno-production}]" \
  --query 'Instances[0].InstanceId' \
  --output text)

echo "Instance created: $INSTANCE_ID"

# 5. インスタンス起動待ち
echo "Waiting for instance to start..."
aws ec2 wait instance-running --instance-ids $INSTANCE_ID

# 6. パブリックIP取得
PUBLIC_IP=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID \
  --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)

echo "==================================="
echo "新EC2インスタンス作成完了！"
echo "==================================="
echo "Instance ID: $INSTANCE_ID"
echo "Public IP: $PUBLIC_IP"
echo "SSH Key: ~/.ssh/${KEY_NAME}.pem"
echo ""
echo "SSH接続:"
echo "ssh -i ~/.ssh/${KEY_NAME}.pem ubuntu@$PUBLIC_IP"
echo ""
echo "次のステップ:"
echo "1. ワークフローのEC2_HOST変数を $PUBLIC_IP に更新"
echo "2. GitHub Secretsに新しいキーを登録"
echo "3. デプロイ実行"