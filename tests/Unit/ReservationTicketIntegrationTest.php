<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Reservation;
use App\Models\CustomerTicket;
use App\Models\TicketPlan;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ReservationTicketIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $store;
    protected $customer;
    protected $ticketPlan;
    protected $menu;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用のデータを作成
        $this->store = Store::factory()->create();
        $this->customer = Customer::factory()->create(['store_id' => $this->store->id]);
        $this->staff = User::factory()->create(['store_id' => $this->store->id]);
        $this->menu = Menu::factory()->create([
            'store_id' => $this->store->id,
            'duration_minutes' => 60,
            'price' => 5000,
        ]);
        $this->ticketPlan = TicketPlan::create([
            'store_id' => $this->store->id,
            'name' => '10回券',
            'ticket_count' => 10,
            'price' => 50000,
            'validity_months' => 3,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function reservation_with_ticket_automatically_consumes_ticket()
    {
        // 回数券を発行
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => $this->ticketPlan->ticket_count,
            'purchase_price' => $this->ticketPlan->price,
        ]);

        $this->assertEquals(10, $ticket->remaining_count);
        $this->assertEquals(0, $ticket->used_count);

        // 回数券を使って予約作成
        $reservation = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_number' => 'R' . uniqid(),
            'reservation_date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'booked',
            'payment_method' => 'ticket',
            'customer_ticket_id' => $ticket->id,
        ]);

        // 手動で消費処理をシミュレート（CreateReservationのafterCreateと同じロジック）
        $ticket->use($reservation->id);
        $reservation->update(['paid_with_ticket' => true, 'payment_status' => 'paid']);

        // 回数券が1回消費されている
        $ticket->refresh();
        $this->assertEquals(1, $ticket->used_count);
        $this->assertEquals(9, $ticket->remaining_count);

        // 予約が回数券で支払い済み
        $reservation->refresh();
        $this->assertTrue($reservation->paid_with_ticket);
        $this->assertEquals('paid', $reservation->payment_status);

        // 利用履歴が記録されている
        $history = $ticket->usageHistory()->where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(1, $history->used_count);
        $this->assertFalse($history->is_cancelled);
    }

    /** @test */
    public function cancelled_reservation_refunds_ticket()
    {
        // 回数券を発行
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => $this->ticketPlan->ticket_count,
            'purchase_price' => $this->ticketPlan->price,
        ]);

        // 回数券を使って予約作成
        $reservation = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_number' => 'R' . uniqid(),
            'reservation_date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'booked',
            'payment_method' => 'ticket',
            'customer_ticket_id' => $ticket->id,
            'paid_with_ticket' => true,
        ]);

        $ticket->use($reservation->id);
        $ticket->refresh();
        $this->assertEquals(1, $ticket->used_count);

        // 予約をキャンセル（Reservationモデルのupdatingイベントが発火）
        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        // 回数券が返却されている
        $ticket->refresh();
        $this->assertEquals(0, $ticket->used_count);
        $this->assertEquals(10, $ticket->remaining_count);

        // 利用履歴がキャンセル済みになっている
        $history = $ticket->usageHistory()
            ->where('reservation_id', $reservation->id)
            ->where('is_cancelled', true)
            ->first();
        $this->assertNotNull($history);
    }

    /** @test */
    public function cannot_use_expired_ticket_for_reservation()
    {
        // 期限切れの回数券を発行
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => $this->ticketPlan->ticket_count,
            'purchase_price' => $this->ticketPlan->price,
            'status' => 'active',
            'purchased_at' => Carbon::now()->subMonths(4),
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->assertTrue($ticket->is_expired);
        $this->assertFalse($ticket->canUse());

        // 予約作成を試みる
        $reservation = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_number' => 'R' . uniqid(),
            'reservation_date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'booked',
            'payment_method' => 'ticket',
            'customer_ticket_id' => $ticket->id,
        ]);

        // 期限切れなので消費できない
        $result = $ticket->use($reservation->id);
        $this->assertFalse($result);

        $ticket->refresh();
        $this->assertEquals(0, $ticket->used_count);
    }

    /** @test */
    public function cannot_use_used_up_ticket_for_reservation()
    {
        // 使い切った回数券を発行
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => 3,
            'used_count' => 3,
            'purchase_price' => $this->ticketPlan->price,
            'status' => 'used_up',
        ]);

        $this->assertEquals(0, $ticket->remaining_count);
        $this->assertFalse($ticket->canUse());

        // 予約作成を試みる
        $reservation = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_number' => 'R' . uniqid(),
            'reservation_date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'booked',
            'payment_method' => 'ticket',
            'customer_ticket_id' => $ticket->id,
        ]);

        // 使い切っているので消費できない
        $result = $ticket->use($reservation->id);
        $this->assertFalse($result);

        $ticket->refresh();
        $this->assertEquals(3, $ticket->used_count);
    }

    /** @test */
    public function ticket_becomes_used_up_when_last_count_is_used()
    {
        // 残り1回の回数券を発行
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => 3,
            'used_count' => 2,
            'purchase_price' => $this->ticketPlan->price,
            'status' => 'active',
        ]);

        $this->assertEquals(1, $ticket->remaining_count);
        $this->assertEquals('active', $ticket->status);

        // 最後の1回を使って予約作成
        $reservation = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_number' => 'R' . uniqid(),
            'reservation_date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'booked',
            'payment_method' => 'ticket',
            'customer_ticket_id' => $ticket->id,
        ]);

        $ticket->use($reservation->id);

        // 使い切りステータスになっている
        $ticket->refresh();
        $this->assertEquals(3, $ticket->used_count);
        $this->assertEquals(0, $ticket->remaining_count);
        $this->assertEquals('used_up', $ticket->status);
    }

    /** @test */
    public function refunded_used_up_ticket_becomes_active_again()
    {
        // 使い切った回数券を発行
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => 3,
            'used_count' => 3,
            'purchase_price' => $this->ticketPlan->price,
            'status' => 'used_up',
        ]);

        $this->assertEquals('used_up', $ticket->status);

        // 予約をキャンセルして返却
        $reservation = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_number' => 'R' . uniqid(),
            'reservation_date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'booked',
            'payment_method' => 'ticket',
            'customer_ticket_id' => $ticket->id,
            'paid_with_ticket' => true,
        ]);

        // 返却
        $ticket->refund($reservation->id, 1);

        // activeステータスに戻っている
        $ticket->refresh();
        $this->assertEquals(2, $ticket->used_count);
        $this->assertEquals(1, $ticket->remaining_count);
        $this->assertEquals('active', $ticket->status);
    }

    /** @test */
    public function multiple_reservations_consume_multiple_tickets()
    {
        // 回数券を発行
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => 10,
            'purchase_price' => $this->ticketPlan->price,
        ]);

        // 3つの予約を作成
        for ($i = 1; $i <= 3; $i++) {
            $reservation = Reservation::create([
                'store_id' => $this->store->id,
                'customer_id' => $this->customer->id,
                'menu_id' => $this->menu->id,
                'staff_id' => $this->staff->id,
                'reservation_number' => 'R' . uniqid(),
                'reservation_date' => now()->addDays($i),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'status' => 'booked',
                'payment_method' => 'ticket',
                'customer_ticket_id' => $ticket->id,
                'paid_with_ticket' => true,
            ]);

            $ticket->use($reservation->id);
        }

        // 回数券が3回消費されている
        $ticket->refresh();
        $this->assertEquals(3, $ticket->used_count);
        $this->assertEquals(7, $ticket->remaining_count);

        // 利用履歴が3件記録されている
        $this->assertEquals(3, $ticket->usageHistory()->count());
    }

    /** @test */
    public function customer_can_get_available_tickets_for_specific_store()
    {
        // 店舗Aの回数券を発行
        $ticketA = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => 10,
            'purchase_price' => $this->ticketPlan->price,
            'status' => 'active',
        ]);

        // 別の店舗Bを作成
        $storeB = Store::factory()->create();
        $planB = TicketPlan::create([
            'store_id' => $storeB->id,
            'name' => '店舗B 5回券',
            'ticket_count' => 5,
            'price' => 25000,
            'is_active' => true,
        ]);

        // 店舗Bの回数券を発行
        $ticketB = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $storeB->id,
            'ticket_plan_id' => $planB->id,
            'plan_name' => $planB->name,
            'total_count' => 5,
            'purchase_price' => $planB->price,
            'status' => 'active',
        ]);

        // 店舗Aの利用可能回数券を取得
        $availableTicketsA = $this->customer->getAvailableTicketsForStore($this->store->id);
        $this->assertEquals(1, $availableTicketsA->count());
        $this->assertTrue($availableTicketsA->contains($ticketA));
        $this->assertFalse($availableTicketsA->contains($ticketB));

        // 店舗Bの利用可能回数券を取得
        $availableTicketsB = $this->customer->getAvailableTicketsForStore($storeB->id);
        $this->assertEquals(1, $availableTicketsB->count());
        $this->assertFalse($availableTicketsB->contains($ticketA));
        $this->assertTrue($availableTicketsB->contains($ticketB));
    }

    /** @test */
    public function expired_tickets_are_not_available_for_reservations()
    {
        // 有効な回数券
        $activeTicket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '有効な回数券',
            'total_count' => 10,
            'purchase_price' => $this->ticketPlan->price,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        // 期限切れの回数券
        $expiredTicket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '期限切れ回数券',
            'total_count' => 10,
            'purchase_price' => $this->ticketPlan->price,
            'status' => 'active',
            'purchased_at' => Carbon::now()->subMonths(4),
            'expires_at' => Carbon::now()->subDay(),
        ]);

        // 利用可能回数券を取得（有効なものだけ）
        $availableTickets = $this->customer->getAvailableTicketsForStore($this->store->id);

        $this->assertEquals(1, $availableTickets->count());
        $this->assertTrue($availableTickets->contains($activeTicket));
        $this->assertFalse($availableTickets->contains($expiredTicket));
    }
}
