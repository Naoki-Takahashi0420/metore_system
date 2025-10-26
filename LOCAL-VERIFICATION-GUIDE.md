# ローカル検証ガイド

## 準備完了事項

✅ 本番DBをローカルにインポート済み（1034件の予約、2524人の顧客）
✅ .envでSMS/メール送信を無効化済み
✅ キャッシュクリア済み

## 🎯 検証目的

以下の2つの問題を本番データで再現・検証します：

### 問題1: タイムライン表示バグ
- **現象**: 10:45-11:30のような中途半端な時刻の予約がタイムライン上で表示されない
- **修正内容**: `@if(floor($reservation['start_slot']) == $index)` に変更
- **期待動作**: 10:45開始の予約が正しく表示される

### 問題2: 日付ズレ問題
- **現象**: 11/2を選択して予約すると、11/3で保存される（4回発生）
- **修正内容**: タイムゾーン処理の明示化 + 詳細ログ追加
- **期待動作**: 選択した日付で正しく保存される

---

## 📋 検証手順

### ステップ1: ローカルサーバー起動

```bash
cd /Applications/MAMP/htdocs/Xsyumeno-main
php artisan serve
```

### ステップ2: 管理画面にログイン

1. ブラウザで開く: http://localhost:8000/admin/login
2. ログイン情報:
   - Email: `admin@eye-training.com`
   - Password: `password`

### ステップ3: ダッシュボードのタイムラインを開く

1. ダッシュボードに移動
2. タイムラインウィジェットを確認
3. **ブラウザのコンソールを開く**（重要！）
   - Chrome: F12 → Console タブ
   - Safari: Option+Cmd+C

---

## 🔍 検証項目

### 検証A: タイムライン表示の確認

#### A-1. 既存予約の表示確認

**手順**:
1. 適当な日付を選択（例: 11/2）
2. コンソールで `🔍 [RESERVATION DISPLAY]` ログを確認

**確認ポイント**:
```javascript
// コンソールに表示されるログ
🔍 [RESERVATION DISPLAY] {
  reservation_id: xxx,
  customer: "xxx様",
  start_slot: 1.5,          // 10:45開始の予約
  start_slot_floor: 1,
  index: 1,
  should_display: true,     // ← これがtrue
  old_condition: false      // ← これがfalse（旧コードでは表示されなかった）
}
```

**期待結果**:
- `old_condition: false` かつ `should_display: true` のログがある
  → 今まで表示されていなかった予約が修正で表示されるようになった

#### A-2. タイムライン上の視覚確認

**手順**:
1. 席1に10:45-11:30の予約が表示されているか確認
2. その時間帯が空きではなく、予約で埋まっているか確認

**期待結果**:
- 10:45-11:30の時間帯に予約ブロックが表示される
- 顧客名とメニューが表示される

---

### 検証B: 予約重複チェックの確認

#### B-1. 同じ席への予約試行

**手順**:
1. 席1に10:45-11:30の予約がある状態で
2. 席1の11:00-12:00に新規予約を入れようとする
3. タイムラインの11:00のセルをクリック

**確認ポイント（コンソールログ）**:
```javascript
Conflict check for reservation creation {
  line_type: "main",
  line_number: 1,           // 席1
  conflicting_count: 1,     // 1件の重複を検出
  conflicting_reservations: [{
    id: xxx,
    time: "10:45-11:30",
    seat_number: 1,
    line_number: 1
  }]
}
```

**期待結果**:
- エラーメッセージ: 「予約が重複しています」
- 重複する予約の時刻と顧客名が表示される

#### B-2. 別の席への予約試行

**手順**:
1. 席1に10:45-11:30の予約がある状態で
2. **席2**の11:00-12:00に新規予約を入れようとする

**確認ポイント（コンソールログ）**:
```javascript
Conflict check for reservation creation {
  line_type: "main",
  line_number: 2,           // 席2
  conflicting_count: 0,     // 重複なし
  conflicting_reservations: []
}
```

**期待結果**:
- 予約作成が成功する
- エラーが出ない

---

### 検証C: 日付ズレ問題の確認

#### C-1. 予約作成時の日付ログ確認

**手順**:
1. タイムラインで11/2を選択
2. 11:00のセルをクリックしてモーダルを開く
3. 顧客とメニューを選択
4. **作成ボタンをクリックする前に**、コンソールログを確認

**確認ポイント（モーダルを開いた時）**:
```javascript
🚨 [DATE DEBUG] openNewReservationFromSlot called {
  selectedDate_before_assignment: "2025-11-02",
  selectedDate_type: "string",
  // ...
}

🚨 [DATE DEBUG] newReservation initialized {
  selectedDate: "2025-11-02",
  newReservation_date: "2025-11-02",
  are_they_same: true,
  // ...
}
```

**確認ポイント（作成ボタンクリック時）**:
```javascript
🚨 [DATE DEBUG] createReservation called {
  raw_date_value: "2025-11-02",    // または "2025-11-03"？
  date_type: "string",             // または "object"？
  date_is_carbon: false,           // または true？
  selectedDate_widget: "2025-11-02",
  normalized_date: "2025-11-02",   // または "2025-11-03"？
  // ...
}
```

**期待結果**:
- 全ての日付値が「11-02」で一致している
- もし「11-03」になっている箇所があれば、どの段階で変わったかを特定

#### C-2. データベースで保存された日付を確認

**手順**:
```bash
php artisan tinker --execute="
\App\Models\Reservation::orderBy('id', 'desc')
    ->first(['id', 'reservation_date', 'created_at']);
"
```

**期待結果**:
- `reservation_date` が選択した日付（11-02）と一致

---

## 📊 確認すべきコンソールログ一覧

### 1. タイムライン表示ログ
```
🔍 [RESERVATION DISPLAY]
```
→ 各予約がなぜ表示される/されないかの判定

### 2. 日付デバッグログ
```
🚨 [DATE DEBUG] openNewReservationFromSlot called
🚨 [DATE DEBUG] newReservation initialized
🚨 [DATE DEBUG] createReservation called
```
→ 日付がどの段階で変わるかを追跡

### 3. 重複チェックログ
```
Conflict check for reservation creation
```
→ どの予約と重複判定されたか

### 4. Reservationモデルログ
```
Creating reservation:
```
→ データベース保存直前の日付

---

## ⚠️ 注意事項

1. **個人情報**: 本番データを使用しているため、画面キャプチャ時は個人情報をマスクしてください
2. **通知無効化**: .envで通知を無効化済みですが、念のため確認
3. **テストデータ**: 検証で作成した予約は、後でDBをロールバックすれば削除できます

---

## 🎉 検証が成功したら

以下を確認してデプロイ判断：

✅ タイムライン表示: 10:45開始の予約が正しく表示される
✅ 予約重複チェック: 同じ席のみをチェックし、別の席は予約可能
✅ 日付ズレ: 選択した日付で正しく保存される（またはログで原因特定）

---

## 📞 問題が発生したら

コンソールログをスクリーンショットで共有してください。
特に重要なのは：
- `🔍 [RESERVATION DISPLAY]` で `old_condition: false, should_display: true`
- `🚨 [DATE DEBUG]` で日付が変わっている箇所
