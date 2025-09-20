# CLAUDE.md - 重要な注意事項

## 📝 開発メモ（2025-09-05）

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