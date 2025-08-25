#!/bin/bash

# このスクリプトをEC2 Instance Connect経由で実行
# 通常のSSH接続を有効化します

echo "🔧 Enabling normal SSH access..."

# 1. SSHDの設定をバックアップ
sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup

# 2. EC2 Instance Connectの設定を確認
echo "Checking EC2 Instance Connect configuration..."
sudo grep -r "AuthorizedKeysCommand" /etc/ssh/

# 3. 通常のSSH設定を確実にする
sudo tee /etc/ssh/sshd_config.d/99-enable-normal-ssh.conf > /dev/null << 'EOF'
# Enable normal SSH access
PubkeyAuthentication yes
PasswordAuthentication no
PermitRootLogin no
# Ensure we're listening on all interfaces
ListenAddress 0.0.0.0
ListenAddress ::
EOF

# 4. 公開鍵が正しく設定されているか確認
echo "Current authorized_keys:"
cat ~/.ssh/authorized_keys

# 5. SSHDの設定をテスト
sudo sshd -t && echo "✅ SSH config is valid"

# 6. SSHDを再起動
sudo systemctl restart sshd

# 7. ファイアウォールを確認
sudo iptables -L INPUT -n | grep 22

echo "✅ SSH should now accept external connections!"
echo "Test with: ssh -i your-key.pem ubuntu@$(curl -s http://169.254.169.254/latest/meta-data/public-ipv4)"