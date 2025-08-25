#!/bin/bash

echo "=== Creating Working EC2 Instance ==="

# Terminate old instance
echo "Terminating old instance..."
aws ec2 terminate-instances --instance-ids i-0c56fc59924cf9e5e --region ap-northeast-1

# Create new instance with proper initialization
echo "Creating new EC2 instance..."
INSTANCE_ID=$(aws ec2 run-instances \
  --image-id ami-02dc59944bc69e802 \
  --instance-type t2.micro \
  --key-name xsyumeno-ec2-key \
  --security-group-ids sg-0e93f55d4076d195e \
  --subnet-id subnet-0c392efc262ed10d8 \
  --region ap-northeast-1 \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=xsyumeno-final}]' \
  --query 'Instances[0].InstanceId' \
  --output text)

echo "Instance created: $INSTANCE_ID"

# Wait for instance
echo "Waiting for instance to start (this takes 1-2 minutes)..."
aws ec2 wait instance-running --instance-ids $INSTANCE_ID --region ap-northeast-1

# Get IP
PUBLIC_IP=$(aws ec2 describe-instances \
  --instance-ids $INSTANCE_ID \
  --region ap-northeast-1 \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text)

echo "========================================="
echo "New EC2 Instance Ready!"
echo "Instance ID: $INSTANCE_ID"
echo "Public IP: $PUBLIC_IP"
echo "========================================="

# Wait for SSH to be ready
echo "Waiting for SSH to be ready..."
for i in {1..30}; do
  if ssh -o ConnectTimeout=3 -o StrictHostKeyChecking=no -i ~/.ssh/xsyumeno-ec2-key.pem ubuntu@$PUBLIC_IP "echo 'SSH is ready'" 2>/dev/null; then
    echo "SSH connection successful!"
    break
  fi
  echo "Waiting... (attempt $i/30)"
  sleep 10
done

# Setup server
echo "Setting up server..."
ssh -o StrictHostKeyChecking=no -i ~/.ssh/xsyumeno-ec2-key.pem ubuntu@$PUBLIC_IP << 'SETUP'
# Update system
sudo apt-get update

# Install required packages
sudo apt-get install -y nginx php8.3-fpm php8.3-mysql php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip git unzip

# Install composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Start services
sudo systemctl enable nginx php8.3-fpm
sudo systemctl start nginx php8.3-fpm

echo "Server setup complete!"
SETUP

echo "========================================="
echo "EC2 READY FOR DEPLOYMENT!"
echo "IP Address: $PUBLIC_IP"
echo "Update deploy.yml with this IP!"
echo "========================================="