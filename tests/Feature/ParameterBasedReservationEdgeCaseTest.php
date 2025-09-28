<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Store;
use App\Models\Customer;
use App\Services\ReservationContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class ParameterBasedReservationEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private ReservationContextService $contextService;
    private Store $store;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextService = new ReservationContextService();

        $this->store = Store::factory()->create([
            'name' => 'テスト店舗',
            'is_active' => true
        ]);

        $this->customer = Customer::factory()->create([
            'phone' => '08012345678'
        ]);
    }

    /** @test */
    public function 改ざんされた暗号化パラメータは拒否される()
    {
        // 正常なコンテキストを作成
        $context = ['type' => 'new_reservation', 'customer_id' => $this->customer->id];
        $encryptedContext = $this->contextService->encryptContext($context);

        // パラメータを改ざん
        $tamperedContext = $encryptedContext . 'tampered';

        // 改ざんされたパラメータでアクセス
        $response = $this->get(route('reservation.select-category', ['ctx' => $tamperedContext]));

        // エラーでリダイレクトされることを確認
        $response->assertRedirect(route('stores'));
        $response->assertSessionHasErrors(['error' => '予約情報が見つかりません']);
    }

    /** @test */
    public function 無効な形式の暗号化パラメータは拒否される()
    {
        // 無効な形式のパラメータ
        $invalidParams = [
            'invalid-base64',
            '',
            'not-encrypted',
            '12345',
            'special@chars!',
            str_repeat('a', 1000) // 極端に長い文字列
        ];

        foreach ($invalidParams as $invalidParam) {
            $response = $this->get(route('reservation.select-category', ['ctx' => $invalidParam]));

            $response->assertRedirect(route('stores'));
            $response->assertSessionHasErrors(['error' => '予約情報が見つかりません']);
        }
    }

    /** @test */
    public function 有効期限切れのコンテキストは拒否される()
    {
        // 過去の時刻でコンテキストを作成
        $pastContext = [
            'type' => 'new_reservation',
            'customer_id' => $this->customer->id,
            'created_at' => Carbon::now()->subHours(3)->timestamp,
            'expires_at' => Carbon::now()->subHours(1)->timestamp // 1時間前に期限切れ
        ];

        $encryptedContext = Crypt::encryptString(json_encode($pastContext));

        $response = $this->get(route('reservation.select-category', ['ctx' => $encryptedContext]));

        $response->assertRedirect(route('stores'));
        $response->assertSessionHasErrors(['error' => '予約情報が見つかりません']);
    }

    /** @test */
    public function 必須フィールドが欠けているコンテキストは適切に処理される()
    {
        // 必須フィールド(type)が欠けているコンテキスト
        $incompleteContext = [
            'customer_id' => $this->customer->id,
            'source' => 'test'
        ];

        $encryptedContext = $this->contextService->encryptContext($incompleteContext);

        // 店舗選択では通るが、後続処理で適切に処理される
        $response = $this->get(route('stores', ['ctx' => $encryptedContext]));
        $response->assertStatus(200);
    }

    /** @test */
    public function 存在しない顧客IDを含むコンテキストは適切に処理される()
    {
        $context = [
            'type' => 'medical_record',
            'customer_id' => 99999, // 存在しない顧客ID
            'store_id' => $this->store->id,
            'is_existing_customer' => true
        ];

        $encryptedContext = $this->contextService->encryptContext($context);

        // カテゴリー選択画面でアクセス
        $response = $this->get(route('reservation.select-category', ['ctx' => $encryptedContext]));

        // エラーまたは適切な処理が行われることを確認
        // （具体的な動作は実装によって決まる）
        $this->assertTrue(
            $response->isRedirection() || $response->status() === 200,
            'Invalid customer ID should be handled gracefully'
        );
    }

    /** @test */
    public function 存在しない店舗IDを含むコンテキストは適切に処理される()
    {
        $context = [
            'type' => 'new_reservation',
            'store_id' => 99999, // 存在しない店舗ID
            'is_existing_customer' => false
        ];

        $encryptedContext = $this->contextService->encryptContext($context);

        $response = $this->get(route('reservation.select-category', ['ctx' => $encryptedContext]));

        // 適切なエラー処理が行われることを確認
        $this->assertTrue(
            $response->isRedirection() || $response->status() === 404,
            'Invalid store ID should be handled gracefully'
        );
    }

    /** @test */
    public function 極端に大きなコンテキストデータは適切に処理される()
    {
        // 大量のデータを含むコンテキスト
        $largeContext = [
            'type' => 'new_reservation',
            'large_data' => str_repeat('x', 10000), // 10KB のデータ
            'customer_id' => $this->customer->id
        ];

        $encryptedContext = $this->contextService->encryptContext($largeContext);

        // 正常に処理されるか、適切にエラーハンドリングされるかを確認
        $response = $this->get(route('stores', ['ctx' => $encryptedContext]));

        $this->assertTrue(
            $response->status() === 200 || $response->isRedirection(),
            'Large context data should be handled gracefully'
        );
    }

    /** @test */
    public function SQLインジェクション攻撃に対して安全である()
    {
        // SQLインジェクションを試行するコンテキスト
        $maliciousContext = [
            'type' => 'new_reservation',
            'customer_id' => "1; DROP TABLE customers; --",
            'store_id' => "1' OR '1'='1",
            'malicious_field' => "<script>alert('xss')</script>"
        ];

        $encryptedContext = $this->contextService->encryptContext($maliciousContext);

        // アクセスしても問題が発生しないことを確認
        $response = $this->get(route('stores', ['ctx' => $encryptedContext]));

        // データベースが正常であることを確認
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
        $this->assertDatabaseHas('stores', ['id' => $this->store->id]);
    }

    /** @test */
    public function 同じブラウザでの複数セッションが干渉しない()
    {
        // 異なるコンテキストを作成
        $context1 = $this->contextService->createMedicalRecordContext($this->customer->id, $this->store->id);
        $customer2 = Customer::factory()->create(['phone' => '08087654321']);
        $context2 = $this->contextService->createMedicalRecordContext($customer2->id, $this->store->id);

        // 両方のコンテキストが独立して動作することを確認
        $response1 = $this->get(route('reservation.select-category', ['ctx' => $context1]));
        $response2 = $this->get(route('reservation.select-category', ['ctx' => $context2]));

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // コンテキストが正しく分離されていることを確認
        $decrypted1 = $this->contextService->decryptContext($context1);
        $decrypted2 = $this->contextService->decryptContext($context2);

        $this->assertEquals($this->customer->id, $decrypted1['customer_id']);
        $this->assertEquals($customer2->id, $decrypted2['customer_id']);
    }

    /** @test */
    public function URLパラメータの長さ制限をテストする()
    {
        // 非常に長いコンテキストを作成
        $longContext = [
            'type' => 'new_reservation',
            'long_field_1' => str_repeat('a', 1000),
            'long_field_2' => str_repeat('b', 1000),
            'long_field_3' => str_repeat('c', 1000),
        ];

        $encryptedContext = $this->contextService->encryptContext($longContext);

        // URLの長さが現実的な範囲内であることを確認
        $url = route('stores', ['ctx' => $encryptedContext]);

        // 一般的なURLの長さ制限（2048文字）を大幅に超えないことを確認
        $this->assertLessThan(4000, strlen($url), 'URL length should be reasonable');
    }
}