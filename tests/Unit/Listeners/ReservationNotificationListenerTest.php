<?php

namespace Tests\Unit\Listeners;

use Tests\TestCase;
use App\Events\ReservationChanged;
use App\Events\ReservationCancelled;
use App\Listeners\SendCustomerReservationChangeNotification;
use App\Listeners\SendCustomerReservationCancellationNotification;
use App\Models\Reservation;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Menu;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;

class ReservationNotificationListenerTest extends TestCase
{
    /**
     * ReservationChangedイベントが配列形式のoldDataを受け取れることのテスト
     */
    public function test_reservation_changed_event_accepts_array(): void
    {
        $reservation = Reservation::with(['customer', 'store', 'menu'])->first();

        if (!$reservation) {
            $this->markTestSkipped('予約データがありません');
        }

        // 配列形式で古いデータを作成
        $oldReservationData = [
            'id' => $reservation->id,
            'reservation_date' => $reservation->reservation_date,
            'start_time' => $reservation->start_time,
            'menu_id' => $reservation->menu_id,
            'total_amount' => $reservation->total_amount,
        ];

        // イベントを作成（例外が発生しないことを確認）
        $event = new ReservationChanged($oldReservationData, $reservation);

        $this->assertIsArray($event->oldReservationData);
        $this->assertInstanceOf(Reservation::class, $event->newReservation);
        $this->assertEquals($reservation->id, $event->oldReservationData['id']);
    }

    /**
     * ReservationCancelledイベントのテスト
     */
    public function test_reservation_cancelled_event(): void
    {
        $reservation = Reservation::with(['customer', 'store', 'menu'])->first();

        if (!$reservation) {
            $this->markTestSkipped('予約データがありません');
        }

        $event = new ReservationCancelled($reservation);

        $this->assertInstanceOf(Reservation::class, $event->reservation);
        $this->assertEquals($reservation->id, $event->reservation->id);
    }

    /**
     * 変更通知リスナーがインスタンス化できることのテスト
     */
    public function test_change_notification_listener_instantiation(): void
    {
        $listener = app(SendCustomerReservationChangeNotification::class);
        $this->assertInstanceOf(SendCustomerReservationChangeNotification::class, $listener);
    }

    /**
     * キャンセル通知リスナーがインスタンス化できることのテスト
     */
    public function test_cancellation_notification_listener_instantiation(): void
    {
        $listener = app(SendCustomerReservationCancellationNotification::class);
        $this->assertInstanceOf(SendCustomerReservationCancellationNotification::class, $listener);
    }

    /**
     * 変更通知リスナーのbuildChangesメソッドのテスト
     */
    public function test_build_changes_method(): void
    {
        $listener = app(SendCustomerReservationChangeNotification::class);

        // リフレクションでprivateメソッドにアクセス
        $reflection = new \ReflectionClass($listener);
        $method = $reflection->getMethod('buildChanges');
        $method->setAccessible(true);

        $reservation = Reservation::with('menu')->first();

        if (!$reservation) {
            $this->markTestSkipped('予約データがありません');
        }

        $oldData = [
            'reservation_date' => '2025-01-01',
            'start_time' => '10:00:00',
            'menu_id' => $reservation->menu_id,
            'total_amount' => 5000,
        ];

        // 日付と時間を変更
        $reservation->reservation_date = '2025-01-02';
        $reservation->start_time = '14:00:00';

        $changes = $method->invoke($listener, $oldData, $reservation);

        $this->assertIsArray($changes);
        $this->assertArrayHasKey('reservation_date', $changes);
        $this->assertArrayHasKey('start_time', $changes);
    }

    /**
     * 重複防止キャッシュキーのテスト
     */
    public function test_deduplication_cache_key(): void
    {
        $reservationId = 12345;
        $dedupeKey = "notify:customer:change:{$reservationId}";

        // キャッシュをクリア
        Cache::forget($dedupeKey);

        // 最初の追加は成功
        $this->assertTrue(Cache::add($dedupeKey, true, now()->addMinutes(5)));

        // 2回目の追加は失敗（重複）
        $this->assertFalse(Cache::add($dedupeKey, true, now()->addMinutes(5)));

        // クリーンアップ
        Cache::forget($dedupeKey);
    }
}
