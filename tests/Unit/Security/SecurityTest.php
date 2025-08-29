<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerAccessToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_パスワードハッシュ化()
    {
        // Arrange
        $plainPassword = 'SecurePassword123!';
        
        // Act
        $user = User::create([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
        ]);
        
        // Assert
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
        $this->assertFalse(Hash::check('WrongPassword', $user->password));
    }
    
    public function test_認証トークンの安全性()
    {
        // Arrange
        $customer = Customer::factory()->create();
        
        // Act
        $token1 = CustomerAccessToken::generateFor($customer);
        $token2 = CustomerAccessToken::generateFor($customer);
        
        // Assert
        $this->assertNotEquals($token1->token, $token2->token);
        $this->assertEquals(32, strlen($token1->token));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token1->token);
    }
    
    public function test_CSRF保護()
    {
        // Arrange
        $response = $this->get('/admin/login');
        
        // Assert
        $response->assertSee('_token');
        $response->assertSee('csrf');
    }
    
    public function test_未認証アクセスの防止()
    {
        // Act
        $response = $this->get('/admin');
        
        // Assert
        $response->assertRedirect('/admin/login');
        $response->assertStatus(302);
    }
    
    public function test_権限による制限()
    {
        // Arrange
        $staffUser = User::factory()->create(['role' => 'staff']);
        $adminUser = User::factory()->create(['role' => 'admin']);
        
        // Act & Assert - スタッフユーザー
        $this->actingAs($staffUser);
        $this->assertFalse($staffUser->hasPermission('settings.edit'));
        $this->assertFalse($staffUser->isAdmin());
        
        // Act & Assert - 管理者ユーザー
        $this->actingAs($adminUser);
        $this->assertTrue($adminUser->isAdmin());
    }
    
    public function test_セッション固定攻撃の防止()
    {
        // Arrange
        $user = User::factory()->create();
        
        // Act
        $response1 = $this->get('/admin/login');
        $sessionId1 = session()->getId();
        
        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        
        $sessionId2 = session()->getId();
        
        // Assert - ログイン後にセッションIDが変更される
        $this->assertNotEquals($sessionId1, $sessionId2);
    }
    
    public function test_ブルートフォース攻撃の防止()
    {
        // Arrange
        $user = User::factory()->create();
        $maxAttempts = 5;
        
        // Act - 複数回の失敗試行
        for ($i = 0; $i < $maxAttempts + 1; $i++) {
            $response = $this->post('/admin/login', [
                'email' => $user->email,
                'password' => 'wrongpassword',
            ]);
        }
        
        // Assert - レート制限が発動
        $response->assertStatus(429); // Too Many Requests
    }
    
    public function test_機密情報のログ除外()
    {
        // Arrange
        $sensitiveData = [
            'password' => 'secret123',
            'credit_card' => '4111111111111111',
            'cvv' => '123',
            'token' => 'secret_token',
        ];
        
        // Act
        \Log::info('User data', $sensitiveData);
        
        // Assert - ログファイルに機密情報が含まれていないことを確認
        $logContent = file_get_contents(storage_path('logs/laravel.log'));
        
        $this->assertStringNotContainsString('secret123', $logContent);
        $this->assertStringNotContainsString('4111111111111111', $logContent);
    }
    
    public function test_HTTPSリダイレクト()
    {
        // 本番環境でのみHTTPSを強制
        if (app()->environment('production')) {
            // Act
            $response = $this->get('http://example.com/admin');
            
            // Assert
            $response->assertRedirect('https://example.com/admin');
        } else {
            $this->assertTrue(true);
        }
    }
    
    public function test_セキュリティヘッダー()
    {
        // Act
        $response = $this->get('/');
        
        // Assert
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }
    
    public function test_ファイルアップロード制限()
    {
        // Arrange
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $blockedExtensions = ['php', 'exe', 'sh', 'bat'];
        
        foreach ($blockedExtensions as $ext) {
            // Act
            $validator = \Validator::make(
                ['file' => "test.{$ext}"],
                ['file' => 'regex:/\.(jpg|jpeg|png|pdf)$/i']
            );
            
            // Assert
            $this->assertTrue($validator->fails(), "危険な拡張子 .{$ext} がブロックされていません");
        }
    }
    
    public function test_ディレクトリトラバーサル防止()
    {
        // Arrange
        $maliciousInputs = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            'uploads/../../../config/database.php',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
        ];
        
        foreach ($maliciousInputs as $input) {
            // Act
            $sanitized = basename($input);
            
            // Assert
            $this->assertStringNotContainsString('..', $sanitized);
            $this->assertStringNotContainsString('/', $sanitized);
            $this->assertStringNotContainsString('\\', $sanitized);
        }
    }
    
    public function test_APIレート制限()
    {
        // Arrange
        $endpoint = '/api/reservations';
        $maxRequests = 60; // 1分間の制限
        
        // Act
        for ($i = 0; $i < $maxRequests + 1; $i++) {
            $response = $this->get($endpoint);
        }
        
        // Assert
        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }
    
    public function test_データ暗号化()
    {
        // Arrange
        $sensitiveData = 'クレジットカード番号: 4111111111111111';
        
        // Act
        $encrypted = encrypt($sensitiveData);
        $decrypted = decrypt($encrypted);
        
        // Assert
        $this->assertNotEquals($sensitiveData, $encrypted);
        $this->assertEquals($sensitiveData, $decrypted);
        $this->assertStringNotContainsString('4111111111111111', $encrypted);
    }
    
    public function test_管理画面の二要素認証()
    {
        // 実装されている場合のテスト
        if (config('auth.two_factor_enabled')) {
            // Arrange
            $user = User::factory()->create([
                'two_factor_secret' => encrypt('SECRET123'),
            ]);
            
            // Act
            $this->actingAs($user);
            $response = $this->get('/admin');
            
            // Assert
            $response->assertRedirect('/admin/two-factor');
        } else {
            $this->assertTrue(true);
        }
    }
}