# リマインダー重複送信バグ修正 - 確認TODO

## 修正日時
- **2026-01-04 12:24 JST** にデプロイ完了

## 問題の概要
- 顧客にリマインダーメール/SMSが6〜10回重複送信されていた
- 秋葉原店から報告あり

## 根本原因
1. `Reservation.php` の `$fillable` に `reminder_sent_at` 等が含まれておらず、更新が無視されていた
2. `NotificationLog.php` の `idempotency_key` が毎分変わる形式（YmdHi）だったため、重複チェックが機能していなかった

## 修正内容
- `app/Models/Reservation.php`: `$fillable` に6カラム追加
- `app/Models/NotificationLog.php`: idempotency_keyを日付ベース（Ymd）に変更

---

## ⏰ 明日（2026-01-05）確認すること

### 10:00〜10:15 JST頃に確認

以下のGitHub Actionsワークフローを実行：

```bash
gh workflow run check-notification-logs.yml
```

または GitHub Actions画面から `Check Notification Logs` を実行

### 確認ポイント

1. **重複送信がないこと**
   - 同じ予約IDに対して1回しか送信されていないこと
   - `send_count` が1であること

2. **idempotency_keyが新形式であること**
   - 旧: `reservation_reminder:予約ID:顧客ID:no-user:202601051000:email` (12桁)
   - 新: `reservation_reminder:予約ID:顧客ID:no-user:20260105` (8桁)

3. **reminder_sent_atが更新されていること**
   - 送信済みの予約の `reminder_sent_at` がNULLでないこと

### 問題がなければ
このファイルを削除してOK

### 問題があれば
再度調査が必要 → Claude Codeで確認
