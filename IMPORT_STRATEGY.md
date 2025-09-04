# 顧客データインポート対策

## 現在の問題
- インポートした顧客データが管理画面で表示できない
- 循環参照エラーやLivewire JSONパースエラーが発生
- 数百件単位のインポートで同様の問題が予想される

## 次回インポート時の対策

### 1. データ検証とクリーニング
```bash
# インポート前の必須チェック項目
- 必須フィールドの存在確認
- 文字エンコーディングの統一（UTF-8）
- 電話番号・メールアドレスの形式確認
- 重複データのチェック
```

### 2. 段階的インポート
```bash
# 小さなバッチでテスト
php artisan import:customers --batch-size=10 --test-mode
# 問題がない場合のみ全量実行
php artisan import:customers --batch-size=50
```

### 3. インポート後の検証
```bash
# インポート直後の整合性チェック
php artisan customers:validate-imported
# 表示テスト
php artisan customers:test-display
```

### 4. フェイルセーフ機能
- インポート失敗時のロールバック機能
- 問題のあるレコードのスキップ機能
- 詳細なログ出力

## 緊急対応案（現在のデータ用）

### Option A: 問題データの特定と修正
```sql
-- 表示できない顧客の特定
SELECT id, last_name, first_name, created_at 
FROM customers 
WHERE id IN (7, 9) -- 問題のあるID
```

### Option B: 新規インポート
- 現在の問題データを削除
- クリーンなデータで再インポート
- 適切な検証を経てインポート実行

## 推奨アプローチ
1. **現在**: 問題データは一旦スキップ
2. **短期**: インポートコマンドの作成と検証機能の追加
3. **長期**: 自動インポート機能の構築

## 実装すべき機能
- `php artisan make:command ImportCustomersCommand`
- データ検証ルールの追加
- インポート進捗の表示
- エラーハンドリングの強化