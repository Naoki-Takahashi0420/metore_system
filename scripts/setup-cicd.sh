#!/bin/bash

echo "=== CI/CD Setup Script ==="

# 1. 新しいSSHキーペアを生成
echo "Generating new SSH key pair for CI/CD..."
ssh-keygen -t rsa -b 4096 -f ~/.ssh/xsyumeno-cicd -N "" -C "github-actions@xsyumeno"

# 2. 公開鍵を表示（EC2に追加用）
echo ""
echo "===== PUBLIC KEY (Add this to EC2) ====="
cat ~/.ssh/xsyumeno-cicd.pub
echo "========================================="

# 3. 秘密鍵を表示（GitHub Secretsに追加用）
echo ""
echo "===== PRIVATE KEY (Add to GitHub Secrets as EC2_SSH_KEY) ====="
cat ~/.ssh/xsyumeno-cicd
echo "========================================="

# 4. GitHub Secretsに自動設定（オプション）
echo ""
read -p "Do you want to automatically add to GitHub Secrets? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    gh secret set EC2_SSH_KEY < ~/.ssh/xsyumeno-cicd
    gh secret set EC2_HOST --body "13.231.41.238"
    gh secret set EC2_USER --body "ubuntu"
    echo "✅ GitHub Secrets updated!"
fi

echo ""
echo "Next steps:"
echo "1. Add the public key to EC2's ~/.ssh/authorized_keys"
echo "2. Verify GitHub Secrets are set correctly"
echo "3. Run the deployment workflow"