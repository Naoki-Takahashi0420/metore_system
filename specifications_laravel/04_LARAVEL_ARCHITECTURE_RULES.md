# 🏗️ Laravel開発アーキテクチャルール

## 概要

Xsyumeno Laravel版の開発において遵守すべきアーキテクチャルールと開発規約を定義します。一貫性のある高品質なコードベースを維持するための指針を提供します。

## アーキテクチャ原則

### 1. 単一責任の原則 (SRP)
- 各クラスは1つの責任のみを持つ
- コントローラーはHTTPリクエスト処理のみ
- モデルはデータ操作とビジネスルールのみ
- サービスは特定のビジネスロジックのみ

### 2. 依存性逆転の原則 (DIP)
- 高レベルモジュールは低レベルモジュールに依存しない
- インターフェースを使用した抽象化
- DIコンテナによる依存性注入

### 3. 開放閉鎖の原則 (OCP)
- 拡張に対して開放的
- 修正に対して閉鎖的
- インターフェースとトレイトの活用

## ディレクトリ構造

```
app/
├── Console/              # Artisanコマンド
├── Exceptions/           # カスタム例外
├── Http/
│   ├── Controllers/      # HTTPコントローラー
│   │   ├── Api/         # APIコントローラー
│   │   └── Web/         # Webコントローラー
│   ├── Middleware/      # ミドルウェア
│   ├── Requests/        # FormRequest
│   └── Resources/       # APIリソース
├── Models/              # Eloquentモデル
├── Services/            # ビジネスロジック
├── Repositories/        # データアクセス層（必要に応じて）
├── Jobs/                # ジョブ（キュー処理）
├── Events/              # イベント
├── Listeners/           # イベントリスナー
├── Notifications/       # 通知
├── Policies/            # 認可ポリシー
├── Providers/           # サービスプロバイダー
├── Rules/               # カスタムバリデーションルール
└── Filament/            # Filament管理画面
    ├── Resources/       # Filamentリソース
    ├── Pages/           # カスタムページ
    └── Widgets/         # ウィジェット
```

## 命名規約

### クラス・インターフェース
```php
// PascalCase
class CustomerService {}
interface SmsServiceInterface {}
abstract class BaseController {}
trait HasPhoneVerification {}

// 例外クラス
class CustomerNotFoundException extends Exception {}
class InvalidOtpException extends Exception {}
```

### メソッド・変数
```php
// camelCase
public function sendOtpCode() {}
private $phoneNumber;
protected $isVerified;

// 定数
const MAX_ATTEMPTS = 3;
const OTP_EXPIRY_MINUTES = 5;
```

### データベース
```php
// テーブル名: snake_case（複数形）
customers, reservations, shift_schedules, medical_records

// カラム名: snake_case
first_name, phone_verified_at, created_at

// 外部キー: {テーブル名単数}_id
customer_id, store_id, staff_id
```

## モデル設計ルール

### Eloquentモデル基本構造
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;
    
    // 1. テーブル名（必要な場合のみ）
    protected $table = 'customers';
    
    // 2. 主キー（id以外の場合のみ）
    protected $primaryKey = 'id';
    
    // 3. Mass assignment
    protected $fillable = [
        'last_name',
        'first_name',
        'phone',
        'email',
    ];
    
    // 4. Hidden attributes
    protected $hidden = [
        'phone_verified_at',
    ];
    
    // 5. Casts
    protected $casts = [
        'preferences' => 'array',
        'medical_notes' => 'array',
        'birth_date' => 'date',
        'phone_verified_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];
    
    // 6. Relationships
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
    
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }
    
    // 7. Scopes
    public function scopeVerified($query)
    {
        return $query->whereNotNull('phone_verified_at');
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_blocked', false);
    }
    
    // 8. Accessors
    public function getFullNameAttribute()
    {
        return $this->last_name . ' ' . $this->first_name;
    }
    
    // 9. Mutators
    public function setPhoneAttribute($value)
    {
        $this->attributes['phone'] = preg_replace('/[^0-9]/', '', $value);
    }
    
    // 10. Business logic methods
    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }
    
    public function markPhoneAsVerified(): void
    {
        $this->phone_verified_at = now();
        $this->save();
    }
}
```

### リレーション定義ルール
```php
// 1対多（hasMany）
public function reservations()
{
    return $this->hasMany(Reservation::class);
}

// 多対1（belongsTo）
public function customer()
{
    return $this->belongsTo(Customer::class);
}

// 1対1（hasOne）
public function profile()
{
    return $this->hasOne(CustomerProfile::class);
}

// 多対多（belongsToMany）
public function menus()
{
    return $this->belongsToMany(Menu::class, 'reservation_menus')
                ->withPivot('quantity', 'price')
                ->withTimestamps();
}

// ポリモーフィック
public function commentable()
{
    return $this->morphTo();
}
```

## コントローラー設計ルール

### APIコントローラー構造
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    private CustomerService $customerService;
    
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }
    
    /**
     * 顧客一覧取得
     */
    public function index(): JsonResponse
    {
        $customers = $this->customerService->getPaginatedCustomers(
            request()->all()
        );
        
        return $this->successResponse(
            CustomerResource::collection($customers),
            '顧客一覧を取得しました'
        );
    }
    
    /**
     * 顧客詳細取得
     */
    public function show(int $id): JsonResponse
    {
        try {
            $customer = $this->customerService->findById($id);
            
            return $this->successResponse(
                new CustomerResource($customer),
                '顧客詳細を取得しました'
            );
            
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'CUSTOMER_NOT_FOUND',
                '指定された顧客が見つかりません',
                404
            );
        }
    }
    
    /**
     * 顧客作成
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->create($request->validated());
            
            return $this->successResponse(
                new CustomerResource($customer),
                '顧客を作成しました',
                201
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'CUSTOMER_CREATE_ERROR',
                '顧客の作成に失敗しました'
            );
        }
    }
    
    /**
     * 顧客更新
     */
    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customerService->update($id, $request->validated());
            
            return $this->successResponse(
                new CustomerResource($customer),
                '顧客を更新しました'
            );
            
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'CUSTOMER_NOT_FOUND',
                '指定された顧客が見つかりません',
                404
            );
        }
    }
    
    /**
     * 顧客削除
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->customerService->delete($id);
            
            return $this->successResponse(
                null,
                '顧客を削除しました'
            );
            
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'CUSTOMER_NOT_FOUND',
                '指定された顧客が見つかりません',
                404
            );
        }
    }
}
```

### ベースコントローラー
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    
    /**
     * 成功レスポンス
     */
    protected function successResponse($data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => '1.0'
            ]
        ], $status);
    }
    
    /**
     * エラーレスポンス
     */
    protected function errorResponse(string $code, string $message, int $status = 500, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => '1.0'
            ]
        ], $status);
    }
}
```

## サービス層設計ルール

### サービスクラス構造
```php
<?php

namespace App\Services;

use App\Models\Customer;
use App\Exceptions\CustomerNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * ページネーション付き顧客一覧取得
     */
    public function getPaginatedCustomers(array $filters = []): LengthAwarePaginator
    {
        $query = Customer::query();
        
        // 検索フィルター適用
        $this->applyFilters($query, $filters);
        
        return $query->with(['reservations' => function ($q) {
                $q->latest()->limit(3);
            }])
            ->paginate($filters['per_page'] ?? 15);
    }
    
    /**
     * ID指定で顧客取得
     */
    public function findById(int $id): Customer
    {
        return Customer::with(['reservations', 'medicalRecords'])
            ->findOrFail($id);
    }
    
    /**
     * 顧客作成
     */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create($data);
            
            // 追加処理があれば実装
            $this->sendWelcomeNotification($customer);
            
            return $customer;
        });
    }
    
    /**
     * 顧客更新
     */
    public function update(int $id, array $data): Customer
    {
        return DB::transaction(function () use ($id, $data) {
            $customer = $this->findById($id);
            $customer->update($data);
            
            return $customer->fresh();
        });
    }
    
    /**
     * 顧客削除
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $customer = $this->findById($id);
            
            // 関連データの処理
            $this->handleRelatedDataBeforeDelete($customer);
            
            return $customer->delete();
        });
    }
    
    /**
     * フィルター適用
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        
        if (!empty($filters['is_verified'])) {
            $query->whereNotNull('phone_verified_at');
        }
        
        if (!empty($filters['is_blocked'])) {
            $query->where('is_blocked', $filters['is_blocked']);
        }
    }
    
    /**
     * ウェルカム通知送信
     */
    private function sendWelcomeNotification(Customer $customer): void
    {
        // 通知送信処理
    }
    
    /**
     * 削除前の関連データ処理
     */
    private function handleRelatedDataBeforeDelete(Customer $customer): void
    {
        // 関連データの処理
        $customer->reservations()->update(['customer_id' => null]);
    }
}
```

## フォームリクエスト設計ルール

### バリデーションリクエスト構造
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /**
     * 認可チェック
     */
    public function authorize(): bool
    {
        return $this->user()->can('customers.create');
    }
    
    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name_kana' => ['nullable', 'string', 'max:100', 'regex:/^[ァ-ヶー]+$/u'],
            'first_name_kana' => ['nullable', 'string', 'max:100', 'regex:/^[ァ-ヶー]+$/u'],
            'phone' => ['required', 'string', 'regex:/^[0-9\-+().\s]+$/', 'unique:customers,phone'],
            'email' => ['nullable', 'email', 'unique:customers,email'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{3}-?\d{4}$/'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
    
    /**
     * カスタム属性名
     */
    public function attributes(): array
    {
        return [
            'last_name' => '姓',
            'first_name' => '名',
            'last_name_kana' => '姓（カナ）',
            'first_name_kana' => '名（カナ）',
            'phone' => '電話番号',
            'email' => 'メールアドレス',
            'birth_date' => '生年月日',
            'gender' => '性別',
            'postal_code' => '郵便番号',
            'address' => '住所',
        ];
    }
    
    /**
     * カスタムエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'last_name_kana.regex' => '姓（カナ）はカタカナで入力してください',
            'first_name_kana.regex' => '名（カナ）はカタカナで入力してください',
            'phone.regex' => '電話番号の形式が正しくありません',
            'postal_code.regex' => '郵便番号は「123-4567」の形式で入力してください',
        ];
    }
    
    /**
     * バリデーション後の処理
     */
    protected function passedValidation(): void
    {
        // 電話番号の正規化
        $this->merge([
            'phone' => preg_replace('/[^0-9]/', '', $this->phone)
        ]);
        
        // 郵便番号の正規化
        if ($this->postal_code) {
            $this->merge([
                'postal_code' => preg_replace('/[^0-9]/', '', $this->postal_code)
            ]);
        }
    }
}
```

## APIリソース設計ルール

### リソースクラス構造
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * リソース変換
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'address' => $this->address,
            'is_phone_verified' => $this->isPhoneVerified(),
            'is_blocked' => $this->is_blocked,
            'last_visit_at' => $this->last_visit_at?->toISOString(),
            
            // 条件付きフィールド
            'recent_reservations' => ReservationResource::collection(
                $this->whenLoaded('reservations')
            ),
            'medical_records_count' => $this->whenCounted('medicalRecords'),
            
            // 管理者のみ表示
            'created_at' => $this->when(
                $request->user()?->can('customers.view.details'),
                $this->created_at?->toISOString()
            ),
            
            'meta' => [
                'reservations_count' => $this->reservations_count ?? 0,
                'total_spent' => $this->calculateTotalSpent(),
            ],
        ];
    }
    
    /**
     * 追加メタデータ
     */
    public function with(Request $request): array
    {
        return [
            'links' => [
                'reservations' => route('api.customers.reservations', $this->id),
                'medical_records' => route('api.customers.medical-records', $this->id),
            ],
        ];
    }
}
```

## 例外処理ルール

### カスタム例外クラス
```php
<?php

namespace App\Exceptions;

use Exception;

class CustomerNotFoundException extends Exception
{
    public function __construct(int $customerId)
    {
        parent::__construct("Customer with ID {$customerId} not found");
    }
    
    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CUSTOMER_NOT_FOUND',
                'message' => 'お客様が見つかりません'
            ]
        ], 404);
    }
}

class InvalidOtpException extends Exception
{
    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_OTP',
                'message' => '認証コードが正しくありません'
            ]
        ], 422);
    }
}
```

### グローバル例外ハンドラー
```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * レポートしない例外
     */
    protected $dontReport = [
        //
    ];
    
    /**
     * 例外レンダリング
     */
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            return $this->handleApiException($request, $exception);
        }
        
        return parent::render($request, $exception);
    }
    
    /**
     * API例外処理
     */
    private function handleApiException($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'バリデーションエラーが発生しました',
                    'details' => $exception->errors()
                ]
            ], 422);
        }
        
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'リソースが見つかりません'
                ]
            ], 404);
        }
        
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ENDPOINT_NOT_FOUND',
                    'message' => 'エンドポイントが見つかりません'
                ]
            ], 404);
        }
        
        // その他の例外
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_SERVER_ERROR',
                'message' => 'サーバー内部エラーが発生しました'
            ]
        ], 500);
    }
}
```

## テストルール

### フィーチャーテスト構造
```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * 顧客一覧取得テスト
     */
    public function test_customer_index_returns_paginated_customers()
    {
        // Arrange
        Customer::factory()->count(20)->create();
        $user = User::factory()->create();
        
        // Act
        $response = $this->actingAs($user, 'sanctum')
                        ->getJson('/api/v1/customers');
        
        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => ['id', 'name', 'phone', 'email']
                    ],
                    'meta' => [
                        'pagination' => ['current_page', 'total', 'per_page']
                    ]
                ]);
    }
    
    /**
     * 顧客作成テスト
     */
    public function test_customer_can_be_created_with_valid_data()
    {
        // Arrange
        $user = User::factory()->create();
        $customerData = [
            'last_name' => '山田',
            'first_name' => '太郎',
            'phone' => '09012345678',
            'email' => 'yamada@example.com'
        ];
        
        // Act
        $response = $this->actingAs($user, 'sanctum')
                        ->postJson('/api/v1/customers', $customerData);
        
        // Assert
        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'last_name' => '山田',
                        'first_name' => '太郎',
                        'phone' => '09012345678'
                    ]
                ]);
        
        $this->assertDatabaseHas('customers', [
            'phone' => '09012345678'
        ]);
    }
}
```

## パフォーマンスルール

### Eloquent最適化
```php
// N+1問題を避けるEager Loading
$customers = Customer::with(['reservations', 'medicalRecords'])->get();

// 条件付きEager Loading
$customers = Customer::with([
    'reservations' => function ($query) {
        $query->where('status', 'confirmed')->latest();
    }
])->get();

// 必要なカラムのみ選択
$customers = Customer::select(['id', 'last_name', 'first_name', 'phone'])->get();

// チャンク処理で大量データを処理
Customer::chunk(200, function ($customers) {
    foreach ($customers as $customer) {
        // 処理
    }
});
```

## セキュリティルール

### 入力検証
```php
// 常にFormRequestを使用
public function store(StoreCustomerRequest $request)
{
    // バリデーション済みデータのみ使用
    $customer = Customer::create($request->validated());
}

// Mass Assignment保護
class Customer extends Model
{
    protected $fillable = ['last_name', 'first_name', 'phone', 'email'];
    protected $guarded = ['id', 'phone_verified_at'];
}
```

### 認可
```php
// ポリシーを使用
$this->authorize('view', $customer);

// ミドルウェアで権限チェック
Route::middleware(['can:customers.create'])->group(function () {
    Route::post('/customers', [CustomerController::class, 'store']);
});
```

このアーキテクチャルールに従うことで、保守性・拡張性・パフォーマンスに優れたLaravelアプリケーションを構築できます。