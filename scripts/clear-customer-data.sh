#!/bin/bash

# 本番環境の顧客データをクリアするスクリプト
echo "顧客データクリア開始..."

# 現在の顧客数を確認
echo "=== 削除前の顧客数 ==="
echo "SELECT COUNT(*) as customer_count FROM customers;" | sqlite3 database/database.sqlite

# 予約データを削除（外部キー制約のため先に削除）
echo "=== 予約データを削除中... ==="
echo "DELETE FROM reservations;" | sqlite3 database/database.sqlite

# 顧客関連データを削除
echo "=== 顧客関連データを削除中... ==="
echo "DELETE FROM customer_subscriptions;" | sqlite3 database/database.sqlite
echo "DELETE FROM customer_access_tokens;" | sqlite3 database/database.sqlite

# 顧客データを削除
echo "=== 顧客データを削除中... ==="
echo "DELETE FROM customers;" | sqlite3 database/database.sqlite

# 削除後の確認
echo "=== 削除後の顧客数 ==="
echo "SELECT COUNT(*) as customer_count FROM customers;" | sqlite3 database/database.sqlite

echo "顧客データクリア完了"