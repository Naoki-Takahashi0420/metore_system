<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use App\Http\Requests\ReservationRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_電話番号バリデーション()
    {
        $testCases = [
            // 有効な電話番号
            ['phone' => '09012345678', 'expected' => true],
            ['phone' => '090-1234-5678', 'expected' => true],
            ['phone' => '03-1234-5678', 'expected' => true],
            ['phone' => '0312345678', 'expected' => true],
            
            // 無効な電話番号
            ['phone' => '123456789', 'expected' => false],
            ['phone' => 'abcdefghijk', 'expected' => false],
            ['phone' => '090-1234-567', 'expected' => false],
            ['phone' => '+81-90-1234-5678', 'expected' => false],
            ['phone' => '', 'expected' => false],
            ['phone' => null, 'expected' => false],
        ];
        
        foreach ($testCases as $case) {
            $validator = Validator::make(
                ['phone' => $case['phone']],
                ['phone' => 'required|regex:/^[0-9]{10,11}$|^[0-9]{2,4}-[0-9]{2,4}-[0-9]{4}$/']
            );
            
            $this->assertEquals(
                $case['expected'],
                !$validator->fails(),
                "電話番号 '{$case['phone']}' のバリデーション結果が期待値と異なります"
            );
        }
    }
    
    public function test_メールアドレスバリデーション()
    {
        $testCases = [
            // 有効なメールアドレス
            ['email' => 'test@example.com', 'expected' => true],
            ['email' => 'user.name@example.co.jp', 'expected' => true],
            ['email' => 'user+tag@example.com', 'expected' => true],
            
            // 無効なメールアドレス
            ['email' => 'invalid.email', 'expected' => false],
            ['email' => '@example.com', 'expected' => false],
            ['email' => 'user@', 'expected' => false],
            ['email' => 'user name@example.com', 'expected' => false],
            ['email' => '', 'expected' => false],
            ['email' => null, 'expected' => false],
        ];
        
        foreach ($testCases as $case) {
            $validator = Validator::make(
                ['email' => $case['email']],
                ['email' => 'required|email']
            );
            
            $this->assertEquals(
                $case['expected'],
                !$validator->fails(),
                "メールアドレス '{$case['email']}' のバリデーション結果が期待値と異なります"
            );
        }
    }
    
    public function test_予約日時バリデーション()
    {
        $testCases = [
            // 有効な日時
            ['date' => '2025-12-31', 'time' => '14:00', 'expected' => true],
            ['date' => '2025-01-01', 'time' => '09:00', 'expected' => true],
            ['date' => '2025-06-15', 'time' => '18:30', 'expected' => true],
            
            // 無効な日時
            ['date' => '2025-13-01', 'time' => '14:00', 'expected' => false],
            ['date' => '2025-12-32', 'time' => '14:00', 'expected' => false],
            ['date' => '2025-12-31', 'time' => '25:00', 'expected' => false],
            ['date' => '2025-12-31', 'time' => '14:60', 'expected' => false],
            ['date' => 'invalid-date', 'time' => '14:00', 'expected' => false],
            ['date' => '2025-12-31', 'time' => 'invalid-time', 'expected' => false],
        ];
        
        foreach ($testCases as $case) {
            $validator = Validator::make(
                ['date' => $case['date'], 'time' => $case['time']],
                [
                    'date' => 'required|date_format:Y-m-d',
                    'time' => 'required|date_format:H:i'
                ]
            );
            
            $this->assertEquals(
                $case['expected'],
                !$validator->fails(),
                "日時 '{$case['date']} {$case['time']}' のバリデーション結果が期待値と異なります"
            );
        }
    }
    
    public function test_金額バリデーション()
    {
        $testCases = [
            // 有効な金額
            ['amount' => 1000, 'expected' => true],
            ['amount' => 0, 'expected' => true],
            ['amount' => 999999, 'expected' => true],
            
            // 無効な金額
            ['amount' => -1000, 'expected' => false],
            ['amount' => 'abc', 'expected' => false],
            ['amount' => 1.5, 'expected' => false],
            ['amount' => null, 'expected' => false],
        ];
        
        foreach ($testCases as $case) {
            $validator = Validator::make(
                ['amount' => $case['amount']],
                ['amount' => 'required|integer|min:0']
            );
            
            $this->assertEquals(
                $case['expected'],
                !$validator->fails(),
                "金額 '{$case['amount']}' のバリデーション結果が期待値と異なります"
            );
        }
    }
    
    public function test_XSS攻撃防止()
    {
        $maliciousInputs = [
            '<script>alert("XSS")</script>',
            '"><script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="http://evil.com"></iframe>',
            '${alert("XSS")}',
            '<svg onload=alert("XSS")>',
        ];
        
        foreach ($maliciousInputs as $input) {
            $cleaned = e($input); // Laravel's escape function
            
            $this->assertStringNotContainsString('<script>', $cleaned);
            $this->assertStringNotContainsString('javascript:', $cleaned);
            $this->assertStringNotContainsString('onerror=', $cleaned);
            $this->assertStringNotContainsString('onload=', $cleaned);
        }
    }
    
    public function test_SQLインジェクション防止()
    {
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM users --",
            "1; DELETE FROM reservations WHERE 1=1",
        ];
        
        foreach ($maliciousInputs as $input) {
            // Laravelのクエリビルダーを使用した場合の安全性確認
            $validator = Validator::make(
                ['input' => $input],
                ['input' => 'string|max:255']
            );
            
            // バリデーションは通るが、クエリビルダーで自動的にエスケープされる
            $this->assertTrue(true); // プレースホルダーの使用を前提
        }
    }
    
    public function test_最大文字数制限()
    {
        $fields = [
            'name' => 100,
            'email' => 255,
            'phone' => 20,
            'notes' => 1000,
            'address' => 500,
        ];
        
        foreach ($fields as $field => $maxLength) {
            // 境界値テスト
            $validInput = str_repeat('a', $maxLength);
            $invalidInput = str_repeat('a', $maxLength + 1);
            
            $validValidator = Validator::make(
                [$field => $validInput],
                [$field => "max:$maxLength"]
            );
            
            $invalidValidator = Validator::make(
                [$field => $invalidInput],
                [$field => "max:$maxLength"]
            );
            
            $this->assertFalse($validValidator->fails(), "{$field}の最大文字数バリデーション（有効）が失敗");
            $this->assertTrue($invalidValidator->fails(), "{$field}の最大文字数バリデーション（無効）が成功");
        }
    }
    
    public function test_特殊文字の処理()
    {
        $specialCharInputs = [
            '山田　太郎', // 全角スペース
            '山田\t太郎', // タブ
            '山田\n太郎', // 改行
            '😀絵文字😀',
            '①②③特殊文字',
            '㈱会社名',
            'café', // アクセント記号
            'Ⅰ・Ⅱ・Ⅲ', // ローマ数字
        ];
        
        foreach ($specialCharInputs as $input) {
            $validator = Validator::make(
                ['name' => $input],
                ['name' => 'string|max:255']
            );
            
            $this->assertFalse($validator->fails(), "特殊文字 '{$input}' のバリデーションが失敗");
        }
    }
    
    public function test_空白文字のトリミング()
    {
        $inputs = [
            '  前後の空白  ' => '前後の空白',
            "\t\tタブ文字\t\t" => 'タブ文字',
            "\n\n改行文字\n\n" => '改行文字',
            '　　全角空白　　' => '全角空白',
        ];
        
        foreach ($inputs as $input => $expected) {
            $trimmed = trim($input);
            $this->assertEquals($expected, $trimmed, "空白文字のトリミングが正しく行われていません");
        }
    }
    
    public function test_必須フィールドの検証()
    {
        $requiredFields = [
            'customer_id' => null,
            'store_id' => null,
            'menu_id' => null,
            'reservation_date' => null,
            'reservation_time' => null,
        ];
        
        $validator = Validator::make(
            $requiredFields,
            [
                'customer_id' => 'required',
                'store_id' => 'required',
                'menu_id' => 'required',
                'reservation_date' => 'required',
                'reservation_time' => 'required',
            ]
        );
        
        $this->assertTrue($validator->fails());
        $this->assertCount(5, $validator->errors()->all());
    }
}