# 通知システム改善計画

> **作成日**: 2025-12-09
> **ステータス**: 計画中
> **最終更新**: 2025-12-09

---

## 目次

1. [現状の問題点](#現状の問題点)
2. [改善方針](#改善方針)
3. [修正計画（優先順位付き）](#修正計画優先順位付き)
4. [注意事項（必読）](#注意事項必読)
5. [ファイル一覧と役割](#ファイル一覧と役割)
6. [テスト計画](#テスト計画)
7. [進捗管理](#進捗管理)

---

## 現状の問題点

### 致命的（本番影響あり）

| # | 問題 | 影響 | 関連ファイル |
|---|------|------|--------------|
| 1 | **予約変更/キャンセルで2重通知** | SMS費用増、顧客に同じ通知が2通届く | `SendCustomerReservationChangeNotification.php`, `SendCustomerReservationCancellationNotification.php` |
| 2 | **API経由の予約変更でエラー** | キュー実行時にエラー | `Api/ReservationController.php` (689, 809, 968行目) |
| 3 | **LINE リマインダーが動作しない** | リマインダー送信不可 | `SendLineReminders.php`, DBスキーマ |

### 重要（改善必須）

| # | 問題 | 影響 |
|---|------|------|
| 4 | 通知優先順位が逆（LINE→SMS→メール） | SMSコスト削減不可 |
| 5 | 11店舗がLINE無効 | LINE移行の障壁 |
| 6 | 2つのLINEサービスが混在 | コード混乱 |

### 軽微（クリーンアップ推奨）

| # | 問題 |
|---|------|
| 7 | 未使用ファイル（`ProcessLineReminders.php`, `ProcessLineMessages.php`） |
| 8 | 2つのリマインダーシステムの混在 |

---

## 改善方針

### 通知優先順位

```
【現在】
LINE → SMS → メール

【改善後】
LINE → メール → SMS

【OTP認証のみ例外】
SMS → メール（セキュリティ上SMSを優先）
```

### 統一すべきサービス

```
【使用推奨】
- CustomerNotificationService  ← 顧客通知の統一エントリポイント
- SimpleLineService            ← LINE送信（店舗別トークン対応）
- SmsService                   ← SMS送信
- EmailService                 ← メール送信

【廃止検討】
- LineMessageService           ← env()直接参照でキャッシュ問題あり
```

---

## 修正計画（優先順位付き）

### Phase 1: 緊急修正（2重通知の停止）

#### 1-1. SendCustomerReservationChangeNotification.php 修正
- [ ] LINE成功時はSMS/Emailをスキップするロジックに変更
- [ ] `CustomerNotificationService.sendReservationChange()` を使用するように統一
- [ ] テスト実施

#### 1-2. SendCustomerReservationCancellationNotification.php 修正
- [ ] 同上のロジック変更
- [ ] `CustomerNotificationService.sendReservationCancellation()` を使用
- [ ] テスト実施

#### 1-3. Api/ReservationController.php 修正
- [ ] 689行目: `$oldReservation` を配列に変換してからイベント発火
- [ ] 809行目: 同上
- [ ] 968行目: 同上
- [ ] テスト実施

### Phase 2: 優先順位の変更

#### 2-1. CustomerNotificationService.php 修正
- [ ] `sendNotification()` メソッドの順序変更
  ```php
  // 現在
  if ($customer->canReceiveLineNotifications()) { /* LINE送信 */ }
  if ($customer->phone && $customer->sms_notifications_enabled) { /* SMS送信 */ }
  if ($customer->email) { /* メール送信 */ }

  // 修正後
  if ($customer->canReceiveLineNotifications()) { /* LINE送信 */ }
  if ($customer->email) { /* メール送信 */ }  // ← 順序変更
  if ($customer->phone && $customer->sms_notifications_enabled) { /* SMS送信 */ }
  ```
- [ ] テスト実施

### Phase 3: リマインダー修正

#### 3-1. DBマイグレーション追加
- [ ] `line_reminder_sent_at` カラムを `reservations` テーブルに追加
- [ ] マイグレーション実行確認

#### 3-2. SendLineReminders.php 修正
- [ ] LINE/メール/SMSの優先順位対応
- [ ] 時間判定ロジックの改善（前日送信に変更検討）
- [ ] テスト実施

### Phase 4: クリーンアップ

#### 4-1. 不要ファイル削除（影響調査後）
- [ ] `ProcessLineReminders.php` の影響範囲調査
- [ ] `ProcessLineMessages.php` の影響範囲調査
- [ ] 削除実施

#### 4-2. LineMessageService の廃止検討
- [ ] 使用箇所の洗い出し
- [ ] SimpleLineService への移行
- [ ] 削除実施

---

## 注意事項（必読）

### 1. ファイル削除時の注意

```
【必須手順】
1. grep で使用箇所を全検索
2. use 文、DI、直接インスタンス化を確認
3. routes/console.php のスケジュール登録を確認
4. 削除前にコメントアウトしてテスト
5. ローカルで動作確認後に削除
```

### 2. デプロイに関する注意

```
⚠️ 絶対に勝手にデプロイしない
⚠️ 必ずユーザーの確認を取ってからデプロイ
⚠️ ローカルでテストしてからデプロイ
```

### 3. 変数に関する注意

```
【ReservationChangedイベントの注意】
- oldReservationData は「配列」で渡す（モデルではない）
- replicate() で複製したモデルをそのまま渡すとキューでエラー
- 必ず toArray() または必要なフィールドのみ抽出して渡す

【例】
// ❌ NG（キューでエラー）
$oldReservation = $reservation->replicate();
event(new ReservationChanged($oldReservation, $newReservation));

// ✅ OK（配列で渡す）
$oldReservationData = [
    'id' => $reservation->id,
    'reservation_date' => $reservation->reservation_date,
    'start_time' => $reservation->start_time,
    'menu_id' => $reservation->menu_id,
];
event(new ReservationChanged($oldReservationData, $newReservation));
```

### 4. テストに関する注意

```
【ローカルテスト必須項目】
1. 予約作成 → 通知送信確認
2. 予約変更 → 通知送信確認（2重送信しないこと）
3. 予約キャンセル → 通知送信確認（2重送信しないこと）
4. LINE連携顧客 → LINE送信のみで完了すること
5. LINE未連携顧客 → メール → SMS の順で送信されること
```

---

## ファイル一覧と役割

### 通知サービス

| ファイル | 役割 | 状態 |
|---------|------|------|
| `app/Services/CustomerNotificationService.php` | 顧客通知の統一エントリポイント | ✅ 使用推奨 |
| `app/Services/SimpleLineService.php` | LINE送信（店舗別トークン） | ✅ 使用推奨 |
| `app/Services/LineMessageService.php` | LINE送信（古い実装） | ⚠️ 廃止検討 |
| `app/Services/SmsService.php` | SMS送信（AWS SNS） | ✅ 使用中 |
| `app/Services/EmailService.php` | メール送信（AWS SES） | ✅ 使用中 |
| `app/Services/OtpService.php` | OTP認証 | ✅ 使用中 |
| `app/Services/ReservationConfirmationService.php` | 予約確認専用 | ✅ 使用中 |
| `app/Services/AdminNotificationService.php` | 管理者通知 | ✅ 使用中 |

### イベントリスナー

| ファイル | 役割 | 状態 |
|---------|------|------|
| `app/Listeners/SendCustomerReservationNotification.php` | 予約作成時の顧客通知 | ✅ 正常 |
| `app/Listeners/SendCustomerReservationChangeNotification.php` | 予約変更時の顧客通知 | ❌ 要修正 |
| `app/Listeners/SendCustomerReservationCancellationNotification.php` | 予約キャンセル時の顧客通知 | ❌ 要修正 |
| `app/Listeners/AdminNotificationListener.php` | 管理者通知 | ✅ 正常 |

### コンソールコマンド

| ファイル | 役割 | 状態 |
|---------|------|------|
| `app/Console/Commands/SendLineReminders.php` | LINEリマインダー | ❌ 要修正（DBカラムなし） |
| `app/Console/Commands/SendReservationReminders.php` | SMSリマインダー（MedicalRecordベース） | ⚠️ 別ロジック |
| `app/Console/Commands/SendLineFollowup.php` | LINE 7日/15日フォローアップ | ✅ 正常 |
| `app/Console/Commands/ProcessLineReminders.php` | 未完成 | ❌ 削除候補 |
| `app/Console/Commands/ProcessLineMessages.php` | 未完成 | ❌ 削除候補 |

---

## テスト計画

### ローカルテスト

```bash
# 1. 通知サービステスト
php artisan tinker
>>> $service = app(\App\Services\CustomerNotificationService::class);
>>> $customer = \App\Models\Customer::find(1);
>>> $store = \App\Models\Store::find(1);
>>> $service->sendNotification($customer, $store, 'テストメッセージ', 'test');

# 2. 予約作成テスト
php artisan test:reservation-flow --create

# 3. 予約変更テスト
php artisan test:reservation-flow --change

# 4. 予約キャンセルテスト
php artisan test:reservation-flow --cancel
```

### 本番テスト（デプロイ後）

```bash
# 1. 通知ログ確認
sqlite3 database/database.sqlite "SELECT * FROM notification_logs ORDER BY created_at DESC LIMIT 10;"

# 2. Laravelログ確認
tail -100 storage/logs/laravel.log | grep -E "通知|LINE|SMS|メール"
```

---

## 進捗管理

| Phase | タスク | 担当 | 状態 | 完了日 |
|-------|--------|------|------|--------|
| 1-1 | SendCustomerReservationChangeNotification 修正 | Claude | ✅完了 | 2025-12-09 |
| 1-2 | SendCustomerReservationCancellationNotification 修正 | Claude | ✅完了 | 2025-12-09 |
| 1-3 | Api/ReservationController 修正（3箇所） | Claude | ✅完了 | 2025-12-09 |
| 2-1 | CustomerNotificationService 優先順位変更 | Claude | ✅完了 | 2025-12-09 |
| 3-1 | DBマイグレーション追加 | Claude | ✅完了 | 2025-12-09 |
| 3-2 | SendLineReminders 修正 | Claude | ✅完了 | 2025-12-09 |
| 4-1 | 不要ファイル削除 | - | 未着手 | - |
| 4-2 | LineMessageService 廃止 | - | 未着手 | - |

---

## 参考：通知統計（2025-12-09時点）

```
SMS:   636件（予約確認）、108件（変更）、66件（キャンセル）
LINE:  10件（予約確認成功）、6件（失敗）
Email: 1件（予約確認成功）、3件（失敗）
```

**結論**: SMSが98%以上、LINE移行が急務

---

## 関連ドキュメント

- [CLAUDE.md](CLAUDE.md) - システム全体のドキュメント
- [DEBUGGING-PROTOCOL.md](DEBUGGING-PROTOCOL.md) - デバッグ手順書
