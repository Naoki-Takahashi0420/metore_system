# 複数店舗対応 顧客管理システム 実装仕様書

**作成日**: 2025-10-15
**バージョン**: 1.0
**ステータス**: 実装待ち

---

## 📋 目次

1. [概要](#概要)
2. [現状の問題](#現状の問題)
3. [解決策](#解決策)
4. [データベース変更](#データベース変更)
5. [バックエンド実装](#バックエンド実装)
6. [フロントエンド実装](#フロントエンド実装)
7. [UI/UX仕様](#uiux仕様)
8. [テストシナリオ](#テストシナリオ)
9. [ロールバック手順](#ロールバック手順)
10. [リスクと対策](#リスクと対策)

---

## 概要

### 目的
同じ顧客が複数店舗を利用できるようにし、マイページで店舗を切り替えて各店舗のデータを閲覧できる機能を実装する。

### 背景
- **現在の問題**: 同じ電話番号で複数店舗に登録できない（UNIQUE制約違反）
- **ユースケース**: 田中太郎さんが新宿店と秋葉原店の両方を利用したい
- **ビジネス要件**: 店舗間での顧客の移動を許容する

### スコープ
- ✅ データベースUNIQUE制約の変更
- ✅ ログイン時の店舗選択機能
- ✅ マイページでの店舗切り替え機能
- ✅ 顧客インポート処理の調整
- ❌ 顧客データの統合（同一人物を1つのIDにまとめる機能は**実装しない**）

### 設計方針
**「シンプル重視」**: 複雑な中間テーブルは使用せず、既存のデータ構造を維持したまま実装する。

---

## 現状の問題

### 問題1: 秋葉原店の顧客インポートエラー

**現象**:
```
秋葉原店で180件のインポートを実施
→ 93件のみ成功
→ 87件が「電話番号が既に登録されています」エラー
```

**原因**:
```sql
-- 現在のUNIQUE制約
ALTER TABLE customers ADD UNIQUE (phone);
ALTER TABLE customers ADD UNIQUE (email);
```

これにより、**全店舗で1つの電話番号しか登録できない**。

### 問題2: マイページの認証問題

**現在の仕組み**:
```php
// CustomerAuthController.php
$customer = Customer::where('phone', $phone)->first();
```

**問題点**:
- 同じ電話番号で複数のcustomer_idが存在する場合、`first()` は最初の1件のみ取得
- 田中太郎さんが新宿店（customer_id=100）と秋葉原店（customer_id=200）に登録されている場合、常にcustomer_id=100でログインされる
- **秋葉原店のデータが永遠に見えない**

---

## 解決策

### アプローチ: マルチアカウント方式

#### 基本設計
```
田中太郎さん (phone: 09012345678)
├─ 新宿店: customer_id = 100, store_id = 1
└─ 秋葉原店: customer_id = 200, store_id = 4

マイページ:
1. ログイン時に両方の customer_id を検出
2. 店舗選択画面を表示
3. 選択した customer_id でトークン発行
4. マイページで店舗切り替え可能
```

#### データフロー
```
電話番号入力 (09012345678)
  ↓
OTP認証
  ↓
Customer::where('phone', '09012345678')->get()
  → 2件見つかる
  ↓
店舗選択画面表示
  [ ] 新宿店 (最終来店: 2025-01-15)
  [ ] 秋葉原店 (最終来店: 2025-02-10)
  ↓
ユーザーが秋葉原店を選択
  ↓
customer_id=200 でトークン発行
  ↓
マイページ表示（秋葉原店のデータ）
```

---

## データベース変更

### マイグレーション: UNIQUE制約の変更

**ファイル**: `database/migrations/YYYY_MM_DD_HHMMSS_change_customer_unique_constraints.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLiteの場合は特別な処理が必要
        if (DB::getDriverName() === 'sqlite') {
            // SQLiteではALTER TABLEでUNIQUE制約を変更できないため、
            // テーブルを再作成する必要がある
            $this->recreateTableForSqlite();
        } else {
            // MySQL/PostgreSQLの場合
            Schema::table('customers', function (Blueprint $table) {
                // 既存のUNIQUE制約を削除
                $table->dropUnique('customers_phone_unique');
                $table->dropUnique('customers_email_unique');

                // 店舗ごとのUNIQUE制約を追加
                $table->unique(['store_id', 'phone'], 'customers_store_phone_unique');
                $table->unique(['store_id', 'email'], 'customers_store_email_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // ロールバック用の処理
            $this->recreateTableForSqliteRollback();
        } else {
            Schema::table('customers', function (Blueprint $table) {
                // 複合UNIQUE制約を削除
                $table->dropUnique('customers_store_phone_unique');
                $table->dropUnique('customers_store_email_unique');

                // 元のUNIQUE制約を復元
                $table->unique('phone', 'customers_phone_unique');
                $table->unique('email', 'customers_email_unique');
            });
        }
    }

    /**
     * SQLite用のテーブル再作成処理
     */
    private function recreateTableForSqlite(): void
    {
        // 1. 一時テーブルを作成
        DB::statement('CREATE TABLE customers_temp AS SELECT * FROM customers');

        // 2. 元のテーブルを削除
        Schema::dropIfExists('customers');

        // 3. 新しいスキーマでテーブルを再作成
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->string('last_name', 100)->comment('姓');
            $table->string('first_name', 100)->comment('名');
            $table->string('last_name_kana', 100)->nullable()->comment('姓カナ');
            $table->string('first_name_kana', 100)->nullable()->comment('名カナ');
            $table->string('phone', 20)->comment('電話番号');
            $table->string('email')->nullable()->comment('メールアドレス');
            $table->date('birth_date')->nullable()->comment('生年月日');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->comment('性別');
            $table->string('postal_code', 8)->nullable()->comment('郵便番号');
            $table->string('prefecture')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable()->comment('住所');
            $table->string('building')->nullable();
            $table->json('preferences')->nullable()->comment('設定・嗜好');
            $table->json('medical_notes')->nullable()->comment('医療メモ');
            $table->text('notes')->nullable();
            $table->text('characteristics')->nullable();
            $table->boolean('is_blocked')->default(false)->comment('ブロック状態');
            $table->integer('cancellation_count')->default(0);
            $table->integer('no_show_count')->default(0);
            $table->integer('change_count')->default(0);
            $table->timestamp('last_cancelled_at')->nullable();
            $table->timestamp('last_visit_at')->nullable()->comment('最終来店日');
            $table->timestamp('phone_verified_at')->nullable()->comment('電話番号認証日時');
            $table->boolean('sms_notifications_enabled')->default(true);
            $table->json('notification_preferences')->nullable();
            $table->string('customer_number')->nullable();
            $table->string('line_registration_source')->nullable();
            $table->foreignId('line_registration_store_id')->nullable();
            $table->foreignId('line_registration_reservation_id')->nullable();
            $table->timestamp('line_registered_at')->nullable();
            $table->timestamp('last_campaign_sent_at')->nullable();
            $table->integer('campaign_send_count')->default(0);
            $table->timestamp('line_followup_30d_sent_at')->nullable();
            $table->timestamp('line_followup_60d_sent_at')->nullable();
            $table->timestamp('line_followup_7d_sent_at')->nullable();
            $table->timestamp('line_followup_15d_sent_at')->nullable();
            $table->string('line_user_id')->nullable();
            $table->boolean('line_notifications_enabled')->default(false);
            $table->timestamp('line_linked_at')->nullable();
            $table->json('line_profile')->nullable();
            $table->timestamps();

            // 🆕 店舗ごとのUNIQUE制約
            $table->unique(['store_id', 'phone'], 'customers_store_phone_unique');
            $table->unique(['store_id', 'email'], 'customers_store_email_unique');
            $table->unique(['store_id', 'customer_number'], 'customers_store_customer_number_unique');

            // インデックス
            $table->index('store_id');
            $table->index('phone_verified_at');
            $table->index('last_visit_at');
            $table->index('is_blocked');
            $table->index(['last_name', 'first_name']);
            $table->index('line_user_id');
            $table->index('cancellation_count');
            $table->index('no_show_count');
        });

        // 4. データを戻す
        DB::statement('INSERT INTO customers SELECT * FROM customers_temp');

        // 5. 一時テーブルを削除
        DB::statement('DROP TABLE customers_temp');
    }

    /**
     * SQLite用のロールバック処理
     */
    private function recreateTableForSqliteRollback(): void
    {
        // 同様の処理で元のスキーマに戻す
        DB::statement('CREATE TABLE customers_temp AS SELECT * FROM customers');
        Schema::dropIfExists('customers');

        // 元のスキーマでテーブルを再作成（phone, email に UNIQUE 制約）
        Schema::create('customers', function (Blueprint $table) {
            // ... (元のスキーマ)
            $table->string('phone', 20)->unique()->comment('電話番号');
            $table->string('email')->unique()->nullable()->comment('メールアドレス');
            // ...
        });

        DB::statement('INSERT INTO customers SELECT * FROM customers_temp');
        DB::statement('DROP TABLE customers_temp');
    }
};
```

### 変更内容

#### Before（現在）
```sql
UNIQUE (phone)           -- 全店舗で1つのみ
UNIQUE (email)           -- 全店舗で1つのみ
UNIQUE (store_id, customer_number)  -- 店舗ごと（既存）
```

#### After（変更後）
```sql
UNIQUE (store_id, phone)          -- 🆕 店舗ごとに1つ
UNIQUE (store_id, email)          -- 🆕 店舗ごとに1つ
UNIQUE (store_id, customer_number) -- 既存（変更なし）
```

### 影響範囲

✅ **影響なし（データ構造は変更なし）**:
- `customers` テーブルのカラム構成は変更なし
- `medical_records`, `reservations`, `customer_subscriptions` などのリレーションは変更なし

⚠️ **影響あり（制約変更）**:
- 同じ電話番号で複数店舗に登録可能になる
- 同じメールアドレスで複数店舗に登録可能になる

---

## バックエンド実装

### 1. ルート追加

**ファイル**: `routes/api.php`

```php
// 顧客認証関連
Route::prefix('auth/customer')->group(function () {
    Route::post('send-otp', [CustomerAuthController::class, 'sendOtp']);
    Route::post('verify-otp', [CustomerAuthController::class, 'verifyOtp']);
    Route::post('select-store', [CustomerAuthController::class, 'selectStore']); // 🆕

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('switch-store', [CustomerAuthController::class, 'switchStore']); // 🆕
        Route::post('logout', [CustomerAuthController::class, 'logout']);
    });
});

// 顧客情報
Route::middleware('auth:sanctum')->group(function () {
    Route::get('customer/stores', [CustomerController::class, 'getStores']); // 🆕
    Route::get('customer/reservations', [CustomerController::class, 'getReservations']);
    Route::get('customer/medical-records', [CustomerController::class, 'getMedicalRecords']);
    // ...
});
```

---

### 2. CustomerAuthController の修正

**ファイル**: `app/Http/Controllers/Api/Auth/CustomerAuthController.php`

#### 2-1. verifyOtp() の修正

```php
/**
 * OTP検証とログイン/店舗選択
 */
public function verifyOtp(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => ['required', 'string', 'regex:/^[0-9\-]+$/'],
        'otp_code' => ['required', 'string', 'size:6'],
        'remember_me' => ['boolean'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '入力内容に誤りがあります',
                'details' => $validator->errors(),
            ],
        ], 422);
    }

    // OTP検証
    if (!$this->otpService->verifyOtp($request->phone, $request->otp_code)) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_OTP',
                'message' => '認証コードが正しくないか、有効期限が切れています',
            ],
        ], 401);
    }

    // 電話番号を正規化して顧客を検索
    $normalizedPhone = PhoneHelper::normalize($request->phone);
    $customers = Customer::where('phone', $normalizedPhone)
        ->with('store')
        ->get();

    // 正規化した電話番号で見つからない場合、元の電話番号でも検索
    if ($customers->isEmpty()) {
        $customers = Customer::where('phone', $request->phone)
            ->with('store')
            ->get();
    }

    if ($customers->isEmpty()) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NO_RESERVATION_HISTORY',
                'message' => '予約履歴が見つかりません。初回のお客様は新規予約からお申し込みください。',
                'redirect_to_booking' => true,
            ],
        ], 404);
    }

    // 🆕 複数店舗に登録がある場合
    if ($customers->count() > 1) {
        // セッションに一時データを保存
        $tempToken = Str::random(32);
        session([
            'store_selection_' . $tempToken => [
                'phone' => $normalizedPhone,
                'expires_at' => now()->addMinutes(10),
            ]
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'multiple_accounts' => true,
                'phone' => $normalizedPhone,
                'temp_token' => $tempToken,
                'stores' => $customers->map(function($customer) {
                    return [
                        'customer_id' => $customer->id,
                        'store_id' => $customer->store_id,
                        'store_name' => $customer->store->name,
                        'last_visit_at' => $customer->last_visit_at,
                    ];
                }),
            ],
        ]);
    }

    // 1店舗のみの場合は通常通りログイン
    $customer = $customers->first();

    // 予約履歴チェック
    $hasReservations = $customer->reservations()
        ->whereIn('status', ['confirmed', 'completed', 'booked'])
        ->exists();

    if (!$hasReservations) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NO_RESERVATION_HISTORY',
                'message' => '予約履歴が見つかりません。',
            ],
        ], 403);
    }

    // トークン生成
    $rememberMe = $request->boolean('remember_me', false);
    $tokenName = $rememberMe ? 'customer-auth-remember' : 'customer-auth';
    $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(2);
    $token = $customer->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

    return response()->json([
        'success' => true,
        'data' => [
            'is_new_customer' => false,
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'last_name' => $customer->last_name,
                'first_name' => $customer->first_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'store_id' => $customer->store_id,
                'store_name' => $customer->store->name,
            ],
        ],
    ]);
}
```

#### 2-2. selectStore() の追加

```php
/**
 * 店舗選択後のログイン
 */
public function selectStore(Request $request)
{
    $validator = Validator::make($request->all(), [
        'temp_token' => ['required', 'string'],
        'customer_id' => ['required', 'integer', 'exists:customers,id'],
        'remember_me' => ['boolean'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '入力内容に誤りがあります',
            ],
        ], 422);
    }

    // 一時トークン検証
    $tempData = session('store_selection_' . $request->temp_token);

    if (!$tempData || now()->isAfter($tempData['expires_at'])) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TOKEN',
                'message' => 'セッションが無効です。再度ログインしてください。',
            ],
        ], 401);
    }

    // 顧客取得
    $customer = Customer::with('store')->find($request->customer_id);

    // セキュリティチェック: 電話番号が一致するか
    if ($customer->phone !== $tempData['phone']) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => '不正なアクセスです',
            ],
        ], 403);
    }

    // セッションクリア
    session()->forget('store_selection_' . $request->temp_token);

    // トークン生成
    $rememberMe = $request->boolean('remember_me', false);
    $tokenName = $rememberMe ? 'customer-auth-remember' : 'customer-auth';
    $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(2);
    $token = $customer->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

    return response()->json([
        'success' => true,
        'data' => [
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'last_name' => $customer->last_name,
                'first_name' => $customer->first_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'store_id' => $customer->store_id,
                'store_name' => $customer->store->name,
            ],
        ],
    ]);
}
```

#### 2-3. switchStore() の追加

```php
/**
 * 店舗切り替え（マイページから）
 */
public function switchStore(Request $request)
{
    $currentCustomer = $request->user();

    $validator = Validator::make($request->all(), [
        'target_customer_id' => ['required', 'integer', 'exists:customers,id'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '入力内容に誤りがあります',
            ],
        ], 422);
    }

    // 切り替え先の顧客レコードを取得
    $targetCustomer = Customer::with('store')->find($request->target_customer_id);

    // セキュリティチェック: 電話番号が一致するか
    if ($targetCustomer->phone !== $currentCustomer->phone) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => '不正なアクセスです',
            ],
        ], 403);
    }

    // 現在のトークンを削除
    $request->user()->currentAccessToken()->delete();

    // 新しいトークンを生成
    $token = $targetCustomer->createToken('customer-auth', ['*'], now()->addHours(2))->plainTextToken;

    return response()->json([
        'success' => true,
        'data' => [
            'token' => $token,
            'customer' => [
                'id' => $targetCustomer->id,
                'name' => $targetCustomer->full_name,
                'last_name' => $targetCustomer->last_name,
                'first_name' => $targetCustomer->first_name,
                'phone' => $targetCustomer->phone,
                'email' => $targetCustomer->email,
                'store_id' => $targetCustomer->store_id,
                'store_name' => $targetCustomer->store->name,
            ],
        ],
    ]);
}
```

---

### 3. CustomerController の追加

**ファイル**: `app/Http/Controllers/Api/CustomerController.php`

```php
/**
 * 同じ電話番号で登録されている全店舗を取得
 */
public function getStores(Request $request)
{
    $currentCustomer = $request->user();

    $stores = Customer::where('phone', $currentCustomer->phone)
        ->with('store')
        ->get()
        ->map(function($customer) {
            return [
                'customer_id' => $customer->id,
                'store_id' => $customer->store_id,
                'store_name' => $customer->store->name,
                'last_visit_at' => $customer->last_visit_at,
            ];
        });

    return response()->json([
        'success' => true,
        'data' => $stores
    ]);
}
```

---

## フロントエンド実装

### 1. ログイン画面の修正

**ファイル**: `resources/views/customer/login.blade.php`

#### JavaScript部分に追加

```javascript
// 既存の verifyOtp 関数を修正
async function verifyOtp() {
    // ... 既存のコード ...

    const response = await fetch('/api/auth/customer/verify-otp', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            phone: phone,
            otp_code: otpCode,
            remember_me: rememberMe
        })
    });

    const data = await response.json();

    if (!response.ok) {
        // エラー処理...
        return;
    }

    // 🆕 複数店舗の場合
    if (data.data.multiple_accounts) {
        showStoreSelection(data.data);
        return;
    }

    // 1店舗の場合（既存の処理）
    localStorage.setItem('customer_token', data.data.token);
    localStorage.setItem('customer_data', JSON.stringify(data.data.customer));
    window.location.href = '/customer/dashboard';
}

// 🆕 店舗選択画面を表示
function showStoreSelection(data) {
    const loginForm = document.getElementById('login-form');
    loginForm.innerHTML = `
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                利用店舗を選択してください
            </h2>
            <p class="text-gray-600 text-sm">
                複数の店舗でご登録があります
            </p>
        </div>

        <div class="space-y-3">
            ${data.stores.map(store => `
                <div class="border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
                     onclick="selectStore('${data.temp_token}', ${store.customer_id})">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">
                                📍 ${store.store_name}
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                最終来店: ${store.last_visit_at ? new Date(store.last_visit_at).toLocaleDateString('ja-JP') : '未来店'}
                            </p>
                        </div>
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// 🆕 店舗を選択
async function selectStore(tempToken, customerId) {
    try {
        const response = await fetch('/api/auth/customer/select-store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                temp_token: tempToken,
                customer_id: customerId,
                remember_me: true
            })
        });

        if (!response.ok) {
            throw new Error('店舗選択に失敗しました');
        }

        const data = await response.json();

        // トークンと顧客データを保存
        localStorage.setItem('customer_token', data.data.token);
        localStorage.setItem('customer_data', JSON.stringify(data.data.customer));

        // マイページへリダイレクト
        window.location.href = '/customer/dashboard';

    } catch (error) {
        console.error('Store selection error:', error);
        alert('店舗選択に失敗しました。再度ログインしてください。');
        window.location.reload();
    }
}
```

---

### 2. マイページの修正

**ファイル**: `resources/views/customer/dashboard.blade.php`

#### HTML部分の修正（14-25行目）

```html
<!-- ヘッダー -->
<div class="bg-white rounded-lg shadow-md p-4 md:p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex-1">
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">マイページ</h1>
            <p class="text-sm md:text-base text-gray-600" id="customer-info">
                読み込み中...
            </p>

            <!-- 🆕 店舗切り替えUI（複数店舗の場合のみ表示） -->
            <div id="store-switcher" class="hidden mt-2 flex items-center gap-2 flex-wrap">
                <span class="text-sm text-gray-500">📍</span>
                <span id="current-store-name" class="text-sm font-medium text-blue-600"></span>
                <button id="switch-store-btn"
                        class="text-sm text-blue-600 hover:text-blue-700 font-medium underline decoration-dotted">
                    店舗を切り替え
                </button>
            </div>
        </div>
        <button id="logout-btn" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
            ログアウト
        </button>
    </div>
</div>
```

#### モーダルの追加（191行目の後に追加）

```html
<!-- 🆕 店舗切り替えモーダル -->
<div id="storeSwitcherModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="relative mx-auto border w-full max-w-md bg-white rounded-lg shadow-xl">
        <div class="p-6">
            <!-- ヘッダー -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    利用店舗を選択
                </h3>
                <button onclick="closeStoreSwitcherModal()"
                        class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- 店舗リスト -->
            <div id="store-list" class="space-y-3">
                <!-- 動的に挿入 -->
            </div>
        </div>
    </div>
</div>
```

#### JavaScript部分の追加（325行目の後に追加）

```javascript
// 🆕 ページロード時に複数店舗チェック
document.addEventListener('DOMContentLoaded', async function() {
    // ... 既存のコード ...

    // 複数店舗チェック
    await checkMultipleStores();
});

// 🆕 複数店舗があるかチェック
async function checkMultipleStores() {
    try {
        const token = localStorage.getItem('customer_token');
        const customerData = JSON.parse(localStorage.getItem('customer_data'));

        const response = await fetch('/api/customer/stores', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) return;

        const data = await response.json();
        const stores = data.data || [];

        if (stores.length > 1) {
            // 複数店舗あり - 切り替えUIを表示
            const storeSwitcher = document.getElementById('store-switcher');
            storeSwitcher.classList.remove('hidden');

            // 現在の店舗名を表示
            const currentStore = stores.find(s => s.customer_id === customerData.id);
            if (currentStore) {
                document.getElementById('current-store-name').textContent = currentStore.store_name;
            }

            // 切り替えボタンのイベント
            document.getElementById('switch-store-btn').addEventListener('click', () => {
                showStoreSwitcherModal(stores, customerData.id);
            });
        }

    } catch (error) {
        console.error('Error checking multiple stores:', error);
    }
}

// 🆕 店舗切り替えモーダルを表示
function showStoreSwitcherModal(stores, currentCustomerId) {
    const storeList = document.getElementById('store-list');

    storeList.innerHTML = stores.map(store => `
        <div class="border ${store.customer_id === currentCustomerId ? 'border-2 border-blue-500 bg-blue-50' : 'border-gray-200'} rounded-lg p-4 cursor-pointer hover:border-blue-300 transition-colors"
             onclick="switchToStore(${store.customer_id})">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-900">
                        📍 ${store.store_name}
                    </h4>
                    <p class="text-sm text-gray-600 mt-1">
                        最終来店: ${store.last_visit_at ? new Date(store.last_visit_at).toLocaleDateString('ja-JP') : '未来店'}
                    </p>
                </div>
                ${store.customer_id === currentCustomerId ? `
                    <span class="bg-blue-600 text-white text-xs px-2 py-1 rounded font-medium">
                        ✓ 表示中
                    </span>
                ` : ''}
            </div>
        </div>
    `).join('');

    document.getElementById('storeSwitcherModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// 🆕 モーダルを閉じる
function closeStoreSwitcherModal() {
    document.getElementById('storeSwitcherModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// 🆕 店舗を切り替え
async function switchToStore(targetCustomerId) {
    const currentCustomer = JSON.parse(localStorage.getItem('customer_data'));

    if (targetCustomerId === currentCustomer.id) {
        // 同じ店舗なので何もしない
        closeStoreSwitcherModal();
        return;
    }

    try {
        // ローディング表示
        const modal = document.getElementById('storeSwitcherModal');
        const originalContent = modal.innerHTML;
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-8 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-700 font-medium">店舗を切り替え中...</p>
                <p class="text-gray-500 text-sm mt-2">しばらくお待ちください</p>
            </div>
        `;

        const token = localStorage.getItem('customer_token');

        const response = await fetch('/api/auth/customer/switch-store', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                target_customer_id: targetCustomerId
            })
        });

        if (!response.ok) {
            throw new Error('店舗切り替えに失敗しました');
        }

        const data = await response.json();

        // 新しいトークンと顧客データを保存
        localStorage.setItem('customer_token', data.data.token);
        localStorage.setItem('customer_data', JSON.stringify(data.data.customer));

        // ページリロード
        window.location.reload();

    } catch (error) {
        console.error('Store switch error:', error);
        alert('店舗の切り替えに失敗しました。もう一度お試しください。');
        closeStoreSwitcherModal();
    }
}

// モーダル外クリックで閉じる
document.addEventListener('click', function(event) {
    if (event.target === document.getElementById('storeSwitcherModal')) {
        closeStoreSwitcherModal();
    }
    // ... 既存のモーダル処理 ...
});
```

---

## UI/UX仕様

### カラースキーム

| 要素 | クラス | 色 |
|------|--------|-----|
| 現在表示中の店舗（背景） | `bg-blue-50` | 薄い青 |
| 現在表示中の店舗（ボーダー） | `border-2 border-blue-500` | 濃い青・太め |
| 現在表示中のバッジ | `bg-blue-600 text-white` | 青地に白文字 |
| 他の店舗（背景） | `bg-white` | 白 |
| 他の店舗（ボーダー） | `border-gray-200` | グレー |
| ホバー時 | `hover:border-blue-300` | 薄い青 |
| 切り替えボタン | `text-blue-600 underline decoration-dotted` | 青・点線下線 |

### レスポンシブ対応

```css
/* モバイル（〜768px） */
- 縦並びレイアウト
- ボタンは幅100%
- テキストサイズ: text-sm

/* タブレット・PC（768px〜） */
- 横並びレイアウト
- ボタンは auto幅
- テキストサイズ: text-base〜text-xl
```

### アニメーション

```css
/* モーダルのフェードイン */
.modal-enter {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* 店舗カードのホバー */
.store-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
```

---

## テストシナリオ

### テスト1: 1店舗のみのユーザー

**前提条件**:
- 田中太郎さん (phone: 09012345678) が新宿店のみに登録

**手順**:
1. ログイン画面で電話番号入力
2. OTP入力
3. ログイン成功

**期待結果**:
- ✅ 店舗選択画面は**表示されない**
- ✅ マイページに直接遷移
- ✅ ヘッダーに店舗切り替えボタンは**表示されない**

---

### テスト2: 複数店舗のユーザー（初回ログイン）

**前提条件**:
- 田中太郎さん (phone: 09012345678) が新宿店と秋葉原店の両方に登録

**手順**:
1. ログイン画面で電話番号入力
2. OTP入力
3. 店舗選択画面が表示される
4. 秋葉原店を選択

**期待結果**:
- ✅ 店舗選択画面に2店舗表示
- ✅ 最終来店日が表示される
- ✅ 秋葉原店を選択後、マイページに遷移
- ✅ ヘッダーに「📍 目のトレーニングAKIBA末広町店 [店舗を切り替え]」が表示

---

### テスト3: 店舗切り替え

**前提条件**:
- テスト2の続き（秋葉原店でログイン中）

**手順**:
1. マイページで「店舗を切り替え」をクリック
2. モーダルで新宿店を選択

**期待結果**:
- ✅ モーダルに2店舗表示
- ✅ 秋葉原店に「✓ 表示中」バッジ
- ✅ 新宿店を選択
- ✅ ローディング表示
- ✅ ページリロード
- ✅ 新宿店のデータが表示される
- ✅ ヘッダーが「📍 目のトレーニング新宿店」に変わる

---

### テスト4: カルテ・予約の表示

**前提条件**:
- 田中太郎さんが新宿店（customer_id=100）と秋葉原店（customer_id=200）に登録
- 新宿店で予約5件、カルテ3件
- 秋葉原店で予約2件、カルテ1件

**手順**:
1. 新宿店でログイン
2. カルテ一覧を表示
3. 店舗を秋葉原店に切り替え
4. カルテ一覧を表示

**期待結果**:
- ✅ 新宿店: カルテ3件表示
- ✅ 秋葉原店: カルテ1件表示
- ✅ 各店舗のデータが正しく分離されている

---

### テスト5: セキュリティ

**手順**:
1. 田中太郎さんでログイン（customer_id=100）
2. ブラウザのコンソールで以下を実行:
```javascript
fetch('/api/auth/customer/switch-store', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        target_customer_id: 999 // 他人のID
    })
});
```

**期待結果**:
- ✅ 403 Forbidden エラー
- ✅ 「不正なアクセスです」のメッセージ

---

## ロールバック手順

### 緊急ロールバックが必要な場合

#### ステップ1: 現在のコミットを確認

```bash
git log --oneline -5
```

現在のコミット: `8c337ae`

#### ステップ2: ロールバック実行

```bash
# マイグレーションをロールバック
php artisan migrate:rollback --step=1

# コードをロールバック
git reset --hard 8c337ae

# 本番環境にデプロイ
git push origin main --force  # ⚠️ 注意: force pushは慎重に
```

#### ステップ3: 本番環境でマイグレーション実行

```bash
ssh ubuntu@54.64.54.226
cd /var/www/html
php artisan migrate:rollback --step=1
```

---

## リスクと対策

### リスク1: 既存データとの競合

**問題**: 既に同じ電話番号で異なる店舗に登録されているデータがある場合、マイグレーションが失敗する可能性

**対策**:
- マイグレーション前にデータチェック
- 重複データを事前に確認

```sql
-- 重複チェックSQL
SELECT phone, COUNT(*) as count, GROUP_CONCAT(id) as customer_ids, GROUP_CONCAT(store_id) as store_ids
FROM customers
WHERE phone IS NOT NULL AND phone != ''
GROUP BY phone
HAVING COUNT(*) > 1;
```

**結果**: ローカル環境では重複0件（確認済み）

---

### リスク2: トークンの無効化

**問題**: 店舗切り替え時に古いトークンを削除するため、他のデバイスでログインしている場合にログアウトされる

**対策**:
- 仕様として受け入れる
- ユーザーに「他のデバイスでログアウトされます」と通知（将来的に検討）

---

### リスク3: セッションの有効期限

**問題**: 店舗選択画面で長時間操作しないと、セッションが切れる

**対策**:
- セッション有効期限を10分に設定（実装済み）
- 有効期限切れ時は適切なエラーメッセージを表示（実装済み）

---

### リスク4: 本番環境でのマイグレーション失敗

**問題**: SQLiteとMySQL/PostgreSQLでマイグレーションの挙動が異なる

**対策**:
- マイグレーションファイルでドライバーを判定（実装済み）
- ステージング環境で事前にテスト（推奨）

---

## 実装チェックリスト

### フェーズ1: データベース（Day 1）

- [ ] マイグレーションファイル作成
- [ ] ローカル環境でマイグレーション実行
- [ ] 重複データチェック
- [ ] ロールバックテスト

### フェーズ2: バックエンド（Day 1-2）

- [ ] `CustomerAuthController::verifyOtp()` 修正
- [ ] `CustomerAuthController::selectStore()` 追加
- [ ] `CustomerAuthController::switchStore()` 追加
- [ ] `CustomerController::getStores()` 追加
- [ ] ルート追加

### フェーズ3: フロントエンド（Day 2）

- [ ] ログイン画面に店舗選択UI追加
- [ ] マイページに店舗切り替えUI追加
- [ ] モーダルコンポーネント追加
- [ ] JavaScript実装

### フェーズ4: テスト（Day 3）

- [ ] ローカル環境でテストシナリオ1-5実施
- [ ] セキュリティテスト
- [ ] レスポンシブテスト（スマホ・タブレット）

### フェーズ5: デプロイ（Day 3-4）

- [ ] gitコミット
- [ ] 本番環境にマイグレーション実行
- [ ] 本番環境でテスト
- [ ] 監視・ログ確認

---

## 付録

### A. データベーススキーマ（変更後）

```sql
CREATE TABLE customers (
    id INTEGER PRIMARY KEY,
    store_id INTEGER,
    phone VARCHAR(20),
    email VARCHAR(255),
    -- ... その他のカラム ...

    -- 🆕 複合UNIQUE制約
    UNIQUE (store_id, phone),
    UNIQUE (store_id, email),
    UNIQUE (store_id, customer_number)
);
```

### B. API仕様

#### POST /api/auth/customer/verify-otp

**リクエスト**:
```json
{
  "phone": "09012345678",
  "otp_code": "123456",
  "remember_me": true
}
```

**レスポンス（複数店舗）**:
```json
{
  "success": true,
  "data": {
    "multiple_accounts": true,
    "phone": "09012345678",
    "temp_token": "abc123...",
    "stores": [
      {
        "customer_id": 100,
        "store_id": 1,
        "store_name": "目のトレーニング新宿店",
        "last_visit_at": "2025-01-15T10:00:00Z"
      },
      {
        "customer_id": 200,
        "store_id": 4,
        "store_name": "目のトレーニングAKIBA末広町店",
        "last_visit_at": "2025-02-10T14:30:00Z"
      }
    ]
  }
}
```

#### POST /api/auth/customer/select-store

**リクエスト**:
```json
{
  "temp_token": "abc123...",
  "customer_id": 200,
  "remember_me": true
}
```

**レスポンス**:
```json
{
  "success": true,
  "data": {
    "token": "xyz789...",
    "customer": {
      "id": 200,
      "name": "田中 太郎",
      "store_id": 4,
      "store_name": "目のトレーニングAKIBA末広町店"
    }
  }
}
```

#### POST /api/auth/customer/switch-store

**リクエスト**:
```json
{
  "target_customer_id": 100
}
```

**レスポンス**:
```json
{
  "success": true,
  "data": {
    "token": "new_token...",
    "customer": {
      "id": 100,
      "name": "田中 太郎",
      "store_id": 1,
      "store_name": "目のトレーニング新宿店"
    }
  }
}
```

#### GET /api/customer/stores

**レスポンス**:
```json
{
  "success": true,
  "data": [
    {
      "customer_id": 100,
      "store_id": 1,
      "store_name": "目のトレーニング新宿店",
      "last_visit_at": "2025-01-15T10:00:00Z"
    },
    {
      "customer_id": 200,
      "store_id": 4,
      "store_name": "目のトレーニングAKIBA末広町店",
      "last_visit_at": "2025-02-10T14:30:00Z"
    }
  ]
}
```

---

## 変更履歴

| 日付 | バージョン | 変更内容 | 作成者 |
|------|-----------|---------|--------|
| 2025-10-15 | 1.0 | 初版作成 | Claude |

---

**End of Document**
