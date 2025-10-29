-- ===================================================================
-- 売上データを外税計算から内税計算に変換するスクリプト
-- ===================================================================
--
-- 目的:
--   既存の売上データ（外税計算：税抜+税額=税込）を
--   内税計算（入力価格=税込）に変換する
--
-- 影響:
--   - sale_items.tax_amount を 0 に設定
--   - sales.total_amount を税抜金額（元の税込 - 税額）に更新
--
-- 実行前の確認:
--   1. 必ずデータベースのバックアップを取得してください
--   2. 本番環境では慎重に実行してください
--
-- 実行方法（ローカル/SQLite）:
--   sqlite3 database/database.sqlite < database/scripts/convert-sales-to-tax-inclusive.sql
--
-- 実行方法（本番/MySQL）:
--   mysql -u root -p xsyumeno_db < database/scripts/convert-sales-to-tax-inclusive.sql
-- ===================================================================

BEGIN TRANSACTION;

-- ステップ1: 影響を受けるデータを確認（実行前確認用）
SELECT '=== 調整対象の売上データ ===';
SELECT
    s.id as sale_id,
    s.sale_date,
    s.total_amount as current_total,
    COALESCE(SUM(si.tax_amount), 0) as total_tax,
    s.total_amount - COALESCE(SUM(si.tax_amount), 0) as new_total
FROM sales s
LEFT JOIN sale_items si ON s.id = si.sale_id
WHERE si.tax_amount > 0
GROUP BY s.id, s.sale_date, s.total_amount
ORDER BY s.sale_date DESC;

-- ステップ2: sale_itemsの税額を0に更新
UPDATE sale_items
SET tax_amount = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE tax_amount > 0;

-- ステップ3: salesの合計金額を税抜に更新
UPDATE sales
SET total_amount = (
    SELECT s.total_amount - COALESCE(SUM(si_old.amount_with_tax), 0) + COALESCE(SUM(si_old.amount), 0)
    FROM sales s
    LEFT JOIN (
        SELECT sale_id, amount, tax_amount, amount + tax_amount as amount_with_tax
        FROM sale_items
    ) si_old ON s.id = si_old.sale_id
    WHERE s.id = sales.id
),
updated_at = CURRENT_TIMESTAMP
WHERE id IN (
    SELECT DISTINCT sale_id
    FROM sale_items
    WHERE tax_amount > 0
);

-- より安全な更新方法（各売上ごとに個別に更新）
-- sale_id = 85 の場合: 14300 - 1300 = 13000
UPDATE sales SET total_amount = 13000, updated_at = CURRENT_TIMESTAMP WHERE id = 85 AND total_amount = 14300;

-- sale_id = 82 の場合: 4840 - 440 = 4400
UPDATE sales SET total_amount = 4400, updated_at = CURRENT_TIMESTAMP WHERE id = 82 AND total_amount = 4840;

-- sale_id = 83 の場合: 550 - 50 = 500
UPDATE sales SET total_amount = 500, updated_at = CURRENT_TIMESTAMP WHERE id = 83 AND total_amount = 550;

-- sale_id = 54 の場合: 1100 - 100 = 1000
UPDATE sales SET total_amount = 1000, updated_at = CURRENT_TIMESTAMP WHERE id = 54 AND total_amount = 1100;

-- ステップ4: 更新後の確認
SELECT '=== 更新後の売上データ ===';
SELECT
    s.id as sale_id,
    s.sale_date,
    s.total_amount,
    s.payment_method,
    GROUP_CONCAT(si.item_name || ' ¥' || si.amount) as items
FROM sales s
LEFT JOIN sale_items si ON s.id = si.sale_id
WHERE s.id IN (54, 82, 83, 85)
GROUP BY s.id, s.sale_date, s.total_amount, s.payment_method
ORDER BY s.sale_date DESC;

-- ステップ5: 税額が0になったことを確認
SELECT '=== 税額確認（全て0になっているはず） ===';
SELECT COUNT(*) as items_with_tax
FROM sale_items
WHERE tax_amount > 0;

COMMIT;

-- 実行完了メッセージ
SELECT '✅ 売上データの内税変換が完了しました';
