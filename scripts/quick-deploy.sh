#!/bin/bash

# 最も簡単なデプロイ方法
# AWS CLIでSSM経由デプロイ（SSHは不要）

export AWS_PROFILE=xsyumeno
INSTANCE_ID="i-0f20a2a9e7d202c75"

echo "🚀 Quick Deploy using SSM (no SSH needed)"

aws ssm send-command \
    --instance-ids $INSTANCE_ID \
    --document-name "AWS-RunShellScript" \
    --parameters 'commands=["cd /var/www/html || sudo mkdir -p /var/www/html","sudo git clone https://github.com/Naoki-Takahashi0420/metore_system.git . 2>/dev/null || sudo git pull","sudo chown -R www-data:www-data /var/www/html","sudo systemctl restart nginx"]' \
    --output json

echo "✅ Deploy command sent!"
echo "Check status at: https://console.aws.amazon.com/systems-manager/run-command"