# 視力推移グラフの問題調査レポート

**調査日**: 2025-10-08
**対象カルテ**: カルテID 132 (本番環境)
**本番URL**: https://reservation.meno-training.com/admin/medical-records/132

---

## 問題の概要

視力推移グラフで、ある視力データが正しく表示されない問題が発生。
ユーザーの報告によると、矯正視力のグラフでデータが期待した値と異なって表示されている。

---

## 調査結果

### 1. 実際のデータベースの値

本番環境のデータベースから直接取得したデータ：

```json
[{
  "session": 1,
  "date": "2025-10-08",
  "intensity": "31",
  "duration": 80,
  "before_naked_left": null,
  "before_naked_right": null,
  "before_corrected_left": "1.5",
  "before_corrected_right": "1,5",  // ← カンマ区切り！
  "after_naked_left": null,
  "after_naked_right": null,
  "after_corrected_left": "1.5",
  "after_corrected_right": "1.5",
  "public_memo": null
}]
```

**重要な発見**: `before_corrected_right` の値が `"1,5"` (カンマ区切り) で保存されている。

### 2. 問題の根本原因

**PHPの型キャストの動作**:
- PHPで `(float)"1,5"` を実行すると `1.0` になる
- カンマ (`,`) は小数点として認識されない
- PHPはカンマを無視して最初の数字部分のみを取得する

**現在のコード** (`vision-chart.blade.php` 38-41行目):
```php
$leftCorrectedBefore[] = (isset($vision['before_corrected_left']) &&
                          $vision['before_corrected_left'] !== '' &&
                          (float)$vision['before_corrected_left'] >= 0)
                          ? (float)$vision['before_corrected_left'] : null;
```

**問題の流れ**:
1. データベース: `"1,5"` (カンマ区切り)
2. PHP変換: `(float)"1,5"` → `1.0`
3. グラフ表示: `1.0` として表示される

### 3. テストケースの検証

| 入力値 | (float)変換 | 条件判定 (>=0) | 最終結果 | 備考 |
|--------|-------------|----------------|----------|------|
| `"1.5"` | `1.5` | `true` | `1.5` | ✅ 正常 |
| `"1,5"` | `1.0` | `true` | `1.0` | ❌ 問題あり |
| `"0,3"` | `0.0` | `true` | `0.0` | ❌ 問題あり |
| `"-1.5"` | `-1.5` | `false` | `null` | ✅ 正常 |
| `""` | `0.0` | `true` | `null` | ✅ 正常（空文字チェックで弾かれる） |
| `null` | `0.0` | `true` | `null` | ✅ 正常（issetで弾かれる） |

### 4. コミット fc12c51 の確認

コミット fc12c51 で実装された修正:
- 空文字列のチェック: `$vision['before_corrected_left'] !== ''`
- マイナス値のチェック: `(float)$vision['before_corrected_left'] >= 0`

**このコミットの効果**:
- ✅ 空文字列は正しくnullに変換される
- ✅ マイナス値は正しくnullに変換される
- ❌ カンマ区切りの数値は変換されない（今回の問題）

---

## 修正方法

### 推奨: カンマをドットに変換してからキャスト

```php
// 修正前
$leftCorrectedBefore[] = (isset($vision['before_corrected_left']) &&
                          $vision['before_corrected_left'] !== '' &&
                          (float)$vision['before_corrected_left'] >= 0)
                          ? (float)$vision['before_corrected_left'] : null;

// 修正後
$value = isset($vision['before_corrected_left']) ? str_replace(',', '.', $vision['before_corrected_left']) : null;
$leftCorrectedBefore[] = (isset($value) && $value !== '' && is_numeric($value) && (float)$value >= 0)
                          ? (float)$value : null;
```

### 修正のポイント

1. **カンマをドットに変換**: `str_replace(',', '.', $value)`
   - `"1,5"` → `"1.5"` → `1.5` ✅

2. **is_numeric チェックを追加**:
   - 数値として有効な文字列かをチェック
   - 無効な文字列（例: `"abc"`）を弾く

3. **全8箇所の視力データ変換ロジックに適用**:
   - `before_naked_left`, `before_naked_right`
   - `after_naked_left`, `after_naked_right`
   - `before_corrected_left`, `before_corrected_right`
   - `after_corrected_left`, `after_corrected_right`

---

## 修正対象ファイル

**ファイル**: `/resources/views/filament/resources/medical-record/vision-chart.blade.php`
**対象行**: 33-41行目

---

## 検証方法

### 1. ローカルテスト
```bash
php analyze-vision-issue.php
```

### 2. 本番環境での検証
```bash
gh workflow run "Debug Vision Chart"
gh run watch $(gh run list --workflow="Debug Vision Chart" --limit 1 --json databaseId -q '.[0].databaseId')
```

---

## その他の発見

### Chart.jsの設定
- `spanGaps: false` は正しく設定されている（97行目、165-240行目）
- nullデータは正しくスキップされる動作になっている

### データ入力の問題
- カンマ区切りの数値が入力されている原因:
  - ユーザーがテンキーやキーボードで `1,5` と入力した可能性
  - 入力フォームでの検証が不足している可能性

### 追加の推奨事項
1. **入力フォームでのバリデーション強化**:
   - カンマを自動的にドットに変換
   - または、カンマ入力時に警告を表示

2. **既存データの修正**:
   - データベース内のカンマ区切り値を一括でドットに変換するスクリプトを実行

---

## まとめ

### 問題の本質
PHPの`(float)`キャストはカンマ (`,`) を小数点として認識せず、`"1,5"` → `1.0` に変換されてしまう。

### 修正方法
`str_replace(',', '.', $value)` でカンマをドットに変換してから`(float)`キャストを実行する。

### 影響範囲
- 視力推移グラフの全データ（裸眼、矯正、施術前後）
- カルテID 132 以外にも同様の問題がある可能性

---

**調査実施**: Claude Code
**デバッグスクリプト**: `debug-vision-data.php`, `analyze-vision-issue.php`
