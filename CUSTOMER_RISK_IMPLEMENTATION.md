# 要注意顧客自動判定機能 実装完了

## 📋 概要

キャンセル/ノーショー/変更回数に基づき、要注意顧客（is_blocked）を自動判定する機能を実装しました。

## ✅ 実装済み機能

### 1. データベース拡張

**customers テーブル**:
- `risk_override` (boolean): 手動上書きフラグ
- `risk_flag_source` (string): 'auto' | 'manual'
- `risk_flag_reason` (json): 自動判定の根拠
- `risk_flagged_at` (datetime): 最終更新日時

**reservations テーブル**:
- `cancel_reason` (string): キャンセル理由

### 2. 自動判定ロジック

**閾値設定** (`config/customer_risk.php`):
- キャンセル: 直近90日で2回以上
- ノーショー: 直近180日で1回以上
- 予約変更: 直近60日で3回以上

**除外ルール**:
- `store_fault` (店舗都合)
- `system_fix` (システム修正)
→ これらはカウント対象外

### 3. 手動上書き機能

**動作**:
- 管理画面で is_blocked を手動変更 → `risk_override=true` に自動設定
- `risk_override=true` の場合、自動判定は is_blocked を変更しない
- 手動で戻したい場合は、risk_override を false に設定

### 4. UI改善

**CustomerResource**:
- 要注意判定の根拠を表示（リスクレベル、判定元、理由）
- 手動上書き中は警告表示

**キャンセルUI**:
- ReservationResource: cancel_reason を選択式に変更
- TodayReservationsWidget: キャンセル/来店なし時に理由を選択

## 🔧 マイグレーション実行

```bash
# 1. マイグレーション実行
php artisan migrate

# 2. キャッシュクリア
php artisan config:clear
php artisan cache:clear
```

## 🧪 テストケース

### ケース1: 顧客都合キャンセル2回 → 自動ON

```bash
# 1. 予約をキャンセル（顧客都合）
# 2. もう1回キャンセル（顧客都合）
# 期待: is_blocked=true, risk_flag_source='auto'
```

### ケース2: 店舗都合キャンセル2回 → カウント不変

```bash
# 1. 予約をキャンセル（店舗都合）
# 2. もう1回キャンセル（店舗都合）
# 期待: cancellation_count=0, is_blocked=false
```

### ケース3: ノーショー1回 → 自動ON

```bash
# 1. 予約を来店なしに変更（顧客都合）
# 期待: is_blocked=true, risk_flag_source='auto'
```

### ケース4: 手動OFF → override=true → 自動でONに戻らない

```bash
# 1. is_blocked=true の顧客を手動で false に変更
# 2. キャンセル/ノーショーを繰り返す
# 期待: risk_override=true, is_blocked=false のまま
```

### ケース5: 二重加算なし

```bash
# 1. 予約をキャンセル
# 2. DBで cancellation_count を確認
# 期待: 1回のキャンセルで +1 のみ（+2 にならない）
```

### ケース6: 根拠表示

```bash
# 1. 顧客詳細画面を開く
# 2. 要注意判定の根拠セクションを確認
# 期待: リスクレベル、判定元、キャンセル回数/閾値が表示される
```

## 📊 ログ確認

```bash
# Laravelログを確認
tail -f storage/logs/laravel.log | grep "\[ReservationObserver\]\|\[Customer::evaluateRiskStatus\]\|\[CustomerResource\]"
```

**期待されるログ**:
```
[ReservationObserver] Cancellation count incremented
[Customer::evaluateRiskStatus] is_blocked changed by auto evaluation
[CustomerResource] Manual is_blocked change
```

## ⚠️ 注意事項

1. **既存データ**: 既存顧客の `risk_override=false` で移行されます
2. **通知除外**: is_blocked=true の顧客は引き続き通知から除外されます
3. **手動上書き**: 一度手動変更すると、自動判定は無効になります
4. **Observer一本化**: カウント更新は ReservationObserver のみが実行します（二重加算なし）

## 🔍 動作確認チェックリスト

### 基本動作

- [ ] マイグレーション実行成功
- [ ] customers テーブルに4カラム追加
- [ ] reservations テーブルに1カラム追加

### UI確認

- [ ] CustomerResource: 要注意判定の根拠が表示される
- [ ] CustomerResource: is_blockedトグル変更時にrisk_override=trueになる
- [ ] ReservationResource: cancel_reason選択フォームが表示される
- [ ] TodayReservationsWidget: キャンセル/来店なし時に理由選択モーダルが表示される
- [ ] IntegratedReservationManagement: キャンセル/来店なし時に理由選択モーダルが表示される

### ロジック確認

- [ ] 顧客都合キャンセル2回 → is_blocked=true, risk_flag_source='auto'
- [ ] 店舗都合キャンセル2回 → cancellation_count=0, is_blocked=false
- [ ] ノーショー1回 → is_blocked=true, risk_flag_source='auto'
- [ ] 手動OFF後のキャンセル → is_blocked=false のまま（risk_override=true）
- [ ] decrementで閾値下回り → is_blocked=false に自動復帰
- [ ] 二重加算なし → 1回のキャンセルで cancellation_count が +1 のみ

## 🔄 ロールバック手順

```bash
# マイグレーションをロールバック
php artisan migrate:rollback --step=2

# または特定のマイグレーションをロールバック
php artisan migrate:rollback --path=database/migrations/2025_10_27_000001_add_risk_fields_to_customers_table.php
php artisan migrate:rollback --path=database/migrations/2025_10_27_000002_add_cancel_reason_to_reservations_table.php
```

## 📝 関連ファイル

**設定**:
- `config/customer_risk.php` - 閾値・cancel_reason定義

**バックエンド**:
- `app/Models/Customer.php` - 自動判定ロジック
- `app/Observers/ReservationObserver.php` - カウント更新と自動判定呼び出し（唯一のincrement実行場所）
- `app/Http/Controllers/Api/ReservationController.php` - 二重加算撤去済み

**Filament UI**:
- `app/Filament/Resources/CustomerResource.php` - 手動トグルと根拠表示
- `app/Filament/Resources/ReservationResource.php` - cancel_reason選択
- `app/Filament/Widgets/TodayReservationsWidget.php` - cancel_reason選択
- `app/Filament/Pages/IntegratedReservationManagement.php` - cancel_reason選択、二重加算撤去済み

## ✅ 二重加算の完全撤去

**確認済み**: 全てのincrement呼び出しは `ReservationObserver` のみ

```bash
# 確認コマンド
grep -rn "increment.*no_show_count\|increment.*cancellation_count" app/ --include="*.php" | grep -v "Observer"
# → 出力なし（Observer以外にincrementなし）
```

**撤去箇所**:
1. `app/Http/Controllers/Api/ReservationController.php` L84, L567
2. `app/Filament/Pages/IntegratedReservationManagement.php` L189-191

---

**実装日**: 2025-10-27
**最終更新**: 2025-10-27
**実装者**: Claude Code
