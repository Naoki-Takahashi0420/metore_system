#!/bin/bash
set -e

echo "🚀 Creating EC2 with Normal SSH (EC2 Instance Connect Disabled)"
echo "Using AWS Profile: xsyumeno"

export AWS_PROFILE=xsyumeno

# Verify correct account
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
if [ "$ACCOUNT_ID" != "273021981629" ]; then
    echo "❌ Wrong AWS account! Expected 273021981629, got $ACCOUNT_ID"
    exit 1
fi
echo "✅ Correct AWS Account: $ACCOUNT_ID"

# Use default VPC
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=is-default,Values=true" --query "Vpcs[0].VpcId" --output text)
echo "Using Default VPC: $VPC_ID"

# Get default subnet
SUBNET_ID=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" "Name=default-for-az,Values=true" --query "Subnets[0].SubnetId" --output text)
echo "Using Default Subnet: $SUBNET_ID"

# Create or use security group
SG_ID=$(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC_ID" "Name=group-name,Values=xsyumeno-ssh-sg" --query "SecurityGroups[0].GroupId" --output text 2>/dev/null)
if [ "$SG_ID" == "None" ] || [ -z "$SG_ID" ]; then
    echo "Creating Security Group..."
    SG_ID=$(aws ec2 create-security-group --group-name xsyumeno-ssh-sg --description "Normal SSH access" --vpc-id $VPC_ID --query 'GroupId' --output text)
    aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 22 --cidr 0.0.0.0/0
    aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 80 --cidr 0.0.0.0/0
    aws ec2 authorize-security-group-ingress --group-id $SG_ID --protocol tcp --port 443 --cidr 0.0.0.0/0
fi
echo "Security Group: $SG_ID"

# Create Key Pair
KEY_NAME="xsyumeno-ssh-key"
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

# Create user data that DISABLES EC2 Instance Connect
cat > /tmp/userdata.sh << 'USERDATA'
#!/bin/bash

# DISABLE EC2 Instance Connect FIRST
echo "Disabling EC2 Instance Connect..."
systemctl stop ec2-instance-connect
systemctl disable ec2-instance-connect
apt-get remove -y ec2-instance-connect

# Remove EC2 Instance Connect SSH configuration
rm -f /usr/lib/systemd/system/ssh.service.d/ec2-instance-connect.conf
rm -f /lib/systemd/system/ssh.service.d/ec2-instance-connect.conf
rm -rf /usr/share/ec2-instance-connect/

# Configure normal SSH
cat > /etc/ssh/sshd_config.d/99-normal-ssh.conf << 'SSH'
PubkeyAuthentication yes
PasswordAuthentication no
PermitRootLogin no
Port 22
SSH

# Restart SSH service
systemctl daemon-reload
systemctl restart sshd

# Install web server
apt-get update
apt-get install -y nginx php8.1-fpm git

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
echo "<?php echo 'SSH-Enabled EC2 Working!'; phpinfo(); ?>" > /var/www/html/index.php
chown -R www-data:www-data /var/www/html

echo "✅ EC2 Instance Connect disabled, normal SSH enabled"
USERDATA

# Launch EC2 Instance
echo "Launching EC2 Instance with SSH enabled..."
INSTANCE_ID=$(aws ec2 run-instances \
    --image-id $AMI_ID \
    --instance-type t2.micro \
    --key-name $KEY_NAME \
    --subnet-id $SUBNET_ID \
    --security-group-ids $SG_ID \
    --associate-public-ip-address \
    --user-data file:///tmp/userdata.sh \
    --metadata-options "HttpTokens=optional,HttpPutResponseHopLimit=1,HttpEndpoint=enabled" \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=xsyumeno-ssh-enabled}]" \
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
echo "Wait 60 seconds for setup to complete, then test:"
echo "ssh -i ~/.ssh/$KEY_NAME.pem ubuntu@$PUBLIC_IP"
echo ""
echo "Web URL: http://$PUBLIC_IP"
echo "========================================="

# Update GitHub Secrets
echo "Updating GitHub Secrets..."
gh secret set EC2_HOST --body "$PUBLIC_IP"
gh secret set EC2_USER --body "ubuntu"
cat ~/.ssh/$KEY_NAME.pem | gh secret set EC2_SSH_KEY

echo "✅ GitHub Secrets updated!"
echo ""
echo "⏰ Waiting 60 seconds for EC2 setup to complete..."
sleep 60

# Test SSH connection
echo "Testing SSH connection..."
if ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -i ~/.ssh/$KEY_NAME.pem ubuntu@$PUBLIC_IP "echo '✅ SSH Connection Successful!'" 2>/dev/null; then
    echo "✅ SSH is working!"
else
    echo "⚠️ SSH not ready yet. Try again in a moment."
fi