<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Reservation;
use App\Models\Menu;
use App\Services\CustomerNotificationService;
use Carbon\Carbon;

class TestCustomerNotificationSystem extends Command
{
    protected $signature = 'test:customer-notifications';
    protected $description = 'Test customer notification system (LINE + SMS)';

    private CustomerNotificationService $notificationService;

    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new CustomerNotificationService();
    }

    public function handle()
    {
        $this->info('=== 顧客通知システムのE2Eテスト開始 ===');
        
        // テスト用の店舗・顧客・予約を作成
        $testData = $this->createTestData();
        
        $this->info('1. 予約確認通知テスト');
        $this->testReservationConfirmation($testData);
        
        $this->info('2. フォローアップ通知テスト');
        $this->testFollowUpNotification($testData);
        
        $this->info('3. 通知設定確認テスト');
        $this->testNotificationSettings($testData);
        
        // クリーンアップ
        $this->cleanupTestData($testData);
        
        $this->info('=== テスト完了 ===');
        
        return Command::SUCCESS;
    }
    
    private function createTestData(): array
    {
        $this->info('テストデータ作成中...');
        
        // 店舗作成
        $timestamp = time();
        $store = Store::create([
            'name' => 'テスト店舗 (LINE連携)',
            'code' => 'TEST_LINE_' . $timestamp,
            'phone' => '03' . substr($timestamp, -8),
            'address' => 'テスト住所',
            'postal_code' => '100-0001',
            'is_active' => true,
            'line_enabled' => true,
            'line_bot_basic_id' => 'testbot' . $timestamp,
            'line_channel_access_token' => 'test_channel_token_' . $timestamp,
        ]);
        
        // メニュー作成
        $menu = Menu::create([
            'store_id' => $store->id,
            'name' => 'テストメニュー',
            'duration' => 60,
            'price' => 5000,
            'is_available' => true,
            'is_visible_to_customer' => true,
        ]);
        
        // LINE連携顧客作成
        $customerLine = Customer::create([
            'customer_number' => 'TEST_LINE_' . $timestamp,
            'store_id' => $store->id,
            'last_name' => 'テスト',
            'first_name' => 'LINE太郎',
            'phone' => '080' . substr($timestamp, -8),
            'email' => 'test.line.' . $timestamp . '@example.com',
            'line_user_id' => 'test_line_user_' . $timestamp,
            'line_notifications_enabled' => true,
            'sms_notifications_enabled' => true,
        ]);
        
        // SMS顧客作成
        $customerSms = Customer::create([
            'customer_number' => 'TEST_SMS_' . $timestamp,
            'store_id' => $store->id,
            'last_name' => 'テスト',
            'first_name' => 'SMS花子',
            'phone' => '090' . substr($timestamp, -8),
            'email' => 'test.sms.' . $timestamp . '@example.com',
            'sms_notifications_enabled' => true,
        ]);
        
        // 予約作成
        $reservation = Reservation::create([
            'reservation_number' => 'TEST_' . $timestamp,
            'store_id' => $store->id,
            'customer_id' => $customerLine->id,
            'menu_id' => $menu->id,
            'reservation_date' => Carbon::tomorrow(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'booked',
            'total_amount' => 5000,
            'source' => 'test',
        ]);
        
        return [
            'store' => $store,
            'menu' => $menu,
            'customer_line' => $customerLine,
            'customer_sms' => $customerSms,
            'reservation' => $reservation,
        ];
    }
    
    private function testReservationConfirmation(array $testData): void
    {
        $this->line('  予約確認通知を送信中...');
        
        $reservation = $testData['reservation'];
        $result = $this->notificationService->sendReservationConfirmation($reservation);
        
        $this->line('  結果:');
        $this->line('    LINE送信: ' . ($result['line'] ?? false ? '成功' : '失敗'));
        $this->line('    SMS送信: ' . (isset($result['sms']) ? ($result['sms'] ? '成功' : '失敗') : 'スキップ'));
    }
    
    private function testFollowUpNotification(array $testData): void
    {
        $this->line('  7日後フォローアップ通知を送信中...');
        
        $customer = $testData['customer_line'];
        $store = $testData['store'];
        $result = $this->notificationService->sendFollowUpMessage($customer, $store, 7);
        
        $this->line('  結果:');
        $this->line('    LINE送信: ' . ($result['line'] ?? false ? '成功' : '失敗'));
        $this->line('    SMS送信: ' . (isset($result['sms']) ? ($result['sms'] ? '成功' : '失敗') : 'スキップ'));
    }
    
    private function testNotificationSettings(array $testData): void
    {
        $this->line('  通知設定確認中...');
        
        $customerLine = $testData['customer_line'];
        $customerSms = $testData['customer_sms'];
        
        $settingsLine = $this->notificationService->canSendNotification($customerLine, 'test');
        $settingSms = $this->notificationService->canSendNotification($customerSms, 'test');
        
        $this->line('  LINE連携顧客:');
        $this->line('    LINE通知可能: ' . ($settingsLine['line'] ? 'はい' : 'いいえ'));
        $this->line('    SMS通知可能: ' . ($settingsLine['sms'] ? 'はい' : 'いいえ'));
        
        $this->line('  SMS顧客:');
        $this->line('    LINE通知可能: ' . ($settingSms['line'] ? 'はい' : 'いいえ'));
        $this->line('    SMS通知可能: ' . ($settingSms['sms'] ? 'はい' : 'いいえ'));
    }
    
    private function cleanupTestData(array $testData): void
    {
        $this->line('テストデータをクリーンアップ中...');
        
        $testData['reservation']->delete();
        $testData['customer_line']->delete();
        $testData['customer_sms']->delete();
        $testData['menu']->delete();
        $testData['store']->delete();
        
        $this->line('クリーンアップ完了');
    }
}