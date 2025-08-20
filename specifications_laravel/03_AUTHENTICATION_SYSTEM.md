# 🔐 認証・認可システム設計書

## 概要

Xsyumeno Laravel版の認証・認可システムの詳細設計書です。Amazon SNS SMS認証と従来の管理者認証を組み合わせたハイブリッド認証システムを実装します。

## 認証アーキテクチャ

### 二重認証システム
1. **顧客認証**: Amazon SNS SMS OTP（パスワードレス）
2. **管理者認証**: Laravel標準認証（メール・パスワード）

### 使用技術
- **Laravel Sanctum**: APIトークン管理
- **Amazon SNS**: SMS送信
- **Laravel Session**: Webセッション管理
- **Spatie Permission**: ロールベース権限管理

## 顧客認証システム（SMS OTP）

### 認証フロー概要

```
1. 電話番号入力
   ↓
2. Amazon SNS でSMS送信
   ↓
3. OTPコード入力・検証
   ↓
4. 既存顧客確認
   ├─ 既存 → ログイン完了
   └─ 新規 → 会員登録フォーム
           ↓
       5. 会員登録完了
```

### Amazon SNS 設定

#### 環境変数設定
```env
# Amazon SNS
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=ap-northeast-1
AWS_SNS_REGION=ap-northeast-1

# OTP設定
OTP_LENGTH=6
OTP_EXPIRY_MINUTES=5
OTP_MAX_ATTEMPTS=3
OTP_RATE_LIMIT_HOUR=5
```

#### SNSサービス実装
```php
<?php

namespace App\Services;

use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SmsService
{
    private SnsClient $snsClient;
    
    public function __construct()
    {
        $this->snsClient = new SnsClient([
            'region' => config('services.aws.sns_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);
    }
    
    /**
     * OTPコードを送信
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        try {
            // レート制限チェック
            if (!$this->checkRateLimit($phone)) {
                throw new \Exception('送信回数上限に達しています');
            }
            
            $message = $this->buildOtpMessage($otp);
            
            $result = $this->snsClient->publish([
                'PhoneNumber' => $this->formatPhoneNumber($phone),
                'Message' => $message,
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Transactional'
                    ],
                    'AWS.SNS.SMS.SenderID' => [
                        'DataType' => 'String', 
                        'StringValue' => 'Xsyumeno'
                    ]
                ]
            ]);
            
            // 送信履歴を記録
            $this->recordSendHistory($phone);
            
            Log::info('SMS送信成功', [
                'phone' => $phone,
                'message_id' => $result['MessageId'] ?? null
            ]);
            
            return true;
            
        } catch (AwsException $e) {
            Log::error('SMS送信エラー (AWS)', [
                'phone' => $phone,
                'error_code' => $e->getAwsErrorCode(),
                'error_message' => $e->getAwsErrorMessage()
            ]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('SMS送信エラー', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 電話番号を国際形式に変換
     */
    private function formatPhoneNumber(string $phone): string
    {
        // 日本の電話番号を+81形式に変換
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = '+81' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+81' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * OTPメッセージを構築
     */
    private function buildOtpMessage(string $otp): string
    {
        return "【Xsyumeno】\n認証コード: {$otp}\n有効期限: 5分\n\n本人以外の方が受信された場合は破棄してください。";
    }
    
    /**
     * レート制限チェック
     */
    private function checkRateLimit(string $phone): bool
    {
        $key = "sms_rate_limit:{$phone}";
        $count = Cache::get($key, 0);
        
        return $count < config('otp.max_attempts_per_hour', 5);
    }
    
    /**
     * 送信履歴を記録
     */
    private function recordSendHistory(string $phone): void
    {
        $key = "sms_rate_limit:{$phone}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }
}
```

### OTP管理サービス

```php
<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OtpService
{
    private SmsService $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    
    /**
     * OTPを生成・送信
     */
    public function sendOtp(string $phone): array
    {
        DB::beginTransaction();
        
        try {
            // 既存の未使用OTPを削除
            OtpVerification::where('phone', $phone)
                ->whereNull('verified_at')
                ->delete();
            
            // 新しいOTPを生成
            $otpCode = $this->generateOtpCode();
            $expiresAt = now()->addMinutes(config('otp.expiry_minutes', 5));
            
            // OTP記録を保存
            $otpRecord = OtpVerification::create([
                'phone' => $phone,
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt,
                'attempts' => 0
            ]);
            
            // SMS送信
            $smsSent = $this->smsService->sendOtp($phone, $otpCode);
            
            if (!$smsSent) {
                throw new \Exception('SMS送信に失敗しました');
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'expires_at' => $expiresAt,
                'resend_available_at' => now()->addMinute()
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * OTPを検証
     */
    public function verifyOtp(string $phone, string $otpCode): array
    {
        $otpRecord = OtpVerification::where('phone', $phone)
            ->where('otp_code', $otpCode)
            ->whereNull('verified_at')
            ->first();
        
        if (!$otpRecord) {
            return [
                'success' => false,
                'error' => 'OTP_INVALID',
                'message' => '認証コードが正しくありません'
            ];
        }
        
        // 有効期限チェック
        if ($otpRecord->expires_at < now()) {
            return [
                'success' => false,
                'error' => 'OTP_EXPIRED',
                'message' => '認証コードの有効期限が切れています'
            ];
        }
        
        // 試行回数チェック
        if ($otpRecord->attempts >= config('otp.max_attempts', 3)) {
            return [
                'success' => false,
                'error' => 'OTP_MAX_ATTEMPTS',
                'message' => '認証回数の上限に達しました'
            ];
        }
        
        // 試行回数を増加
        $otpRecord->increment('attempts');
        
        // 認証成功時
        $otpRecord->update(['verified_at' => now()]);
        
        return [
            'success' => true,
            'verified_at' => now()
        ];
    }
    
    /**
     * OTPコードを生成
     */
    private function generateOtpCode(): string
    {
        $length = config('otp.length', 6);
        return str_pad(random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * 期限切れOTPを削除
     */
    public function cleanupExpiredOtps(): void
    {
        OtpVerification::where('expires_at', '<', now()->subDay())->delete();
    }
}
```

### 顧客認証コントローラー

```php
<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\HasApiTokens;

class CustomerAuthController extends Controller
{
    private OtpService $otpService;
    
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }
    
    /**
     * OTP送信
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9\-+().\s]+$/'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'バリデーションエラー',
                    'details' => $validator->errors()
                ]
            ], 422);
        }
        
        try {
            $phone = $this->normalizePhoneNumber($request->phone);
            $result = $this->otpService->sendOtp($phone);
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => '認証コードを送信しました'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SMS_SEND_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * OTP検証・ログイン
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp_code' => 'required|string|size:6'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'バリデーションエラー',
                    'details' => $validator->errors()
                ]
            ], 422);
        }
        
        $phone = $this->normalizePhoneNumber($request->phone);
        $otpResult = $this->otpService->verifyOtp($phone, $request->otp_code);
        
        if (!$otpResult['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $otpResult['error'],
                    'message' => $otpResult['message']
                ]
            ], 422);
        }
        
        // 既存顧客を確認
        $customer = Customer::where('phone', $phone)->first();
        
        if ($customer) {
            // 既存顧客: ログイン処理
            $customer->update([
                'phone_verified_at' => now(),
                'last_visit_at' => now()
            ]);
            
            $token = $customer->createToken('customer-token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => $customer->only([
                        'id', 'last_name', 'first_name', 'phone', 'email'
                    ]),
                    'token' => $token,
                    'is_new_customer' => false
                ],
                'message' => 'ログインしました'
            ]);
            
        } else {
            // 新規顧客: 一時トークン発行
            $tempToken = encrypt([
                'phone' => $phone,
                'verified_at' => now(),
                'expires_at' => now()->addMinutes(30)
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'phone' => $phone,
                    'temp_token' => $tempToken,
                    'is_new_customer' => true
                ],
                'message' => '会員登録が必要です'
            ]);
        }
    }
    
    /**
     * 新規会員登録
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => 'required|string',
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'last_name_kana' => 'nullable|string|max:100',
            'first_name_kana' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:customers,email',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'postal_code' => 'nullable|string|max:8',
            'address' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'バリデーションエラー',
                    'details' => $validator->errors()
                ]
            ], 422);
        }
        
        try {
            // 一時トークンを検証
            $tokenData = decrypt($request->temp_token);
            
            if ($tokenData['expires_at'] < now()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TOKEN_EXPIRED',
                        'message' => 'トークンの有効期限が切れています'
                    ]
                ], 422);
            }
            
            // 顧客を作成
            $customer = Customer::create([
                'last_name' => $request->last_name,
                'first_name' => $request->first_name,
                'last_name_kana' => $request->last_name_kana,
                'first_name_kana' => $request->first_name_kana,
                'phone' => $tokenData['phone'],
                'email' => $request->email,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'postal_code' => $request->postal_code,
                'address' => $request->address,
                'phone_verified_at' => $tokenData['verified_at'],
                'last_visit_at' => now()
            ]);
            
            $token = $customer->createToken('customer-token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => $customer->only([
                        'id', 'last_name', 'first_name', 'phone', 'email'
                    ]),
                    'token' => $token
                ],
                'message' => '会員登録が完了しました'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REGISTRATION_ERROR',
                    'message' => '会員登録に失敗しました'
                ]
            ], 500);
        }
    }
    
    /**
     * ログアウト
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'ログアウトしました'
        ]);
    }
    
    /**
     * 電話番号を正規化
     */
    private function normalizePhoneNumber(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
```

## 管理者認証システム

### Filament 管理画面認証

#### AdminUser モデル
```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
    
    protected $fillable = [
        'name', 'email', 'password', 'store_id', 'role', 
        'permissions', 'specialties', 'hourly_rate', 'is_active'
    ];
    
    protected $hidden = ['password', 'remember_token'];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'specialties' => 'array',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean'
    ];
    
    /**
     * Filament管理画面アクセス権限
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole([
            'superadmin', 'admin', 'manager', 'staff'
        ]);
    }
    
    /**
     * 店舗関係
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    
    /**
     * 自分の店舗の権限チェック
     */
    public function canAccessStore(int $storeId): bool
    {
        return $this->hasRole('superadmin') || 
               $this->hasRole('admin') || 
               $this->store_id === $storeId;
    }
}
```

### 権限管理システム（Spatie Permission）

#### 権限定義
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // 権限を作成
        $permissions = [
            // 店舗管理
            'stores.view',
            'stores.create', 
            'stores.edit',
            'stores.delete',
            
            // 顧客管理
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'customers.export',
            
            // 予約管理
            'reservations.view',
            'reservations.create',
            'reservations.edit',
            'reservations.delete',
            'reservations.status.change',
            
            // メニュー管理
            'menus.view',
            'menus.create',
            'menus.edit',
            'menus.delete',
            
            // スタッフ管理
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            
            // シフト管理
            'shifts.view',
            'shifts.create',
            'shifts.edit',
            'shifts.delete',
            'shifts.bulk',
            
            // カルテ管理
            'medical.view',
            'medical.create',
            'medical.edit',
            'medical.delete',
            
            // レポート
            'reports.view',
            'reports.export',
            
            // システム管理
            'system.settings',
            'system.backup',
            'system.logs'
        ];
        
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
        
        // ロールを作成
        $roles = [
            'superadmin' => Permission::all(),
            'admin' => [
                'stores.view', 'stores.edit',
                'customers.view', 'customers.create', 'customers.edit', 'customers.export',
                'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.status.change',
                'menus.view', 'menus.create', 'menus.edit',
                'users.view', 'users.create', 'users.edit',
                'shifts.view', 'shifts.create', 'shifts.edit', 'shifts.bulk',
                'medical.view', 'medical.create', 'medical.edit',
                'reports.view', 'reports.export'
            ],
            'manager' => [
                'customers.view', 'customers.create', 'customers.edit',
                'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.status.change',
                'menus.view',
                'users.view',
                'shifts.view', 'shifts.create', 'shifts.edit',
                'medical.view', 'medical.create', 'medical.edit',
                'reports.view'
            ],
            'staff' => [
                'customers.view',
                'reservations.view', 'reservations.status.change',
                'menus.view',
                'shifts.view',
                'medical.view', 'medical.create', 'medical.edit'
            ],
            'readonly' => [
                'customers.view',
                'reservations.view',
                'menus.view',
                'shifts.view',
                'medical.view',
                'reports.view'
            ]
        ];
        
        foreach ($roles as $roleName => $permissions) {
            $role = Role::create(['name' => $roleName]);
            
            if (is_array($permissions)) {
                $role->givePermissionTo($permissions);
            } else {
                $role->givePermissionTo($permissions);
            }
        }
    }
}
```

### Filament認証設定

#### config/filament.php
```php
<?php

return [
    'auth' => [
        'guard' => 'web',
        'providers' => [
            'users' => [
                'driver' => 'eloquent',
                'model' => App\Models\User::class,
            ],
        ],
        
        'pages' => [
            'login' => App\Filament\Pages\Auth\Login::class,
        ],
        
        'middleware' => [
            'auth:web',
            'verified',
        ],
    ],
    
    'middleware' => [
        'auth' => [
            Authenticate::class,
        ],
        'can' => [
            Authorize::class,
        ],
    ],
];
```

## セキュリティ機能

### ミドルウェア実装

#### OTP認証ミドルウェア
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifiedPhoneMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $customer = $request->user();
        
        if (!$customer || !$customer->phone_verified_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PHONE_NOT_VERIFIED',
                    'message' => '電話番号の認証が必要です'
                ]
            ], 422);
        }
        
        return $next($request);
    }
}
```

#### ストア権限チェックミドルウェア
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckStoreAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $storeId = $request->route('store_id') ?? $request->input('store_id');
        
        if (!$user->canAccessStore($storeId)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_ACCESS_DENIED',
                    'message' => 'この店舗へのアクセス権限がありません'
                ]
            ], 403);
        }
        
        return $next($request);
    }
}
```

### レート制限

#### config/sanctum.php
```php
<?php

return [
    'expiration' => 60 * 24 * 7, // 7日間
    
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

#### ルートでのレート制限
```php
// routes/api.php
Route::middleware(['throttle:otp'])->group(function () {
    Route::post('/auth/send-otp', [CustomerAuthController::class, 'sendOtp']);
});

Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/auth/verify-otp', [CustomerAuthController::class, 'verifyOtp']);
    Route::post('/auth/register', [CustomerAuthController::class, 'register']);
});
```

#### app/Providers/RouteServiceProvider.php
```php
protected function configureRateLimiting()
{
    RateLimiter::for('otp', function (Request $request) {
        return Limit::perHour(5)->by($request->ip());
    });
    
    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(10)->by($request->ip());
    });
}
```

## 監査ログ

### ログ記録
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditLogService
{
    public static function logAuth(string $action, array $data = [])
    {
        Log::channel('auth')->info($action, [
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'data' => $data
        ]);
    }
    
    public static function logOtpAttempt(string $phone, bool $success, string $error = null)
    {
        Log::channel('otp')->info('OTP認証試行', [
            'phone' => $phone,
            'success' => $success,
            'error' => $error,
            'ip_address' => request()->ip(),
            'timestamp' => now()
        ]);
    }
}
```

この認証システム設計により、セキュアで使いやすい認証機能を提供できます。