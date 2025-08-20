# 📚 Xsyumeno Laravel移行仕様書

## 概要

このディレクトリには、現在のReact+PHP SlimからLaravel+Filamentへの移行に必要な全ての仕様書が含まれています。

## 📋 仕様書一覧

### [01_DATABASE_DESIGN.md](./01_DATABASE_DESIGN.md)
**データベース設計書**
- Laravel規約に準拠したテーブル設計
- Eloquentリレーション定義
- マイグレーション戦略
- パフォーマンス最適化

### [02_API_SPECIFICATION.md](./02_API_SPECIFICATION.md)
**API仕様書**
- RESTful API設計
- エンドポイント一覧
- リクエスト・レスポンス形式
- エラーハンドリング

### [03_AUTHENTICATION_SYSTEM.md](./03_AUTHENTICATION_SYSTEM.md)
**認証・認可システム設計書**
- Amazon SNS SMS OTP認証
- Laravel Sanctum設定
- Spatie Permission権限管理
- セキュリティ機能

### [04_LARAVEL_ARCHITECTURE_RULES.md](./04_LARAVEL_ARCHITECTURE_RULES.md)
**Laravel開発アーキテクチャルール**
- コーディング規約
- ディレクトリ構造
- 命名規則
- パフォーマンスルール

### [05_FRONTEND_DESIGN.md](./05_FRONTEND_DESIGN.md)
**フロントエンド設計書**
- Blade + Alpine.js設計
- Tailwind CSS設定
- コンポーネントシステム
- レスポンシブデザイン

### [06_FILAMENT_ADMIN_DESIGN.md](./06_FILAMENT_ADMIN_DESIGN.md)
**Filament管理画面設計書**
- Filament 3.x設定
- リソース設計
- ダッシュボードウィジェット
- 権限管理

## 🚀 開発フェーズ

### Phase 1: 基盤構築（3日）
- [ ] Laravelプロジェクト作成
- [ ] 依存関係インストール
- [ ] 環境設定
- [ ] データベースマイグレーション実行

### Phase 2: 認証システム（2日）
- [ ] Amazon SNS設定
- [ ] OTP認証フロー実装
- [ ] Sanctumトークン管理
- [ ] 権限システム設定

### Phase 3: 基本CRUD（4日）
- [ ] 顧客管理機能
- [ ] 店舗管理機能
- [ ] 予約管理機能
- [ ] メニュー管理機能

### Phase 4: 管理画面（3日）
- [ ] Filament設定・カスタマイズ
- [ ] リソース実装
- [ ] ダッシュボード作成
- [ ] ウィジェット実装

### Phase 5: フロントエンド（2日）
- [ ] 顧客向け画面実装
- [ ] Alpine.jsコンポーネント
- [ ] レスポンシブ対応

### Phase 6: テスト・デプロイ（2日）
- [ ] 機能テスト
- [ ] データ移行
- [ ] 本番環境設定

## 🛠️ 必要な技術スタック

### バックエンド
- **Laravel 11**: メインフレームワーク
- **Filament 3**: 管理画面
- **Laravel Sanctum**: API認証
- **Spatie Permission**: 権限管理
- **AWS SDK**: SNS統合

### フロントエンド
- **Blade Templates**: サーバーサイドレンダリング
- **Alpine.js 3**: リアクティブ機能
- **Tailwind CSS 3**: スタイリング
- **Vite**: ビルドツール

### データベース・インフラ
- **MySQL 8.0**: データベース
- **Redis**: キャッシュ・セッション
- **Amazon SNS**: SMS送信

## 📦 Composer依存関係

```bash
composer require laravel/framework
composer require filament/filament
composer require laravel/sanctum
composer require spatie/laravel-permission
composer require aws/aws-sdk-php
```

## 📦 NPM依存関係

```bash
npm install alpinejs @tailwindcss/forms @tailwindcss/typography
npm install --save-dev tailwindcss postcss autoprefixer
```

## 🔧 環境変数設定

```env
# 基本設定
APP_NAME="Xsyumeno"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# データベース
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xsyumeno
DB_USERNAME=root
DB_PASSWORD=

# Amazon SNS
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=ap-northeast-1

# セッション・キャッシュ
SESSION_DRIVER=database
CACHE_DRIVER=redis
QUEUE_CONNECTION=database
```

## 🎯 移行計画

### 1. データ移行戦略
- 現在のMySQLデータベースからエクスポート
- Laravel Seederでテストデータ作成
- 段階的データ移行とテスト

### 2. 機能移行優先順位
1. **認証システム** - 最優先
2. **顧客・予約管理** - コア機能
3. **管理画面** - 業務効率化
4. **レポート・統計** - 最後

### 3. テスト戦略
- ユニットテスト（PHPUnit）
- 機能テスト（Livewire Testing）
- ブラウザテスト（Laravel Dusk）

## 📈 成功指標

- [ ] 全ての既存機能が動作
- [ ] OTP認証が正常動作
- [ ] 管理画面の完全動作
- [ ] レスポンス時間 < 200ms
- [ ] データ移行の完全性確保
- [ ] セキュリティテスト合格

## 📞 開発支援

このドキュメントに基づいて、効率的にLaravel移行を進めることができます。各フェーズで詰まった場合は、該当する仕様書を参照してください。

**重要**: 各仕様書は相互に関連しているため、実装前に全体を把握することを推奨します。

---

**このLaravel移行により、開発効率の大幅な向上と保守性の改善が期待できます。**