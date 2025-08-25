#!/bin/bash

# Create EC2 instance with proper configuration
echo "Creating EC2 instance for Xsyumeno..."

# Launch EC2 instance
INSTANCE_ID=$(aws ec2 run-instances \
  --image-id ami-02dc59944bc69e802 \
  --instance-type t2.micro \
  --key-name xsyumeno-ec2-key \
  --security-group-ids sg-0e93f55d4076d195e \
  --subnet-id subnet-0c392efc262ed10d8 \
  --region ap-northeast-1 \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=xsyumeno-server}]' \
  --user-data '#!/bin/bash
apt-get update
apt-get install -y nginx php8.3-fpm php8.3-mysql php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip git unzip
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
systemctl enable nginx php8.3-fpm
systemctl start nginx php8.3-fpm' \
  --query 'Instances[0].InstanceId' \
  --output text)

echo "Instance created: $INSTANCE_ID"

# Wait for instance to be running
echo "Waiting for instance to start..."
aws ec2 wait instance-running --instance-ids $INSTANCE_ID --region ap-northeast-1

# Get public IP
PUBLIC_IP=$(aws ec2 describe-instances \
  --instance-ids $INSTANCE_ID \
  --region ap-northeast-1 \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text)

echo "========================================="
echo "EC2 Instance created successfully!"
echo "Instance ID: $INSTANCE_ID"
echo "Public IP: $PUBLIC_IP"
echo "========================================="
echo "Save this IP for the workflow!"