<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Store;
use App\Models\MenuCategory;
use App\Models\Menu;
use App\Models\Customer;
use App\Services\ReservationContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

class ParameterBasedReservationE2ETest extends TestCase
{
    use RefreshDatabase;

    private ReservationContextService $contextService;
    private Store $store;
    private MenuCategory $category;
    private Menu $menu;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextService = new ReservationContextService();

        // テストデータを作成
        $this->store = Store::factory()->create([
            'name' => 'テスト店舗',
            'is_active' => true,
            'business_hours' => [
                ['day' => 'monday', 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
                ['day' => 'tuesday', 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
                ['day' => 'wednesday', 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
                ['day' => 'thursday', 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
                ['day' => 'friday', 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
                ['day' => 'saturday', 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
                ['day' => 'sunday', 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
            ]
        ]);

        $this->category = MenuCategory::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'テストカテゴリー',
            'is_active' => true
        ]);

        $this->menu = Menu::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'テストメニュー',
            'duration_minutes' => 30,
            'price' => 3000,
            'is_active' => true
        ]);

        $this->customer = Customer::factory()->create([
            'phone' => '08012345678',
            'first_name' => 'テスト',
            'last_name' => '太郎'
        ]);
    }

    /** @test */
    public function 新規予約の完全フローが正常に動作する()
    {
        // 1. 店舗選択画面にアクセス
        $response = $this->get(route('stores'));
        $response->assertStatus(200);
        $response->assertViewHas('encryptedContext');

        // contextを取得
        $encryptedContext = $response->original->getData()['encryptedContext'];
        $context = $this->contextService->decryptContext($encryptedContext);

        $this->assertEquals('new_reservation', $context['type']);
        $this->assertFalse($context['is_existing_customer']);

        // 2. 店舗を選択
        $response = $this->post(route('reservation.store-selection'), [
            'store_id' => $this->store->id,
            'ctx' => $encryptedContext
        ]);

        $response->assertRedirect(route('reservation.select-category'));
        $newContext = $this->extractContextFromRedirectUrl($response->headers->get('Location'));
        $this->assertEquals($this->store->id, $newContext['store_id']);

        // 3. カテゴリー選択画面
        $response = $this->get(route('reservation.select-category', ['ctx' => $this->contextService->encryptContext($newContext)]));
        $response->assertStatus(200);

        // 4. メニュー選択
        $response = $this->post(route('reservation.select-menu'), [
            'category_id' => $this->category->id,
            'ctx' => $this->contextService->encryptContext($newContext)
        ]);

        $response->assertRedirect(route('reservation.select-menu'));
    }

    /** @test */
    public function カルテからの予約フローが正常に動作する()
    {
        // カルテからの予約コンテキストを作成
        $context = [
            'type' => 'medical_record',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'is_existing_customer' => true,
            'source' => 'medical_record'
        ];

        $encryptedContext = $this->contextService->encryptContext($context);

        // カテゴリー選択画面に直接アクセス（店舗は既に選択済み）
        $response = $this->get(route('reservation.select-category', ['ctx' => $encryptedContext]));
        $response->assertStatus(200);

        // 顧客情報が正しく設定されていることを確認
        $decryptedContext = $this->contextService->extractContextFromRequest(
            request()->merge(['ctx' => $encryptedContext])
        );

        $this->assertEquals($this->customer->id, $decryptedContext['customer_id']);
        $this->assertTrue($decryptedContext['is_existing_customer']);
    }

    /** @test */
    public function 同時に複数の顧客が予約を行っても混在しない()
    {
        // 顧客Aのコンテキスト
        $customerA = Customer::factory()->create(['phone' => '08011111111']);
        $contextA = $this->contextService->createMedicalRecordContext($customerA->id, $this->store->id);

        // 顧客Bのコンテキスト
        $customerB = Customer::factory()->create(['phone' => '08022222222']);
        $contextB = $this->contextService->createMedicalRecordContext($customerB->id, $this->store->id);

        // 顧客Aでアクセス
        $responseA = $this->get(route('reservation.select-category', ['ctx' => $contextA]));
        $responseA->assertStatus(200);

        // 顧客Bでアクセス
        $responseB = $this->get(route('reservation.select-category', ['ctx' => $contextB]));
        $responseB->assertStatus(200);

        // それぞれのコンテキストが正しいことを確認
        $decryptedContextA = $this->contextService->decryptContext($contextA);
        $decryptedContextB = $this->contextService->decryptContext($contextB);

        $this->assertEquals($customerA->id, $decryptedContextA['customer_id']);
        $this->assertEquals($customerB->id, $decryptedContextB['customer_id']);
        $this->assertNotEquals($decryptedContextA['customer_id'], $decryptedContextB['customer_id']);
    }

    /** @test */
    public function パラメータが正しく引き継がれる()
    {
        // 初期コンテキスト
        $initialContext = [
            'type' => 'new_reservation',
            'is_existing_customer' => false,
            'source' => 'public'
        ];

        $encryptedContext = $this->contextService->encryptContext($initialContext);

        // 店舗選択
        $response = $this->post(route('reservation.store-selection'), [
            'store_id' => $this->store->id,
            'ctx' => $encryptedContext
        ]);

        // リダイレクト先のコンテキストを確認
        $newContext = $this->extractContextFromRedirectUrl($response->headers->get('Location'));

        // 初期情報が保持されていることを確認
        $this->assertEquals('new_reservation', $newContext['type']);
        $this->assertFalse($newContext['is_existing_customer']);
        $this->assertEquals('public', $newContext['source']);

        // 新しい情報が追加されていることを確認
        $this->assertEquals($this->store->id, $newContext['store_id']);
    }

    /**
     * リダイレクトURLからコンテキストを抽出
     */
    private function extractContextFromRedirectUrl(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        return $this->contextService->decryptContext($params['ctx']);
    }
}