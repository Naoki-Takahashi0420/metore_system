# CLAUDE.md - 重要な注意事項

## 🚨 エラー修正時の必須ルール（2025-10-25追加）

**エラーが発生したら、必ず以下を実行してください：**

1. **`/DEBUGGING-PROTOCOL.md` を読む** - 根本原因分析の手順書
2. **5 Whys分析を実施** - 表面的な原因で満足しない
3. **過去の履歴を確認** - `git log` で同じエラーの修正履歴を調査
4. **全体を検索** - 同じパターンがないか `grep/Grep` で確認
5. **複数案を比較** - 最低3つの解決策を検討してから実装

### ❌ 絶対にやってはいけないこと
- エラーログだけ見て即修正（根本原因を理解せずに対症療法）
- 1箇所だけ修正して他を放置
- 「とりあえず動けばOK」思考
- 修正後にドキュメント化しない

### 過去の失敗例
- **SQL曖昧性エラー**: 3回修正したが、最初の2回は対症療法で根本解決せず（2025-10-20～24）

---

## 📝 開発メモ（2025-09-05）

### 🚨 重要：メール通知重複バグ調査中（2025-10-06）

**問題**: 同じメールアドレスに同じ内容の通知が2通届く

**調査状況**:
1. デバッグログを本番環境にデプロイ済み（commit: 6141fb9）
2. 次回予約発生時に以下のログが記録される：
   - 🔍 getStoreAdmins result: 通知対象の管理者リスト（user_id付き）
   - Email notification check: 各ユーザーの通知設定
   - 📧 Sending email: 実際のメール送信ログ

**ログ確認方法**:
```bash
# GitHub Actionsワークフローを使用
gh workflow run "Check Latest Logs"
gh run watch $(gh run list --workflow="Check Latest Logs" --limit 1 --json databaseId -q '.[0].databaseId')
gh run view <RUN_ID> --log | grep "🔍\|📧"
```

**確認すべきポイント**:
1. `🔍 [DEBUG] getStoreAdmins result` のログで、同じuser_idが2回含まれていないか？
2. `📧 [DEBUG] Sending email` のログで、同じemailに2回送信していないか？
3. もし同じuser_idが2回あれば → `getStoreAdmins()` メソッドの重複除去ロジック（154行目の`unique()`）が機能していない
4. もし異なるuser_idで同じemailなら → 同じメールアドレスを持つユーザーが複数存在している

**修正候補の場所**:
- `app/Services/AdminNotificationService.php` の `getStoreAdmins()` メソッド（122-157行目）
- 特に154行目: `$uniqueAdminIds = $adminIds->unique()->filter();`

**次のアクション**:
1. 本番環境で予約が発生するのを待つ
2. ログを確認して重複の原因を特定
3. 原因に応じて修正を実施

---

### 視力関連の用語
- **裸眼矯正** → 正しくは以下の3分類：
  - **裸眼**: メガネ・コンタクトなしの視力
  - **矯正**: メガネ・コンタクト使用時の視力
  - **老眼**: 老眼鏡使用

### サブスクリプションの構成変更予定
- 現在の構成を見直し必要
- 詳細は後日確定

### J-Payment決済連携（2025-09-19）
- **決済用Webhook URL**: `/api/webhook/jpayment/payment`
- **サブスク用Webhook URL**: `/api/webhook/jpayment/subscription`
- 株式会社ロボットペイメントとの連携
- 実装時は上記URLでWebhook受信エンドポイントを作成すること

### オープン前のTODO
- **権限エラー時のUX改善**（本番環境のみ）
  - 403/404/500エラー時にトースト通知＋トップへリダイレクト
  - 開発環境では詳細エラー表示を維持（デバッグ優先）

---

## 本番環境情報
- **URL**: https://reservation.meno-training.com/
- **管理画面**: https://reservation.meno-training.com/admin/login
- **SSL証明書**: Let's Encrypt（自動更新設定済み）
- **管理者ログイン**:
  - メール: `admin@eye-training.com`
  - パスワード: `password`
  - メール2: `naoki@yumeno-marketing.jp`
  - パスワード2: `Takahashi5000`

## 🚨 AWS操作時の厳守事項

### 必ずAWSプロファイルを指定する
**デフォルトプロファイルを使用しないこと！**

```bash
# 正しい使い方 - 必ずプロファイルを指定
export AWS_PROFILE=xsyumeno
aws ec2 describe-instances

# または
aws ec2 describe-instances --profile xsyumeno
```

### 現在のAWSアカウント情報
- **正しいアカウント**: 273021981629 (metore_system)
- **プロファイル名**: xsyumeno
- **既存のEC2**: xsyumeno-ssh-enabled (i-061a146fcb1cc54ae) IP: 54.64.54.226
- **IAMユーザー**: xsyumeno-admin
- **アクセスキーID**: AKIAT7ELA2O6SBMIY5EP
- **設定日**: 2025-09-12

### 操作前の確認事項
1. 必ず現在のプロファイルを確認
```bash
aws configure list
aws sts get-caller-identity
```

2. アカウントIDが 2730-2198-1629 であることを確認

3. 既存のリソースを削除・変更する前に必ず確認

## ⚠️ 絶対にやってはいけないこと
- プロファイル未指定でのAWS操作
- 既存の本番環境リソースの削除・変更
- 確認なしでのリソース作成

## 過去のミス（2025-08-25）
- 間違ったアカウント（221082183439）でリソースを作成してしまった
- 原因：AWS_PROFILEを指定せずにデフォルトプロファイルを使用した