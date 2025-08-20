# 🌐 API仕様書

## 概要

Xsyumeno Laravel版のAPI仕様書です。RESTful APIの原則に従い、明確で一貫性のあるインターフェースを提供します。

## API設計原則

- **RESTful設計**: HTTP メソッドとステータスコードの適切な使用
- **JSON形式**: 全てのリクエスト・レスポンスはJSON
- **認証**: Laravel Sanctumによるトークンベース認証
- **バージョニング**: URLパスによるバージョン管理 (`/api/v1/`)
- **エラーハンドリング**: 統一されたエラーレスポンス形式

## 認証方式

### 顧客認証（OTP）
- Amazon SNS SMS認証
- Laravel Sanctumトークン発行
- セッションベース認証も併用

### 管理者認証
- Filament標準認証
- Laravel Sanctum APIトークン

## ベースURL

```
開発環境: http://localhost:8000/api/v1
本番環境: https://your-domain.com/api/v1
```

## 共通レスポンス形式

### 成功レスポンス
```json
{
  "success": true,
  "data": {},
  "message": "操作が完了しました",
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z",
    "version": "1.0"
  }
}
```

### エラーレスポンス
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "バリデーションエラーが発生しました",
    "details": {
      "field": ["具体的なエラーメッセージ"]
    }
  },
  "meta": {
    "timestamp": "2024-01-01T00:00:00Z",
    "version": "1.0"
  }
}
```

### ページネーション
```json
{
  "success": true,
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "from": 1,
      "last_page": 5,
      "per_page": 15,
      "to": 15,
      "total": 67
    }
  }
}
```

## 公開API（認証不要）

### ヘルスチェック

```http
GET /api/v1/health
```

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "database": "connected",
    "cache": "available",
    "queue": "running"
  },
  "message": "システムは正常に動作しています"
}
```

### 店舗関連

#### 店舗一覧取得
```http
GET /api/v1/stores
```

**クエリパラメータ:**
- `prefecture`: 都道府県でフィルタ
- `city`: 市区町村でフィルタ
- `is_active`: アクティブ状態（true/false）

**レスポンス:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "銀座店",
      "name_kana": "ギンザテン",
      "address": "東京都中央区銀座1-1-1",
      "phone": "03-1234-5678",
      "opening_hours": {
        "monday": {"open": "09:00", "close": "18:00"}
      },
      "is_active": true
    }
  ]
}
```

#### 店舗詳細取得
```http
GET /api/v1/stores/{id}
```

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "銀座店",
    "description": "銀座の中心部にある便利な立地の店舗です",
    "address": "東京都中央区銀座1-1-1",
    "phone": "03-1234-5678",
    "email": "ginza@xsyumeno.com",
    "opening_hours": {},
    "holidays": [],
    "capacity": 5,
    "settings": {},
    "menus": [
      {
        "id": 1,
        "name": "基本検査",
        "price": 5000,
        "duration": 60
      }
    ]
  }
}
```

#### 店舗メニュー取得
```http
GET /api/v1/stores/{id}/menus
```

**クエリパラメータ:**
- `category`: カテゴリでフィルタ
- `is_available`: 提供可能メニューのみ

**レスポンス:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "category": "検査",
      "name": "基本検査",
      "description": "視力の基本的な検査です",
      "price": 5000,
      "duration": 60,
      "is_available": true,
      "options": []
    }
  ]
}
```

#### 店舗空き状況確認
```http
GET /api/v1/stores/{id}/availability
```

**クエリパラメータ:**
- `date`: 確認したい日付（YYYY-MM-DD）
- `duration`: 所要時間（分）

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "date": "2024-01-15",
    "available_slots": [
      {
        "start_time": "09:00",
        "end_time": "10:00",
        "staff_id": 1,
        "staff_name": "田中太郎"
      },
      {
        "start_time": "10:00", 
        "end_time": "11:00",
        "staff_id": 1,
        "staff_name": "田中太郎"
      }
    ]
  }
}
```

## 認証API

### OTP送信
```http
POST /api/v1/auth/send-otp
```

**リクエストボディ:**
```json
{
  "phone": "090-1234-5678"
}
```

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "expires_at": "2024-01-01T00:05:00Z",
    "resend_available_at": "2024-01-01T00:01:00Z"
  },
  "message": "認証コードを送信しました"
}
```

### OTP検証
```http
POST /api/v1/auth/verify-otp
```

**リクエストボディ:**
```json
{
  "phone": "090-1234-5678",
  "otp_code": "123456"
}
```

**レスポンス（既存顧客）:**
```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 1,
      "name": "山田太郎",
      "phone": "090-1234-5678",
      "email": "yamada@example.com"
    },
    "token": "1|abcdef...",
    "is_new_customer": false
  },
  "message": "ログインしました"
}
```

**レスポンス（新規顧客）:**
```json
{
  "success": true,
  "data": {
    "phone": "090-1234-5678",
    "temp_token": "temp_abcdef...",
    "is_new_customer": true
  },
  "message": "会員登録が必要です"
}
```

### 新規会員登録
```http
POST /api/v1/auth/register
```

**リクエストボディ:**
```json
{
  "temp_token": "temp_abcdef...",
  "last_name": "山田",
  "first_name": "太郎",
  "last_name_kana": "ヤマダ",
  "first_name_kana": "タロウ",
  "email": "yamada@example.com",
  "birth_date": "1990-01-01",
  "gender": "male",
  "postal_code": "100-0001",
  "address": "東京都千代田区千代田1-1"
}
```

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 1,
      "name": "山田太郎",
      "phone": "090-1234-5678",
      "email": "yamada@example.com"
    },
    "token": "1|abcdef..."
  },
  "message": "会員登録が完了しました"
}
```

### ログアウト
```http
POST /api/v1/auth/logout
```

**ヘッダー:**
```
Authorization: Bearer {token}
```

**レスポンス:**
```json
{
  "success": true,
  "message": "ログアウトしました"
}
```

## 顧客API（認証必要）

### プロフィール管理

#### プロフィール取得
```http
GET /api/v1/customer/profile
```

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "last_name": "山田",
    "first_name": "太郎",
    "phone": "090-1234-5678",
    "email": "yamada@example.com",
    "birth_date": "1990-01-01",
    "gender": "male",
    "address": "東京都千代田区千代田1-1",
    "last_visit_at": "2024-01-01T10:00:00Z"
  }
}
```

#### プロフィール更新
```http
PUT /api/v1/customer/profile
```

**リクエストボディ:**
```json
{
  "last_name": "田中",
  "first_name": "花子",
  "email": "tanaka@example.com",
  "address": "東京都渋谷区渋谷1-1"
}
```

### 予約管理

#### 予約作成
```http
POST /api/v1/reservations
```

**リクエストボディ:**
```json
{
  "store_id": 1,
  "reservation_date": "2024-01-15",
  "start_time": "10:00",
  "menu_items": [
    {
      "menu_id": 1,
      "quantity": 1
    }
  ],
  "guest_count": 1,
  "notes": "初回です。よろしくお願いします。"
}
```

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "reservation_number": "R2024011500001",
    "store": {
      "id": 1,
      "name": "銀座店"
    },
    "reservation_date": "2024-01-15",
    "start_time": "10:00",
    "end_time": "11:00",
    "status": "pending",
    "total_amount": 5000,
    "menu_items": [
      {
        "menu_id": 1,
        "name": "基本検査",
        "price": 5000,
        "quantity": 1
      }
    ]
  },
  "message": "予約を作成しました"
}
```

#### 予約一覧取得
```http
GET /api/v1/reservations
```

**クエリパラメータ:**
- `status`: ステータスでフィルタ
- `from_date`: 開始日
- `to_date`: 終了日
- `per_page`: ページあたりの件数（デフォルト15）

**レスポンス:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "reservation_number": "R2024011500001",
      "store": {
        "id": 1,
        "name": "銀座店"
      },
      "reservation_date": "2024-01-15",
      "start_time": "10:00",
      "status": "confirmed",
      "total_amount": 5000
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "total": 5,
      "per_page": 15
    }
  }
}
```

#### 予約詳細取得
```http
GET /api/v1/reservations/{id}
```

#### 予約変更
```http
PUT /api/v1/reservations/{id}
```

**リクエストボディ:**
```json
{
  "reservation_date": "2024-01-16",
  "start_time": "14:00",
  "notes": "時間を変更しました"
}
```

#### 予約キャンセル
```http
DELETE /api/v1/reservations/{id}
```

**リクエストボディ:**
```json
{
  "cancel_reason": "急用のため"
}
```

### カルテ履歴

#### カルテ一覧取得
```http
GET /api/v1/customer/medical-records
```

**レスポンス:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "visit_date": "2024-01-15",
      "staff_name": "田中医師",
      "symptoms": "視力低下",
      "treatment": "視力矯正トレーニング",
      "next_visit_date": "2024-02-15"
    }
  ]
}
```

## 管理者API（認証必要）

### ダッシュボード

#### 統計情報取得
```http
GET /api/v1/admin/dashboard/stats
```

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "overview": {
      "today_reservations": 15,
      "active_customers": 1250,
      "total_stores": 3,
      "monthly_revenue": 450000
    },
    "charts": {
      "weekly_reservations": [
        {"date": "2024-01-01", "day": "月", "count": 12},
        {"date": "2024-01-02", "day": "火", "count": 15}
      ],
      "reservations_by_status": {
        "pending": 5,
        "confirmed": 10,
        "completed": 100
      },
      "popular_stores": [
        {"store_id": 1, "store_name": "銀座店", "count": 45}
      ]
    }
  }
}
```

#### 最近のアクティビティ
```http
GET /api/v1/admin/dashboard/activities
```

**クエリパラメータ:**
- `limit`: 取得件数（デフォルト10）

### 顧客管理

#### 顧客一覧取得
```http
GET /api/v1/admin/customers
```

**クエリパラメータ:**
- `search`: 検索キーワード（名前・電話番号）
- `store_id`: 店舗でフィルタ
- `last_visit_from`: 最終来店日（開始）
- `last_visit_to`: 最終来店日（終了）
- `is_blocked`: ブロック状態
- `sort`: ソート項目
- `order`: ソート順（asc/desc）
- `per_page`: ページあたりの件数

#### 顧客詳細取得
```http
GET /api/v1/admin/customers/{id}
```

#### 顧客作成
```http
POST /api/v1/admin/customers
```

#### 顧客更新
```http
PUT /api/v1/admin/customers/{id}
```

#### 顧客削除
```http
DELETE /api/v1/admin/customers/{id}
```

### 予約管理

#### 予約一覧取得
```http
GET /api/v1/admin/reservations
```

**クエリパラメータ:**
- `store_id`: 店舗でフィルタ
- `staff_id`: スタッフでフィルタ
- `customer_id`: 顧客でフィルタ
- `status`: ステータスでフィルタ
- `date_from`: 予約日（開始）
- `date_to`: 予約日（終了）
- `search`: 検索キーワード

#### 予約ステータス更新
```http
PATCH /api/v1/admin/reservations/{id}/status
```

**リクエストボディ:**
```json
{
  "status": "confirmed",
  "notes": "確認完了"
}
```

### 店舗管理

#### 店舗一覧取得
```http
GET /api/v1/admin/stores
```

#### 店舗作成
```http
POST /api/v1/admin/stores
```

#### 店舗更新
```http
PUT /api/v1/admin/stores/{id}
```

### メニュー管理

#### メニュー一覧取得
```http
GET /api/v1/admin/menus
```

#### メニュー作成
```http
POST /api/v1/admin/menus
```

### スタッフ管理

#### スタッフ一覧取得
```http
GET /api/v1/admin/users
```

#### スタッフ作成
```http
POST /api/v1/admin/users
```

### シフト管理

#### シフト一覧取得
```http
GET /api/v1/admin/shifts
```

**クエリパラメータ:**
- `store_id`: 店舗でフィルタ
- `staff_id`: スタッフでフィルタ
- `date_from`: シフト日（開始）
- `date_to`: シフト日（終了）

#### シフト一括作成
```http
POST /api/v1/admin/shifts/bulk
```

**リクエストボディ:**
```json
{
  "staff_id": 1,
  "date_from": "2024-01-01",
  "date_to": "2024-01-31",
  "shifts": [
    {
      "day_of_week": "monday",
      "start_time": "09:00",
      "end_time": "18:00",
      "break_start": "12:00",
      "break_end": "13:00"
    }
  ]
}
```

### カルテ管理

#### カルテ作成
```http
POST /api/v1/admin/medical-records
```

#### カルテ更新
```http
PUT /api/v1/admin/medical-records/{id}
```

## エラーコード一覧

| コード | HTTP Status | 説明 |
|--------|-------------|------|
| `VALIDATION_ERROR` | 422 | バリデーションエラー |
| `UNAUTHORIZED` | 401 | 認証が必要 |
| `FORBIDDEN` | 403 | 権限不足 |
| `NOT_FOUND` | 404 | リソースが見つからない |
| `CONFLICT` | 409 | データの競合 |
| `RATE_LIMIT_EXCEEDED` | 429 | レート制限超過 |
| `INTERNAL_SERVER_ERROR` | 500 | サーバー内部エラー |
| `OTP_EXPIRED` | 422 | OTPの有効期限切れ |
| `OTP_INVALID` | 422 | OTPが無効 |
| `PHONE_NOT_VERIFIED` | 422 | 電話番号未認証 |
| `RESERVATION_CONFLICT` | 409 | 予約時間の競合 |

## レート制限

- **一般API**: 60リクエスト/分
- **OTP送信**: 5リクエスト/時間
- **認証API**: 10リクエスト/分

## テスト用エンドポイント（開発環境のみ）

```http
POST /api/v1/test/seed-data        # テストデータ作成
DELETE /api/v1/test/clean-data     # テストデータ削除
GET /api/v1/test/otp/{phone}       # OTPコード確認（テスト用）
```

この API 仕様書に基づいて、Laravel での実装を進めることができます。