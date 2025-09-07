<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Menu;
use App\Events\ReservationCreated;
use App\Events\ReservationCancelled;
use App\Events\ReservationChanged;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TestReservationFlow extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:reservation-flow 
                            {action=create : Action to test (create|cancel|change)}
                            {--email=dasuna2305@gmail.com : Test email address}';

    /**
     * The console command description.
     */
    protected $description = '実際の予約フローをシミュレートしてアドミン通知をテスト';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $email = $this->option('email');

        $this->info("🚀 予約フローシミュレーションテストを開始します");
        $this->info("テストメール: {$email}");
        $this->info("テストアクション: {$action}");
        $this->newLine();

        switch ($action) {
            case 'create':
                $this->testCreateReservation();
                break;
            case 'cancel':
                $this->testCancelReservation();
                break;
            case 'change':
                $this->testChangeReservation();
                break;
            default:
                $this->error("無効なアクションです: {$action}");
                return Command::FAILURE;
        }

        $this->newLine();
        $this->info("✅ テスト完了！");
        $this->info("ログファイルと {$email} のメールをご確認ください。");
        
        return Command::SUCCESS;
    }

    private function testCreateReservation(): void
    {
        $this->info('📋 新規予約作成フローをシミュレート中...');
        
        DB::beginTransaction();
        try {
            // テストデータを準備
            $store = Store::first() ?? $this->createTestStore();
            $menu = Menu::where('store_id', $store->id)->first() ?? $this->createTestMenu($store);
            $customer = $this->createTestCustomer();
            
            $this->line("店舗: {$store->name}");
            $this->line("メニュー: {$menu->name} (¥{$menu->price})");
            $this->line("顧客: {$customer->last_name} {$customer->first_name}様");
            
            // PublicReservationController::store() の処理をシミュレート
            $reservation = Reservation::create([
                'reservation_number' => Reservation::generateReservationNumber(),
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'menu_id' => $menu->id,
                'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '14:00:00',
                'end_time' => Carbon::parse('14:00:00')->addMinutes($menu->duration ?? 60)->format('H:i:s'),
                'status' => 'booked',
                'total_amount' => $menu->price,
                'source' => 'online',
                'notes' => 'E2Eテストによる予約作成です',
            ]);
            
            DB::commit();
            
            $this->info("✅ 予約作成完了 - ID: {$reservation->id}, 予約番号: {$reservation->reservation_number}");
            
            // イベント発行（PublicReservationControllerと同じ処理）
            $this->line("📤 ReservationCreatedイベントを発行中...");
            event(new ReservationCreated($reservation));
            
            // 少し待機してイベント処理を完了
            sleep(1);
            
            $this->info("🔔 アドミン通知が送信されました");
            
        } catch (\Exception $e) {
            DB::rollback();
            $this->error("❌ 予約作成に失敗: " . $e->getMessage());
        }
    }

    private function testCancelReservation(): void
    {
        $this->info('📋 予約キャンセルフローをシミュレート中...');
        
        // 既存の予約を探す、または新規作成
        $reservation = Reservation::where('status', 'booked')
            ->where('reservation_date', '>=', today())
            ->first();
            
        if (!$reservation) {
            $this->line("既存の予約がないため、テスト用予約を作成します...");
            $this->testCreateReservation();
            $reservation = Reservation::latest()->first();
        }
        
        if (!$reservation) {
            $this->error("❌ テスト用予約の作成に失敗しました");
            return;
        }
        
        $this->line("予約ID: {$reservation->id}");
        $this->line("顧客: {$reservation->customer->last_name}様");
        
        // Api\ReservationController::cancelReservation() の処理をシミュレート
        $reservation->update([
            'status' => 'cancelled',
            'cancel_reason' => 'E2Eテストによるキャンセル',
            'cancelled_at' => now()
        ]);
        
        $this->info("✅ 予約キャンセル完了");
        
        // イベント発行
        $this->line("📤 ReservationCancelledイベントを発行中...");
        event(new ReservationCancelled($reservation));
        
        sleep(1);
        
        $this->info("🔔 キャンセル通知が送信されました");
    }

    private function testChangeReservation(): void
    {
        $this->info('📋 予約変更フローをシミュレート中...');
        
        // 既存の予約を探す、または新規作成
        $reservation = Reservation::where('status', 'booked')
            ->where('reservation_date', '>=', today())
            ->first();
            
        if (!$reservation) {
            $this->line("既存の予約がないため、テスト用予約を作成します...");
            $this->testCreateReservation();
            $reservation = Reservation::latest()->first();
        }
        
        if (!$reservation) {
            $this->error("❌ テスト用予約の作成に失敗しました");
            return;
        }
        
        $this->line("変更前 - 予約ID: {$reservation->id}");
        $this->line("変更前 - 日時: {$reservation->reservation_date} {$reservation->start_time}");
        
        // 変更前の状態を保存
        $oldReservation = $reservation->replicate();
        
        // Api\ReservationController::updateReservation() の処理をシミュレート
        $newDate = Carbon::tomorrow()->addDay()->format('Y-m-d');
        $newTime = '10:00:00';
        
        $reservation->update([
            'reservation_date' => $newDate,
            'start_time' => $newTime,
            'end_time' => Carbon::parse($newTime)->addMinutes($reservation->menu->duration ?? 60)->format('H:i:s'),
        ]);
        
        $this->line("変更後 - 日時: {$reservation->reservation_date} {$reservation->start_time}");
        $this->info("✅ 予約変更完了");
        
        // イベント発行
        $this->line("📤 ReservationChangedイベントを発行中...");
        event(new ReservationChanged($oldReservation, $reservation));
        
        sleep(1);
        
        $this->info("🔔 変更通知が送信されました");
    }

    private function createTestStore(): Store
    {
        return Store::create([
            'name' => 'E2Eテスト店舗',
            'phone' => '03-1234-5678',
            'email' => 'e2e-test-store@example.com',
            'address' => 'E2Eテスト住所',
            'business_hours' => [
                'monday' => ['start' => '09:00', 'end' => '18:00'],
                'tuesday' => ['start' => '09:00', 'end' => '18:00'],
            ],
        ]);
    }

    private function createTestMenu(Store $store): Menu
    {
        return Menu::create([
            'store_id' => $store->id,
            'name' => 'E2Eテストメニュー',
            'price' => 5000,
            'duration' => 60,
            'description' => 'E2Eテスト用のメニューです',
        ]);
    }

    private function createTestCustomer(): Customer
    {
        $randomPhone = '090-' . rand(1000, 9999) . '-' . rand(1000, 9999);
        $randomEmail = 'e2e-customer-' . rand(1000, 9999) . '@example.com';
        
        return Customer::create([
            'customer_number' => 'E2E' . rand(1000, 9999),
            'last_name' => 'E2Eテスト',
            'first_name' => '花子',
            'phone' => $randomPhone,
            'email' => $randomEmail,
        ]);
    }
}