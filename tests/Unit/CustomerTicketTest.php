<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CustomerTicket;
use App\Models\TicketPlan;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CustomerTicketTest extends TestCase
{
    use RefreshDatabase;

    protected $store;
    protected $customer;
    protected $ticketPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用のデータを作成
        $this->store = Store::factory()->create();
        $this->customer = Customer::factory()->create(['store_id' => $this->store->id]);
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
    public function it_creates_ticket_with_expiry_date_from_plan()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => $this->ticketPlan->name,
            'total_count' => $this->ticketPlan->ticket_count,
            'purchase_price' => $this->ticketPlan->price,
        ]);

        $this->assertNotNull($ticket->expires_at);
        $this->assertNotNull($ticket->purchased_at);

        // 3ヶ月後の日付であることを確認
        $expectedExpiry = Carbon::now()->addMonths(3)->endOfDay();
        $this->assertEquals(
            $expectedExpiry->format('Y-m-d'),
            $ticket->expires_at->format('Y-m-d')
        );
    }

    /** @test */
    public function it_calculates_remaining_count_correctly()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 3,
            'purchase_price' => 50000,
        ]);

        $this->assertEquals(7, $ticket->remaining_count);

        $ticket->update(['used_count' => 10]);
        $this->assertEquals(0, $ticket->remaining_count);
    }

    /** @test */
    public function it_can_use_ticket_when_active_and_has_remaining_count()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
        ]);

        $this->assertTrue($ticket->canUse());

        $result = $ticket->use();
        $this->assertTrue($result);

        $ticket->refresh();
        $this->assertEquals(1, $ticket->used_count);
        $this->assertEquals(9, $ticket->remaining_count);
    }

    /** @test */
    public function it_cannot_use_expired_ticket()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'purchased_at' => Carbon::now()->subMonths(4), // 3ヶ月前に購入（期限切れ）
            'expires_at' => Carbon::now()->subDay(), // 昨日が期限
        ]);

        $this->assertTrue($ticket->is_expired);
        $this->assertFalse($ticket->canUse());

        $result = $ticket->use();
        $this->assertFalse($result);

        $ticket->refresh();
        $this->assertEquals(0, $ticket->used_count); // 使用されていない
    }

    /** @test */
    public function it_cannot_use_used_up_ticket()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 10, // 使い切っている
            'purchase_price' => 50000,
            'status' => 'active',
        ]);

        $this->assertEquals(0, $ticket->remaining_count);
        $this->assertFalse($ticket->canUse());

        $result = $ticket->use();
        $this->assertFalse($result);
    }

    /** @test */
    public function it_updates_status_to_used_up_when_all_tickets_are_used()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '5回券',
            'total_count' => 5,
            'used_count' => 4,
            'purchase_price' => 25000,
            'status' => 'active',
        ]);

        $this->assertEquals('active', $ticket->status);

        // 最後の1回を使用
        $ticket->use();

        $ticket->refresh();
        $this->assertEquals(5, $ticket->used_count);
        $this->assertEquals('used_up', $ticket->status);
    }

    /** @test */
    public function it_can_refund_ticket_usage()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 3,
            'purchase_price' => 50000,
            'status' => 'active',
        ]);

        $result = $ticket->refund(null, 1);
        $this->assertTrue($result);

        $ticket->refresh();
        $this->assertEquals(2, $ticket->used_count);
        $this->assertEquals(8, $ticket->remaining_count);
    }

    /** @test */
    public function it_changes_status_from_used_up_to_active_when_refunded()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '5回券',
            'total_count' => 5,
            'used_count' => 5,
            'purchase_price' => 25000,
            'status' => 'used_up',
        ]);

        $this->assertEquals('used_up', $ticket->status);

        $ticket->refund(null, 1);

        $ticket->refresh();
        $this->assertEquals(4, $ticket->used_count);
        $this->assertEquals('active', $ticket->status);
    }

    /** @test */
    public function it_creates_usage_history_when_ticket_is_used()
    {
        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
        ]);

        $this->assertEquals(0, $ticket->usageHistory()->count());

        $ticket->use();

        $this->assertEquals(1, $ticket->usageHistory()->count());
        $history = $ticket->usageHistory()->first();
        $this->assertEquals(1, $history->used_count);
        $this->assertFalse($history->is_cancelled);
    }

    /** @test */
    public function it_detects_expiring_soon_tickets()
    {
        // 5日後に期限切れ
        $expiringSoon = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'purchased_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(5),
        ]);

        // 30日後に期限切れ
        $notExpiringSoon = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'purchased_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $this->assertTrue($expiringSoon->is_expiring_soon);
        $this->assertFalse($notExpiringSoon->is_expiring_soon);
    }

    /** @test */
    public function it_calculates_validity_with_both_months_and_days()
    {
        $plan = TicketPlan::create([
            'store_id' => $this->store->id,
            'name' => '特別10回券',
            'ticket_count' => 10,
            'price' => 50000,
            'validity_months' => 3,
            'validity_days' => 15,
            'is_active' => true,
        ]);

        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'total_count' => $plan->ticket_count,
            'purchase_price' => $plan->price,
            'purchased_at' => Carbon::now(),
        ]);

        // 3ヶ月15日後の日付であることを確認
        $expectedExpiry = Carbon::now()->addMonths(3)->addDays(15)->endOfDay();
        $this->assertEquals(
            $expectedExpiry->format('Y-m-d'),
            $ticket->expires_at->format('Y-m-d')
        );
    }

    /** @test */
    public function it_creates_unlimited_validity_ticket_when_no_validity_set()
    {
        $plan = TicketPlan::create([
            'store_id' => $this->store->id,
            'name' => '無期限10回券',
            'ticket_count' => 10,
            'price' => 50000,
            'validity_months' => null,
            'validity_days' => null,
            'is_active' => true,
        ]);

        $ticket = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'total_count' => $plan->ticket_count,
            'purchase_price' => $plan->price,
        ]);

        $this->assertNull($ticket->expires_at);
        $this->assertFalse($ticket->is_expired);
        $this->assertNull($ticket->days_until_expiry);
    }

    /** @test */
    public function active_scope_returns_only_usable_tickets()
    {
        // アクティブで使用可能
        $active = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMonths(1),
        ]);

        // 期限切れ
        $expired = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'expires_at' => Carbon::now()->subDay(),
        ]);

        // 使い切り
        $usedUp = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 10,
            'purchase_price' => 50000,
            'status' => 'active',
        ]);

        $activeTickets = CustomerTicket::active()->get();

        $this->assertEquals(1, $activeTickets->count());
        $this->assertTrue($activeTickets->contains($active));
        $this->assertFalse($activeTickets->contains($expired));
        $this->assertFalse($activeTickets->contains($usedUp));
    }

    /** @test */
    public function customer_can_get_available_tickets_in_priority_order()
    {
        // 期限が近い順、残回数が少ない順、購入日が古い順

        // 1. 30日後に期限切れ
        $ticket1 = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'purchased_at' => Carbon::now()->subDays(60),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        // 2. 60日後に期限切れ
        $ticket2 = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'purchased_at' => Carbon::now()->subDays(30),
            'expires_at' => Carbon::now()->addDays(60),
        ]);

        // 3. 無期限（最後に使用されるべき）
        $ticket3 = CustomerTicket::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'ticket_plan_id' => $this->ticketPlan->id,
            'plan_name' => '10回券',
            'total_count' => 10,
            'used_count' => 0,
            'purchase_price' => 50000,
            'status' => 'active',
            'purchased_at' => Carbon::now(),
            'expires_at' => null,
        ]);

        $availableTickets = $this->customer->getAvailableTicketsForStore($this->store->id);

        $this->assertEquals(3, $availableTickets->count());
        // 期限が近い順
        $this->assertEquals($ticket1->id, $availableTickets[0]->id);
        $this->assertEquals($ticket2->id, $availableTickets[1]->id);
        $this->assertEquals($ticket3->id, $availableTickets[2]->id);
    }
}
