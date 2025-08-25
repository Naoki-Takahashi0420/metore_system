#!/bin/bash

echo "Creating fresh EC2 instance for Xsyumeno..."

# VPCとサブネットの情報を取得
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=tag:Name,Values=xsyumeno-vpc" --query "Vpcs[0].VpcId" --output text)
SUBNET_ID=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" "Name=tag:Name,Values=xsyumeno-public-subnet" --query "Subnets[0].SubnetId" --output text)
SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=xsyumeno-ec2-sg" --query "SecurityGroups[0].GroupId" --output text)

# 既存のキーペアを使用
KEY_NAME="xsyumeno-ec2-key"

# 新しいEC2インスタンスを作成
echo "Creating new EC2 instance..."
INSTANCE_ID=$(aws ec2 run-instances \
    --image-id ami-0d88b56ff2c65082e \
    --instance-type t2.micro \
    --key-name $KEY_NAME \
    --subnet-id $SUBNET_ID \
    --security-group-ids $SG_ID \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=xsyumeno-web}]" \
    --user-data file:///Applications/MAMP/htdocs/Xsyumeno-main/scripts/userdata.sh \
    --query "Instances[0].InstanceId" \
    --output text)

echo "Instance ID: $INSTANCE_ID"

# インスタンスが起動するまで待つ
echo "Waiting for instance to be running..."
aws ec2 wait instance-running --instance-ids $INSTANCE_ID

# パブリックIPを取得
PUBLIC_IP=$(aws ec2 describe-instances \
    --instance-ids $INSTANCE_ID \
    --query "Reservations[0].Instances[0].PublicIpAddress" \
    --output text)

echo "Instance created successfully!"
echo "Public IP: $PUBLIC_IP"

# GitHub Secretsを更新
echo "Updating GitHub Secrets..."
gh secret set EC2_HOST --body "$PUBLIC_IP"

echo ""
echo "====================================="
echo "EC2 instance created successfully!"
echo "Public IP: $PUBLIC_IP"
echo "====================================="
echo ""
echo "Wait 3-5 minutes for initialization, then access:"
echo "http://$PUBLIC_IP"