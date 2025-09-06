# メニュー・サブスクリプション機能 実装記録

## 実装日: 2025-09-05

## 概要
メニューシステムにサブスクリプション機能を統合し、通常メニューと月額プランメニューを一元管理できるように改善しました。

## 主な変更点

### 1. サブスクリプション機能の統合
- 従来の独立したSubscriptionPlanモデルを廃止
- Menuモデルにサブスクリプション機能を統合
- `is_subscription`トグルで通常メニューとサブスクメニューを切り替え

### 2. データベース構造

#### 追加されたカラム (menus テーブル)
```sql
- is_subscription (boolean) - サブスクリプションメニューかどうか
- subscription_monthly_price (integer) - 月額料金
- default_contract_months (integer) - デフォルト契約期間
- max_monthly_usage (integer) - 月間利用回数上限
```

#### 削除されたカラム
```sql
- medical_record_only (boolean) - customer_type_restrictionで代替
- auto_renewal (boolean) - サブスクは自動更新がデフォルトのため削除
- is_popular (boolean) - マーケティング設定として不要と判断
```

### 3. 管理画面の改善

#### メニュータイプ選択
最初にサブスクリプションメニューか通常メニューかを選択する仕組みに変更

#### 条件付き表示ロジック

**サブスクリプションONの場合に表示:**
- サブスクリプション料金設定セクション
  - 月額料金
  - 契約期間
  - 月間利用回数上限

**サブスクリプションOFFの場合に表示:**
- 通常メニュー料金設定セクション
  - 料金
  - 所要時間
- オプションメニュー設定セクション
  - 追加オプションとして提案
  - 提案メッセージ
- サブスク会員限定トグル

**共通で表示される設定:**
- 基本情報（店舗、メニュー名、カテゴリー、説明、画像）
- 表示設定（利用可能、顧客に表示、スタッフ指定必須）
- 予約窓口制限

### 4. 予約窓口制限の明確化

`customer_type_restriction`フィールドの選択肢を改善：
- `all`: 全ての窓口（新規予約・カルテ両方）
- `new`: 新規予約窓口のみ
- `existing`: カルテからの予約のみ

これにより、どの予約経路でメニューを表示するかを明確に制御できます。

### 5. 顧客のサブスクリプション契約管理

CustomerResourceに契約管理セクションを追加：
- 契約メニューの選択（サブスクリプション対応メニューのみ表示）
- 契約開始日
- 請求開始日（別管理）
- 契約期間
- 終了日（自動計算）
- 月間利用回数の追跡

## APIとの連携（今後の実装予定）

### 予約可能メニューの取得
```php
// 新規予約窓口
$menus = Menu::where('customer_type_restriction', 'all')
             ->orWhere('customer_type_restriction', 'new')
             ->where('is_visible_to_customer', true)
             ->get();

// カルテからの予約
$menus = Menu::where('customer_type_restriction', 'all')
             ->orWhere('customer_type_restriction', 'existing')
             ->where('is_visible_to_customer', true)
             ->get();
```

### サブスクリプション会員の判定
```php
// 顧客がサブスク契約を持っているか
$hasSubscription = Subscription::where('customer_id', $customerId)
                               ->where('status', 'active')
                               ->where('end_date', '>=', now())
                               ->exists();

// サブスク限定メニューの表示制御
if (!$hasSubscription) {
    $menus = $menus->where('is_subscription_only', false);
}
```

## マイグレーションファイル

実行済みのマイグレーション：
1. `2025_09_05_130000_add_subscription_to_menus.php` - サブスク機能追加
2. `2025_09_05_132300_add_subscription_fields_to_menus_table.php` - 追加フィールド
3. `2025_09_05_133242_remove_auto_renewal_from_menus_table.php` - 自動更新削除
4. `2025_09_05_133555_remove_medical_record_only_from_menus_table.php` - medical_record_only削除

## 今後の実装予定

1. **フロントエンド連携**
   - 新規予約画面でのメニュー表示ロジック
   - カルテ予約画面でのメニュー表示ロジック
   - サブスク会員向けの専用メニュー表示

2. **サブスクリプション管理機能**
   - 契約更新通知
   - 利用回数の自動カウント
   - 請求管理との連携

3. **レポート機能**
   - サブスク契約状況レポート
   - メニュー別利用統計

## 注意事項

- サブスクリプションメニューと通常メニューは同じMenusテーブルで管理
- SubscriptionPlanResourceは廃止（`shouldRegisterNavigation = false`）
- フロントエンドとの連携時は`customer_type_restriction`を必ず考慮すること