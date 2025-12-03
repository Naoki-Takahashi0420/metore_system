# FC本部機能 大規模改修要件書

> **作成日**: 2025-11-28
> **バージョン**: 1.0
> **ステータス**: 要件定義完了・実装準備中

---

## 📋 目次

1. [改修概要](#改修概要)
2. [要件一覧](#要件一覧)
3. [実装優先順位](#実装優先順位)
4. [データベース設計](#データベース設計)
5. [画面設計](#画面設計)
6. [権限制御設計](#権限制御設計)
7. [実装手順](#実装手順)
8. [チェックリスト](#チェックリスト)

---

## 🎯 改修概要

### 背景
FC本部と加盟店間の業務効率化を図るため、お知らせ機能の拡張、発送管理システム、請求書システムの大規模改修を実施する。

### 目標
- **本部業務の効率化**: 発送・請求業務の自動化
- **加盟店業務の透明化**: 請求内容の可視化・追跡可能性の向上
- **権限管理の厳格化**: FC店舗側の不要な操作権限を制限

### 影響範囲
- お知らせページ（`/admin/announcements-list`）
- FC発注管理システム
- FC請求書システム
- 権限制御ロジック

---

## 📝 要件一覧

### 1. お知らせページの改善 ⭐⭐⭐

#### 現在の状況
- 単一のお知らせリストのみ
- フィルタ機能：`all`, `unread`, `read`

#### 改修内容
```
┌─────────────────────────────────────┐
│ お知らせ管理                          │
├─────────────────────────────────────┤
│ [お知らせ] [発注通知] ← タブ追加      │
├─────────────────────────────────────┤
│ タブ1: 通常のお知らせ                 │
│ - 一般的な連絡事項                    │
│ - 本部からの重要通知                  │
│ - システムメンテナンス情報など          │
├─────────────────────────────────────┤
│ タブ2: 発注通知                      │
│ - 発送完了通知                       │
│ - 請求書発行通知                     │
│ - 支払い関連通知                     │
│ - 在庫関連通知                       │
└─────────────────────────────────────┘
```

#### 技術仕様
- `Announcement`モデルに`type`カラム追加：`general`（一般）, `order`（発注関連）
- フロントエンドにタブ機能実装
- 既存のフィルタ機能は両タブで維持

---

### 2. 発送管理システム ⭐⭐⭐

#### 現在の状況
- FC側で発送ボタンが表示されている
- 在庫管理が不適切

#### 改修内容

##### A. 権限制御の厳格化
```php
// FC店舗側：発送ボタンを非表示
// 本部側のみ：発送ボタンを表示
if ($user->store && $user->store->isHeadquarters()) {
    // 発送ボタン表示
} else {
    // 発送ボタン非表示
}
```

##### B. 在庫管理の改善
- FC店舗の在庫数を強制的に0に設定
- 本部からの発送により在庫が増加
- 発送時に納品書自動生成

##### C. 締め日サイクルの実装
```
前半サイクル: 1日～15日
├─ 注文締切: 15日
├─ 発送実行: 16日～月末
└─ 納品書同封: 発送時に自動生成

後半サイクル: 16日～月末
├─ 注文締切: 月末
├─ 発送実行: 翌月1日～15日
└─ 納品書同封: 発送時に自動生成
```

##### D. 部分発送機能
- 複数商品注文時の一部先行発送対応
- 発送済み・未発送の明確な区別
- 発送履歴の詳細追跡

---

### 3. 請求書システム ⭐⭐⭐⭐

#### 改修内容

##### A. 請求書発行タイミング
```
毎月1日 00:00
├─ 「請求書発送」ボタンが本部画面に表示
├─ 前月末までの発送済み商品のみが請求対象
├─ 部分発送の場合は発送済み分のみ請求
└─ 未発送分は翌月以降の請求に持ち越し
```

##### B. 請求書フォーマット（スプレッドシート風）
```
┌─────────────────────────────────────┐
│ 株式会社〇〇 御中                      │
│ 振込先: [店舗管理から自動取得]           │
├─────────────────────────────────────┤
│ 項目       │数量│単価    │金額      │備考│
├─────────────────────────────────────┤
│ 商品A      │ 10 │ 1,000  │ 10,000  │    │
│ 商品B      │  5 │ 2,000  │ 10,000  │    │
│ ロイヤリティ │  1 │ 50,000 │ 50,000  │月額│
│ 広告費     │  1 │ 10,000 │ 10,000  │    │
├─────────────────────────────────────┤
│ 小計                   │ 80,000      │
│ 消費税(10%)            │  8,000      │
│ 合計                   │ 88,000      │
└─────────────────────────────────────┘
```

##### C. カスタム項目機能
- **ロイヤリティ**: 月額固定費
- **システム使用料**: システム利用料金
- **広告費**: 販促・マーケティング費用
- **その他**: 自由入力項目
- **値引き**: マイナス金額の入力可能

##### D. 編集機能（スプレッドシート風UI）
- 行の追加・削除
- 項目名・数量・単価の直接編集
- 自動計算（数量×単価＝金額）
- 合計・税額の自動更新

---

### 4. 権限制御の強化 ⭐⭐⭐

#### A. FC店舗側の制限
```php
// 非表示・無効化する機能
- 発送ボタン（発注管理画面）
- 請求書削除ボタン（請求書管理画面）
- 在庫数変更（商品管理画面）
- 発送履歴編集（発送管理画面）
```

#### B. 本部側の権限
```php
// 本部のみ実行可能な機能
- 商品発送処理
- 請求書発行・編集・削除
- 在庫数調整
- 発送履歴管理
- カスタム項目追加・編集
```

#### C. 権限チェック強化
```php
// Policy クラスの拡張
class FcOrderPolicy
{
    public function ship(User $user): bool
    {
        return $user->hasRole('super_admin') || 
               ($user->store && $user->store->isHeadquarters());
    }
}

class FcInvoicePolicy  
{
    public function delete(User $user): bool
    {
        return $user->hasRole('super_admin') || 
               ($user->store && $user->store->isHeadquarters());
    }
}
```

---

## 🏆 実装優先順位

### Phase 1: 基盤整備（優先度: 最高）
1. **データベース設計・マイグレーション作成** - 2日
2. **権限制御の強化** - 1日
3. **お知らせページのタブ機能** - 1日

### Phase 2: 発送管理システム（優先度: 高）
4. **FC側発送ボタン非表示化** - 0.5日
5. **在庫管理ロジック改修** - 1日
6. **締め日サイクル実装** - 2日
7. **部分発送機能** - 2日

### Phase 3: 請求書システム（優先度: 高）
8. **請求書フォーマット改修** - 2日
9. **スプレッドシート風編集UI** - 3日
10. **カスタム項目機能** - 2日
11. **自動請求書発行機能** - 1日

### Phase 4: テスト・最適化（優先度: 中）
12. **単体テスト作成** - 2日
13. **統合テスト実施** - 1日
14. **UI/UX最適化** - 1日

**合計予定工数**: 18.5日

---

## 🗄️ データベース設計

### 1. `announcements`テーブル拡張

```sql
ALTER TABLE announcements ADD COLUMN type VARCHAR(20) DEFAULT 'general';
-- 'general': 一般お知らせ, 'order': 発注関連通知

-- インデックス追加
CREATE INDEX idx_announcements_type ON announcements(type);
CREATE INDEX idx_announcements_type_published ON announcements(type, published_at);
```

### 2. `fc_orders`テーブル拡張

```sql
ALTER TABLE fc_orders ADD COLUMN shipping_cycle VARCHAR(20);
-- 'first_half': 前半(1-15日), 'second_half': 後半(16-月末)

ALTER TABLE fc_orders ADD COLUMN cut_off_date DATE;
-- 締切日

ALTER TABLE fc_orders ADD COLUMN scheduled_shipping_date DATE;
-- 発送予定日
```

### 3. `fc_order_items`テーブル拡張

```sql
ALTER TABLE fc_order_items ADD COLUMN shipped_quantity INTEGER DEFAULT 0;
-- 発送済み数量

ALTER TABLE fc_order_items ADD COLUMN shipping_status VARCHAR(20) DEFAULT 'pending';
-- 'pending': 未発送, 'partial': 部分発送, 'completed': 発送完了
```

### 4. 新テーブル: `fc_invoice_items`

```sql
CREATE TABLE fc_invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fc_invoice_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(50) NOT NULL, -- 'product', 'royalty', 'system_fee', 'advertising', 'custom'
    item_name VARCHAR(255) NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fc_invoice_id) REFERENCES fc_invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_items_invoice_id (fc_invoice_id),
    INDEX idx_invoice_items_sort_order (fc_invoice_id, sort_order)
);
```

### 5. 新テーブル: `fc_shipping_cycles`

```sql
CREATE TABLE fc_shipping_cycles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cycle_name VARCHAR(50) NOT NULL, -- 'first_half_2025_11', 'second_half_2025_11'
    year INTEGER NOT NULL,
    month INTEGER NOT NULL,
    cycle_type VARCHAR(20) NOT NULL, -- 'first_half', 'second_half'
    cut_off_date DATE NOT NULL,
    shipping_start_date DATE NOT NULL,
    shipping_end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'closed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cycle (year, month, cycle_type),
    INDEX idx_shipping_cycles_date (year, month, cycle_type)
);
```

---

## 🎨 画面設計

### 1. お知らせページ改修

#### ファイル: `/admin/announcements-list`

```html
<!-- タブナビゲーション -->
<div class="tabs-navigation">
    <button class="tab-button active" data-tab="general">
        📢 お知らせ
    </button>
    <button class="tab-button" data-tab="order">
        📦 発注通知
    </button>
</div>

<!-- タブコンテンツ -->
<div id="general-tab" class="tab-content active">
    <!-- 既存のお知らせ一覧 -->
</div>

<div id="order-tab" class="tab-content hidden">
    <!-- 発注関連通知一覧 -->
</div>
```

### 2. 請求書編集画面（スプレッドシート風）

#### ファイル: `/admin/fc-invoices/{id}/edit`

```html
<div class="invoice-editor">
    <!-- ヘッダー情報 -->
    <div class="invoice-header">
        <h2>請求書 {{ $invoice->invoice_number }}</h2>
        <div class="store-info">
            {{ $invoice->fcStore->name }} 御中<br>
            振込先: {{ $invoice->headquartersStore->bank_info }}
        </div>
    </div>

    <!-- スプレッドシート風テーブル -->
    <div class="spreadsheet-container">
        <table class="editable-table">
            <thead>
                <tr>
                    <th width="300">項目</th>
                    <th width="80">数量</th>
                    <th width="120">単価</th>
                    <th width="120">金額</th>
                    <th width="200">備考</th>
                    <th width="60">操作</th>
                </tr>
            </thead>
            <tbody id="invoice-items">
                <!-- 動的に行が追加される -->
            </tbody>
        </table>
        
        <!-- 行追加ボタン -->
        <button class="add-row-btn">+ 行を追加</button>
    </div>

    <!-- 集計エリア -->
    <div class="summary-section">
        <table class="summary-table">
            <tr>
                <td>小計</td>
                <td class="amount">¥<span id="subtotal">0</span></td>
            </tr>
            <tr>
                <td>消費税(10%)</td>
                <td class="amount">¥<span id="tax">0</span></td>
            </tr>
            <tr class="total-row">
                <td><strong>合計</strong></td>
                <td class="amount"><strong>¥<span id="total">0</span></strong></td>
            </tr>
        </table>
    </div>
</div>
```

### 3. 発送管理画面の権限制御

#### FC店舗側表示（発送ボタン非表示）
```html
<div class="order-status">
    <span class="status-badge pending">発送待ち</span>
    <small>本部にて発送準備中です</small>
</div>
```

#### 本部側表示（発送ボタン表示）
```html
<div class="shipping-actions">
    <button class="ship-btn primary">
        📦 発送完了
    </button>
    <button class="partial-ship-btn secondary">
        📦 部分発送
    </button>
</div>
```

---

## 🔐 権限制御設計

### 1. ロール定義
```php
// 既存ロール
'super_admin'      // 全権限
'admin'            // 管理者
'staff'            // スタッフ
'fc_admin'         // FC管理者（新設）
'fc_staff'         // FCスタッフ（新設）
```

### 2. 権限マトリックス

| 機能 | super_admin | admin | staff | fc_admin | fc_staff |
|------|-------------|-------|-------|----------|----------|
| 商品発送 | ✅ | ✅ | ❌ | ❌ | ❌ |
| 請求書発行 | ✅ | ✅ | ❌ | ❌ | ❌ |
| 請求書削除 | ✅ | ✅ | ❌ | ❌ | ❌ |
| 在庫調整 | ✅ | ✅ | ❌ | ❌ | ❌ |
| 請求書閲覧 | ✅ | ✅ | ✅ | ✅ | ✅ |
| 発注依頼 | ✅ | ✅ | ✅ | ✅ | ✅ |
| お知らせ閲覧 | ✅ | ✅ | ✅ | ✅ | ✅ |

### 3. Policy クラス拡張

#### `app/Policies/FcOrderPolicy.php`
```php
public function ship(User $user, FcOrder $order): bool
{
    return $user->hasRole('super_admin') || 
           ($user->store && $user->store->isHeadquarters());
}

public function partialShip(User $user, FcOrder $order): bool
{
    return $this->ship($user, $order);
}
```

#### `app/Policies/FcInvoicePolicy.php`
```php
public function delete(User $user, FcInvoice $invoice): bool
{
    return $user->hasRole('super_admin') || 
           ($user->store && $user->store->isHeadquarters());
}

public function addCustomItem(User $user, FcInvoice $invoice): bool
{
    return $this->delete($user, $invoice);
}
```

---

## 🛠️ 実装手順

### Step 1: データベースマイグレーション
```bash
php artisan make:migration add_type_to_announcements_table
php artisan make:migration add_shipping_cycle_to_fc_orders_table
php artisan make:migration add_shipped_quantity_to_fc_order_items_table
php artisan make:migration create_fc_invoice_items_table
php artisan make:migration create_fc_shipping_cycles_table
```

### Step 2: モデル拡張
```php
// app/Models/Announcement.php
public function scopeGeneral($query) {
    return $query->where('type', 'general');
}

public function scopeOrder($query) {
    return $query->where('type', 'order');
}

// app/Models/FcOrder.php  
public function shippedItems() {
    return $this->hasMany(FcOrderItem::class)
                ->where('shipping_status', 'completed');
}

public function partiallyShippedItems() {
    return $this->hasMany(FcOrderItem::class)
                ->where('shipping_status', 'partial');
}

// app/Models/FcInvoice.php
public function items() {
    return $this->hasMany(FcInvoiceItem::class)->orderBy('sort_order');
}

public function productItems() {
    return $this->items()->where('item_type', 'product');
}

public function customItems() {
    return $this->items()->where('item_type', 'custom');
}
```

### Step 3: コントローラー作成
```bash
php artisan make:controller FcShippingController
php artisan make:controller FcInvoiceItemController
```

### Step 4: Livewire コンポーネント作成
```bash
php artisan make:livewire FcInvoiceEditor
php artisan make:livewire FcShippingManager
php artisan make:livewire AnnouncementTabs
```

### Step 5: ポリシー作成
```bash
php artisan make:policy FcOrderPolicy
php artisan make:policy FcInvoicePolicy
```

### Step 6: コマンド作成（自動化処理）
```bash
php artisan make:command GenerateMonthlyInvoices
php artisan make:command ProcessShippingCycles
```

---

## ✅ 実装チェックリスト

### Phase 1: 基盤整備
- [ ] マイグレーションファイル作成・実行
- [ ] モデルクラス拡張
- [ ] ポリシークラス作成
- [ ] 権限チェックロジック実装
- [ ] お知らせページのタブ機能実装

### Phase 2: 発送管理システム
- [ ] FC側発送ボタン非表示化
- [ ] 本部側発送機能実装
- [ ] 在庫管理ロジック改修
- [ ] 締め日サイクル自動計算
- [ ] 部分発送機能実装
- [ ] 発送履歴トラッキング

### Phase 3: 請求書システム
- [ ] `FcInvoiceItem`モデル作成
- [ ] スプレッドシート風編集UI実装
- [ ] カスタム項目追加・編集機能
- [ ] 自動計算ロジック（小計・税額・合計）
- [ ] 請求書PDF生成機能拡張
- [ ] 月次自動請求書発行コマンド

### Phase 4: UI/UX最適化
- [ ] レスポンシブデザイン対応
- [ ] アクセシビリティ対応
- [ ] ローディング状態の表示
- [ ] エラーハンドリング強化

### Phase 5: テスト
- [ ] 単体テスト作成（PHPUnit）
- [ ] 統合テスト作成（Feature Test）
- [ ] ブラウザテスト作成（Laravel Dusk/Playwright）
- [ ] 権限テスト作成
- [ ] パフォーマンステスト

### Phase 6: ドキュメント・運用
- [ ] ユーザーマニュアル作成
- [ ] 運用手順書作成
- [ ] 障害対応手順書作成
- [ ] データバックアップ手順確認
- [ ] 本番環境デプロイ手順確認

---

## 🚨 リスク・注意事項

### 1. データ整合性
- **リスク**: 既存請求書データとの互換性
- **対策**: マイグレーション前のデータバックアップ必須

### 2. 権限制御
- **リスク**: 権限設定ミスによる不正操作
- **対策**: 段階的ロールアウト、十分なテスト実施

### 3. パフォーマンス
- **リスク**: スプレッドシート風UIの動作重量化
- **対策**: 仮想スクロール、遅延読み込み実装

### 4. ユーザビリティ
- **リスク**: 新UIへの適応コスト
- **対策**: 段階的リリース、ユーザートレーニング実施

---

## 📈 成功指標（KPI）

### 1. 業務効率性
- 発送処理時間: 50%削減
- 請求書作成時間: 70%削減
- 手動エラー件数: 80%削減

### 2. ユーザー満足度
- 本部スタッフ満足度: 4.0/5.0以上
- FC店舗満足度: 4.0/5.0以上
- システム利用率: 95%以上

### 3. システム安定性
- システム稼働率: 99.9%以上
- レスポンス時間: 平均2秒以下
- エラー発生率: 0.1%以下

---

**最終更新**: 2025-11-28  
**作成者**: Claude Code  
**承認者**: [未定]  
**次回レビュー予定**: 2025-12-05