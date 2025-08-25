# CLAUDE.md - 重要な注意事項

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
- **既存のEC2**: xsyumeno-simple (i-03560178f4d1538c5) IP: 13.231.41.238

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