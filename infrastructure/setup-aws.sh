#!/bin/bash

# AWS Infrastructure Setup Script
# Account ID: 273021981629
# Region: ap-northeast-1 (Tokyo)

set -e

echo "AWS Infrastructure Setup for Laravel Application"
echo "================================================"
echo "Account ID: 273021981629"
echo "Region: ap-northeast-1"
echo ""

# Variables
REGION="ap-northeast-1"
ACCOUNT_ID="273021981629"
PROJECT_NAME="xsyumeno"
ENVIRONMENT="production"

# EC2 Configuration (t3.micro for minimal cost)
INSTANCE_TYPE="t3.micro"
AMI_ID="ami-0d52744d6551d851e" # Amazon Linux 2023 in ap-northeast-1
KEY_NAME="${PROJECT_NAME}-key"

# RDS Configuration (t3.micro for minimal cost)
DB_INSTANCE_CLASS="db.t3.micro"
DB_ENGINE="mysql"
DB_ENGINE_VERSION="8.0"
DB_NAME="${PROJECT_NAME}db"
DB_USERNAME="admin"
DB_ALLOCATED_STORAGE="20"

# VPC and Network
VPC_CIDR="10.0.0.0/16"
PUBLIC_SUBNET_CIDR="10.0.1.0/24"
PRIVATE_SUBNET_CIDR="10.0.2.0/24"

echo "1. Creating VPC and Network Infrastructure..."
echo "----------------------------------------------"

# Create VPC
VPC_ID=$(aws ec2 create-vpc \
    --cidr-block $VPC_CIDR \
    --tag-specifications "ResourceType=vpc,Tags=[{Key=Name,Value=${PROJECT_NAME}-vpc}]" \
    --region $REGION \
    --query 'Vpc.VpcId' \
    --output text)

echo "Created VPC: $VPC_ID"

# Enable DNS hostnames
aws ec2 modify-vpc-attribute \
    --vpc-id $VPC_ID \
    --enable-dns-hostnames \
    --region $REGION

# Create Internet Gateway
IGW_ID=$(aws ec2 create-internet-gateway \
    --tag-specifications "ResourceType=internet-gateway,Tags=[{Key=Name,Value=${PROJECT_NAME}-igw}]" \
    --region $REGION \
    --query 'InternetGateway.InternetGatewayId' \
    --output text)

aws ec2 attach-internet-gateway \
    --vpc-id $VPC_ID \
    --internet-gateway-id $IGW_ID \
    --region $REGION

echo "Created Internet Gateway: $IGW_ID"

# Create Public Subnet
PUBLIC_SUBNET_ID=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block $PUBLIC_SUBNET_CIDR \
    --availability-zone "${REGION}a" \
    --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${PROJECT_NAME}-public-subnet}]" \
    --region $REGION \
    --query 'Subnet.SubnetId' \
    --output text)

echo "Created Public Subnet: $PUBLIC_SUBNET_ID"

# Create Private Subnet for RDS
PRIVATE_SUBNET_ID=$(aws ec2 create-subnet \
    --vpc-id $VPC_ID \
    --cidr-block $PRIVATE_SUBNET_CIDR \
    --availability-zone "${REGION}c" \
    --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${PROJECT_NAME}-private-subnet}]" \
    --region $REGION \
    --query 'Subnet.SubnetId' \
    --output text)

echo "Created Private Subnet: $PRIVATE_SUBNET_ID"

# Create Route Table
ROUTE_TABLE_ID=$(aws ec2 create-route-table \
    --vpc-id $VPC_ID \
    --tag-specifications "ResourceType=route-table,Tags=[{Key=Name,Value=${PROJECT_NAME}-public-rt}]" \
    --region $REGION \
    --query 'RouteTable.RouteTableId' \
    --output text)

# Add route to Internet Gateway
aws ec2 create-route \
    --route-table-id $ROUTE_TABLE_ID \
    --destination-cidr-block 0.0.0.0/0 \
    --gateway-id $IGW_ID \
    --region $REGION

# Associate route table with public subnet
aws ec2 associate-route-table \
    --route-table-id $ROUTE_TABLE_ID \
    --subnet-id $PUBLIC_SUBNET_ID \
    --region $REGION

echo "2. Creating Security Groups..."
echo "-------------------------------"

# Security Group for EC2
EC2_SG_ID=$(aws ec2 create-security-group \
    --group-name "${PROJECT_NAME}-ec2-sg" \
    --description "Security group for EC2 instance" \
    --vpc-id $VPC_ID \
    --region $REGION \
    --query 'GroupId' \
    --output text)

# Allow SSH
aws ec2 authorize-security-group-ingress \
    --group-id $EC2_SG_ID \
    --protocol tcp \
    --port 22 \
    --cidr 0.0.0.0/0 \
    --region $REGION

# Allow HTTP
aws ec2 authorize-security-group-ingress \
    --group-id $EC2_SG_ID \
    --protocol tcp \
    --port 80 \
    --cidr 0.0.0.0/0 \
    --region $REGION

# Allow HTTPS
aws ec2 authorize-security-group-ingress \
    --group-id $EC2_SG_ID \
    --protocol tcp \
    --port 443 \
    --cidr 0.0.0.0/0 \
    --region $REGION

echo "Created EC2 Security Group: $EC2_SG_ID"

# Security Group for RDS
RDS_SG_ID=$(aws ec2 create-security-group \
    --group-name "${PROJECT_NAME}-rds-sg" \
    --description "Security group for RDS instance" \
    --vpc-id $VPC_ID \
    --region $REGION \
    --query 'GroupId' \
    --output text)

# Allow MySQL from EC2 Security Group
aws ec2 authorize-security-group-ingress \
    --group-id $RDS_SG_ID \
    --protocol tcp \
    --port 3306 \
    --source-group $EC2_SG_ID \
    --region $REGION

echo "Created RDS Security Group: $RDS_SG_ID"

echo "3. Creating EC2 Key Pair..."
echo "----------------------------"

# Create key pair
aws ec2 create-key-pair \
    --key-name $KEY_NAME \
    --region $REGION \
    --query 'KeyMaterial' \
    --output text > ${KEY_NAME}.pem

chmod 400 ${KEY_NAME}.pem
echo "Created Key Pair: ${KEY_NAME}.pem"

echo "4. Launching EC2 Instance..."
echo "-----------------------------"

# User data script for EC2 setup
cat > user-data.sh << 'USERDATA'
#!/bin/bash
yum update -y
yum install -y nginx php82 php82-fpm php82-mysqlnd php82-bcmath php82-xml php82-mbstring php82-gd php82-curl php82-zip git

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -sL https://rpm.nodesource.com/setup_18.x | bash -
yum install -y nodejs

# Configure PHP-FPM
sed -i 's/user = apache/user = nginx/g' /etc/php-fpm.d/www.conf
sed -i 's/group = apache/group = nginx/g' /etc/php-fpm.d/www.conf

# Start services
systemctl start php-fpm
systemctl enable php-fpm
systemctl start nginx
systemctl enable nginx

# Create deployment directory
mkdir -p /var/www/html
chown -R nginx:nginx /var/www/html
USERDATA

# Launch EC2 instance
INSTANCE_ID=$(aws ec2 run-instances \
    --image-id $AMI_ID \
    --instance-type $INSTANCE_TYPE \
    --key-name $KEY_NAME \
    --security-group-ids $EC2_SG_ID \
    --subnet-id $PUBLIC_SUBNET_ID \
    --associate-public-ip-address \
    --user-data file://user-data.sh \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=${PROJECT_NAME}-web}]" \
    --region $REGION \
    --query 'Instances[0].InstanceId' \
    --output text)

echo "Launched EC2 Instance: $INSTANCE_ID"

# Wait for instance to be running
echo "Waiting for instance to be running..."
aws ec2 wait instance-running --instance-ids $INSTANCE_ID --region $REGION

# Get public IP
PUBLIC_IP=$(aws ec2 describe-instances \
    --instance-ids $INSTANCE_ID \
    --region $REGION \
    --query 'Reservations[0].Instances[0].PublicIpAddress' \
    --output text)

echo "EC2 Public IP: $PUBLIC_IP"

echo "5. Creating RDS Database..."
echo "----------------------------"

# Generate random password for RDS
DB_PASSWORD=$(openssl rand -base64 32)

# Create DB Subnet Group
aws rds create-db-subnet-group \
    --db-subnet-group-name "${PROJECT_NAME}-db-subnet-group" \
    --db-subnet-group-description "Subnet group for RDS" \
    --subnet-ids $PUBLIC_SUBNET_ID $PRIVATE_SUBNET_ID \
    --region $REGION

# Create RDS instance
aws rds create-db-instance \
    --db-instance-identifier "${PROJECT_NAME}-db" \
    --db-instance-class $DB_INSTANCE_CLASS \
    --engine $DB_ENGINE \
    --engine-version $DB_ENGINE_VERSION \
    --master-username $DB_USERNAME \
    --master-user-password "$DB_PASSWORD" \
    --allocated-storage $DB_ALLOCATED_STORAGE \
    --db-name $DB_NAME \
    --vpc-security-group-ids $RDS_SG_ID \
    --db-subnet-group-name "${PROJECT_NAME}-db-subnet-group" \
    --backup-retention-period 7 \
    --publicly-accessible \
    --region $REGION

echo "Creating RDS instance... This will take several minutes."

echo "6. Setting up SNS for SMS..."
echo "-----------------------------"

# Create SNS topic for notifications
SNS_TOPIC_ARN=$(aws sns create-topic \
    --name "${PROJECT_NAME}-notifications" \
    --region $REGION \
    --query 'TopicArn' \
    --output text)

echo "Created SNS Topic: $SNS_TOPIC_ARN"

echo "7. Creating IAM User for CI/CD..."
echo "----------------------------------"

# Create IAM user for GitHub Actions
aws iam create-user --user-name "${PROJECT_NAME}-github-actions"

# Create and attach policy
cat > github-actions-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ec2:DescribeInstances",
                "ec2:DescribeSecurityGroups",
                "sns:Publish",
                "sns:ListTopics"
            ],
            "Resource": "*"
        }
    ]
}
EOF

POLICY_ARN=$(aws iam create-policy \
    --policy-name "${PROJECT_NAME}-github-actions-policy" \
    --policy-document file://github-actions-policy.json \
    --query 'Policy.Arn' \
    --output text)

aws iam attach-user-policy \
    --user-name "${PROJECT_NAME}-github-actions" \
    --policy-arn $POLICY_ARN

# Create access key
ACCESS_KEY_OUTPUT=$(aws iam create-access-key \
    --user-name "${PROJECT_NAME}-github-actions" \
    --query 'AccessKey.[AccessKeyId,SecretAccessKey]' \
    --output text)

ACCESS_KEY_ID=$(echo $ACCESS_KEY_OUTPUT | cut -d' ' -f1)
SECRET_ACCESS_KEY=$(echo $ACCESS_KEY_OUTPUT | cut -d' ' -f2)

echo ""
echo "================================================"
echo "AWS Infrastructure Setup Complete!"
echo "================================================"
echo ""
echo "IMPORTANT: Save these values for GitHub Secrets:"
echo "-------------------------------------------------"
echo "AWS_ACCESS_KEY_ID: $ACCESS_KEY_ID"
echo "AWS_SECRET_ACCESS_KEY: $SECRET_ACCESS_KEY"
echo "EC2_HOST: $PUBLIC_IP"
echo "EC2_USER: ec2-user"
echo "EC2_SSH_KEY: (contents of ${KEY_NAME}.pem)"
echo ""
echo "Database Information:"
echo "---------------------"
echo "DB_HOST: (will be available after RDS creation completes)"
echo "DB_DATABASE: $DB_NAME"
echo "DB_USERNAME: $DB_USERNAME"
echo "DB_PASSWORD: $DB_PASSWORD"
echo ""
echo "SNS Configuration:"
echo "------------------"
echo "AWS_SNS_REGION: $REGION"
echo "AWS_SNS_TOPIC_ARN: $SNS_TOPIC_ARN"
echo ""
echo "Estimated Monthly Cost:"
echo "-----------------------"
echo "EC2 t3.micro: ~$8-10/month"
echo "RDS t3.micro: ~$15-20/month"
echo "SNS: ~$0.50 per 1000 SMS (Japan)"
echo "Total: ~$25-35/month + SMS costs"
echo ""
echo "Next Steps:"
echo "-----------"
echo "1. Add the above secrets to your GitHub repository"
echo "2. Configure your .env.production file"
echo "3. Push to main branch to trigger deployment"
echo ""

# Clean up temporary files
rm -f user-data.sh github-actions-policy.json