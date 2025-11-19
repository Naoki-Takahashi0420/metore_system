# FC本部管理システム - 技術仕様書

**作成日**: 2025-11-17
**バージョン**: 1.0
**システム名**: METORE FC本部管理システム
**フレームワーク**: Laravel 11.x + Filament 3.x + Livewire 3.x

---

## 目次

1. [プロジェクト概要](#1-プロジェクト概要)
2. [既存システム流用分析](#2-既存システム流用分析)
3. [データベース設計](#3-データベース設計)
4. [システムアーキテクチャ](#4-システムアーキテクチャ)
5. [画面設計](#5-画面設計)
6. [APIエンドポイント](#6-apiエンドポイント)
7. [権限設計（RBAC）](#7-権限設計rbac)
8. [業務フロー](#8-業務フロー)
9. [実装計画](#9-実装計画)
10. [技術的決定事項](#10-技術的決定事項)

---

## 1. プロジェクト概要

### 1.1 目的
目のトレーニング全国FC本部の業務を一元管理するシステム。FC加盟店からの商品発注、受注処理、発送、請求書発行、入金管理を効率化する。

### 1.2 主要業務フロー
```
FC加盟店 → 商品発注
   ↓
本部: 受注確認 → 承認 → 発送処理
   ↓
本部: 請求書発行 → PDF送付
   ↓
FC店舗: 入金
   ↓
本部: 入金確認（手動）
```

### 1.3 ユーザーロール
- **本部スタッフ** (Headquarters Staff): 発注承認、発送処理、請求書発行
- **本部マネージャー** (Headquarters Manager): 全権限 + レポート閲覧
- **FC店舗バイヤー** (FC Buyer): 発注作成、履歴閲覧
- **FC店舗マネージャー** (FC Manager): 全店舗操作 + 請求書確認

---

## 2. 既存システム流用分析

### 2.1 流用可能コンポーネント（開発時間を大幅短縮）

| カテゴリ | 既存コンポーネント | 流用率 | 流用方法 | 節約効果 |
|---------|------------------|-------|---------|---------|
| **認証** | Spatie Permission | 95% | ロール追加のみ | 8時間 |
| **ユーザー管理** | User モデル | 95% | そのまま使用 | 6時間 |
| **店舗管理** | Store モデル | 90% | typeフィールド追加 | 4時間 |
| **売上計算ロジック** | SalePostingService | 85% | パターン複製 | 12時間 |
| **通知基盤** | SmsService, EmailService | 90% | そのまま使用 | 10時間 |
| **Filament基盤** | 既存リソースパターン | 80% | 構造複製 | 16時間 |

**合計節約時間**: 約56時間（7人日相当）

### 2.2 新規開発が必要なコンポーネント

| カテゴリ | コンポーネント | 理由 | 工数 |
|---------|--------------|------|------|
| **モデル** | FcOrder, FcOrderItem | 発注専用ステータス管理 | 4時間 |
| **モデル** | FcProduct, FcProductCategory | 商品カタログ専用 | 3時間 |
| **モデル** | FcInvoice, FcPayment | 請求書・入金管理 | 4時間 |
| **サービス** | FcOrderService | 発注ワークフロー | 6時間 |
| **サービス** | FcInvoiceService | 請求書生成・PDF | 8時間 |
| **リソース** | FC専用Filamentリソース | 5画面 | 12時間 |

**合計開発時間**: 約37時間（4.6人日）

### 2.3 参照のみ（コピーしない）

- `Reservation` - 予約管理（ドメインが異なる）
- `Customer` - 顧客管理（FC店舗≠顧客）
- `MedicalRecord` - カルテ管理（関連なし）
- `Shift` - シフト管理（関連なし）

---

## 3. データベース設計

### 3.1 ER図（主要テーブル）

```
┌─────────────┐       ┌──────────────┐
│   stores    │       │    users     │
│  (EXTEND)   │       │  (REUSE)     │
├─────────────┤       ├──────────────┤
│ id          │←──────│ store_id     │
│ type ★NEW   │       │ name         │
│ parent_id ★ │       │ role         │
│ name        │       │ email        │
│ code        │       └──────────────┘
│ address     │
└─────────────┘
      │
      │ fc_store_id
      ▼
┌─────────────┐       ┌──────────────┐
│  fc_orders  │       │ fc_products  │
│   (NEW)     │       │   (NEW)      │
├─────────────┤       ├──────────────┤
│ id          │       │ id           │
│ order_number│       │ sku          │
│ fc_store_id │       │ name         │
│ headquarters│       │ unit_price   │
│ order_date  │       │ stock_qty    │
│ status      │       │ category_id  │
│ total_amount│       │ is_active    │
└─────────────┘       └──────────────┘
      │                      │
      │ fc_order_id          │ fc_product_id
      ▼                      ▼
┌──────────────┐      ┌──────────────┐
│fc_order_items│      │fc_product_   │
│   (NEW)      │      │ categories   │
├──────────────┤      │   (NEW)      │
│ id           │      ├──────────────┤
│ fc_order_id  │      │ id           │
│ fc_product_id│      │ name         │
│ quantity     │      │ description  │
│ unit_price   │      └──────────────┘
│ total_amount │
└──────────────┘
      │
      │ fc_order_id
      ▼
┌──────────────┐      ┌──────────────┐
│ fc_invoices  │      │ fc_payments  │
│   (NEW)      │      │   (NEW)      │
├──────────────┤      ├──────────────┤
│ id           │      │ id           │
│ invoice_no   │      │ payment_no   │
│ fc_order_id  │      │ fc_invoice_id│
│ fc_store_id  │      │ amount       │
│ total_amount │      │ status       │
│ paid_amount  │      │ reference_no │
│ status       │      └──────────────┘
│ pdf_path     │
└──────────────┘
```

### 3.2 テーブル定義

#### 3.2.1 stores テーブル（拡張）
```sql
ALTER TABLE stores ADD COLUMN type ENUM('headquarters', 'salon', 'fc_store') DEFAULT 'salon';
ALTER TABLE stores ADD COLUMN parent_store_id BIGINT UNSIGNED NULLABLE;
ALTER TABLE stores ADD COLUMN order_cutoff_time TIME NULLABLE;
ALTER TABLE stores ADD COLUMN delivery_address TEXT NULLABLE;
```

#### 3.2.2 fc_product_categories テーブル（新規）
```sql
CREATE TABLE fc_product_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULLABLE,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 3.2.3 fc_products テーブル（新規）
```sql
CREATE TABLE fc_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    barcode VARCHAR(50) NULLABLE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULLABLE,
    unit_price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 10.00,
    stock_quantity INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    category_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NULLABLE,
    is_active BOOLEAN DEFAULT TRUE,
    status ENUM('available', 'discontinued', 'out_of_stock') DEFAULT 'available',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES fc_product_categories(id),
    INDEX idx_sku (sku),
    INDEX idx_category (category_id),
    INDEX idx_status (status)
);
```

#### 3.2.4 fc_orders テーブル（新規）
```sql
CREATE TABLE fc_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    fc_store_id BIGINT UNSIGNED NOT NULL,
    headquarters_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULLABLE,
    order_date TIMESTAMP NOT NULL,
    requested_delivery_date DATE NULLABLE,
    actual_delivery_date DATE NULLABLE,

    -- 金額
    subtotal DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,

    -- 支払い
    payment_method ENUM('bank_transfer', 'credit_card', 'cash', 'other') DEFAULT 'bank_transfer',
    payment_status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',

    -- ステータスワークフロー
    status ENUM('draft', 'pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled', 'returned') DEFAULT 'draft',

    -- 承認
    approved_by BIGINT UNSIGNED NULLABLE,
    approved_at TIMESTAMP NULLABLE,
    shipped_at TIMESTAMP NULLABLE,
    delivered_at TIMESTAMP NULLABLE,

    notes TEXT NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (fc_store_id) REFERENCES stores(id),
    FOREIGN KEY (headquarters_id) REFERENCES stores(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_store_date (fc_store_id, order_date),
    INDEX idx_status (status),
    INDEX idx_payment (payment_status)
);
```

#### 3.2.5 fc_order_items テーブル（新規）
```sql
CREATE TABLE fc_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fc_order_id BIGINT UNSIGNED NOT NULL,
    fc_product_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 10.00,
    tax_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    notes TEXT NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (fc_order_id) REFERENCES fc_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (fc_product_id) REFERENCES fc_products(id)
);
```

#### 3.2.6 fc_invoices テーブル（新規）
```sql
CREATE TABLE fc_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    fc_order_id BIGINT UNSIGNED NOT NULL,
    fc_store_id BIGINT UNSIGNED NOT NULL,
    headquarters_id BIGINT UNSIGNED NOT NULL DEFAULT 1,

    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,

    status ENUM('draft', 'issued', 'sent', 'viewed', 'partial_paid', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',

    issued_by BIGINT UNSIGNED NULLABLE,
    issued_at TIMESTAMP NULLABLE,
    sent_at TIMESTAMP NULLABLE,
    viewed_at TIMESTAMP NULLABLE,
    paid_at TIMESTAMP NULLABLE,

    pdf_path VARCHAR(255) NULLABLE,
    notes TEXT NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (fc_order_id) REFERENCES fc_orders(id),
    FOREIGN KEY (fc_store_id) REFERENCES stores(id),
    FOREIGN KEY (headquarters_id) REFERENCES stores(id),
    FOREIGN KEY (issued_by) REFERENCES users(id),
    INDEX idx_store_date (fc_store_id, issue_date),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);
```

#### 3.2.7 fc_payments テーブル（新規）
```sql
CREATE TABLE fc_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(20) UNIQUE NOT NULL,
    fc_invoice_id BIGINT UNSIGNED NOT NULL,
    fc_order_id BIGINT UNSIGNED NOT NULL,

    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('bank_transfer', 'credit_card', 'cash', 'check', 'other') DEFAULT 'bank_transfer',
    status ENUM('pending', 'confirmed', 'failed', 'refunded') DEFAULT 'pending',

    reference_number VARCHAR(100) NULLABLE,
    bank_name VARCHAR(100) NULLABLE,
    account_info VARCHAR(255) NULLABLE,

    recorded_by BIGINT UNSIGNED NULLABLE,
    confirmed_by BIGINT UNSIGNED NULLABLE,
    confirmed_at TIMESTAMP NULLABLE,

    notes TEXT NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (fc_invoice_id) REFERENCES fc_invoices(id),
    FOREIGN KEY (fc_order_id) REFERENCES fc_orders(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    FOREIGN KEY (confirmed_by) REFERENCES users(id),
    INDEX idx_invoice (fc_invoice_id),
    INDEX idx_date (payment_date),
    INDEX idx_status (status)
);
```

---

## 4. システムアーキテクチャ

### 4.1 ディレクトリ構造

```
app/
├── Models/
│   ├── FcOrder.php              ★ NEW (Sale.phpベース)
│   ├── FcOrderItem.php          ★ NEW (SaleItem.phpベース)
│   ├── FcProduct.php            ★ NEW
│   ├── FcProductCategory.php    ★ NEW
│   ├── FcInvoice.php            ★ NEW
│   ├── FcPayment.php            ★ NEW
│   ├── Store.php                EXTEND (type追加)
│   └── User.php                 REUSE
│
├── Services/
│   ├── FcOrderService.php       ★ NEW (SalePostingServiceパターン)
│   ├── FcInvoiceService.php     ★ NEW
│   ├── FcPaymentService.php     ★ NEW
│   ├── FcNotificationService.php ★ NEW
│   ├── SmsService.php           REUSE
│   └── EmailService.php         REUSE
│
├── Filament/
│   └── Resources/
│       ├── FcOrderResource.php          ★ NEW
│       ├── FcProductResource.php        ★ NEW
│       ├── FcProductCategoryResource.php ★ NEW
│       ├── FcInvoiceResource.php        ★ NEW
│       ├── FcPaymentResource.php        ★ NEW
│       └── FcDashboard.php              ★ NEW
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── FcOrderController.php   ★ NEW
│   │       ├── FcProductController.php ★ NEW
│   │       └── FcInvoiceController.php ★ NEW
│   └── Requests/
│       ├── CreateFcOrderRequest.php    ★ NEW
│       ├── UpdateFcOrderRequest.php    ★ NEW
│       └── RecordFcPaymentRequest.php  ★ NEW
│
└── Policies/
    ├── FcOrderPolicy.php        ★ NEW
    ├── FcProductPolicy.php      ★ NEW
    └── FcInvoicePolicy.php      ★ NEW

database/
└── migrations/
    ├── 2025_11_17_000001_add_type_to_stores_table.php        ★ NEW
    ├── 2025_11_17_000002_create_fc_product_categories_table.php ★ NEW
    ├── 2025_11_17_000003_create_fc_products_table.php        ★ NEW
    ├── 2025_11_17_000004_create_fc_orders_table.php          ★ NEW
    ├── 2025_11_17_000005_create_fc_order_items_table.php     ★ NEW
    ├── 2025_11_17_000006_create_fc_invoices_table.php        ★ NEW
    └── 2025_11_17_000007_create_fc_payments_table.php        ★ NEW

resources/
└── views/
    ├── pdf/
    │   └── fc_invoice.blade.php ★ NEW (請求書PDFテンプレート)
    └── emails/
        ├── fc_order_confirmation.blade.php  ★ NEW
        ├── fc_order_approved.blade.php      ★ NEW
        ├── fc_order_shipped.blade.php       ★ NEW
        └── fc_invoice_issued.blade.php      ★ NEW
```

### 4.2 モデル関連図

```php
// FcOrder.php
class FcOrder extends Model
{
    public function fcStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'fc_store_id');
    }

    public function headquarters(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'headquarters_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FcOrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(FcInvoice::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(FcPayment::class, FcInvoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

// FcOrderItem.php
class FcOrderItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(FcOrder::class, 'fc_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FcProduct::class, 'fc_product_id');
    }

    // SaleItem.phpから流用
    public function calculateAmount(): void
    {
        $subtotal = $this->unit_price * $this->quantity - $this->discount_amount;
        $this->tax_amount = round($subtotal * ($this->tax_rate / 100), 2);
        $this->total_amount = $subtotal + $this->tax_amount;
    }
}

// FcProduct.php
class FcProduct extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(FcProductCategory::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(FcOrderItem::class, 'fc_product_id');
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function needsReorder(): bool
    {
        return $this->stock_quantity <= $this->reorder_level;
    }
}

// FcInvoice.php
class FcInvoice extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(FcOrder::class, 'fc_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FcPayment::class, 'fc_invoice_id');
    }

    public function getRemainingAmountAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->total_amount;
    }

    public function isOverdue(): bool
    {
        return !$this->isFullyPaid() && $this->due_date < now();
    }
}
```

---

## 5. 画面設計

### 5.1 本部管理画面（Headquarters Admin）

#### ダッシュボード
```
┌─────────────────────────────────────────────┐
│  FC本部管理ダッシュボード                      │
├─────────────────────────────────────────────┤
│  ┌─────────┐  ┌─────────┐  ┌─────────┐     │
│  │ 発注待ち │  │ 発送待ち │  │ 未入金   │     │
│  │   12件   │  │   5件   │  │  ¥350K  │     │
│  └─────────┘  └─────────┘  └─────────┘     │
│                                              │
│  ┌───────────────────────────────────────┐  │
│  │ 最近の発注                              │  │
│  │ FC001 銀座店   ¥45,000  承認待ち      │  │
│  │ FC002 渋谷店   ¥32,000  発送処理中     │  │
│  │ FC003 新宿店   ¥58,000  配送完了       │  │
│  └───────────────────────────────────────┘  │
│                                              │
│  ┌───────────────────────────────────────┐  │
│  │ 入金催促アラート                         │  │
│  │ ⚠ FC005 横浜店  ¥120,000  期限超過5日  │  │
│  │ ⚠ FC007 大阪店  ¥85,000   期限超過2日  │  │
│  └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

**機能一覧:**
- [ ] 店舗別の発注件数
- [ ] ステータス別件数（発送待ち/発送済み/未入金）
- [ ] 今月の売上グラフ
- [ ] 期限超過アラート
- [ ] クイックアクション（承認/発送処理）

#### 商品管理
```
┌─────────────────────────────────────────────┐
│  商品管理                          [新規登録] │
├─────────────────────────────────────────────┤
│  検索: [________] カテゴリ: [全て▼]          │
│                                              │
│  ┌──────┬───────┬──────┬──────┬────────┐  │
│  │ SKU  │ 商品名 │ 単価 │ 在庫 │ 状態   │  │
│  ├──────┼───────┼──────┼──────┼────────┤  │
│  │P001  │目薬A   │¥2,000│ 150  │有効    │  │
│  │P002  │視力検査│¥5,500│  25  │⚠低在庫 │  │
│  │P003  │トレーニ│¥12,000│  0  │❌在庫切 │  │
│  └──────┴───────┴──────┴──────┴────────┘  │
│                                              │
│  [編集] [在庫調整] [販売停止]                  │
└─────────────────────────────────────────────┘
```

**機能一覧:**
- [ ] 商品CRUD
- [ ] SKU/バーコード管理
- [ ] 在庫数管理
- [ ] 低在庫アラート
- [ ] カテゴリフィルター
- [ ] CSV一括インポート/エクスポート

#### 発注管理
```
┌─────────────────────────────────────────────┐
│  発注管理                                    │
├─────────────────────────────────────────────┤
│  ステータス: [全て▼] 店舗: [全て▼] 期間: [  ] │
│                                              │
│  ┌───────┬───────┬────────┬────────┬─────┐ │
│  │発注No │ FC店舗 │ 金額   │ 状態   │操作 │ │
│  ├───────┼───────┼────────┼────────┼─────┤ │
│  │FC001  │銀座店  │¥45,000 │承認待ち│[承認]│ │
│  │FC002  │渋谷店  │¥32,000 │発送待ち│[発送]│ │
│  │FC003  │新宿店  │¥58,000 │配送完了│[請求]│ │
│  └───────┴───────┴────────┴────────┴─────┘ │
│                                              │
│  [詳細表示] [一括承認] [請求書作成]            │
└─────────────────────────────────────────────┘
```

**機能一覧:**
- [ ] 全店舗の発注一覧
- [ ] ステータスフィルター（draft/pending/approved/processing/shipped/delivered）
- [ ] 発注詳細モーダル
- [ ] 承認ボタン（ステータス: pending → approved）
- [ ] 発送処理ボタン（ステータス: approved → shipped）
- [ ] 請求書作成ボタン（delivered → 請求書生成）
- [ ] 一括操作

#### 請求管理
```
┌─────────────────────────────────────────────┐
│  請求書管理                                  │
├─────────────────────────────────────────────┤
│  ステータス: [全て▼] 店舗: [全て▼]            │
│                                              │
│  ┌───────┬───────┬────────┬────────┬─────┐ │
│  │請求No │ FC店舗 │ 金額   │入金状況│操作 │ │
│  ├───────┼───────┼────────┼────────┼─────┤ │
│  │INV001 │銀座店  │¥45,000 │未入金  │[PDF]│ │
│  │INV002 │渋谷店  │¥32,000 │一部入金│[入金]│ │
│  │INV003 │新宿店  │¥58,000 │入金済み│[履歴]│ │
│  └───────┴───────┴────────┴────────┴─────┘ │
│                                              │
│  [PDFダウンロード] [入金記録] [リマインダー送信] │
└─────────────────────────────────────────────┘
```

**機能一覧:**
- [ ] 店舗別請求履歴
- [ ] 入金ステータス管理（手動更新: unpaid/partial/paid）
- [ ] PDF請求書生成・ダウンロード
- [ ] 入金催促メール送信
- [ ] 期限超過フィルター

### 5.2 FC店舗管理画面（FC Store Admin）

#### トップページ
```
┌─────────────────────────────────────────────┐
│  銀座店 FC管理                               │
├─────────────────────────────────────────────┤
│  ┌─────────┐  ┌─────────┐  ┌─────────┐     │
│  │今月の発注│  │ 処理中  │  │未払い請求│     │
│  │   3件   │  │   1件   │  │ ¥45,000 │     │
│  └─────────┘  └─────────┘  └─────────┘     │
│                                              │
│  ┌───────────────────────────────────────┐  │
│  │ 最近の発注                              │  │
│  │ FC001 2024-11-15 ¥45,000 配送待ち     │  │
│  │ FC002 2024-11-10 ¥32,000 配送完了      │  │
│  └───────────────────────────────────────┘  │
│                                              │
│  [新規発注を作成]                             │
└─────────────────────────────────────────────┘
```

#### 発注作成
```
┌─────────────────────────────────────────────┐
│  新規発注作成                                │
├─────────────────────────────────────────────┤
│  希望納品日: [2024-11-20▼]                   │
│                                              │
│  商品選択:                                   │
│  ┌───────┬────────┬──────┬──────┬──────┐   │
│  │商品名  │ 単価   │在庫  │数量  │小計  │   │
│  ├───────┼────────┼──────┼──────┼──────┤   │
│  │目薬A   │¥2,000  │150   │[10]  │¥20,000│  │
│  │視力検査│¥5,500  │25    │[ 5]  │¥27,500│  │
│  │トレーニ│¥12,000 │0     │[ 0]  │¥0     │  │
│  └───────┴────────┴──────┴──────┴──────┘   │
│                                              │
│  小計: ¥47,500                               │
│  消費税(10%): ¥4,750                         │
│  合計: ¥52,250                               │
│                                              │
│  備考: [                              ]      │
│                                              │
│  [下書き保存] [発注を確定]                     │
└─────────────────────────────────────────────┘
```

**機能一覧:**
- [ ] 商品カタログから選択
- [ ] 数量入力
- [ ] 自動計算（小計・税・合計）
- [ ] 下書き保存
- [ ] 発注確定（ステータス: draft → pending）
- [ ] 在庫確認

#### 発注履歴
```
┌─────────────────────────────────────────────┐
│  発注履歴                                    │
├─────────────────────────────────────────────┤
│  期間: [2024-11▼] ステータス: [全て▼]         │
│                                              │
│  ┌───────┬────────┬────────┬────────┐      │
│  │発注No │ 日付   │ 金額   │ 状態   │      │
│  ├───────┼────────┼────────┼────────┤      │
│  │FC001  │11/15   │¥52,250 │配送待ち│      │
│  │FC002  │11/10   │¥35,200 │配送完了│      │
│  │FC003  │11/05   │¥63,800 │請求済み│      │
│  └───────┴────────┴────────┴────────┘      │
│                                              │
│  [詳細表示] [キャンセル] [再発注]              │
└─────────────────────────────────────────────┘
```

#### 請求書履歴
```
┌─────────────────────────────────────────────┐
│  請求書履歴                                  │
├─────────────────────────────────────────────┤
│  ┌───────┬────────┬────────┬────────┬─────┐ │
│  │請求No │発行日  │金額    │支払期限│状態 │ │
│  ├───────┼────────┼────────┼────────┼─────┤ │
│  │INV001 │11/16   │¥52,250 │12/15   │未払 │ │
│  │INV002 │11/11   │¥35,200 │12/10   │支払済│ │
│  │INV003 │11/06   │¥63,800 │12/05   │支払済│ │
│  └───────┴────────┴────────┴────────┴─────┘ │
│                                              │
│  [PDF表示/ダウンロード]                       │
└─────────────────────────────────────────────┘
```

---

## 6. APIエンドポイント

### 6.1 FC発注API

```http
# 商品一覧取得
GET /api/fc/products
Headers: Authorization: Bearer {token}
Query: ?category_id=1&search=目薬&page=1&per_page=20
Response:
{
  "data": [
    {
      "id": 1,
      "sku": "P001",
      "name": "目薬A",
      "unit_price": 2000,
      "stock_quantity": 150,
      "is_active": true,
      "category": { "id": 1, "name": "消耗品" }
    }
  ],
  "meta": { "current_page": 1, "total": 50 }
}

# 発注作成
POST /api/fc/orders
Headers: Authorization: Bearer {token}
Body:
{
  "fc_store_id": 2,
  "requested_delivery_date": "2024-11-20",
  "items": [
    { "fc_product_id": 1, "quantity": 10 },
    { "fc_product_id": 2, "quantity": 5 }
  ],
  "notes": "至急対応お願いします"
}
Response:
{
  "success": true,
  "order": {
    "id": 1,
    "order_number": "FC2411150001",
    "status": "pending",
    "total_amount": 52250
  }
}

# 発注詳細取得
GET /api/fc/orders/{order_number}
Response:
{
  "order_number": "FC2411150001",
  "fc_store": { "id": 2, "name": "銀座店" },
  "order_date": "2024-11-15T10:00:00Z",
  "status": "pending",
  "items": [...],
  "subtotal": 47500,
  "tax_amount": 4750,
  "total_amount": 52250,
  "invoice": null
}

# 発注承認（本部のみ）
POST /api/fc/orders/{order_number}/approve
Headers: Authorization: Bearer {token}
Response:
{
  "success": true,
  "order": {
    "status": "approved",
    "approved_by": 1,
    "approved_at": "2024-11-15T11:00:00Z"
  }
}

# 発送処理（本部のみ）
POST /api/fc/orders/{order_number}/ship
Body:
{
  "tracking_number": "1234567890",
  "carrier": "ヤマト運輸"
}
Response:
{
  "success": true,
  "order": {
    "status": "shipped",
    "shipped_at": "2024-11-16T09:00:00Z"
  }
}

# 請求書生成（本部のみ）
POST /api/fc/orders/{order_number}/invoice
Response:
{
  "success": true,
  "invoice": {
    "invoice_number": "INV2411160001",
    "issue_date": "2024-11-16",
    "due_date": "2024-12-15",
    "total_amount": 52250,
    "pdf_url": "/storage/invoices/INV2411160001.pdf"
  }
}
```

### 6.2 請求・入金API

```http
# 請求書一覧
GET /api/fc/invoices
Query: ?fc_store_id=2&status=unpaid&overdue=true

# 請求書PDF取得
GET /api/fc/invoices/{invoice_number}/pdf
Response: PDF binary

# 入金記録（本部のみ）
POST /api/fc/invoices/{invoice_number}/payments
Body:
{
  "payment_date": "2024-11-20",
  "amount": 52250,
  "payment_method": "bank_transfer",
  "reference_number": "振込番号12345"
}
Response:
{
  "success": true,
  "payment": {
    "payment_number": "PAY2411200001",
    "amount": 52250,
    "status": "pending"
  },
  "invoice": {
    "paid_amount": 52250,
    "status": "paid"
  }
}

# 入金確認（本部のみ）
POST /api/fc/payments/{payment_number}/confirm
Response:
{
  "success": true,
  "payment": {
    "status": "confirmed",
    "confirmed_at": "2024-11-21T10:00:00Z"
  }
}
```

### 6.3 レポートAPI

```http
# 月次売上レポート
GET /api/fc/reports/monthly
Query: ?year=2024&month=11
Response:
{
  "total_orders": 45,
  "total_amount": 2350000,
  "by_store": [
    { "store_id": 2, "name": "銀座店", "orders": 12, "amount": 580000 },
    { "store_id": 3, "name": "渋谷店", "orders": 8, "amount": 420000 }
  ],
  "by_product": [
    { "product_id": 1, "name": "目薬A", "quantity": 150, "amount": 300000 }
  ],
  "unpaid_total": 350000
}

# 未払い請求アラート
GET /api/fc/reports/overdue
Response:
{
  "overdue_invoices": [
    {
      "invoice_number": "INV2411010001",
      "fc_store": { "id": 5, "name": "横浜店" },
      "total_amount": 120000,
      "due_date": "2024-11-10",
      "days_overdue": 5
    }
  ]
}
```

---

## 7. 権限設計（RBAC）

### 7.1 ロール定義

```php
// database/seeders/FcRoleSeeder.php

// 既存のSpatie Permissionを使用
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// FCロール作成
Role::create(['name' => 'fc_superadmin']);      // 全権限
Role::create(['name' => 'headquarters_manager']); // 本部マネージャー
Role::create(['name' => 'headquarters_staff']);   // 本部スタッフ
Role::create(['name' => 'fc_manager']);           // FC店舗マネージャー
Role::create(['name' => 'fc_buyer']);             // FC店舗バイヤー

// パーミッション作成
// 商品管理
Permission::create(['name' => 'fc_products.view']);
Permission::create(['name' => 'fc_products.create']);
Permission::create(['name' => 'fc_products.update']);
Permission::create(['name' => 'fc_products.delete']);

// 発注管理
Permission::create(['name' => 'fc_orders.view']);
Permission::create(['name' => 'fc_orders.create']);
Permission::create(['name' => 'fc_orders.update']);
Permission::create(['name' => 'fc_orders.approve']);
Permission::create(['name' => 'fc_orders.ship']);
Permission::create(['name' => 'fc_orders.cancel']);

// 請求書管理
Permission::create(['name' => 'fc_invoices.view']);
Permission::create(['name' => 'fc_invoices.create']);
Permission::create(['name' => 'fc_invoices.send']);

// 入金管理
Permission::create(['name' => 'fc_payments.view']);
Permission::create(['name' => 'fc_payments.record']);
Permission::create(['name' => 'fc_payments.confirm']);

// レポート
Permission::create(['name' => 'fc_reports.view']);
```

### 7.2 ロール別権限マトリクス

| 権限 | fc_superadmin | hq_manager | hq_staff | fc_manager | fc_buyer |
|-----|--------------|-----------|---------|-----------|---------|
| **商品管理** |
| fc_products.view | ✅ | ✅ | ✅ | ✅ | ✅ |
| fc_products.create | ✅ | ✅ | ✅ | ❌ | ❌ |
| fc_products.update | ✅ | ✅ | ✅ | ❌ | ❌ |
| fc_products.delete | ✅ | ✅ | ❌ | ❌ | ❌ |
| **発注管理** |
| fc_orders.view | ✅ | ✅ | ✅ | ✅※ | ✅※ |
| fc_orders.create | ✅ | ❌ | ❌ | ✅ | ✅ |
| fc_orders.update | ✅ | ✅ | ✅ | ✅※ | ❌ |
| fc_orders.approve | ✅ | ✅ | ✅ | ❌ | ❌ |
| fc_orders.ship | ✅ | ✅ | ✅ | ❌ | ❌ |
| fc_orders.cancel | ✅ | ✅ | ✅ | ✅※ | ❌ |
| **請求書管理** |
| fc_invoices.view | ✅ | ✅ | ✅ | ✅※ | ✅※ |
| fc_invoices.create | ✅ | ✅ | ✅ | ❌ | ❌ |
| fc_invoices.send | ✅ | ✅ | ✅ | ❌ | ❌ |
| **入金管理** |
| fc_payments.view | ✅ | ✅ | ✅ | ✅※ | ❌ |
| fc_payments.record | ✅ | ✅ | ✅ | ❌ | ❌ |
| fc_payments.confirm | ✅ | ✅ | ❌ | ❌ | ❌ |
| **レポート** |
| fc_reports.view | ✅ | ✅ | ✅ | ✅※ | ❌ |

※ 自店舗のみ

### 7.3 Policyクラス実装例

```php
// app/Policies/FcOrderPolicy.php

namespace App\Policies;

use App\Models\FcOrder;
use App\Models\User;

class FcOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fc_orders.view');
    }

    public function view(User $user, FcOrder $order): bool
    {
        if ($user->hasRole(['fc_superadmin', 'headquarters_manager', 'headquarters_staff'])) {
            return true;
        }

        // FC店舗ユーザーは自店舗の発注のみ
        if ($user->hasRole(['fc_manager', 'fc_buyer'])) {
            return $user->store_id === $order->fc_store_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fc_orders.create');
    }

    public function approve(User $user, FcOrder $order): bool
    {
        return $user->hasPermissionTo('fc_orders.approve')
            && $order->status === 'pending';
    }

    public function ship(User $user, FcOrder $order): bool
    {
        return $user->hasPermissionTo('fc_orders.ship')
            && in_array($order->status, ['approved', 'processing']);
    }
}
```

---

## 8. 業務フロー

### 8.1 発注フロー

```
[FC店舗]                    [本部]
   │
   │ 1. 商品選択・数量入力
   ▼
┌─────────┐
│  draft  │ (下書き)
└────┬────┘
     │ 2. 発注確定
     ▼
┌─────────┐
│ pending │ (承認待ち)  ────────────▶  通知: 新規発注
└────┬────┘                              │
     │                                   ▼
     │                             3. 発注内容確認
     │                                   │
     │ ◀─────────────────────────────────┘
     │                             4. 承認ボタン
     ▼
┌─────────┐
│approved │ (承認済み)  ◀─────────  通知: 発注承認
└────┬────┘
     │                             5. 商品準備
     │                                   │
     │ ◀─────────────────────────────────┘
     │                             6. 発送処理ボタン
     ▼
┌─────────┐
│ shipped │ (発送済み)  ◀─────────  通知: 発送完了
└────┬────┘
     │
     │ 7. 商品受領
     ▼
┌─────────┐
│delivered│ (配送完了)
└────┬────┘                              │
     │                                   ▼
     │                             8. 請求書作成ボタン
     ▼
┌─────────┐
│invoiced │ (請求済み)  ◀─────────  請求書PDF生成
└─────────┘
```

### 8.2 請求・入金フロー

```
[請求書発行]
     │
     ▼
┌─────────┐
│  draft  │ (下書き)
└────┬────┘
     │ PDF生成
     ▼
┌─────────┐
│ issued  │ (発行済み)
└────┬────┘
     │ メール送信
     ▼
┌─────────┐
│  sent   │ (送信済み)  ────────▶  FC店舗: 請求書受領
└────┬────┘
     │                              │
     │ ◀────────────────────────────┘
     │                          FC店舗: 銀行振込
     │
     │ 本部: 入金確認（銀行口座チェック）
     ▼
┌─────────┐
│  paid   │ (入金済み)
└─────────┘

※ 支払い期限超過の場合
┌─────────┐
│ overdue │ (期限超過)  ────────▶  自動リマインダー送信
└─────────┘
```

### 8.3 通知タイミング

| タイミング | 送信先 | 通知方法 | テンプレート |
|-----------|-------|---------|-------------|
| 発注確定時 | 本部スタッフ全員 | メール | fc_order_pending.blade.php |
| 発注承認時 | FC店舗マネージャー | メール + SMS | fc_order_approved.blade.php |
| 発送完了時 | FC店舗マネージャー | メール + SMS | fc_order_shipped.blade.php |
| 請求書発行時 | FC店舗マネージャー | メール（PDF添付） | fc_invoice_issued.blade.php |
| 入金確認時 | FC店舗マネージャー | メール | fc_payment_confirmed.blade.php |
| 支払い期限3日前 | FC店舗マネージャー | メール | fc_payment_reminder.blade.php |
| 支払い期限超過 | FC店舗マネージャー + 本部 | メール + SMS | fc_payment_overdue.blade.php |

---

## 9. 実装計画

### 9.1 フェーズ分け

#### フェーズ1: 基盤構築（1日）
- [ ] データベースマイグレーション作成
- [ ] Eloquentモデル作成（FcOrder, FcOrderItem, FcProduct, FcInvoice, FcPayment）
- [ ] Spatie権限・ロール設定
- [ ] Storeモデル拡張（type追加）

#### フェーズ2: 商品管理（0.5日）
- [ ] FcProductResource（Filament）
- [ ] FcProductCategoryResource（Filament）
- [ ] 商品CRUD機能
- [ ] 在庫管理基本機能

#### フェーズ3: 発注管理（1.5日）
- [ ] FcOrderService実装
- [ ] FcOrderResource（Filament）- 本部用
- [ ] FC店舗用発注作成画面
- [ ] 承認・発送ワークフロー
- [ ] ステータス管理

#### フェーズ4: 請求・入金管理（1.5日）
- [ ] FcInvoiceService実装
- [ ] FcInvoiceResource（Filament）
- [ ] PDF請求書生成（DomPDF）
- [ ] FcPaymentService実装
- [ ] 入金記録・確認機能

#### フェーズ5: 通知・レポート（1日）
- [ ] FcNotificationService実装
- [ ] メールテンプレート作成
- [ ] ダッシュボード作成
- [ ] 基本レポート機能

#### フェーズ6: テスト・デプロイ（0.5日）
- [ ] ユニットテスト
- [ ] 統合テスト
- [ ] ステージング環境テスト
- [ ] 本番デプロイ

**合計工数**: 約6日（48時間）

### 9.2 優先度マトリクス

| 機能 | 重要度 | 緊急度 | 優先度 |
|-----|-------|-------|-------|
| 発注作成・承認 | 高 | 高 | ⭐⭐⭐ |
| 商品管理 | 高 | 高 | ⭐⭐⭐ |
| 請求書生成 | 高 | 中 | ⭐⭐ |
| 入金管理 | 高 | 中 | ⭐⭐ |
| 通知機能 | 中 | 中 | ⭐ |
| レポート | 中 | 低 | ⭐ |
| 在庫管理 | 低 | 低 | オプション |

---

## 10. 技術的決定事項

### 10.1 採用技術

- **PDF生成**: barryvdh/laravel-dompdf（インストール必要）
- **認証**: Spatie Laravel Permission（既存）
- **メール**: AWS SES（既存）
- **SMS**: AWS SNS（既存）
- **ファイルストレージ**: storage/app/fc_invoices/

### 10.2 設計原則

1. **ドメイン分離**: FC業務と既存予約システムを完全分離
2. **再利用最大化**: 既存パターン（Sale → FcOrder）を活用
3. **拡張性**: 将来の機能追加を考慮したモジュラー設計
4. **監査証跡**: 全操作を記録（created_by, approved_by, etc.）
5. **マルチテナント**: store_id による厳格なデータ分離

### 10.3 注意事項

- **Sale と FcOrder を混同しない** - 異なるワークフロー
- **Customer と FcStore を混同しない** - 異なるエンティティ
- **store_id フィルタリングを徹底** - セキュリティ必須
- **税計算ロジックは既存を流用** - 日本の消費税対応済み
- **PDF生成はキューを使用** - 大量生成時のパフォーマンス対策

### 10.4 将来拡張ポイント

- 定期発注（スタンディングオーダー）
- 在庫自動補充アラート
- J-Payment連携（決済自動化）
- 複数倉庫対応
- 返品・交換管理
- 原価・利益率分析

---

## 付録: ファイル一覧

### 新規作成ファイル（21ファイル）

```
database/migrations/
├── 2025_11_17_000001_add_type_to_stores_table.php
├── 2025_11_17_000002_create_fc_product_categories_table.php
├── 2025_11_17_000003_create_fc_products_table.php
├── 2025_11_17_000004_create_fc_orders_table.php
├── 2025_11_17_000005_create_fc_order_items_table.php
├── 2025_11_17_000006_create_fc_invoices_table.php
└── 2025_11_17_000007_create_fc_payments_table.php (7ファイル)

app/Models/
├── FcOrder.php
├── FcOrderItem.php
├── FcProduct.php
├── FcProductCategory.php
├── FcInvoice.php
└── FcPayment.php (6ファイル)

app/Services/
├── FcOrderService.php
├── FcInvoiceService.php
├── FcPaymentService.php
└── FcNotificationService.php (4ファイル)

app/Filament/Resources/
├── FcOrderResource.php
├── FcProductResource.php
├── FcInvoiceResource.php
└── FcPaymentResource.php (4ファイル)
```

### 修正ファイル（2ファイル）

```
app/Models/Store.php - typeフィールド対応
database/seeders/DatabaseSeeder.php - FCロール追加
```

---

**仕様書作成日**: 2025-11-17
**作成者**: Claude Code
**次のステップ**: フェーズ1（基盤構築）の実装開始

---

**承認欄**

| 項目 | 承認者 | 日付 | 署名 |
|-----|-------|------|------|
| 技術仕様 | | | |
| データベース設計 | | | |
| 画面設計 | | | |
| 権限設計 | | | |
