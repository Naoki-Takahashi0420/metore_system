# 📊 データベース設計書

## 概要

Xsyumeno Laravel版のデータベース設計書です。現在のシステムを分析し、Laravelの規約に従って最適化された設計を提供します。

## 設計方針

- **Laravel命名規約準拠**: テーブル名は複数形、主キーは`id`
- **Eloquent ORM最適化**: リレーション定義を考慮した設計
- **パフォーマンス重視**: 適切なインデックス配置
- **データ整合性**: 外部キー制約とバリデーション
- **拡張性**: 将来の機能追加を考慮

## マイグレーション順序

```php
2024_01_01_000001_create_stores_table.php
2024_01_01_000002_create_users_table.php
2024_01_01_000003_create_customers_table.php
2024_01_01_000004_create_menus_table.php
2024_01_01_000005_create_reservations_table.php
2024_01_01_000006_create_shift_schedules_table.php
2024_01_01_000007_create_medical_records_table.php
2024_01_01_000008_create_otp_verifications_table.php
2024_01_01_000009_add_indexes_for_performance.php
```

## テーブル設計詳細

### 1. stores（店舗テーブル）

```php
Schema::create('stores', function (Blueprint $table) {
    $table->id();
    $table->string('name')->comment('店舗名');
    $table->string('name_kana')->nullable()->comment('店舗名カナ');
    $table->string('postal_code', 8)->nullable()->comment('郵便番号');
    $table->string('prefecture', 50)->nullable()->comment('都道府県');
    $table->string('city', 100)->nullable()->comment('市区町村');
    $table->string('address')->nullable()->comment('住所');
    $table->string('phone', 20)->unique()->comment('電話番号');
    $table->string('email')->unique()->nullable()->comment('メールアドレス');
    $table->json('opening_hours')->nullable()->comment('営業時間');
    $table->json('holidays')->nullable()->comment('定休日');
    $table->integer('capacity')->default(1)->comment('収容人数');
    $table->json('settings')->nullable()->comment('店舗設定');
    $table->json('reservation_settings')->nullable()->comment('予約設定');
    $table->boolean('is_active')->default(true)->comment('アクティブ状態');
    $table->timestamps();
    
    // インデックス
    $table->index(['is_active']);
    $table->index(['prefecture', 'city']);
});
```

**JSON構造例:**
```json
// opening_hours
{
  "monday": {"open": "09:00", "close": "18:00"},
  "tuesday": {"open": "09:00", "close": "18:00"},
  "wednesday": {"open": "09:00", "close": "18:00"},
  "thursday": {"open": "09:00", "close": "18:00"},
  "friday": {"open": "09:00", "close": "18:00"},
  "saturday": {"open": "09:00", "close": "17:00"},
  "sunday": null
}

// reservation_settings
{
  "advance_booking_days": 60,
  "min_interval_days": 5,
  "max_concurrent_reservations": 3,
  "cancellation_deadline_hours": 24,
  "auto_confirm": false
}
```

### 2. users（スタッフ・管理者テーブル）

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->nullable()->constrained()->onDelete('set null');
    $table->string('name')->comment('氏名');
    $table->string('email')->unique()->comment('メールアドレス');
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password')->comment('パスワード');
    $table->enum('role', ['superadmin', 'admin', 'manager', 'staff', 'readonly'])
          ->default('staff')->comment('役職');
    $table->json('permissions')->nullable()->comment('権限設定');
    $table->json('specialties')->nullable()->comment('専門分野');
    $table->decimal('hourly_rate', 8, 2)->nullable()->comment('時給');
    $table->boolean('is_active')->default(true)->comment('アクティブ状態');
    $table->timestamp('last_login_at')->nullable()->comment('最終ログイン');
    $table->rememberToken();
    $table->timestamps();
    
    // インデックス
    $table->index(['store_id', 'role']);
    $table->index(['is_active']);
});
```

### 3. customers（顧客テーブル）

```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('last_name', 100)->comment('姓');
    $table->string('first_name', 100)->comment('名');
    $table->string('last_name_kana', 100)->nullable()->comment('姓カナ');
    $table->string('first_name_kana', 100)->nullable()->comment('名カナ');
    $table->string('phone', 20)->unique()->comment('電話番号');
    $table->string('email')->unique()->nullable()->comment('メールアドレス');
    $table->date('birth_date')->nullable()->comment('生年月日');
    $table->enum('gender', ['male', 'female', 'other'])->nullable()->comment('性別');
    $table->string('postal_code', 8)->nullable()->comment('郵便番号');
    $table->text('address')->nullable()->comment('住所');
    $table->json('preferences')->nullable()->comment('設定・嗜好');
    $table->json('medical_notes')->nullable()->comment('医療メモ');
    $table->boolean('is_blocked')->default(false)->comment('ブロック状態');
    $table->timestamp('last_visit_at')->nullable()->comment('最終来店日');
    $table->timestamp('phone_verified_at')->nullable()->comment('電話番号認証日時');
    $table->timestamps();
    
    // インデックス
    $table->index(['phone_verified_at']);
    $table->index(['last_visit_at']);
    $table->index(['is_blocked']);
    $table->index(['last_name', 'first_name']);
});
```

### 4. menus（メニューテーブル）

```php
Schema::create('menus', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->string('category', 100)->nullable()->comment('カテゴリ');
    $table->string('name')->comment('メニュー名');
    $table->text('description')->nullable()->comment('説明');
    $table->decimal('price', 8, 2)->comment('価格');
    $table->integer('duration')->comment('所要時間（分）');
    $table->boolean('is_available')->default(true)->comment('提供可能');
    $table->integer('max_daily_quantity')->nullable()->comment('1日最大提供数');
    $table->integer('sort_order')->default(0)->comment('表示順');
    $table->json('options')->nullable()->comment('オプション設定');
    $table->json('tags')->nullable()->comment('タグ');
    $table->timestamps();
    
    // インデックス
    $table->index(['store_id', 'category']);
    $table->index(['store_id', 'is_available']);
    $table->index(['sort_order']);
});
```

### 5. reservations（予約テーブル）

```php
Schema::create('reservations', function (Blueprint $table) {
    $table->id();
    $table->string('reservation_number', 50)->unique()->comment('予約番号');
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->foreignId('customer_id')->constrained()->onDelete('cascade');
    $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('set null');
    $table->date('reservation_date')->comment('予約日');
    $table->time('start_time')->comment('開始時刻');
    $table->time('end_time')->comment('終了時刻');
    $table->enum('status', [
        'pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'
    ])->default('pending')->comment('ステータス');
    $table->integer('guest_count')->default(1)->comment('来店人数');
    $table->decimal('total_amount', 10, 2)->default(0)->comment('合計金額');
    $table->decimal('deposit_amount', 10, 2)->default(0)->comment('預かり金');
    $table->string('payment_method', 50)->nullable()->comment('支払方法');
    $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
    $table->json('menu_items')->nullable()->comment('選択メニュー');
    $table->text('notes')->nullable()->comment('備考');
    $table->text('cancel_reason')->nullable()->comment('キャンセル理由');
    $table->timestamp('confirmed_at')->nullable()->comment('確定日時');
    $table->timestamp('cancelled_at')->nullable()->comment('キャンセル日時');
    $table->timestamps();
    
    // インデックス
    $table->index(['store_id', 'reservation_date']);
    $table->index(['store_id', 'status', 'reservation_date']);
    $table->index(['customer_id', 'status']);
    $table->index(['staff_id', 'reservation_date']);
    $table->unique(['staff_id', 'reservation_date', 'start_time'], 'unique_staff_time');
});
```

### 6. shift_schedules（シフトスケジュールテーブル）

```php
Schema::create('shift_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
    $table->date('shift_date')->comment('シフト日');
    $table->time('start_time')->comment('開始時刻');
    $table->time('end_time')->comment('終了時刻');
    $table->time('break_start')->nullable()->comment('休憩開始');
    $table->time('break_end')->nullable()->comment('休憩終了');
    $table->enum('status', [
        'scheduled', 'confirmed', 'working', 'completed', 'cancelled'
    ])->default('scheduled')->comment('ステータス');
    $table->time('actual_start')->nullable()->comment('実際の開始時刻');
    $table->time('actual_end')->nullable()->comment('実際の終了時刻');
    $table->text('notes')->nullable()->comment('備考');
    $table->timestamps();
    
    // インデックス
    $table->index(['store_id', 'shift_date']);
    $table->index(['staff_id', 'shift_date']);
    $table->unique(['staff_id', 'shift_date', 'start_time'], 'unique_staff_shift');
});
```

### 7. medical_records（カルテテーブル）

```php
Schema::create('medical_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->onDelete('cascade');
    $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');
    $table->date('visit_date')->comment('来院日');
    $table->text('symptoms')->nullable()->comment('症状');
    $table->text('diagnosis')->nullable()->comment('診断');
    $table->text('treatment')->nullable()->comment('治療内容');
    $table->json('medications')->nullable()->comment('処方薬');
    $table->text('notes')->nullable()->comment('備考');
    $table->date('next_visit_date')->nullable()->comment('次回来院予定日');
    $table->timestamps();
    
    // インデックス
    $table->index(['customer_id', 'visit_date']);
    $table->index(['staff_id', 'visit_date']);
    $table->index(['reservation_id']);
});
```

### 8. otp_verifications（OTP認証テーブル）

```php
Schema::create('otp_verifications', function (Blueprint $table) {
    $table->id();
    $table->string('phone', 20)->comment('電話番号');
    $table->string('otp_code', 6)->comment('OTPコード');
    $table->timestamp('expires_at')->comment('有効期限');
    $table->timestamp('verified_at')->nullable()->comment('認証完了日時');
    $table->integer('attempts')->default(0)->comment('試行回数');
    $table->timestamps();
    
    // インデックス
    $table->index(['phone', 'otp_code']);
    $table->index(['expires_at']);
});
```

## モデルリレーション定義

### Store Model
```php
class Store extends Model
{
    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    public function menus()
    {
        return $this->hasMany(Menu::class);
    }
    
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
    
    public function shiftSchedules()
    {
        return $this->hasMany(ShiftSchedule::class);
    }
}
```

### User Model
```php
class User extends Authenticatable
{
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'staff_id');
    }
    
    public function shiftSchedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'staff_id');
    }
    
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class, 'staff_id');
    }
}
```

### Customer Model
```php
class Customer extends Model
{
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
    
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }
}
```

### Reservation Model
```php
class Reservation extends Model
{
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
    
    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }
}
```

## パフォーマンス最適化

### 重要インデックス
```sql
-- 予約検索の最適化
CREATE INDEX idx_reservations_store_date_status ON reservations(store_id, reservation_date, status);

-- スタッフスケジュール検索
CREATE INDEX idx_shifts_staff_date ON shift_schedules(staff_id, shift_date);

-- 顧客検索
CREATE INDEX idx_customers_name ON customers(last_name, first_name);

-- フルテキスト検索（必要に応じて）
ALTER TABLE customers ADD FULLTEXT(last_name, first_name, last_name_kana, first_name_kana);
```

### Eloquentクエリ最適化
```php
// N+1問題を避けるEager Loading
$reservations = Reservation::with(['customer', 'staff', 'store'])
    ->where('reservation_date', today())
    ->get();

// 条件付きEager Loading
$stores = Store::with(['reservations' => function ($query) {
    $query->where('reservation_date', '>=', today());
}])->get();
```

## データ整合性制約

### ビジネスルール制約
```sql
-- 予約時間の重複防止
ALTER TABLE reservations ADD CONSTRAINT unique_staff_time 
UNIQUE (staff_id, reservation_date, start_time);

-- シフト時間の重複防止
ALTER TABLE shift_schedules ADD CONSTRAINT unique_staff_shift 
UNIQUE (staff_id, shift_date, start_time);

-- 有効な時間範囲チェック（アプリケーションレベルで実装）
```

### バリデーションルール
```php
// Reservationモデルでの例
public static function rules()
{
    return [
        'reservation_date' => 'required|date|after:today',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'guest_count' => 'required|integer|min:1|max:10',
    ];
}
```

## 移行戦略

### 現行データベースからの移行
1. **データエクスポート**: 現行MySQLからデータ抽出
2. **データクリーニング**: ID形式統一、不整合データ修正
3. **テストデータ作成**: SeederとFactoryでテストデータ生成
4. **段階的移行**: テーブル単位での移行とテスト

### 移行用Seeder例
```php
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            StoreSeeder::class,
            UserSeeder::class,
            CustomerSeeder::class,
            MenuSeeder::class,
            // テストデータは本番では実行しない
            // ReservationSeeder::class,
        ]);
    }
}
```

このデータベース設計により、現在のシステムの全機能を維持しながら、Laravelの規約に準拠した効率的なデータ管理が可能になります。