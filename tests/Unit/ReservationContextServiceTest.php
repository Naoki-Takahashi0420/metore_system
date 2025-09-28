<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ReservationContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class ReservationContextServiceTest extends TestCase
{
    private ReservationContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReservationContextService();
    }

    /** @test */
    public function コンテキストの暗号化と復号化が正常に動作する()
    {
        $originalContext = [
            'type' => 'new_reservation',
            'customer_id' => 123,
            'store_id' => 456,
            'is_existing_customer' => true,
            'source' => 'medical_record'
        ];

        // 暗号化
        $encrypted = $this->service->encryptContext($originalContext);

        // 復号化
        $decrypted = $this->service->decryptContext($encrypted);

        // 元のデータが復元されることを確認
        $this->assertEquals($originalContext['type'], $decrypted['type']);
        $this->assertEquals($originalContext['customer_id'], $decrypted['customer_id']);
        $this->assertEquals($originalContext['store_id'], $decrypted['store_id']);
        $this->assertEquals($originalContext['is_existing_customer'], $decrypted['is_existing_customer']);
        $this->assertEquals($originalContext['source'], $decrypted['source']);

        // タイムスタンプが自動追加されることを確認
        $this->assertArrayHasKey('created_at', $decrypted);
        $this->assertArrayHasKey('expires_at', $decrypted);
    }

    /** @test */
    public function 有効期限が正しく設定される()
    {
        $context = ['type' => 'test'];
        $encrypted = $this->service->encryptContext($context);
        $decrypted = $this->service->decryptContext($encrypted);

        $createdAt = Carbon::createFromTimestamp($decrypted['created_at']);
        $expiresAt = Carbon::createFromTimestamp($decrypted['expires_at']);

        // 作成時刻が現在時刻に近いことを確認
        $this->assertTrue($createdAt->diffInSeconds(Carbon::now()) < 5);

        // 有効期限が2時間後であることを確認
        $this->assertTrue($expiresAt->diffInHours($createdAt) === 2);
    }

    /** @test */
    public function 有効期限切れのコンテキストはnullを返す()
    {
        // 期限切れのコンテキストを手動作成
        $expiredContext = [
            'type' => 'test',
            'created_at' => Carbon::now()->subHours(3)->timestamp,
            'expires_at' => Carbon::now()->subHours(1)->timestamp
        ];

        $encrypted = Crypt::encryptString(json_encode($expiredContext));
        $result = $this->service->decryptContext($encrypted);

        $this->assertNull($result);
    }

    /** @test */
    public function 不正な暗号化データはnullを返す()
    {
        $invalidData = [
            'not-encrypted-data',
            '',
            '12345',
            'invalid-base64!@#',
            str_repeat('x', 1000)
        ];

        foreach ($invalidData as $invalid) {
            $result = $this->service->decryptContext($invalid);
            $this->assertNull($result, "Invalid data '{$invalid}' should return null");
        }
    }

    /** @test */
    public function カルテからの予約コンテキストが正しく生成される()
    {
        $customerId = 123;
        $storeId = 456;

        $encrypted = $this->service->createMedicalRecordContext($customerId, $storeId);
        $decrypted = $this->service->decryptContext($encrypted);

        $this->assertEquals('medical_record', $decrypted['type']);
        $this->assertEquals($customerId, $decrypted['customer_id']);
        $this->assertEquals($storeId, $decrypted['store_id']);
        $this->assertTrue($decrypted['is_existing_customer']);
        $this->assertEquals('medical_record', $decrypted['source']);
    }

    /** @test */
    public function 新規予約コンテキストが正しく生成される()
    {
        // 店舗IDなしの場合
        $encrypted1 = $this->service->createNewReservationContext();
        $decrypted1 = $this->service->decryptContext($encrypted1);

        $this->assertEquals('new_reservation', $decrypted1['type']);
        $this->assertFalse($decrypted1['is_existing_customer']);
        $this->assertEquals('public', $decrypted1['source']);
        $this->assertArrayNotHasKey('store_id', $decrypted1);

        // 店舗IDありの場合
        $storeId = 789;
        $encrypted2 = $this->service->createNewReservationContext($storeId);
        $decrypted2 = $this->service->decryptContext($encrypted2);

        $this->assertEquals('new_reservation', $decrypted2['type']);
        $this->assertFalse($decrypted2['is_existing_customer']);
        $this->assertEquals('public', $decrypted2['source']);
        $this->assertEquals($storeId, $decrypted2['store_id']);
    }

    /** @test */
    public function コンテキストの更新が正常に動作する()
    {
        $originalContext = [
            'type' => 'new_reservation',
            'is_existing_customer' => false,
            'source' => 'public'
        ];

        $updates = [
            'store_id' => 123,
            'category_id' => 456
        ];

        $encrypted = $this->service->updateContext($originalContext, $updates);
        $decrypted = $this->service->decryptContext($encrypted);

        // 元の情報が保持されることを確認
        $this->assertEquals('new_reservation', $decrypted['type']);
        $this->assertFalse($decrypted['is_existing_customer']);
        $this->assertEquals('public', $decrypted['source']);

        // 新しい情報が追加されることを確認
        $this->assertEquals(123, $decrypted['store_id']);
        $this->assertEquals(456, $decrypted['category_id']);
    }

    /** @test */
    public function リクエストからコンテキストが正しく抽出される()
    {
        $context = ['type' => 'test', 'data' => 'value'];
        $encrypted = $this->service->encryptContext($context);

        // モックリクエストを作成
        $request = Request::create('/test', 'GET', ['ctx' => $encrypted]);

        $extracted = $this->service->extractContextFromRequest($request);

        $this->assertEquals('test', $extracted['type']);
        $this->assertEquals('value', $extracted['data']);
    }

    /** @test */
    public function リクエストにコンテキストがない場合nullを返す()
    {
        $request = Request::create('/test', 'GET', []);

        $extracted = $this->service->extractContextFromRequest($request);

        $this->assertNull($extracted);
    }

    /** @test */
    public function URLの生成が正常に動作する()
    {
        $context = ['type' => 'test', 'customer_id' => 123];
        $parameters = ['extra' => 'param'];

        $url = $this->service->buildUrlWithContext('stores', $context, $parameters);

        // URLが正しい形式であることを確認
        $this->assertStringContainsString(route('stores'), $url);
        $this->assertStringContainsString('ctx=', $url);
        $this->assertStringContainsString('extra=param', $url);

        // URLからコンテキストを抽出できることを確認
        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $params);

        $extractedContext = $this->service->decryptContext($params['ctx']);
        $this->assertEquals('test', $extractedContext['type']);
        $this->assertEquals(123, $extractedContext['customer_id']);
    }

    /** @test */
    public function 大きなコンテキストデータも正常に処理される()
    {
        $largeContext = [
            'type' => 'test',
            'large_data' => str_repeat('x', 1000),
            'array_data' => array_fill(0, 100, 'test'),
            'nested' => [
                'level1' => [
                    'level2' => [
                        'data' => 'deep_value'
                    ]
                ]
            ]
        ];

        $encrypted = $this->service->encryptContext($largeContext);
        $decrypted = $this->service->decryptContext($encrypted);

        $this->assertEquals('test', $decrypted['type']);
        $this->assertEquals(str_repeat('x', 1000), $decrypted['large_data']);
        $this->assertCount(100, $decrypted['array_data']);
        $this->assertEquals('deep_value', $decrypted['nested']['level1']['level2']['data']);
    }

    /** @test */
    public function 特殊文字を含むコンテキストが正常に処理される()
    {
        $specialContext = [
            'type' => 'test',
            'japanese' => 'テスト文字列',
            'symbols' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
            'unicode' => '🍎🍊🍌',
            'quotes' => 'It\'s a "test" string',
            'newlines' => "Line 1\nLine 2\r\nLine 3"
        ];

        $encrypted = $this->service->encryptContext($specialContext);
        $decrypted = $this->service->decryptContext($encrypted);

        $this->assertEquals($specialContext['japanese'], $decrypted['japanese']);
        $this->assertEquals($specialContext['symbols'], $decrypted['symbols']);
        $this->assertEquals($specialContext['unicode'], $decrypted['unicode']);
        $this->assertEquals($specialContext['quotes'], $decrypted['quotes']);
        $this->assertEquals($specialContext['newlines'], $decrypted['newlines']);
    }
}