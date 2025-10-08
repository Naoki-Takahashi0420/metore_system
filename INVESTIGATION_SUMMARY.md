# 視力推移グラフ問題 - 調査完了報告

**調査日**: 2025-10-08
**対象**: 本番環境 (https://reservation.meno-training.com/)
**報告対象カルテ**: カルテID 132

---

## エグゼクティブサマリー

視力推移グラフで特定のデータが正しく表示されない問題を調査した結果、**PHPの型キャストにおけるカンマ (`,`) の扱い**が原因であることが判明しました。

- **問題**: データベースに `"1,5"` (カンマ区切り) で保存された視力データが、グラフで `1.0` として表示される
- **原因**: PHPで `(float)"1,5"` を実行すると `1.0` になる（カンマは小数点として認識されない）
- **影響範囲**: 本番環境で2カルテ、2件のデータが影響を受けている

---

## 調査結果の詳細

### 1. 本番環境のデータ（カルテID 132）

```json
{
  "before_corrected_right": "1,5"  // カンマ区切り
}
```

**実際のデータベースの値**:
```bash
ssh ubuntu@54.64.54.226
cd /var/www/html
sudo php debug-vision-data.php
```

出力:
```
右眼（施術前）矯正: '1,5' → 型: string
```

### 2. 問題の再現

```php
// PHPの動作
var_dump((float)"1,5");  // float(1.0) ← カンマが無視される
var_dump((float)"1.5");  // float(1.5) ← 正常
```

### 3. 影響範囲

本番環境でカンマ区切りデータを検出した結果:

| カルテID | 顧客ID | 施術日 | フィールド | 値 | 変換後 |
|---------|--------|--------|-----------|-----|--------|
| 132 | 3591 | 2025-10-08 | 右眼（施術前）矯正 | `'1,5'` | `1.5` |
| 135 | 3594 | 2025-10-08 | 左眼（施術後）矯正 | `'1,5'` | `1.5` |

**合計**: 2カルテ、2件

---

## 根本原因

### コード解析

**現在のコード** (`vision-chart.blade.php` 38行目):
```php
$leftCorrectedBefore[] = (isset($vision['before_corrected_left']) &&
                          $vision['before_corrected_left'] !== '' &&
                          (float)$vision['before_corrected_left'] >= 0)
                          ? (float)$vision['before_corrected_left'] : null;
```

**問題点**:
1. `str_replace(',', '.', $value)` を行っていない
2. カンマ区切りの値が誤った数値に変換される

### PHPの型キャストの動作

| 入力値 | `(float)` 変換 | 期待値 | 結果 |
|--------|---------------|--------|------|
| `"1.5"` | `1.5` | `1.5` | ✅ |
| `"1,5"` | `1.0` | `1.5` | ❌ |
| `"0,3"` | `0.0` | `0.3` | ❌ |
| `"-1.5"` | `-1.5` | `null` | ✅ |
| `""` | `0.0` | `null` | ✅ |

---

## 修正方法

### 1. vision-chart.blade.php の修正

**修正箇所**: 33-41行目（全8箇所の視力フィールド）

**修正前**:
```php
$leftCorrectedBefore[] = (isset($vision['before_corrected_left']) &&
                          $vision['before_corrected_left'] !== '' &&
                          (float)$vision['before_corrected_left'] >= 0)
                          ? (float)$vision['before_corrected_left'] : null;
```

**修正後**:
```php
$value = isset($vision['before_corrected_left']) ? str_replace(',', '.', $vision['before_corrected_left']) : null;
$leftCorrectedBefore[] = (isset($value) && $value !== '' && is_numeric($value) && (float)$value >= 0)
                          ? (float)$value : null;
```

**変更点**:
1. `str_replace(',', '.', $value)` でカンマをドットに変換
2. `is_numeric($value)` で数値として有効かチェック
3. 無効な文字列（例: `"abc"`）を弾く

### 2. 既存データの修正

本番環境で以下のスクリプトを実行:

```bash
# 1. プレビュー（DRY RUN）
php fix-comma-values.php --dry-run

# 2. 実際に修正（バックアップ取得後）
php fix-comma-values.php
```

---

## 実施した調査

### 1. デバッグスクリプトの作成

作成したファイル:
- `debug-vision-data.php` - 本番環境のデータを直接確認
- `analyze-vision-issue.php` - ローカル環境で問題を再現
- `check-comma-values.php` - カンマ区切りデータを検出
- `fix-comma-values.php` - カンマ区切りデータを修正

### 2. GitHub Actionsワークフロー

- `.github/workflows/debug-vision-chart.yml` - デバッグスクリプトを本番で実行
- `.github/workflows/check-comma-values.yml` - カンマ値チェックを本番で実行

### 3. 実行結果

```bash
# デバッグスクリプトの実行
gh workflow run "Debug Vision Chart"
gh run watch 18352352528

# カンマ値チェックの実行
gh workflow run "Check Comma Values"
gh run watch 18352434759
```

**結果**:
- ✅ データベースの実際の値を確認
- ✅ カンマ区切りデータを2件検出
- ✅ 問題の根本原因を特定

---

## 検証方法

### ローカル環境

```bash
# 問題の再現テスト
php analyze-vision-issue.php
```

出力:
```
右眼（施術前）矯正 (カンマ区切り '1,5'):
  isset: true
  !== '': true
  (float)値: 1
  >= 0: true
  → 変換後: 1.0

⚠️  重要な発見:
  '1,5' を (float) でキャストすると → 1 になります！
  PHPはカンマを無視して最初の数字だけを取るため、'1,5' → 1.0 に変換されます。
```

### 本番環境

```bash
# デバッグログの確認
gh run view 18352352528 --log | grep -A 50 "Vision Chart Debug"

# カンマ値チェック
gh run view 18352434759 --log | grep -A 50 "Comma Values Check"
```

---

## 次のアクション

### 緊急対応（推奨）

1. **コードの修正** (vision-chart.blade.php)
   - 全8箇所の視力フィールドに `str_replace(',', '.', $value)` を追加
   - コミット & デプロイ

2. **既存データの修正**
   - 本番環境で `fix-comma-values.php` を実行
   - カルテID 132, 135 のデータを修正

### 恒久対策（推奨）

1. **入力フォームでのバリデーション強化**
   - カンマを自動的にドットに変換
   - または、カンマ入力時に警告を表示

2. **定期的なデータチェック**
   - 月次で `check-comma-values.php` を実行
   - カンマ区切りデータの早期発見

3. **ユニットテストの追加**
   - カンマ区切り値のテストケースを追加
   - 回帰防止

---

## 関連ファイル

### 調査関連
- `/Applications/MAMP/htdocs/Xsyumeno-main/VISION_GRAPH_ISSUE_REPORT.md` - 詳細な技術レポート
- `/Applications/MAMP/htdocs/Xsyumeno-main/debug-vision-data.php` - 本番データ確認スクリプト
- `/Applications/MAMP/htdocs/Xsyumeno-main/analyze-vision-issue.php` - ローカル再現スクリプト
- `/Applications/MAMP/htdocs/Xsyumeno-main/check-comma-values.php` - カンマ値検出スクリプト
- `/Applications/MAMP/htdocs/Xsyumeno-main/fix-comma-values.php` - カンマ値修正スクリプト

### コード関連
- `/Applications/MAMP/htdocs/Xsyumeno-main/resources/views/filament/resources/medical-record/vision-chart.blade.php` - 修正対象ファイル

### ワークフロー
- `.github/workflows/debug-vision-chart.yml`
- `.github/workflows/check-comma-values.yml`

---

## 結論

視力推移グラフの問題は、**PHPの型キャストにおけるカンマの扱い**が原因でした。

- **短期的な解決策**: コードの修正（`str_replace(',', '.', $value)`）
- **長期的な解決策**: 入力フォームでのバリデーション強化

本番環境では現在2件のカンマ区切りデータが存在しており、修正が必要です。

---

**調査実施**: Claude Code
**調査方法**: SSH経由でのデータベース直接確認、PHPスクリプトでの再現テスト
**コミット**: a7877a6, 7a7d712
