#!/bin/bash

echo "Creating complete infrastructure for Xsyumeno..."

# 1. VPCを作成
echo "Creating VPC..."
VPC_ID=$(aws ec2 create-vpc \
    --cidr-block 10.0.0.0/16 \
    --tag-specifications "ResourceType=vpc,Tags=[{Key=Name,Value=xsyumeno-vpc}]" \
    --query "Vpc.VpcId" \
    --output text)
echo "VPC created: $VPC_ID"

# DNSサポートを有効化
aws ec2 modify-vpc-attribute --vpc-id $VPC_ID --enable-dns-support
aws ec2 modify-vpc-attribute --vpc-id $VPC_ID --enable-dns-hostnames

# 2. インターネットゲートウェイを作成
echo "Creating Internet Gateway..."
IGW_ID=$(aws ec2 create-internet-gateway \
    --tag-specifications "ResourceType=internet-gateway,Tags=[{Key=Name,Value=xsyumeno-igw}]" \
    --query "InternetGateway.InternetGatewayId" \
    --output text)
echo "Internet Gateway created: $IGW_ID"

# VPCにアタッチ
aws ec2 attach-internet-gateway --vpc-id $VPC_ID --internet-gateway-id $IGW_ID

# 3. パブリックサブネットを作成
echo "Creating Public Subnet..."
SUBNET_ID=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block 10.0.1.0/24 \
    --availability-zone ap-northeast-1a \
    --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=xsyumeno-public-subnet}]" \
    --query "Subnet.SubnetId" \
    --output text)
echo "Subnet created: $SUBNET_ID"

# 自動パブリックIP割り当てを有効化
aws ec2 modify-subnet-attribute --subnet-id $SUBNET_ID --map-public-ip-on-launch

# 4. ルートテーブルを作成
echo "Creating Route Table..."
RT_ID=$(aws ec2 create-route-table \
    --vpc-id $VPC_ID \
    --tag-specifications "ResourceType=route-table,Tags=[{Key=Name,Value=xsyumeno-public-rt}]" \
    --query "RouteTable.RouteTableId" \
    --output text)
echo "Route Table created: $RT_ID"

# インターネットゲートウェイへのルートを追加
aws ec2 create-route \
    --route-table-id $RT_ID \
    --destination-cidr-block 0.0.0.0/0 \
    --gateway-id $IGW_ID

# サブネットに関連付け
aws ec2 associate-route-table --route-table-id $RT_ID --subnet-id $SUBNET_ID

# 5. セキュリティグループを作成
echo "Creating Security Group..."
SG_ID=$(aws ec2 create-security-group \
    --group-name xsyumeno-ec2-sg \
    --description "Security group for Xsyumeno EC2 instances" \
    --vpc-id $VPC_ID \
    --query "GroupId" \
    --output text)
echo "Security Group created: $SG_ID"

# インバウンドルールを追加
aws ec2 authorize-security-group-ingress \
    --group-id $SG_ID \
    --protocol tcp \
    --port 22 \
    --cidr 0.0.0.0/0

aws ec2 authorize-security-group-ingress \
    --group-id $SG_ID \
    --protocol tcp \
    --port 80 \
    --cidr 0.0.0.0/0

aws ec2 authorize-security-group-ingress \
    --group-id $SG_ID \
    --protocol tcp \
    --port 443 \
    --cidr 0.0.0.0/0

echo ""
echo "====================================="
echo "Infrastructure created successfully!"
echo "VPC ID: $VPC_ID"
echo "Subnet ID: $SUBNET_ID"
echo "Security Group ID: $SG_ID"
echo "====================================="
echo ""
echo "Run create-fresh-ec2.sh to create EC2 instance"