#!/bin/bash
set -e

echo "🚀 Creating Simple and Correct EC2 Instance"
echo "Using AWS Profile: xsyumeno"

export AWS_PROFILE=xsyumeno

# Verify correct account
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
if [ "$ACCOUNT_ID" != "273021981629" ]; then
    echo "❌ Wrong AWS account! Expected 273021981629, got $ACCOUNT_ID"
    exit 1
fi
echo "✅ Correct AWS Account: $ACCOUNT_ID"

# Use existing VPC or create new one
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=tag:Name,Values=xsyumeno-vpc" --query "Vpcs[0].VpcId" --output text 2>/dev/null)
if [ "$VPC_ID" == "None" ] || [ -z "$VPC_ID" ]; then
    echo "Creating new VPC..."
    VPC_ID=$(aws ec2 create-vpc --cidr-block 10.0.0.0/16 --query 'Vpc.VpcId' --output text)
    aws ec2 create-tags --resources $VPC_ID --tags Key=Name,Value=xsyumeno-vpc
    aws ec2 modify-vpc-attribute --vpc-id $VPC_ID --enable-dns-hostnames
fi
echo "VPC: $VPC_ID"

# Create or get Internet Gateway
IGW_ID=$(aws ec2 describe-internet-gateways --filters "Name=attachment.vpc-id,Values=$VPC_ID" --query "InternetGateways[0].InternetGatewayId" --output text 2>/dev/null)
if [ "$IGW_ID" == "None" ] || [ -z "$IGW_ID" ]; then
    echo "Creating Internet Gateway..."
    IGW_ID=$(aws ec2 create-internet-gateway --query 'InternetGateway.InternetGatewayId' --output text)
    aws ec2 attach-internet-gateway --vpc-id $VPC_ID --internet-gateway-id $IGW_ID
fi
echo "Internet Gateway: $IGW_ID"

# Create Public Subnet
SUBNET_ID=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" "Name=tag:Name,Values=xsyumeno-public" --query "Subnets[0].SubnetId" --output text 2>/dev/null)
if [ "$SUBNET_ID" == "None" ] || [ -z "$SUBNET_ID" ]; then
    echo "Creating Public Subnet..."
    SUBNET_ID=$(aws ec2 create-subnet --vpc-id $VPC_ID --cidr-block 10.0.10.0/24 --query 'Subnet.SubnetId' --output text)
    aws ec2 create-tags --resources $SUBNET_ID --tags Key=Name,Value=xsyumeno-public
    aws ec2 modify-subnet-attribute --subnet-id $SUBNET_ID --map-public-ip-on-launch
fi
echo "Subnet: $SUBNET_ID"

# Create Route Table
RT_ID=$(aws ec2 describe-route-tables --filters "Name=vpc-id,Values=$VPC_ID" "Name=tag:Name,Values=xsyumeno-public-rt" --query "RouteTables[0].RouteTableId" --output text 2>/dev/null)
if [ "$RT_ID" == "None" ] || [ -z "$RT_ID" ]; then
    echo "Creating Route Table..."
    RT_ID=$(aws ec2 create-route-table --vpc-id $VPC_ID --query 'RouteTable.RouteTableId' --output text)
    aws ec2 create-tags --resources $RT_ID --tags Key=Name,Value=xsyumeno-public-rt
    aws ec2 create-route --route-table-id $RT_ID --destination-cidr-block 0.0.0.0/0 --gateway-id $IGW_ID
    aws ec2 associate-route-table --route-table-id $RT_ID --subnet-id $SUBNET_ID
fi
echo "Route Table: $RT_ID"

# Create Security Group
SG_ID=$(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC_ID" "Name=group-name,Values=xsyumeno-simple-sg" --query "SecurityGroups[0].GroupId" --output text 2>/dev/null)
if [ "$SG_ID" == "None" ] || [ -z "$SG_ID" ]; then
    echo "Creating Security Group..."
    SG_ID=$(aws ec2 create-security-group --group-name xsyumeno-simple-sg --description "Simple SSH and Web access" --vpc-id $VPC_ID --query 'GroupId' --output text)
    aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 22 --cidr 0.0.0.0/0
    aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 80 --cidr 0.0.0.0/0
    aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 443 --cidr 0.0.0.0/0
fi
echo "Security Group: $SG_ID"

# Create Key Pair
KEY_NAME="xsyumeno-simple-key"
if ! aws ec2 describe-key-pairs --key-names $KEY_NAME &>/dev/null; then
    echo "Creating new key pair..."
    aws ec2 create-key-pair --key-name $KEY_NAME --query 'KeyMaterial' --output text > ~/.ssh/$KEY_NAME.pem
    chmod 400 ~/.ssh/$KEY_NAME.pem
else
    echo "Using existing key pair: $KEY_NAME"
fi

# Get latest Ubuntu AMI
AMI_ID=$(aws ec2 describe-images \
    --owners 099720109477 \
    --filters "Name=name,Values=ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*" \
    --query 'Images[0].ImageId' \
    --output text)
echo "AMI: $AMI_ID"

# Create User Data Script
cat > /tmp/userdata.sh << 'USERDATA'
#!/bin/bash
apt-get update
apt-get install -y nginx php8.1-fpm php8.1-cli php8.1-common php8.1-mysql php8.1-xml php8.1-curl git

# Configure Nginx
cat > /etc/nginx/sites-available/default << 'NGINX'
server {
    listen 80 default_server;
    root /var/www/html;
    index index.php index.html;
    server_name _;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
NGINX

systemctl restart nginx
systemctl restart php8.1-fpm

# Create test page
echo "<?php phpinfo(); ?>" > /var/www/html/index.php
chown -R www-data:www-data /var/www/html
USERDATA

# Launch EC2 Instance
echo "Launching EC2 Instance..."
INSTANCE_ID=$(aws ec2 run-instances \
    --image-id $AMI_ID \
    --instance-type t2.micro \
    --key-name $KEY_NAME \
    --subnet-id $SUBNET_ID \
    --security-group-ids $SG_ID \
    --user-data file:///tmp/userdata.sh \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=xsyumeno-simple}]" \
    --query 'Instances[0].InstanceId' \
    --output text)

echo "Instance created: $INSTANCE_ID"
echo "Waiting for instance to be running..."
aws ec2 wait instance-running --instance-ids $INSTANCE_ID

# Get Public IP
PUBLIC_IP=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)

echo ""
echo "========================================="
echo "✅ EC2 Instance Created Successfully!"
echo "========================================="
echo "Instance ID: $INSTANCE_ID"
echo "Public IP: $PUBLIC_IP"
echo "SSH Key: ~/.ssh/$KEY_NAME.pem"
echo ""
echo "Test SSH connection:"
echo "ssh -i ~/.ssh/$KEY_NAME.pem ubuntu@$PUBLIC_IP"
echo ""
echo "Web URL: http://$PUBLIC_IP"
echo "========================================="

# Update GitHub Secrets
echo "Updating GitHub Secrets..."
gh secret set EC2_HOST --body "$PUBLIC_IP"
gh secret set EC2_USER --body "ubuntu"

# Save the private key to GitHub Secrets
cat ~/.ssh/$KEY_NAME.pem | gh secret set EC2_SSH_KEY

echo "✅ GitHub Secrets updated!"