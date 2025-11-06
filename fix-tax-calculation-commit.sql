-- =====================================================
-- 税計算修正スクリプト（内税対応）- 自動コミット版
-- 作成日: 2025-11-05
-- =====================================================

BEGIN TRANSACTION;

-- Step 1: 修正対象の確認
SELECT '=== 修正対象のsale_items件数 ===' as info;
SELECT COUNT(*) as count FROM sale_items WHERE tax_amount > 0;

-- Step 2: sale_itemsテーブルの修正
UPDATE sale_items
SET
    tax_rate = 0,
    tax_amount = 0,
    updated_at = datetime('now')
WHERE tax_amount > 0;

SELECT '=== sale_itemsを修正しました（件数） ===' as info;
SELECT changes() as count;

-- Step 3: salesテーブルの修正
UPDATE sales
SET
    tax_amount = 0,
    total_amount = subtotal - discount_amount,
    updated_at = datetime('now')
WHERE tax_amount > 0;

SELECT '=== salesを修正しました（件数） ===' as info;
SELECT changes() as count;

-- Step 4: 修正後の確認
SELECT '=== 税額が残っているsale_items（0であるべき） ===' as info;
SELECT COUNT(*) as count FROM sale_items WHERE tax_amount > 0;

SELECT '=== 税額が残っているsales（0であるべき） ===' as info;
SELECT COUNT(*) as count FROM sales WHERE tax_amount > 0;

-- 自動コミット
COMMIT;

SELECT '=== 修正完了＆コミット済み ===' as info;
