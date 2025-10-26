<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class MenuChangeConflictTest extends TestCase
{
    use RefreshDatabase;

    protected $store;
    protected $category;
    protected $shortMenu;
    protected $longMenu;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // テストデータのセットアップ
        $this->store = Store::factory()->create(['name' => 'テスト店舗']);

        $this->category = MenuCategory::create([
            'store_id' => $this->store->id,
            'name' => 'テストカテゴリー',
            'display_order' => 1
        ]);

        $this->shortMenu = Menu::create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => '短いメニュー',
            'price' => 3000,
            'duration_minutes' => 30,
            'is_available' => true
        ]);

        $this->longMenu = Menu::create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => '長いメニュー',
            'price' => 5000,
            'duration_minutes' => 90,
            'is_available' => true
        ]);

        $this->customer = Customer::factory()->create([
            'store_id' => $this->store->id
        ]);
    }

    /** @test */
    public function メニュー変更で次の予約と重複する場合はエラーになる()
    {
        // 10:00-10:30 の予約（短いメニュー）
        $reservation1 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'seat_key' => 'seat_1',
            'status' => 'booked'
        ]);

        // 10:30-11:00 の予約（別の予約）
        $reservation2 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
            'seat_key' => 'seat_1',
            'status' => 'booked'
        ]);

        echo "\n====================================\n";
        echo "テスト1: メニュー変更で次の予約と重複\n";
        echo "====================================\n";
        echo "予約1: 10:00-10:30 (短いメニュー 30分)\n";
        echo "予約2: 10:30-11:00 (短いメニュー 30分)\n";
        echo "→ 予約1を長いメニュー(90分)に変更\n";
        echo "→ 新しい終了時刻: 11:30 (10:30-11:00と重複!)\n\n";

        // 予約1のメニューを長いメニュー(90分)に変更しようとする
        // これは10:00-11:30になるので、予約2(10:30-11:00)と重複する
        $widget = new \App\Filament\Widgets\ReservationTimelineWidget();
        $result = $widget->changeReservationMenu(
            $reservation1->id,
            $this->longMenu->id,
            []
        );

        echo "結果: " . ($result['success'] ? '成功' : '失敗') . "\n";
        echo "メッセージ: " . ($result['message'] ?? 'なし') . "\n";
        if (isset($result['details'])) {
            echo "詳細:\n";
            echo "  - 新しい終了時刻: " . ($result['details']['new_end_time'] ?? 'なし') . "\n";
            echo "  - 重複する時間: " . ($result['details']['conflicting_times'] ?? 'なし') . "\n";
        }
        echo "\n";

        // 重複チェックが機能していればfalseになるはず
        $this->assertFalse($result['success'], 'メニュー変更が成功してしまった（重複チェックが機能していない）');
        $this->assertStringContainsString('重複', $result['message']);
    }

    /** @test */
    public function 異なる座席なら時間が重複しても変更できる()
    {
        // seat_1 の予約
        $reservation1 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'seat_key' => 'seat_1',
            'status' => 'booked'
        ]);

        // seat_2 の予約（異なる座席）
        $reservation2 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
            'seat_key' => 'seat_2',  // 異なる座席
            'status' => 'booked'
        ]);

        echo "\n====================================\n";
        echo "テスト2: 異なる座席なら重複OK\n";
        echo "====================================\n";
        echo "予約1: seat_1 10:00-10:30\n";
        echo "予約2: seat_2 10:30-11:00 (異なる座席)\n";
        echo "→ 予約1を長いメニュー(90分)に変更\n";
        echo "→ 座席が異なるので変更可能なはず\n\n";

        $widget = new \App\Filament\Widgets\ReservationTimelineWidget();
        $result = $widget->changeReservationMenu(
            $reservation1->id,
            $this->longMenu->id,
            []
        );

        echo "結果: " . ($result['success'] ? '成功' : '失敗') . "\n";
        echo "メッセージ: " . ($result['message'] ?? 'なし') . "\n\n";

        // 座席が異なるので成功するはず
        $this->assertTrue($result['success'], '異なる座席なのにメニュー変更が失敗した');
    }

    /** @test */
    public function キャンセル済みの予約とは重複チェックしない()
    {
        $reservation1 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'seat_key' => 'seat_1',
            'status' => 'booked'
        ]);

        // キャンセル済みの予約
        $reservation2 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
            'seat_key' => 'seat_1',
            'status' => 'cancelled'  // キャンセル済み
        ]);

        echo "\n====================================\n";
        echo "テスト3: キャンセル済み予約は無視\n";
        echo "====================================\n";
        echo "予約1: 10:00-10:30 (booked)\n";
        echo "予約2: 10:30-11:00 (cancelled)\n";
        echo "→ 予約1を長いメニュー(90分)に変更\n";
        echo "→ キャンセル済みは無視されるので変更可能なはず\n\n";

        $widget = new \App\Filament\Widgets\ReservationTimelineWidget();
        $result = $widget->changeReservationMenu(
            $reservation1->id,
            $this->longMenu->id,
            []
        );

        echo "結果: " . ($result['success'] ? '成功' : '失敗') . "\n";
        echo "メッセージ: " . ($result['message'] ?? 'なし') . "\n\n";

        // キャンセル済みは無視されるので成功するはず
        $this->assertTrue($result['success'], 'キャンセル済みなのにメニュー変更が失敗した');
    }

    /** @test */
    public function 重複しない場合は正常に変更できる()
    {
        $reservation1 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'seat_key' => 'seat_1',
            'status' => 'booked'
        ]);

        // 十分離れた予約
        $reservation2 = Reservation::create([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'menu_id' => $this->shortMenu->id,
            'reservation_date' => Carbon::today(),
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'seat_key' => 'seat_1',
            'status' => 'booked'
        ]);

        echo "\n====================================\n";
        echo "テスト4: 重複しない正常ケース\n";
        echo "====================================\n";
        echo "予約1: 10:00-10:30\n";
        echo "予約2: 12:00-12:30 (十分離れている)\n";
        echo "→ 予約1を長いメニュー(90分)に変更\n";
        echo "→ 新しい終了時刻: 11:30 (重複なし)\n\n";

        $widget = new \App\Filament\Widgets\ReservationTimelineWidget();
        $result = $widget->changeReservationMenu(
            $reservation1->id,
            $this->longMenu->id,
            []
        );

        echo "結果: " . ($result['success'] ? '成功' : '失敗') . "\n";
        echo "メッセージ: " . ($result['message'] ?? 'なし') . "\n\n";

        $this->assertTrue($result['success'], '重複がないのにメニュー変更が失敗した');

        // DBを確認
        $reservation1->refresh();
        $this->assertEquals($this->longMenu->id, $reservation1->menu_id);
        $this->assertEquals('11:30:00', $reservation1->end_time);
    }
}
