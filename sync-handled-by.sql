-- =====================================================
-- 売上とカルテの担当者情報を同期
-- 作成日: 2025-11-06
--
-- 【目的】
-- 売上のhandled_byがNULLで、カルテにhandled_byがある場合
-- カルテの情報を売上に同期する
--
-- 【対象】
-- - sales.handled_by が NULL または空
-- - medical_records.handled_by が入力されている
-- =====================================================

BEGIN TRANSACTION;

-- =====================================================
-- Step 1: 対象データの確認
-- =====================================================

SELECT '=== 修正対象の売上件数 ===' as info;
SELECT COUNT(*) as count
FROM sales s
INNER JOIN reservations r ON s.reservation_id = r.id
INNER JOIN medical_records mr ON r.id = mr.reservation_id
WHERE (s.handled_by IS NULL OR s.handled_by = '')
  AND mr.handled_by IS NOT NULL
  AND mr.handled_by != '';

SELECT '=== 修正対象のサンプル（5件） ===' as info;
SELECT
    s.id as 売上ID,
    s.reservation_id,
    s.handled_by as 売上の担当者,
    mr.handled_by as カルテの担当者,
    s.staff_id as 売上のスタッフID,
    mr.staff_id as カルテのスタッフID
FROM sales s
INNER JOIN reservations r ON s.reservation_id = r.id
INNER JOIN medical_records mr ON r.id = mr.reservation_id
WHERE (s.handled_by IS NULL OR s.handled_by = '')
  AND mr.handled_by IS NOT NULL
  AND mr.handled_by != ''
LIMIT 5;

-- =====================================================
-- Step 2: 売上データを更新
-- =====================================================

UPDATE sales
SET
    handled_by = (
        SELECT mr.handled_by
        FROM medical_records mr
        INNER JOIN reservations r ON mr.reservation_id = r.id
        WHERE r.id = sales.reservation_id
        LIMIT 1
    ),
    staff_id = (
        SELECT mr.staff_id
        FROM medical_records mr
        INNER JOIN reservations r ON mr.reservation_id = r.id
        WHERE r.id = sales.reservation_id
        LIMIT 1
    ),
    updated_at = datetime('now')
WHERE id IN (
    SELECT s.id
    FROM sales s
    INNER JOIN reservations r ON s.reservation_id = r.id
    INNER JOIN medical_records mr ON r.id = mr.reservation_id
    WHERE (s.handled_by IS NULL OR s.handled_by = '')
      AND mr.handled_by IS NOT NULL
      AND mr.handled_by != ''
);

SELECT '=== 更新件数 ===' as info;
SELECT changes() as count;

-- =====================================================
-- Step 3: 修正後の確認
-- =====================================================

SELECT '=== 修正後の確認（サンプル5件） ===' as info;
SELECT
    s.id as 売上ID,
    s.reservation_id,
    s.handled_by as 売上の担当者,
    s.staff_id as 売上のスタッフID
FROM sales s
WHERE id IN (
    SELECT id FROM sales ORDER BY updated_at DESC LIMIT 5
);

SELECT '=== まだhandled_byがNULLの売上（0であるべき） ===' as info;
SELECT COUNT(*) as count
FROM sales s
INNER JOIN reservations r ON s.reservation_id = r.id
INNER JOIN medical_records mr ON r.id = mr.reservation_id
WHERE (s.handled_by IS NULL OR s.handled_by = '')
  AND mr.handled_by IS NOT NULL
  AND mr.handled_by != '';

-- 自動コミット
COMMIT;

SELECT '=== 同期完了＆コミット済み ===' as info;
