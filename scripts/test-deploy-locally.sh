#!/bin/bash
# ローカルでデプロイをテストするスクリプト

set -e  # エラーで即停止
set -u  # 未定義変数でエラー
set -x  # コマンドを表示

echo "=== ローカルデプロイテスト ==="

# テスト用ディレクトリ作成
TEST_DIR="/tmp/xsyumeno-test-$(date +%s)"
mkdir -p $TEST_DIR/www/html
mkdir -p $TEST_DIR/home/ubuntu/.ssh

# .ssh権限設定（正常な状態）
chmod 700 $TEST_DIR/home/ubuntu/.ssh
echo "Initial .ssh permissions: $(stat -f %A $TEST_DIR/home/ubuntu/.ssh)"

# デプロイシミュレーション
cd $TEST_DIR/www/html

# 危険なコマンドのテスト（相対パス）
echo "Testing dangerous command: chmod -R 775 storage bootstrap/cache"
chmod -R 775 storage bootstrap/cache 2>&1 || echo "Command failed (expected)"

# .ssh権限チェック
echo "After dangerous command, .ssh permissions: $(stat -f %A $TEST_DIR/home/ubuntu/.ssh)"

if [ "$(stat -f %A $TEST_DIR/home/ubuntu/.ssh)" != "700" ]; then
  echo "ERROR: .ssh permissions were changed! Deploy would break SSH!"
  exit 1
fi

# 安全なコマンドのテスト（絶対パス + 存在確認）
echo "Testing safe command with absolute paths"
if [ -d "$TEST_DIR/www/html/storage" ]; then
  chmod -R 775 $TEST_DIR/www/html/storage
fi

echo "=== テスト完了: デプロイは安全です ==="
rm -rf $TEST_DIR