# CLAUDE.md - システムドキュメント

> **最終更新**: 2025-10-31
> **システム名**: 目のトレーニング予約管理システム (METORE)

---

## 📋 目次

1. [重要な注意事項](#重要な注意事項)
2. [システム概要](#システム概要)
3. [主要機能一覧](#主要機能一覧)
4. [データモデル構成](#データモデル構成)
5. [サービスクラス一覧](#サービスクラス一覧)
6. [本番環境情報](#本番環境情報)
7. [開発メモ](#開発メモ)

---

## 🚨 重要な注意事項

### エラー修正時の必須ルール（2025-10-25追加）

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

#### 1. JavaScript関数重複による500エラー（2025-10-27, 2025-10-31）
- **症状**: `modifyReservation()` / `goToReservation()` が2回定義されLivewire初期化失敗 → 全画面500エラー
- **影響**: 本番環境で全ユーザーがアクセス不可（約30分）
- **原因**: コード追加時に既存の関数定義を確認せずマージ
- **対策**: Bladeファイル編集時は必ず `grep -n "function functionName"` で重複確認すること
- **教訓**: **同じバグが2回発生 - 手動チェックは必ず漏れる**

#### 2. SQL曖昧性エラー（2025-10-20～24）
- **症状**: 3回修正したが、最初の2回は対症療法で根本解決せず
- **教訓**: `select()` と `pluck()` の組み合わせを理解していなかった
- **解決**: pluck()に任せる（select()削除）

#### 3. メール通知重複バグ（2025-10-06～2025-10-29）
- **症状**: 同じメールアドレスに同じ内容の通知が2通届く
- **解決**: メールアドレスベースの重複除去 `$admins->unique('email')`

---

## 📖 システム概要

### システムの目的
目のトレーニングサロンの予約・顧客管理・売上管理を一元化するシステム

### 技術スタック
- **バックエンド**: Laravel 11.x + Filament 3.x
- **フロントエンド**: Livewire 3.x + Tailwind CSS
- **データベース**: SQLite（本番環境）
- **外部連携**:
  - AWS SNS (SMS送信)
  - AWS SES (メール送信)
  - LINE Messaging API
  - J-Payment（決済連携）※予定

### アーキテクチャ
```
┌─────────────────┐
│  顧客向けWeb    │  ← マイページ・予約フォーム
└────────┬────────┘
         │
┌────────▼────────┐
│  管理者向けWeb  │  ← Filament管理画面
└────────┬────────┘
         │
┌────────▼────────┐
│   Laravel API   │  ← RESTful API
└────────┬────────┘
         │
┌────────▼────────┐
│ SQLite Database │
└─────────────────┘
```

---

## 🎯 主要機能一覧

### 1. 予約管理 ⭐⭐⭐
**基本機能:**
- オンライン予約フォーム（ステップ形式）
- 店舗・コース・日時選択
- タイムライン表示（ドラッグ&ドロップ対応）
- 予約変更・キャンセル
- 予約ブロック（休業日設定）

**拡張機能（2025-10追加）:**
- ✅ **予約ライン（席番号）管理** - 席ごとの予約管理
- ✅ タイムライン日付選択UI改善
- ✅ 予約詳細モーダル拡張
- ✅ フォーム入力中のブラウザバック警告

**主要モデル:**
- `Reservation` - 予約本体
- `ReservationLine` - 予約ライン（席）
- `ReservationLineSchedule` - ライン別スケジュール
- `BlockedTimePeriod` - 予約ブロック

**管理画面:**
- `ReservationResource` - 予約管理
- `ReservationLineResource` - 予約ライン管理
- `BlockedTimePeriodResource` - ブロック管理

---

### 2. 顧客管理 ⭐⭐⭐
**基本機能:**
- 顧客情報登録・編集
- 予約履歴管理
- カルテ（医療記録）管理
- OTP認証（SMS/メール）

**拡張機能（2025-10追加）:**
- ✅ **要注意顧客自動判定システム** - リスクレベル自動判定・アラート表示
- ✅ **顧客画像管理** - 写真アップロード・ギャラリー表示
- ✅ 顧客統合（マージ）機能
- ✅ 顧客インポート機能
- ✅ マイページ予約時の店舗自動選択
- ✅ 店舗変更機能（OTP認証付き）

**主要モデル:**
- `Customer` - 顧客情報
- `CustomerImage` - 顧客画像
- `CustomerAccessToken` - アクセストークン
- `OtpVerification` - OTP認証
- `CustomerLabel` - 顧客ラベル（要注意フラグなど）

**サービス:**
- `CustomerMergeService` - 顧客統合
- `CustomerImportService` - 顧客インポート
- `CustomerNotificationService` - 顧客通知

---

### 3. サブスクリプション管理 ⭐⭐⭐
**基本機能:**
- サブスク契約管理
- 月額プラン設定
- 契約更新・解約

**拡張機能（2025-10追加）:**
- ✅ **サブスク契約の削除機能**（未使用契約のみ）
- ✅ サブスク契約サイクル基準化
- ✅ メニュー変更UX大幅改善
- ✅ サブスク判定ロジックの大幅改善
- ✅ 決済失敗アラート最適化
- ✅ ステータス管理の厳格化（必須＆デフォルト有効）

**主要モデル:**
- `CustomerSubscription` - サブスク契約
- `SubscriptionPlan` - サブスクプラン
- `SubscriptionPauseHistory` - 停止履歴

**サービス:**
- `SubscriptionService` - サブスク管理

**管理画面:**
- `CustomerSubscriptionResource` - 契約管理
- `SubscriptionPlanResource` - プラン管理

---

### 4. 回数券管理 ⭐⭐ NEW（2025-10追加）
**機能:**
- 回数券プラン作成（5回券、10回券など）
- 顧客への回数券販売
- 残り回数の自動カウント
- 使用履歴の追跡
- 予約時に回数券を自動消費

**主要モデル:**
- `CustomerTicket` - 顧客が購入した回数券
- `TicketPlan` - 回数券プラン
- `TicketUsageHistory` - 使用履歴

**管理画面:**
- `CustomerTicketResource` - 回数券管理
- `TicketPlanResource` - プラン設定

---

### 5. 売上管理 ⭐⭐⭐
**基本機能:**
- 売上計上
- 日次精算
- 売上レポート

**大幅改善（2025-10）:**
- ✅ **ワンタップ計上** - タイムラインから直接計上可能
- ✅ **完全可逆** - 計上後も編集・削除可能
- ✅ **税込み表示への変更**
- ✅ 日次精算に割引機能追加
- ✅ 日付移動ボタン追加
- ✅ オプション・物販の編集対応
- ✅ サブスク判定の自動化
- ✅ スタッフID取得ロジック改善

**主要モデル:**
- `Sale` - 売上本体
- `SaleItem` - 売上明細
- `DailyClosing` - 日次精算

**サービス:**
- `SalePostingService` - 売上計上ロジック

**管理画面:**
- `SaleResource` - 売上管理
- `DailyClosingResource` - 日次精算

---

### 6. カルテ（医療記録）管理 ⭐⭐
**基本機能:**
- カルテ作成・編集
- 視力測定記録
- 施術履歴

**拡張機能（2025-10追加）:**
- ✅ **カルテ作成時の自動入力機能**
- ✅ **カルテ対応者のテキスト入力機能**
- ✅ **予約なしでカルテ作成可能**
- ✅ **カルテ画像のアップロード機能**
- ✅ 老眼測定機能（グラフ表示）

**主要モデル:**
- `MedicalRecord` - カルテ本体
- `MedicalRecordImage` - カルテ画像
- `PresbyopiaMeasurement` - 老眼測定データ

**管理画面:**
- `MedicalRecordResource` - カルテ管理
- `ImagesRelationManager` - 画像管理

---

### 7. お知らせ管理 ⭐ NEW（2025-10追加）
**機能:**
- 店舗ごとのお知らせ配信
- 既読/未読管理
- 既読状況タブで確認可能

**主要モデル:**
- `Announcement` - お知らせ本体
- `AnnouncementRead` - 既読状況

**管理画面:**
- `AnnouncementResource` - お知らせ作成・管理
- `ReadsRelationManager` - 既読状況確認

---

### 8. 通知システム ⭐⭐
**基本機能:**
- SMS送信（AWS SNS）
- メール送信（AWS SES）
- LINE通知

**大幅改善（2025-10）:**
- ✅ **通知履歴システム** - 全通知の送信履歴を記録
- ✅ SMS/メール重複送信の修正
- ✅ 初回SMS、再送時SMS+メール
- ✅ **メール件名に認証コード表示**
- ✅ 予約キャンセル通知
- ✅ 予約変更通知
- ✅ 管理者への重複通知修正
- ✅ メールフォールバック機能

**主要モデル:**
- `NotificationLog` - 通知送信履歴

**サービス:**
- `SmsService` - SMS送信
- `EmailService` - メール送信
- `LineMessageService` - LINE通知
- `CustomerNotificationService` - 顧客通知
- `AdminNotificationService` - 管理者通知
- `ReservationConfirmationService` - 予約確認通知

**管理画面:**
- `NotificationLogResource` - 通知ログ閲覧

---

### 9. シフト管理 ⭐⭐
**基本機能:**
- スタッフシフト登録
- シフトパターン設定

**拡張機能（2025-10追加）:**
- ✅ **シフト編集機能の大幅拡張**
- ✅ 一括シフト作成機能
- ✅ シフト重複エラーメッセージ改善

**主要モデル:**
- `Shift` - シフト
- `ShiftPattern` - シフトパターン

**管理画面:**
- `ShiftResource` - シフト管理
- `CreateBulkShifts` - 一括作成

---

### 10. Claudeヘルプチャット ⭐ NEW（2025-10追加）
**機能:**
- 右下のヘルプチャットでClaudeに質問可能
- 会話履歴の記録
- システムドキュメントを参照した回答

**主要モデル:**
- `HelpChatLog` - チャットログ

**サービス:**
- `ClaudeHelpService` - Claude API連携

**管理画面:**
- `HelpChatLogResource` - チャットログ管理

---

## 📦 データモデル構成

### 顧客関連
```
Customer (顧客)
├── CustomerSubscription (サブスク契約)
├── CustomerTicket (回数券)
├── CustomerImage (画像)
├── CustomerLabel (ラベル)
├── Reservation (予約)
├── MedicalRecord (カルテ)
├── PointCard (ポイントカード)
└── CustomerAccessToken (アクセストークン)
```

### 予約関連
```
Reservation (予約)
├── ReservationLine (予約ライン・席)
├── ReservationOption (予約オプション)
├── Sale (売上)
└── MedicalRecord (カルテ)

ReservationLine (予約ライン)
├── ReservationLineSchedule (スケジュール)
└── ReservationLineAssignment (割り当て)
```

### 売上関連
```
Sale (売上)
├── SaleItem (売上明細)
├── Reservation (予約)
└── CustomerSubscription (サブスク契約)
```

### メニュー関連
```
MenuCategory (メニューカテゴリ)
└── Menu (メニュー)
    └── MenuOption (オプション)
```

### 通知関連
```
NotificationLog (通知ログ)
└── Customer (顧客)

OtpVerification (OTP認証)
└── Customer (顧客)
```

### その他
```
Store (店舗)
├── StoreManager (店舗管理者)
├── Reservation (予約)
└── Shift (シフト)

User (スタッフ)
├── Shift (シフト)
└── Sale (売上)

Announcement (お知らせ)
└── AnnouncementRead (既読)
```

---

## 🛠️ サービスクラス一覧

### 顧客・通知系
| サービス | 役割 |
|---------|------|
| `CustomerNotificationService` | 顧客への通知（SMS/メール） |
| `AdminNotificationService` | 管理者への通知 |
| `ReservationConfirmationService` | 予約確認通知 |
| `CustomerMergeService` | 顧客統合処理 |
| `CustomerImportService` | 顧客データインポート |

### 通信系
| サービス | 役割 |
|---------|------|
| `SmsService` | SMS送信（AWS SNS） |
| `EmailService` | メール送信（AWS SES） |
| `LineMessageService` | LINE通知送信 |
| `LineTokenVerificationService` | LINE認証 |
| `SimpleLineService` | シンプルLINE送信 |

### 認証系
| サービス | 役割 |
|---------|------|
| `OtpService` | OTP生成・検証 |
| `PasswordResetService` | パスワードリセット |

### ビジネスロジック系
| サービス | 役割 |
|---------|------|
| `SalePostingService` | 売上計上処理 |
| `SubscriptionService` | サブスク管理 |
| `ReservationLineService` | 予約ライン管理 |
| `ReservationContextService` | 予約コンテキスト管理 |

### その他
| サービス | 役割 |
|---------|------|
| `ClaudeHelpService` | Claudeヘルプチャット |
| `MarketingAnalyticsService` | マーケティング分析 |

---

## 🌐 本番環境情報

### URL
- **顧客向けサイト**: https://reservation.meno-training.com/
- **管理画面**: https://reservation.meno-training.com/admin/login
- **SSL証明書**: Let's Encrypt（自動更新設定済み）

### 管理者ログイン
- **メール**: `admin@eye-training.com`
- **パスワード**: `password`
- **メール2**: `naoki@yumeno-marketing.jp`
- **パスワード2**: `Takahashi5000`

### インフラ
- **サーバー**: AWS EC2 (Ubuntu)
- **インスタンスID**: i-061a146fcb1cc54ae
- **IPアドレス**: 54.64.54.226
- **データベース**: SQLite
- **Webサーバー**: Nginx + PHP 8.3-FPM

---

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

### ⚠️ 絶対にやってはいけないこと
- プロファイル未指定でのAWS操作
- 既存の本番環境リソースの削除・変更
- 確認なしでのリソース作成

### 過去のミス（2025-08-25）
- 間違ったアカウント（221082183439）でリソースを作成してしまった
- 原因：AWS_PROFILEを指定せずにデフォルトプロファイルを使用した

---

## 📝 開発メモ

### ✅ 解決済み：メール通知重複バグ（2025-10-06調査開始 → 2025-10-29解決）

**問題**: 同じメールアドレスに同じ内容の通知が2通届いていた

**解決内容**:
1. **2025-10-23（commit: 307fe9d7）**: 最初の重複対策
   - `store_managers`から取得時に`unique()`を追加

2. **2025-10-29（commit: ca66914b）**: 完全な解決
   - **メールアドレスベースの重複除去を追加**
   ```php
   // app/Services/AdminNotificationService.php (176-177行目)
   $uniqueAdmins = $admins->unique('email')->values();
   ```
   - 同じメールアドレスを持つ複数アカウントに対して2通送信されていた問題を解決
   - デバッグログも追加して送信先メールアドレスを追跡可能に

**現在の状態**: ✅ 正常動作（1通のみ送信される）

---

### 視力関連の用語
- **裸眼矯正** → 正しくは以下の3分類：
  - **裸眼**: メガネ・コンタクトなしの視力
  - **矯正**: メガネ・コンタクト使用時の視力
  - **老眼**: 老眼鏡使用

---

### J-Payment決済連携（2025-09-19）
**ステータス**: 🟡 準備中（未実装）

- **決済用Webhook URL**: `/api/webhook/jpayment/payment`
- **サブスク用Webhook URL**: `/api/webhook/jpayment/subscription`
- **連携先**: 株式会社ロボットペイメント
- **実装時の注意**: 上記URLでWebhook受信エンドポイントを作成すること

---

### オープン前のTODO
- **権限エラー時のUX改善**（本番環境のみ）
  - 403/404/500エラー時にトースト通知＋トップへリダイレクト
  - 開発環境では詳細エラー表示を維持（デバッグ優先）

---

### 定期実行タスク（Cron）
- **Laravelスケジューラー**: 毎分実行
- **キューワーカー**: バックグラウンドで常時実行
- **サブスク更新**: 月次バッチ（予定）

---

## 🔗 関連ドキュメント

- [DEBUGGING-PROTOCOL.md](DEBUGGING-PROTOCOL.md) - デバッグ手順書
- [DEPLOY_GUIDE.md](DEPLOY_GUIDE.md) - デプロイガイド
- [AWS_SNS_PRODUCTION_GUIDE.md](AWS_SNS_PRODUCTION_GUIDE.md) - AWS SNS設定ガイド
- [LINE_INTEGRATION_DEPLOYMENT_SUMMARY.md](LINE_INTEGRATION_DEPLOYMENT_SUMMARY.md) - LINE連携ガイド

---

**最終更新日**: 2025-10-31
**ドキュメント管理者**: Claude Code
