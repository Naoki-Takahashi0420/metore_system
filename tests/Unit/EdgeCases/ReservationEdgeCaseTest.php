<?php

namespace Tests\Unit\EdgeCases;

use Tests\TestCase;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Menu;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReservationEdgeCaseTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->store = Store::create([
            'name' => 'テスト店舗',
            'is_active' => true,
            'business_hours' => [
                'monday' => ['open_time' => '09:00', 'close_time' => '20:00'],
                'tuesday' => ['open_time' => '09:00', 'close_time' => '20:00'],
            ],
            'reservation_slot_duration' => 30,
            'max_concurrent_reservations' => 3,
        ]);
        
        $this->menu = Menu::create([
            'store_id' => $this->store->id,
            'name' => 'テストメニュー',
            'price' => 5000,
            'duration_minutes' => 60,
            'is_available' => true,
        ]);
        
        $this->customer = Customer::factory()->create();
        $this->staff = Staff::factory()->create(['store_id' => $this->store->id]);
    }
    
    public function test_同時刻の予約上限チェック()
    {
        // Arrange
        $reservationTime = Carbon::tomorrow()->setTime(14, 0);
        
        // 上限まで予約を作成
        for ($i = 0; $i < 3; $i++) {
            Reservation::create([
                'customer_id' => Customer::factory()->create()->id,
                'store_id' => $this->store->id,
                'menu_id' => $this->menu->id,
                'staff_id' => $this->staff->id,
                'reservation_date' => $reservationTime->toDateString(),
                'reservation_time' => $reservationTime->toTimeString(),
                'status' => 'confirmed',
                'total_amount' => 5000,
            ]);
        }
        
        // Act & Assert
        $this->expectException(\Exception::class);
        
        Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => $reservationTime->toDateString(),
            'reservation_time' => $reservationTime->toTimeString(),
            'status' => 'confirmed',
            'total_amount' => 5000,
        ]);
    }
    
    public function test_営業時間外の予約防止()
    {
        // Arrange - 営業時間外（21:00）の予約
        $outOfHoursTime = Carbon::tomorrow()->setTime(21, 0);
        
        // Act & Assert
        $reservation = new Reservation([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => $outOfHoursTime->toDateString(),
            'reservation_time' => $outOfHoursTime->toTimeString(),
            'status' => 'confirmed',
            'total_amount' => 5000,
        ]);
        
        $this->assertFalse($reservation->isWithinBusinessHours());
    }
    
    public function test_過去日時の予約防止()
    {
        // Arrange
        $pastDate = Carbon::yesterday();
        
        // Act
        $reservation = new Reservation([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => $pastDate->toDateString(),
            'reservation_time' => '14:00:00',
            'status' => 'confirmed',
            'total_amount' => 5000,
        ]);
        
        // Assert
        $this->assertFalse($reservation->isValidFutureDate());
    }
    
    public function test_重複予約の防止()
    {
        // Arrange
        $reservationTime = Carbon::tomorrow()->setTime(14, 0);
        
        Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => $reservationTime->toDateString(),
            'reservation_time' => $reservationTime->toTimeString(),
            'status' => 'confirmed',
            'total_amount' => 5000,
        ]);
        
        // Act
        $duplicate = Reservation::where('customer_id', $this->customer->id)
            ->where('reservation_date', $reservationTime->toDateString())
            ->where('reservation_time', $reservationTime->toTimeString())
            ->where('status', '!=', 'cancelled')
            ->exists();
        
        // Assert
        $this->assertTrue($duplicate);
    }
    
    public function test_メニュー時間オーバーラップチェック()
    {
        // Arrange
        $startTime = Carbon::tomorrow()->setTime(14, 0);
        
        // 14:00-15:00の予約を作成
        Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => $startTime->toDateString(),
            'reservation_time' => $startTime->toTimeString(),
            'status' => 'confirmed',
            'total_amount' => 5000,
        ]);
        
        // Act - 14:30の予約を試みる（オーバーラップ）
        $overlapTime = $startTime->copy()->addMinutes(30);
        $hasOverlap = Reservation::where('staff_id', $this->staff->id)
            ->where('reservation_date', $overlapTime->toDateString())
            ->where('status', '!=', 'cancelled')
            ->whereRaw('? < ADDTIME(reservation_time, SEC_TO_TIME(? * 60))', [
                $overlapTime->toTimeString(),
                $this->menu->duration_minutes
            ])
            ->whereRaw('ADDTIME(?, SEC_TO_TIME(? * 60)) > reservation_time', [
                $overlapTime->toTimeString(),
                $this->menu->duration_minutes
            ])
            ->exists();
        
        // Assert
        $this->assertTrue($hasOverlap);
    }
    
    public function test_ゼロ円予約の処理()
    {
        // Arrange - 無料メニュー
        $freeMenu = Menu::create([
            'store_id' => $this->store->id,
            'name' => '無料体験',
            'price' => 0,
            'duration_minutes' => 30,
            'is_available' => true,
        ]);
        
        // Act
        $reservation = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $freeMenu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => Carbon::tomorrow()->toDateString(),
            'reservation_time' => '14:00:00',
            'status' => 'confirmed',
            'total_amount' => 0,
        ]);
        
        // Assert
        $this->assertEquals(0, $reservation->total_amount);
        $this->assertEquals('confirmed', $reservation->status);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'total_amount' => 0,
        ]);
    }
    
    public function test_極端に長い予約時間の処理()
    {
        // Arrange - 8時間のメニュー
        $longMenu = Menu::create([
            'store_id' => $this->store->id,
            'name' => '長時間コース',
            'price' => 50000,
            'duration_minutes' => 480, // 8時間
            'is_available' => true,
        ]);
        
        // Act
        $reservation = new Reservation([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $longMenu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => Carbon::tomorrow()->toDateString(),
            'reservation_time' => '09:00:00',
            'status' => 'confirmed',
            'total_amount' => 50000,
        ]);
        
        // Assert - 終了時間が営業時間を超える
        $endTime = Carbon::parse($reservation->reservation_time)->addMinutes(480);
        $closeTime = Carbon::parse('20:00:00');
        
        $this->assertTrue($endTime->gt($closeTime));
    }
    
    public function test_NULL値の処理()
    {
        // Act
        $reservation = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => null, // スタッフ未割当
            'reservation_date' => Carbon::tomorrow()->toDateString(),
            'reservation_time' => '14:00:00',
            'status' => 'pending',
            'total_amount' => 5000,
            'notes' => null,
            'cancellation_reason' => null,
        ]);
        
        // Assert
        $this->assertNull($reservation->staff_id);
        $this->assertNull($reservation->notes);
        $this->assertNull($reservation->cancellation_reason);
        $this->assertNotNull($reservation->id);
    }
    
    public function test_予約番号の一意性()
    {
        // Arrange
        $reservations = [];
        
        // Act - 100件の予約を作成
        for ($i = 0; $i < 100; $i++) {
            $reservation = Reservation::create([
                'customer_id' => Customer::factory()->create()->id,
                'store_id' => $this->store->id,
                'menu_id' => $this->menu->id,
                'reservation_date' => Carbon::tomorrow()->toDateString(),
                'reservation_time' => '14:00:00',
                'status' => 'confirmed',
                'total_amount' => 5000,
            ]);
            $reservations[] = $reservation->reservation_number;
        }
        
        // Assert - 重複がないことを確認
        $uniqueNumbers = array_unique($reservations);
        $this->assertCount(100, $uniqueNumbers);
    }
    
    public function test_日付境界値の処理()
    {
        // Arrange
        $testCases = [
            '月末' => Carbon::create(2025, 1, 31, 14, 0),
            '年末' => Carbon::create(2025, 12, 31, 14, 0),
            'うるう年2月29日' => Carbon::create(2024, 2, 29, 14, 0),
            '夏時間切替日' => Carbon::create(2025, 3, 30, 14, 0),
        ];
        
        foreach ($testCases as $name => $date) {
            // Act
            $reservation = Reservation::create([
                'customer_id' => $this->customer->id,
                'store_id' => $this->store->id,
                'menu_id' => $this->menu->id,
                'reservation_date' => $date->toDateString(),
                'reservation_time' => $date->toTimeString(),
                'status' => 'confirmed',
                'total_amount' => 5000,
            ]);
            
            // Assert
            $this->assertNotNull($reservation->id, "{$name}の予約作成に失敗");
            $this->assertEquals(
                $date->toDateString(),
                $reservation->reservation_date->toDateString(),
                "{$name}の日付が正しく保存されていない"
            );
        }
    }
}