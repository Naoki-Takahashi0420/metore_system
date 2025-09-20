-- 本番環境顧客データクリア用SQLコマンド
-- 実行前にバックアップを取ることを推奨

-- 削除前の確認
SELECT 'Before deletion:' as action;
SELECT COUNT(*) as customer_count FROM customers;
SELECT COUNT(*) as reservation_count FROM reservations;
SELECT COUNT(*) as subscription_count FROM customer_subscriptions;

-- 外部キー制約のため、順序を守って削除

-- 1. 予約データを削除
DELETE FROM reservations;

-- 2. 顧客サブスクリプションを削除
DELETE FROM customer_subscriptions;

-- 3. 顧客アクセストークンを削除
DELETE FROM customer_access_tokens;

-- 4. 顧客ラベルを削除（存在する場合）
DELETE FROM customer_labels WHERE 1=1;

-- 5. LINE メッセージログを削除（存在する場合）
DELETE FROM line_message_logs WHERE 1=1;

-- 6. 医療記録を削除（存在する場合）
DELETE FROM medical_records WHERE 1=1;

-- 7. 最後に顧客データを削除
DELETE FROM customers;

-- 削除後の確認
SELECT 'After deletion:' as action;
SELECT COUNT(*) as customer_count FROM customers;
SELECT COUNT(*) as reservation_count FROM reservations;
SELECT COUNT(*) as subscription_count FROM customer_subscriptions;

-- VACUUM を実行してデータベースサイズを最適化
VACUUM;