# デプロイ手順

## 最も簡単な方法（推奨）

1. **AWS Consoleにログイン**
2. **EC2 → インスタンス → i-03560178f4d1538c5 を選択**
3. **「接続」ボタン → EC2 Instance Connect**
4. **以下のコマンドを実行：**

```bash
curl -s https://raw.githubusercontent.com/Naoki-Takahashi0420/metore_system/main/deploy-manual.sh | bash
```

完了！

## なぜ手動？

- このEC2は**セキュリティ重視設計**で外部SSHを拒否
- GitHub ActionsなどのCI/CDツールは外部サービスなので接続不可
- EC2 Instance Connectは**AWS内部接続**なので可能

## これで十分な理由

- シンプル
- 確実に動く
- 余計な設定不要
- セキュリティも保たれる

---

**サイトURL:** http://13.158.240.0
