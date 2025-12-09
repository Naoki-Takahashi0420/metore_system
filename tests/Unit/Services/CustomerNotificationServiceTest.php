<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CustomerNotificationService;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Reservation;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class CustomerNotificationServiceTest extends TestCase
{
    private CustomerNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerNotificationService::class);
    }

    /**
     * 通知優先順位のテスト: LINE連携顧客はLINEのみで通知
     */
    public function test_notification_priority_line_first(): void
    {
        // LINE連携済みの顧客を取得
        $customer = Customer::whereNotNull('line_user_id')
            ->where('line_user_id', '!=', '')
            ->first();

        if (!$customer) {
            $this->markTestSkipped('LINE連携顧客がいません');
        }

        // 通知可能かチェック
        $canSend = $this->service->canSendNotification($customer, 'test');

        $this->assertTrue($canSend['line'], 'LINE通知が可能であること');
        $this->assertTrue($canSend['any'], 'いずれかの通知手段が利用可能であること');
    }

    /**
     * 通知優先順位のテスト: LINE未連携顧客はメール→SMSの順
     */
    public function test_notification_priority_email_before_sms(): void
    {
        // LINE未連携でメールとSMSが両方ある顧客を取得
        $customer = Customer::whereNull('line_user_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->first();

        if (!$customer) {
            $this->markTestSkipped('LINE未連携でメール・SMS両方持つ顧客がいません');
        }

        $canSend = $this->service->canSendNotification($customer, 'test');

        $this->assertFalse($canSend['line'], 'LINE通知が不可であること');
        $this->assertTrue($canSend['email'], 'メール通知が可能であること');
        $this->assertTrue($canSend['sms'] || !$customer->sms_notifications_enabled, 'SMS設定が正しいこと');
    }

    /**
     * 変更内容テキスト生成のテスト
     */
    public function test_build_change_text(): void
    {
        // リフレクションで private メソッドにアクセス
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildChangeText');
        $method->setAccessible(true);

        $changes = [
            'reservation_date' => ['old' => '2025年1月1日', 'new' => '2025年1月2日'],
            'start_time' => ['old' => '10:00', 'new' => '14:00'],
        ];

        $result = $method->invoke($this->service, $changes);

        $this->assertStringContainsString('日付:', $result);
        $this->assertStringContainsString('2025年1月1日', $result);
        $this->assertStringContainsString('2025年1月2日', $result);
        $this->assertStringContainsString('時間:', $result);
        $this->assertStringContainsString('10:00', $result);
        $this->assertStringContainsString('14:00', $result);
    }

    /**
     * メール件名取得のテスト
     */
    public function test_get_email_subject(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getEmailSubject');
        $method->setAccessible(true);

        $this->assertEquals('予約確認', $method->invoke($this->service, 'reservation_confirmation'));
        $this->assertEquals('予約変更のお知らせ', $method->invoke($this->service, 'reservation_change'));
        $this->assertEquals('予約キャンセル確認', $method->invoke($this->service, 'reservation_cancellation'));
        $this->assertEquals('予約リマインダー', $method->invoke($this->service, 'reservation_reminder'));
    }

    /**
     * サービスが正しくインスタンス化されることのテスト
     */
    public function test_service_instantiation(): void
    {
        $service = app(CustomerNotificationService::class);
        $this->assertInstanceOf(CustomerNotificationService::class, $service);
    }
}
