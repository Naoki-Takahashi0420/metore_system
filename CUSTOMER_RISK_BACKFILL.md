# 要注意顧客自動判定バックフィル

## 📋 概要

既存顧客に対して、自動判定ロジック（`Customer::evaluateRiskStatus`）を一括適用し、`is_blocked` を同期するArtisanコマンドです。

## 🎯 目的

- **既存顧客への自動判定適用**: 新機能導入前から存在する顧客に対して、自動判定ロジックを適用
- **手動上書きの尊重**: `risk_override=true` の顧客は更新しない（オプションで解除可）
- **安全な一括処理**: ドライラン・チャンク処理・詳細ログで本番でも安全に実行

## 📦 コマンド

### 基本構文

```bash
php artisan customer:risk-backfill [オプション]
```

### オプション

| オプション | 説明 | 例 |
|----------|------|-----|
| `--dry-run` | 変更せず集計・出力のみ（シミュレーション） | `--dry-run` |
| `--include-overrides` | `risk_override=true` の顧客も対象に含める | `--include-overrides` |
| `--store=ID` | 特定店舗の顧客に限定 | `--store=1` |
| `--since-days=90` | 期間の上書き（任意、未指定なら config の既定を使用） | `--since-days=120` |
| `--limit=N` | 上限件数（テスト用） | `--limit=10` |
| `--only=SOURCE` | `risk_flag_source` の絞り込み（`auto` または `manual-backfill`） | `--only=auto` |

## 🚀 使用例

### 1. ドライラン（必須の最初のステップ）

```bash
# 全顧客でドライラン
php artisan customer:risk-backfill --dry-run

# 特定店舗でドライラン
php artisan customer:risk-backfill --dry-run --store=1

# 少数でドライラン（テスト）
php artisan customer:risk-backfill --dry-run --limit=10
```

**出力例**:
```
=== 要注意顧客自動判定バックフィル ===
モード: ドライラン（変更なし）
対象顧客数: 2524 件
手動上書き顧客: 対象外（risk_override=false のみ）

[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

=== 実行結果 ===
処理件数: 2524 件
自動ON (false → true): 17 件
自動OFF (true → false): 0 件
変更なし: 2507 件

実行時間: 1.202539 秒
※ ドライランのため、データベースは変更されていません。
```

### 2. 本実行（店舗ごとに段階的）

```bash
# 店舗1（銀座本店）で本実行
php artisan customer:risk-backfill --store=1

# 店舗2（小山店）で本実行
php artisan customer:risk-backfill --store=2
```

### 3. 全体適用

```bash
# 全顧客で本実行
php artisan customer:risk-backfill
```

### 4. 手動上書き顧客も含めて強制同期（必要時のみ）

```bash
# risk_override=true の顧客も対象に含める
php artisan customer:risk-backfill --include-overrides
```

⚠️ **警告**: `--include-overrides` を使用すると、手動で設定した `is_blocked` の値が上書きされます。本当に必要な場合のみ使用してください。

### 5. 期間の上書き（検証用）

```bash
# 期間を120日に変更してテスト
php artisan customer:risk-backfill --dry-run --since-days=120
```

## 🔧 動作仕様

### 対象顧客の抽出

1. **基本**: `customers.risk_override = false` の顧客のみ
2. **--include-overrides 指定時**: 全顧客が対象
3. **--store 指定時**: `customers.store_id = 指定店舗` の顧客のみ
4. **--only 指定時**: `customers.risk_flag_source = 指定値` の顧客のみ

### 処理内容

1. **チャンク処理**: 500件ずつ安全に処理（メモリ対策）
2. **自動判定**: `Customer::evaluateRiskStatus()` を各顧客に適用
3. **変更検出**: `is_blocked` の変更を検出してログ出力
4. **例外処理**: エラーが発生しても継続処理

### 自動判定ロジック

`config/customer_risk.php` の閾値に基づいて判定:

| 項目 | 期間 | 閾値 | 除外 |
|------|------|------|------|
| キャンセル | 90日 | 2回以上 | `store_fault`, `system_fix` |
| ノーショー | 180日 | 1回以上 | `store_fault`, `system_fix` |
| 予約変更 | 60日 | 3回以上 | - |

いずれか1つでも閾値を超えると `is_blocked = true` になります。

## 📊 ログ出力

### コンソール出力

- 対象顧客数
- 処理進捗（プログレスバー）
- 実行結果サマリ
  - 処理件数
  - 自動ON件数（false → true）
  - 自動OFF件数（true → false）
  - 変更なし件数
  - エラー件数
- 実行時間

### ファイルログ

`storage/logs/laravel-YYYY-MM-DD.log` に詳細ログを出力:

```json
[2025-10-27 23:21:09] local.INFO: [BackfillCustomerRisk] is_blocked変更 {
  "customer_id": 2592,
  "change": "false → true (自動ON)",
  "old_blocked": false,
  "new_blocked": true,
  "old_source": null,
  "new_source": "auto",
  "old_flagged_at": null,
  "new_flagged_at": "2025-10-27 23:21:09",
  "dry_run": false
}
```

## ✅ 受け入れ基準

### ドライラン
- ✓ 変更が一切発生しない
- ✓ 対象件数と想定変更件数がログに表示される
- ✓ `※ ドライランのため、データベースは変更されていません。` が表示される

### 本実行
- ✓ `risk_override=false` の顧客のみ `is_blocked` が同期される
- ✓ `risk_override=true` の顧客はデフォルトで対象外
- ✓ `--include-overrides` 指定時のみ手動上書き顧客も対象
- ✓ 大量件数でもタイムアウト/メモリ異常なし（チャンク処理）
- ✓ 実行後に `is_blocked`、`risk_flag_source`、`risk_flagged_at` が更新される

### ログ
- ✓ 実行開始/終了時刻がログに記録される
- ✓ オプション内容がログに記録される
- ✓ 変更された顧客の詳細がログに記録される

## 🛡️ 安全機能

### 1. ドライランモード

```bash
php artisan customer:risk-backfill --dry-run
```

- データベースを一切変更しない
- 実行結果をシミュレーションして表示
- **必ず本実行前にドライランを実行してください**

### 2. チャンク処理

- 500件ずつ処理（メモリ対策）
- `DB::disableQueryLog()` でクエリログ無効化
- 大量データでも安全に実行可能

### 3. 例外処理

- 個別顧客のエラーは記録して継続
- エラー件数を最終サマリに表示
- `storage/logs/laravel.log` に詳細エラーログ

### 4. 確認プロンプト

本実行時は確認プロンプトが表示されます:

```
本実行を開始します。2524 件の顧客を処理しますか？ (yes/no) [yes]:
```

自動実行（CI/CD）では `--no-interaction` オプションを追加:

```bash
php artisan customer:risk-backfill --no-interaction
```

## 📝 運用手順（本番環境）

### ステップ1: 事前準備

```bash
# 1. データベースバックアップ
mysqldump -u username -p database_name > backup.sql

# 2. SQLiteの場合
cp database/database.sqlite database/database.sqlite.backup
```

### ステップ2: ドライラン（全体）

```bash
# 全顧客でドライラン
php artisan customer:risk-backfill --dry-run

# 結果を確認
# - 対象顧客数
# - 自動ON件数
# - 自動OFF件数
```

### ステップ3: ドライラン（店舗別）

```bash
# 店舗1でドライラン
php artisan customer:risk-backfill --dry-run --store=1

# 店舗2でドライラン
php artisan customer:risk-backfill --dry-run --store=2
```

### ステップ4: 部分適用（店舗ごとに段階的）

```bash
# 店舗1で本実行
php artisan customer:risk-backfill --store=1

# 結果を確認してから次の店舗へ

# 店舗2で本実行
php artisan customer:risk-backfill --store=2
```

### ステップ5: 全体適用

```bash
# 全顧客で本実行
php artisan customer:risk-backfill
```

### ステップ6: 結果確認

```bash
# is_blocked=true になった顧客を確認
sqlite3 database/database.sqlite "
  SELECT id, cancellation_count, no_show_count, is_blocked,
         risk_flag_source, risk_flagged_at
  FROM customers
  WHERE is_blocked = 1
  LIMIT 10;
"

# ログ確認
tail -50 storage/logs/laravel-$(date +%Y-%m-%d).log | grep BackfillCustomerRisk
```

## ⚠️ 注意事項

### 1. 手動上書きの尊重

- デフォルトでは `risk_override=true` の顧客は対象外
- 手動で設定した `is_blocked` の値を保護
- `--include-overrides` を使用する場合は慎重に判断

### 2. 期間の上書き

- `--since-days` オプションは一時的な上書き
- `config/customer_risk.php` の設定は変更されない
- 検証目的でのみ使用推奨

### 3. 実行タイミング

- 営業時間外の実行を推奨
- 大量データの場合は数分かかる可能性あり
- 本番実行前に必ずドライランで確認

### 4. ロールバック

もし誤って実行した場合:

```bash
# バックアップから復元（SQLite）
cp database/database.sqlite.backup database/database.sqlite

# または手動でリセット（特定顧客）
UPDATE customers
SET is_blocked = 0,
    risk_flag_source = NULL,
    risk_flag_reason = NULL,
    risk_flagged_at = NULL
WHERE risk_flag_source = 'auto';
```

## 🔍 トラブルシューティング

### Q1: 対象顧客が0件と表示される

**原因**: 全顧客が `risk_override=true` になっている

**解決策**:
```bash
# 確認
sqlite3 database/database.sqlite "
  SELECT COUNT(*) as total,
         SUM(CASE WHEN risk_override = 1 THEN 1 ELSE 0 END) as override_true
  FROM customers;
"

# 必要に応じて --include-overrides を使用
php artisan customer:risk-backfill --dry-run --include-overrides
```

### Q2: メモリ不足エラー

**原因**: 大量データ処理時のメモリ不足

**解決策**:
```bash
# PHP メモリ上限を一時的に増やす
php -d memory_limit=512M artisan customer:risk-backfill
```

### Q3: 実行が遅い

**原因**: 顧客数が多い、またはDBクエリが遅い

**解決策**:
```bash
# 店舗別に分けて実行
php artisan customer:risk-backfill --store=1
php artisan customer:risk-backfill --store=2

# または limit で分割実行（非推奨）
php artisan customer:risk-backfill --limit=500
```

## 📚 関連ファイル

### コマンド実装

- `app/Console/Commands/BackfillCustomerRisk.php` - バックフィルコマンド

### 自動判定ロジック

- `app/Models/Customer.php` - `evaluateRiskStatus()`, `getRecentReservations()`
- `config/customer_risk.php` - 閾値と除外理由の設定

### ドキュメント

- `CUSTOMER_RISK_IMPLEMENTATION.md` - 自動判定機能の実装ドキュメント
- `CUSTOMER_RISK_BACKFILL.md` - このドキュメント

## 📊 実行例（実際の結果）

### ローカル環境での実行結果

```bash
$ php artisan customer:risk-backfill --dry-run

=== 要注意顧客自動判定バックフィル ===
モード: ドライラン（変更なし）
対象顧客数: 2524 件
手動上書き顧客: 対象外（risk_override=false のみ）

 2524/2524 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

=== 実行結果 ===
処理件数: 2524 件
自動ON (false → true): 17 件
自動OFF (true → false): 0 件
変更なし: 2507 件

実行時間: 1.202539 秒
※ ドライランのため、データベースは変更されていません。
```

### 本実行の結果

```bash
$ php artisan customer:risk-backfill

=== 要注意顧客自動判定バックフィル ===
モード: 本実行
対象顧客数: 2524 件
手動上書き顧客: 対象外（risk_override=false のみ）

本実行を開始します。2524 件の顧客を処理しますか？ (yes/no) [yes]:
> yes

 2524/2524 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

=== 実行結果 ===
処理件数: 2524 件
自動ON (false → true): 17 件
自動OFF (true → false): 0 件
変更なし: 2507 件

実行時間: 1.156838 秒
✓ is_blocked の同期が完了しました。
```

### 変更された顧客の例

```bash
$ sqlite3 database/database.sqlite "
  SELECT id, cancellation_count, is_blocked, risk_flag_source
  FROM customers
  WHERE is_blocked = 1
  LIMIT 5;
"

2592|15|1|auto
3469|12|1|auto
2834|8|1|auto
2946|6|1|auto
3673|4|1|auto
```

---

**実装日**: 2025-10-27
**最終更新**: 2025-10-27
**実装者**: Claude Code
