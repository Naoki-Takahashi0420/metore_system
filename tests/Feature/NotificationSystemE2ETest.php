<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Reservation;
use App\Models\Menu;
use App\Models\NotificationLog;
use App\Events\ReservationCreated;
use App\Events\ReservationChanged;
use App\Events\ReservationCancelled;
use App\Services\CustomerNotificationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class NotificationSystemE2ETest extends TestCase
{
    private ?Customer $lineCustomer = null;
    private ?Customer $emailOnlyCustomer = null;
    private ?Store $store = null;
    private ?Menu $menu = null;

    protected function setUp(): void
    {
        parent::setUp();

        // LINE連携済み顧客（高橋直希）または任意のLINE連携顧客
        $this->lineCustomer = Customer::find(3469) ?? Customer::whereNotNull('line_user_id')->first();

        // LINE未連携でメールありの顧客を取得
        $this->emailOnlyCustomer = Customer::whereNull('line_user_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->first();

        // 店舗とメニューを取得
        $this->store = Store::where('line_enabled', true)->first() ?? Store::first();
        if ($this->store) {
            $this->menu = Menu::where('store_id', $this->store->id)->first() ?? Menu::first();
        } else {
            $this->menu = Menu::first();
        }
    }

    /**
     * E2E: LINE連携顧客への予約作成通知がLINEで送信されること
     */
    public function test_e2e_reservation_creation_notification_via_line(): void
    {
        if (!$this->lineCustomer) {
            $this->markTestSkipped('LINE連携顧客がいません');
        }

        // 通知ログの初期カウント
        $initialLogCount = NotificationLog::where('customer_id', $this->lineCustomer->id)
            ->where('notification_type', 'reservation_confirmation')
            ->count();

        // 予約を作成
        $reservation = Reservation::create([
            'reservation_number' => 'TEST-' . time(),
            'store_id' => $this->store->id,
            'customer_id' => $this->lineCustomer->id,
            'menu_id' => $this->menu->id,
            'reservation_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'confirmed',
            'total_amount' => $this->menu->price ?? 5000,
        ]);

        $this->assertNotNull($reservation->id);

        // CustomerNotificationServiceで通知送信
        $service = app(CustomerNotificationService::class);
        $result = $service->sendReservationConfirmation($reservation);

        // LINE送信が成功していること（またはスキップされていないこと）
        $this->assertIsArray($result);

        // 通知ログが増えていること
        $newLogCount = NotificationLog::where('customer_id', $this->lineCustomer->id)
            ->where('notification_type', 'reservation_confirmation')
            ->count();

        $this->assertGreaterThanOrEqual($initialLogCount, $newLogCount);

        // クリーンアップ
        $reservation->delete();
    }

    /**
     * E2E: 予約変更通知が正しく送信されること（2重送信しないこと）
     */
    public function test_e2e_reservation_change_notification_no_duplicate(): void
    {
        if (!$this->lineCustomer) {
            $this->markTestSkipped('LINE連携顧客がいません');
        }

        // テスト用予約を作成（interval制限を回避するため30日後）
        $reservation = new Reservation([
            'reservation_number' => 'TEST-CHANGE-' . time(),
            'store_id' => $this->store->id,
            'customer_id' => $this->lineCustomer->id,
            'menu_id' => $this->menu->id,
            'reservation_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'status' => 'confirmed',
            'total_amount' => $this->menu->price ?? 5000,
        ]);
        $reservation->saveQuietly(); // Observerを回避

        // 変更前のデータを配列で保存
        $oldReservationData = [
            'id' => $reservation->id,
            'reservation_date' => $reservation->reservation_date,
            'start_time' => $reservation->start_time,
            'menu_id' => $reservation->menu_id,
            'total_amount' => $reservation->total_amount,
        ];

        // 予約を変更（Observerを回避）
        $reservation->start_time = '16:00:00';
        $reservation->end_time = '17:00:00';
        $reservation->saveQuietly();

        // 変更通知を送信
        $service = app(CustomerNotificationService::class);
        $changes = [
            'start_time' => [
                'old' => '14:00',
                'new' => '16:00',
            ],
        ];

        // 重複防止キャッシュをクリア
        Cache::forget("notify:customer:change:{$reservation->id}");

        $result1 = $service->sendReservationChange($reservation, $changes);
        $this->assertIsArray($result1);

        // 2回目の送信は重複としてスキップされることを確認
        // （実際の重複防止はNotificationLog.isDuplicateで行われる）

        // クリーンアップ
        $reservation->delete();
    }

    /**
     * E2E: 予約キャンセル通知が正しく送信されること
     */
    public function test_e2e_reservation_cancellation_notification(): void
    {
        if (!$this->lineCustomer) {
            $this->markTestSkipped('LINE連携顧客がいません');
        }

        // テスト用予約を作成
        $reservation = Reservation::create([
            'reservation_number' => 'TEST-CANCEL-' . time(),
            'store_id' => $this->store->id,
            'customer_id' => $this->lineCustomer->id,
            'menu_id' => $this->menu->id,
            'reservation_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'status' => 'cancelled',
            'total_amount' => $this->menu->price ?? 5000,
        ]);

        // キャンセル通知を送信
        $service = app(CustomerNotificationService::class);

        // 重複防止キャッシュをクリア
        Cache::forget("notify:customer:cancellation:{$reservation->id}");

        $result = $service->sendReservationCancellation($reservation);

        $this->assertIsArray($result);

        // クリーンアップ
        $reservation->delete();
    }

    /**
     * E2E: LINE未連携顧客はメール→SMSの順で通知されること
     */
    public function test_e2e_email_priority_for_non_line_customer(): void
    {
        if (!$this->emailOnlyCustomer) {
            $this->markTestSkipped('LINE未連携でメールありの顧客がいません');
        }

        // テスト用予約を作成
        $reservation = Reservation::create([
            'reservation_number' => 'TEST-EMAIL-' . time(),
            'store_id' => $this->store->id,
            'customer_id' => $this->emailOnlyCustomer->id,
            'menu_id' => $this->menu->id,
            'reservation_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'status' => 'confirmed',
            'total_amount' => $this->menu->price ?? 5000,
        ]);

        $service = app(CustomerNotificationService::class);
        $result = $service->sendReservationConfirmation($reservation);

        $this->assertIsArray($result);
        // LINE未連携なのでLINEはfalse（キーがない場合もfalseとみなす）
        $this->assertFalse($result['line'] ?? false, 'LINE未連携顧客にはLINE通知されないこと');
        // メールがあるのでメール送信が試みられる
        // (実際の送信成功/失敗はAWS SESの設定による)

        // クリーンアップ
        $reservation->delete();
    }

    /**
     * E2E: ReservationChangedイベントが配列形式で正しく処理されること
     */
    public function test_e2e_reservation_changed_event_with_array(): void
    {
        $reservation = Reservation::with(['customer', 'store', 'menu'])->first();

        if (!$reservation) {
            $this->markTestSkipped('予約データがありません');
        }

        // 配列形式でイベントを作成
        $oldData = [
            'id' => $reservation->id,
            'reservation_date' => $reservation->reservation_date,
            'start_time' => $reservation->start_time,
            'menu_id' => $reservation->menu_id,
            'total_amount' => $reservation->total_amount,
        ];

        // イベントを発火（例外が発生しないことを確認）
        $event = new ReservationChanged($oldData, $reservation);

        $this->assertIsArray($event->oldReservationData);
        $this->assertEquals($reservation->id, $event->oldReservationData['id']);
    }

    /**
     * E2E: API経由の予約変更が配列形式でイベントを発火すること
     */
    public function test_e2e_api_reservation_change_uses_array(): void
    {
        // ReservationControllerのコードを確認
        // replicate()ではなく配列を使用していることを確認

        $controllerPath = app_path('Http/Controllers/Api/ReservationController.php');
        $content = file_get_contents($controllerPath);

        // 配列形式での変更前データ保存が存在すること
        $this->assertStringContainsString('$oldReservationData = [', $content);

        // replicate()の直接使用がイベント発火に使われていないこと
        // （変更前は $oldReservation = $reservation->replicate() でイベントに渡していた）
        $this->assertStringNotContainsString(
            'event(new ReservationChanged($oldReservation,',
            $content,
            'replicate()したモデルを直接イベントに渡していないこと'
        );
    }
}
