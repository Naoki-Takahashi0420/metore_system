<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class FiveDayRuleOnUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $store;
    protected $menu;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の店舗を作成（5日ルール設定）
        $this->store = Store::create([
            'name' => 'テスト店舗',
            'code' => 'TEST001',
            'phone' => '0312345678',
            'address' => 'テスト住所',
            'min_interval_days' => 5,
            'is_active' => true,
            'fc_type' => 'regular',
        ]);

        // テスト用のメニューを作成
        $this->menu = Menu::create([
            'store_id' => $this->store->id,
            'name' => 'テストメニュー',
            'price' => 5000,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        // テスト用のスタッフを作成
        $this->staff = User::create([
            'name' => 'テストスタッフ',
            'email' => 'test-staff@example.com',
            'password' => bcrypt('password'),
            'store_id' => $this->store->id,
        ]);

        // テスト用の顧客を作成
        $this->customer = Customer::create([
            'last_name' => 'テスト',
            'first_name' => '顧客',
            'last_name_kana' => 'テスト',
            'first_name_kana' => 'コキャク',
            'name' => 'テスト顧客',
            'name_kana' => 'テストコキャク',
            'email' => 'test@example.com',
            'phone' => '09012345678',
            'ignore_interval_rule' => false, // 5日ルール適用対象
        ]);
    }

    /**
     * テスト1: 予約日変更時に5日ルール違反が検出されることを確認
     */
    public function test_reservation_date_change_triggers_five_day_rule_check()
    {
        Log::info('===== テスト1: 予約日変更時の5日ルールチェック =====');

        // 12月5日に予約を作成
        $reservation1 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-05',
            'start_time' => '14:00',
            'end_time' => '14:30',
            'status' => 'booked',
        ]);

        Log::info('12月5日の予約作成', ['id' => $reservation1->id]);

        // 12月10日に別の予約を作成（5日以上離れているのでOK）
        $reservation2 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-10',
            'start_time' => '15:00',
            'end_time' => '15:30',
            'status' => 'booked',
        ]);

        Log::info('12月10日の予約作成（問題なし）', ['id' => $reservation2->id]);

        // 12月10日の予約を12月4日に変更しようとする（5日ルール違反）
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('1日以内となるため変更できません');

        Log::info('12月10日→12月4日への変更を試行（違反予定）');
        
        $reservation2->reservation_date = '2025-12-04';
        $reservation2->save();
    }

    /**
     * テスト2: ignore_interval_ruleがtrueの顧客は5日ルールをスキップできることを確認
     */
    public function test_customer_with_ignore_rule_can_change_date_freely()
    {
        Log::info('===== テスト2: ignore_interval_rule=trueの顧客テスト =====');

        // 顧客の設定を変更
        $this->customer->ignore_interval_rule = true;
        $this->customer->save();

        // 12月5日に予約を作成
        $reservation1 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-05',
            'start_time' => '14:00',
            'end_time' => '14:30',
            'status' => 'booked',
        ]);

        // 12月10日に別の予約を作成
        $reservation2 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-10',
            'start_time' => '15:00',
            'end_time' => '15:30',
            'status' => 'booked',
        ]);

        // 12月10日の予約を12月4日に変更（ignore_interval_rule=trueなので成功するはず）
        Log::info('ignore_interval_rule=trueで12月10日→12月4日への変更');
        
        $reservation2->reservation_date = '2025-12-04';
        $reservation2->save();

        // 変更が成功したことを確認
        $this->assertEquals('2025-12-04', $reservation2->fresh()->reservation_date->format('Y-m-d'));
        
        Log::info('✅ 変更成功（ignore_interval_rule=true）');
    }

    /**
     * テスト3: キャンセル済みの予約は5日ルールチェックから除外されることを確認
     */
    public function test_cancelled_reservations_are_excluded_from_five_day_rule()
    {
        Log::info('===== テスト3: キャンセル済み予約の除外テスト =====');

        // 12月5日に予約を作成してキャンセル
        $reservation1 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-05',
            'start_time' => '14:00',
            'end_time' => '14:30',
            'status' => 'cancelled', // キャンセル済み
        ]);

        Log::info('12月5日のキャンセル済み予約作成', ['id' => $reservation1->id]);

        // 12月10日に別の予約を作成
        $reservation2 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-10',
            'start_time' => '15:00',
            'end_time' => '15:30',
            'status' => 'booked',
        ]);

        // 12月10日の予約を12月4日に変更（12月5日がキャンセル済みなので成功するはず）
        Log::info('12月10日→12月4日への変更（12月5日はキャンセル済み）');
        
        $reservation2->reservation_date = '2025-12-04';
        $reservation2->save();

        // 変更が成功したことを確認
        $this->assertEquals('2025-12-04', $reservation2->fresh()->reservation_date->format('Y-m-d'));
        
        Log::info('✅ 変更成功（キャンセル済み予約は除外）');
    }

    /**
     * テスト4: 同日の時間変更は5日ルールの対象外であることを確認
     */
    public function test_same_day_time_change_is_allowed()
    {
        Log::info('===== テスト4: 同日時間変更テスト =====');

        // 12月5日に2つの予約を作成
        $reservation1 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-05',
            'start_time' => '14:00',
            'end_time' => '14:30',
            'status' => 'booked',
        ]);

        $reservation2 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-05',
            'start_time' => '15:00',
            'end_time' => '15:30',
            'status' => 'booked',
        ]);

        // 時間だけを変更（同日内の変更）
        Log::info('同日内での時間変更（14:00→16:00）');
        
        $reservation1->start_time = '16:00';
        $reservation1->end_time = '16:30';
        $reservation1->save();

        // 変更が成功したことを確認
        $this->assertEquals('16:00', $reservation1->fresh()->start_time);
        
        Log::info('✅ 同日内の時間変更は成功');
    }

    /**
     * テスト5: 異なる店舗の予約は5日ルールの対象外であることを確認
     */
    public function test_different_store_reservations_are_independent()
    {
        Log::info('===== テスト5: 異なる店舗間の予約テスト =====');

        // 別の店舗を作成
        $store2 = Store::create([
            'name' => 'テスト店舗2',
            'code' => 'TEST002',
            'phone' => '0398765432',
            'address' => 'テスト住所2',
            'min_interval_days' => 5,
            'is_active' => true,
            'fc_type' => 'regular',
        ]);

        $menu2 = Menu::create([
            'store_id' => $store2->id,
            'name' => 'テストメニュー2',
            'price' => 5000,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        // 店舗1で12月5日に予約
        $reservation1 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-05',
            'start_time' => '14:00',
            'end_time' => '14:30',
            'status' => 'booked',
        ]);

        // 店舗2で12月10日に予約
        $reservation2 = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $store2->id,
            'menu_id' => $menu2->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-10',
            'start_time' => '15:00',
            'end_time' => '15:30',
            'status' => 'booked',
        ]);

        // 店舗2の予約を12月4日に変更（異なる店舗なので成功するはず）
        Log::info('異なる店舗間での日付変更');
        
        $reservation2->reservation_date = '2025-12-04';
        $reservation2->save();

        // 変更が成功したことを確認
        $this->assertEquals('2025-12-04', $reservation2->fresh()->reservation_date->format('Y-m-d'));
        
        Log::info('✅ 異なる店舗間では5日ルールは独立');
    }

    /**
     * テスト6: 実際の問題ケースを再現（11月28日→12月4日への変更）
     */
    public function test_reproduce_actual_problem_case()
    {
        Log::info('===== テスト6: 実際の問題ケースの再現 =====');

        // 嶋田美穂さんのケースを再現
        $mihoCustomer = Customer::create([
            'last_name' => '嶋田',
            'first_name' => '美穂',
            'last_name_kana' => 'シマダ',
            'first_name_kana' => 'ミホ',
            'name' => '嶋田美穂',
            'name_kana' => 'シマダミホ',
            'email' => 'shimada@example.com',
            'phone' => '09040205884',
            'ignore_interval_rule' => false,
        ]);

        // 11月28日の予約を作成
        $reservation1 = Reservation::create([
            'customer_id' => $mihoCustomer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-11-28',
            'start_time' => '13:00',
            'end_time' => '13:30',
            'status' => 'booked',
        ]);

        Log::info('11月28日の予約作成', ['id' => $reservation1->id]);

        // 12月5日の予約を作成（この時点では問題なし）
        $reservation2 = Reservation::create([
            'customer_id' => $mihoCustomer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-05',
            'start_time' => '13:30',
            'end_time' => '14:00',
            'status' => 'booked',
        ]);

        Log::info('12月5日の予約作成（7日差でOK）', ['id' => $reservation2->id]);

        // 11月28日の予約を12月4日に変更しようとする（これで連続になる）
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('1日以内となるため変更できません');

        Log::info('11月28日→12月4日への変更を試行（違反予定）');
        
        $reservation1->reservation_date = '2025-12-04';
        $reservation1->save();
    }

    /**
     * 周辺影響テスト: change_countが正しく増加することを確認
     */
    public function test_change_count_increments_correctly_with_rule_check()
    {
        Log::info('===== 周辺影響テスト: change_count =====');

        // 初期状態を確認
        $this->assertEquals(0, $this->customer->change_count);

        // 12月10日に予約を作成
        $reservation = Reservation::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'menu_id' => $this->menu->id,
            'staff_id' => $this->staff->id,
            'reservation_date' => '2025-12-10',
            'start_time' => '14:00',
            'end_time' => '14:30',
            'status' => 'booked',
        ]);

        // 日付を変更（5日ルール違反しない範囲で）
        $reservation->reservation_date = '2025-12-20';
        $reservation->save();

        // change_countが増加したことを確認
        $this->customer->refresh();
        $this->assertEquals(1, $this->customer->change_count);
        
        Log::info('✅ change_countが正しく増加');
    }
}