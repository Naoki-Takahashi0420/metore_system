<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Menu;
use App\Services\AdminNotificationService;
use App\Events\ReservationCreated;
use App\Events\ReservationCancelled;
use App\Events\ReservationChanged;
use Carbon\Carbon;

class TestAdminNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:admin-notification 
                            {type=all : 通知のタイプ (created|cancelled|changed|all)}
                            {--email=dasuna2305@gmail.com : テスト用メールアドレス}
                            {--store-id=1 : 店舗ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'アドミン通知システムのテスト実行';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $email = $this->option('email');
        $storeId = $this->option('store-id');

        $this->info("アドミン通知テストを開始します...");
        $this->info("テストメール: {$email}");
        $this->info("店舗ID: {$storeId}");
        $this->info("テストタイプ: {$type}");
        $this->newLine();

        // テスト用店舗を取得または作成
        $store = Store::find($storeId) ?? $this->createTestStore();
        
        // テスト用顧客を作成
        $customer = $this->createTestCustomer();
        
        // テスト用メニューを取得または作成
        $menu = $this->getOrCreateTestMenu($store);

        switch ($type) {
            case 'created':
                $this->testReservationCreated($store, $customer, $menu);
                break;
            case 'cancelled':
                $this->testReservationCancelled($store, $customer, $menu);
                break;
            case 'changed':
                $this->testReservationChanged($store, $customer, $menu);
                break;
            case 'all':
                $this->testReservationCreated($store, $customer, $menu);
                $this->newLine();
                $this->testReservationCancelled($store, $customer, $menu);
                $this->newLine();
                $this->testReservationChanged($store, $customer, $menu);
                break;
            default:
                $this->error("無効なテストタイプです: {$type}");
                return;
        }

        $this->newLine();
        $this->info('テスト完了！');
        $this->info("メール({$email})をご確認ください。");
    }

    private function createTestStore(): Store
    {
        return Store::create([
            'name' => 'テスト店舗',
            'phone' => '03-1234-5678',
            'email' => 'test-store@example.com',
            'address' => 'テスト住所',
            'business_hours' => [
                'monday' => ['start' => '09:00', 'end' => '18:00'],
                'tuesday' => ['start' => '09:00', 'end' => '18:00'],
            ],
        ]);
    }

    private function createTestCustomer(): Customer
    {
        $randomPhone = '090-' . rand(1000, 9999) . '-' . rand(1000, 9999);
        $randomEmail = 'test-customer-' . rand(1000, 9999) . '@example.com';
        
        return Customer::create([
            'customer_number' => 'TEST' . rand(1000, 9999),
            'last_name' => 'テスト',
            'first_name' => '太郎',
            'phone' => $randomPhone,
            'email' => $randomEmail,
        ]);
    }

    private function getOrCreateTestMenu(Store $store): Menu
    {
        $menu = Menu::where('store_id', $store->id)->first();
        
        if (!$menu) {
            $menu = Menu::create([
                'store_id' => $store->id,
                'name' => 'テストメニュー',
                'price' => 5000,
                'duration' => 60,
                'description' => 'テスト用のメニューです',
            ]);
        }

        return $menu;
    }

    private function testReservationCreated(Store $store, Customer $customer, Menu $menu): void
    {
        $this->info('【新規予約通知テスト】');
        
        $reservation = Reservation::create([
            'reservation_number' => 'TEST-' . rand(10000, 99999),
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'status' => 'confirmed',
            'total_amount' => $menu->price,
            'source' => 'test',
        ]);

        event(new ReservationCreated($reservation));
        
        $this->line("予約ID: {$reservation->id}");
        $this->line("予約番号: {$reservation->reservation_number}");
        $this->info('新規予約通知を送信しました');
    }

    private function testReservationCancelled(Store $store, Customer $customer, Menu $menu): void
    {
        $this->info('【予約キャンセル通知テスト】');
        
        $reservation = Reservation::create([
            'reservation_number' => 'CANCEL-' . rand(10000, 99999),
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'status' => 'cancelled',
            'total_amount' => $menu->price,
            'source' => 'test',
            'cancel_reason' => 'テストキャンセル',
            'cancelled_at' => now(),
        ]);

        event(new ReservationCancelled($reservation));
        
        $this->line("予約ID: {$reservation->id}");
        $this->line("予約番号: {$reservation->reservation_number}");
        $this->info('キャンセル通知を送信しました');
    }

    private function testReservationChanged(Store $store, Customer $customer, Menu $menu): void
    {
        $this->info('【予約変更通知テスト】');
        
        // 変更前の予約
        $oldReservation = new Reservation([
            'reservation_number' => 'OLD-' . rand(10000, 99999),
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'status' => 'confirmed',
            'total_amount' => $menu->price,
            'source' => 'test',
        ]);

        // 変更後の予約
        $newReservation = Reservation::create([
            'reservation_number' => 'NEW-' . rand(10000, 99999),
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'reservation_date' => Carbon::tomorrow()->addDay()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'confirmed',
            'total_amount' => $menu->price,
            'source' => 'test',
        ]);

        event(new ReservationChanged($oldReservation, $newReservation));
        
        $this->line("新予約ID: {$newReservation->id}");
        $this->line("新予約番号: {$newReservation->reservation_number}");
        $this->info('予約変更通知を送信しました');
    }
}