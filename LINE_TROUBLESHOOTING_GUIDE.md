# 🚨 LINE Bot 動作確認ガイド

## 📍 **テストページへのアクセス方法**

1. 管理画面にログイン
2. ブラウザで以下にアクセス：
```
http://localhost:8000/admin/line-test
```
または本番環境：
```
https://reservation.meno-training.com/admin/line-test
```

## ✅ **確認項目チェックリスト**

### 1. LINE Developer Console設定

#### 必須設定項目：
- [ ] **Channel ID**: `2008004739`
- [ ] **Channel Secret**: `25164a304a76f2f86169517751db7c1a`  
- [ ] **Channel Access Token**: 正しく設定されているか

#### Webhook設定：
- [ ] **Webhook URL**: `https://reservation.meno-training.com/api/line/webhook`
- [ ] **Webhook送信**: **オン**
- [ ] **応答メッセージ**: **オフ**（重要！）
- [ ] **あいさつメッセージ**: **オン**

### 2. 環境変数（.env）確認

```env
LINE_CHANNEL_ID=2008004739
LINE_CHANNEL_SECRET=25164a304a76f2f86169517751db7c1a
LINE_CHANNEL_ACCESS_TOKEN=JwkMQSfEm4+FFOQIAurwnIK6WyW+i/ml6z3pR4LMmKMYOEASI8HEG/SxXdhqwZE5DKgQB7YhNSSx+OQmCy4QZu+sOIP7gjfM9Drlw9/SvLYfXJNFyyTaWRVdvjWw2FAZsaleFfgbct3BjuWP5yUNjgdB04t89/1O/w1cDnyilFU=
LINE_ADD_FRIEND_URL=https://line.me/R/ti/p/@415gvduf
```

## 🔍 **動作テスト手順**

### ステップ1: LINE Bot接続テスト
1. テストページの「LINE Bot接続テスト」ボタンをクリック
2. 成功すれば Bot名とIDが表示される
3. 失敗の場合はAccess Tokenを確認

### ステップ2: テストメッセージ送信
1. LINE登録済み顧客を選択
2. テストメッセージを入力
3. 「送信テスト」ボタンをクリック
4. LINEアプリでメッセージを確認

### ステップ3: 友だち追加テスト
1. QRコード: `https://line.me/R/ti/p/@415gvduf`
2. 友だち追加後、ウェルカムメッセージが届くか確認

## 📅 **メッセージが送信されるタイミング**

| メッセージ種類 | 送信タイミング | 条件 |
|------------|------------|-----|
| ウェルカムメッセージ | 友だち追加の即時 | 新規LINE登録 |
| 予約リマインダー | 前日の10:00 | 予約ステータス = `confirmed` |
| 来店お礼 | 来店2-3時間後 | 予約ステータス = `completed` |

## ❌ **よくある問題と解決方法**

### 問題1: メッセージが届かない
**原因**: Webhook URLが正しく設定されていない
**解決**: 
1. LINE Developer Consoleで Webhook URLを確認
2. HTTPSである必要がある（本番環境）
3. 応答メッセージを**オフ**にする

### 問題2: 友だち追加してもウェルカムメッセージが来ない  
**原因**: Webhookが動作していない
**解決**:
1. LINE Developer Consoleで「Webhook送信」を**オン**
2. ログファイルを確認: `storage/logs/laravel.log`

### 問題3: リマインダーが送信されない
**原因**: Cronジョブが設定されていない
**解決**:
```bash
# サーバーのcrontabに追加
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

### 問題4: LINE登録したのにデータベースに記録されない
**原因**: Webhookエンドポイントのエラー
**解決**:
1. ログ確認: `grep "LINE" storage/logs/laravel.log`
2. Webhook検証が失敗していないか確認

## 🛠️ **手動テストコマンド**

```bash
# リマインダーをテスト送信（実際には送らない）
php artisan line:send-reminders --test

# 強制的にリマインダー送信（時間制限無視）
php artisan line:send-reminders --force

# フォローアップメッセージテスト
php artisan line:send-followup --test

# スケジュール確認
php artisan schedule:list
```

## 📊 **データベース確認SQL**

```sql
-- LINE登録済み顧客を確認
SELECT * FROM customers WHERE line_user_id IS NOT NULL;

-- 今日送信されたメッセージを確認
SELECT * FROM line_message_logs WHERE DATE(sent_at) = CURDATE();

-- 明日の予約でリマインダー対象を確認
SELECT * FROM reservations 
WHERE DATE(reservation_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
AND status = 'confirmed'
AND reminder_sent_at IS NULL;
```

## 📞 **それでも解決しない場合**

1. **ログを確認**:
```bash
tail -f storage/logs/laravel.log | grep LINE
```

2. **LINE Developer Console**:
- Messaging API設定を再確認
- Channel Access Tokenを再生成して更新

3. **本番環境の場合**:
- SSL証明書が有効か確認
- ファイアウォールがLINE APIをブロックしていないか確認

---

💡 **ヒント**: まずはテストページで「LINE Bot接続テスト」を実行してください。これが成功すれば基本的な設定は正しいです！