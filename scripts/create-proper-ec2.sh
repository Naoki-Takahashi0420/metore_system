#!/bin/bash

echo "Creating properly configured EC2 for CI/CD..."

export AWS_PROFILE=xsyumeno

# Create new VPC with public subnet
VPC_ID=$(aws ec2 create-vpc --cidr-block 10.1.0.0/16 --query 'Vpc.VpcId' --output text)
aws ec2 modify-vpc-attribute --vpc-id $VPC_ID --enable-dns-hostnames
aws ec2 modify-vpc-attribute --vpc-id $VPC_ID --enable-dns-support

# Create Internet Gateway
IGW_ID=$(aws ec2 create-internet-gateway --query 'InternetGateway.InternetGatewayId' --output text)
aws ec2 attach-internet-gateway --vpc-id $VPC_ID --internet-gateway-id $IGW_ID

# Create PUBLIC subnet (properly configured)
SUBNET_ID=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block 10.1.1.0/24 \
    --availability-zone ap-northeast-1a \
    --query 'Subnet.SubnetId' \
    --output text)

# Enable auto-assign public IP
aws ec2 modify-subnet-attribute --subnet-id $SUBNET_ID --map-public-ip-on-launch

# Create route table with internet route
RT_ID=$(aws ec2 create-route-table --vpc-id $VPC_ID --query 'RouteTable.RouteTableId' --output text)
aws ec2 create-route --route-table-id $RT_ID --destination-cidr-block 0.0.0.0/0 --gateway-id $IGW_ID
aws ec2 associate-route-table --route-table-id $RT_ID --subnet-id $SUBNET_ID

# Create security group
SG_ID=$(aws ec2 create-security-group \
    --group-name xsyumeno-public-sg \
    --description "Public EC2 for CI/CD" \
    --vpc-id $VPC_ID \
    --query 'GroupId' \
    --output text)

# Allow SSH from anywhere (for CI/CD)
aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 22 --cidr 0.0.0.0/0
aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 80 --cidr 0.0.0.0/0
aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 443 --cidr 0.0.0.0/0

# Create key pair
aws ec2 create-key-pair --key-name xsyumeno-cicd-key --query 'KeyMaterial' --output text > ~/.ssh/xsyumeno-cicd-new.pem
chmod 400 ~/.ssh/xsyumeno-cicd-new.pem

# Launch EC2 in PUBLIC subnet
INSTANCE_ID=$(aws ec2 run-instances \
    --image-id ami-0d88b56ff2c65082e \
    --instance-type t2.micro \
    --key-name xsyumeno-cicd-key \
    --subnet-id $SUBNET_ID \
    --security-group-ids $SG_ID \
    --associate-public-ip-address \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=xsyumeno-public}]" \
    --user-data file://scripts/userdata.sh \
    --query 'Instances[0].InstanceId' \
    --output text)

echo "Waiting for instance..."
aws ec2 wait instance-running --instance-ids $INSTANCE_ID

PUBLIC_IP=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)

echo "✅ New PUBLIC EC2 created!"
echo "Instance ID: $INSTANCE_ID"
echo "Public IP: $PUBLIC_IP"
echo "This EC2 WILL accept SSH from GitHub Actions!"