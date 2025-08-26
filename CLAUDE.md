# CLAUDE.md - 重要な注意事項

## 🎉 CI/CD稼働完了！（2025-08-26）

### 本番環境情報
- **URL**: http://54.64.54.226/
- **管理画面**: http://54.64.54.226/admin/login
- **管理者ログイン**:
  - メール: `admin@eye-training.com`
  - パスワード: `password`

### デプロイ方法
```bash
# ローカルで開発・修正後
git add -A && git commit -m "feat: 新機能追加"
git push

# GitHub Actionsが自動でデプロイ（または手動実行）
gh workflow run deploy-simple.yml
```

### 使用するワークフロー
- **`deploy-simple.yml`のみ使用すること**
- 他のワークフローは使わない（アーカイブ済み）

### EC2情報（絶対に削除・変更しないこと）
- **インスタンス名**: xsyumeno-ssh-enabled
- **インスタンスID**: i-061a146fcb1cc54ae
- **IP**: 54.64.54.226
- **SSHキー**: ~/.ssh/xsyumeno-20250826-095948.pem
- **PHP**: 8.3
- **DB**: SQLite

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
- **正しいアカウント**: 2730-2198-1629 (metore_system)
- **プロファイル名**: xsyumeno
- **既存のEC2**: xsyumeno-ssh-enabled (i-061a146fcb1cc54ae) IP: 54.64.54.226

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