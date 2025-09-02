<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\User;
use App\Models\MenuCategory;
use App\Models\Menu;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\MedicalRecord;
use App\Models\ReservationLine;
use App\Models\LineMessageTemplate;
use App\Models\SubscriptionPlan;
use App\Models\CustomerSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class ComprehensiveTestSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('🌱 包括的なテストデータを作成開始...');

        // 1. 店舗の作成
        $this->command->info('📍 店舗データ作成中...');
        $stores = $this->createStores();

        // 2. ユーザー（スタッフ）の作成
        $this->command->info('👥 スタッフデータ作成中...');
        $users = $this->createUsers($stores);

        // 3. メニューカテゴリーとメニューの作成
        $this->command->info('📋 メニューデータ作成中...');
        $menus = $this->createMenus($stores);

        // 4. 顧客の作成
        $this->command->info('👤 顧客データ作成中...');
        $customers = $this->createCustomers();

        // 5. 予約ラインの作成
        $this->command->info('📊 予約ライン作成中...');
        $lines = $this->createReservationLines($stores);

        // 6. 予約の作成（過去、現在、未来）
        $this->command->info('📅 予約データ作成中...');
        $reservations = $this->createReservations($stores, $customers, $menus, $lines);

        // 7. カルテの作成
        $this->command->info('📝 カルテデータ作成中...');
        $this->createMedicalRecords($reservations, $customers);

        // 8. サブスクリプションプランと契約
        $this->command->info('💳 サブスクリプションデータ作成中...');
        $this->createSubscriptions($customers);

        // 9. LINEメッセージテンプレート
        $this->command->info('💬 LINEテンプレート作成中...');
        $this->createLineTemplates($stores);

        $this->command->info('✅ テストデータ作成完了！');
        $this->displaySummary($stores, $customers, $reservations);
    }

    private function createStores()
    {
        $stores = [];

        // 銀座本店
        $stores[] = Store::create([
            'name' => '銀座本店',
            'code' => 'GINZA001',
            'postal_code' => '104-0061',
            'address' => '東京都中央区銀座1-2-3 銀座ビル5F',
            'phone' => '03-1234-5678',
            'email' => 'ginza@eye-training.jp',
            'description' => '銀座駅から徒歩1分。最新設備を完備した旗艦店です。',
            'status' => 'active',
            'main_lines_count' => 3,
            'sub_lines_count' => 2,
            'use_staff_assignment' => true,
            'line_enabled' => true,
            'line_official_account_id' => '@ginza_eye',
            'line_send_reminder' => true,
            'line_reminder_time' => '10:00:00',
            'line_reminder_days_before' => 1,
        ]);

        // 新宿店
        $stores[] = Store::create([
            'name' => '新宿店',
            'code' => 'SHINJUKU001',
            'postal_code' => '160-0023',
            'address' => '東京都新宿区西新宿1-1-1 新宿ビル3F',
            'phone' => '03-9876-5432',
            'email' => 'shinjuku@eye-training.jp',
            'description' => '新宿駅西口から徒歩3分。アクセス抜群の店舗です。',
            'status' => 'active',
            'main_lines_count' => 2,
            'sub_lines_count' => 1,
            'use_staff_assignment' => false,
            'line_enabled' => true,
            'line_official_account_id' => '@shinjuku_eye',
        ]);

        // 横浜店（準備中）
        $stores[] = Store::create([
            'name' => '横浜店',
            'code' => 'YOKOHAMA001',
            'postal_code' => '220-0011',
            'address' => '神奈川県横浜市西区高島2-19-12 横浜ビル2F',
            'phone' => '045-123-4567',
            'email' => 'yokohama@eye-training.jp',
            'description' => '2025年3月オープン予定',
            'status' => 'inactive',
            'main_lines_count' => 2,
            'sub_lines_count' => 1,
        ]);

        return $stores;
    }

    private function createUsers($stores)
    {
        $users = [];

        // スーパー管理者
        $users[] = User::create([
            'name' => '管理者太郎',
            'email' => 'admin@eye-training.jp',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'phone' => '090-0000-0001',
        ]);

        // 各店舗のスタッフ
        foreach ($stores as $index => $store) {
            if ($store->status === 'active') {
                // 店舗管理者
                $users[] = User::create([
                    'name' => $store->name . ' 店長',
                    'email' => 'manager' . ($index + 1) . '@eye-training.jp',
                    'password' => Hash::make('password'),
                    'role' => 'manager',
                    'store_id' => $store->id,
                    'phone' => '090-1111-000' . ($index + 1),
                ]);

                // スタッフ
                for ($i = 1; $i <= 2; $i++) {
                    $users[] = User::create([
                        'name' => $store->name . ' スタッフ' . $i,
                        'email' => 'staff' . $index . '_' . $i . '@eye-training.jp',
                        'password' => Hash::make('password'),
                        'role' => 'staff',
                        'store_id' => $store->id,
                        'phone' => '090-2222-' . str_pad($index * 10 + $i, 4, '0', STR_PAD_LEFT),
                    ]);
                }
            }
        }

        return $users;
    }

    private function createMenus($stores)
    {
        $allMenus = [];

        foreach ($stores as $store) {
            // カテゴリー作成
            $categories = [];

            $categories[] = MenuCategory::create([
                'store_id' => $store->id,
                'name' => 'ケアコース',
                'slug' => 'care-course-' . $store->id,
                'description' => '目の疲れを癒やす基本的なケアコース',
                'sort_order' => 1,
                'is_active' => true,
            ]);

            $categories[] = MenuCategory::create([
                'store_id' => $store->id,
                'name' => '水素コース',
                'slug' => 'hydrogen-course-' . $store->id,
                'description' => '水素吸入による目の健康改善コース',
                'sort_order' => 2,
                'is_active' => true,
            ]);

            $categories[] = MenuCategory::create([
                'store_id' => $store->id,
                'name' => 'トレーニングコース',
                'slug' => 'training-course-' . $store->id,
                'description' => '視力改善のための本格トレーニング',
                'sort_order' => 3,
                'is_active' => true,
            ]);

            // 各カテゴリーにメニュー作成
            foreach ($categories as $category) {
                if ($category->name === 'ケアコース') {
                    $menus = [
                        ['name' => 'ベーシックケア30分', 'duration' => 30, 'price' => 3000],
                        ['name' => 'スタンダードケア45分', 'duration' => 45, 'price' => 4500],
                        ['name' => 'プレミアムケア60分', 'duration' => 60, 'price' => 6000],
                    ];
                } elseif ($category->name === '水素コース') {
                    $menus = [
                        ['name' => '水素吸入30分', 'duration' => 30, 'price' => 4000],
                        ['name' => '水素吸入60分', 'duration' => 60, 'price' => 7000],
                        ['name' => '水素吸入90分', 'duration' => 90, 'price' => 9000],
                    ];
                } else {
                    $menus = [
                        ['name' => '視力回復トレーニング', 'duration' => 45, 'price' => 5000],
                        ['name' => '眼筋トレーニング', 'duration' => 30, 'price' => 3500],
                        ['name' => '総合トレーニング', 'duration' => 60, 'price' => 7000],
                    ];
                }

                foreach ($menus as $index => $menuData) {
                    $menu = Menu::create([
                        'store_id' => $store->id,
                        'category_id' => $category->id,
                        'name' => $menuData['name'],
                        'description' => $menuData['name'] . 'の説明文です。効果的な施術を行います。',
                        'price' => $menuData['price'],
                        'duration' => $menuData['duration'],
                        'customer_type_restriction' => ($index === 0) ? 'first_time_only' : 'all',
                        'is_available' => true,
                        'sort_order' => $index + 1,
                        'max_simultaneous_bookings' => 2,
                    ]);
                    $allMenus[] = $menu;
                }
            }
        }

        return $allMenus;
    }

    private function createCustomers()
    {
        $customers = [];

        // 様々なタイプの顧客を作成
        $customerData = [
            ['last_name' => '山田', 'first_name' => '太郎', 'phone' => '090-1234-5678', 'is_first_visit' => false, 'visit_count' => 10],
            ['last_name' => '佐藤', 'first_name' => '花子', 'phone' => '080-2345-6789', 'is_first_visit' => false, 'visit_count' => 5],
            ['last_name' => '鈴木', 'first_name' => '一郎', 'phone' => '070-3456-7890', 'is_first_visit' => true, 'visit_count' => 0],
            ['last_name' => '高橋', 'first_name' => '美咲', 'phone' => '090-4567-8901', 'is_first_visit' => false, 'visit_count' => 3],
            ['last_name' => '田中', 'first_name' => '健太', 'phone' => '080-5678-9012', 'is_first_visit' => true, 'visit_count' => 0],
            ['last_name' => '渡辺', 'first_name' => '裕子', 'phone' => '070-6789-0123', 'is_first_visit' => false, 'visit_count' => 15],
            ['last_name' => '伊藤', 'first_name' => '大輔', 'phone' => '090-7890-1234', 'is_first_visit' => false, 'visit_count' => 8],
            ['last_name' => '中村', 'first_name' => '真理', 'phone' => '080-8901-2345', 'is_first_visit' => true, 'visit_count' => 0],
            ['last_name' => '小林', 'first_name' => '翔太', 'phone' => '070-9012-3456', 'is_first_visit' => false, 'visit_count' => 6],
            ['last_name' => '加藤', 'first_name' => 'さくら', 'phone' => '090-0123-4567', 'is_first_visit' => false, 'visit_count' => 12],
        ];

        foreach ($customerData as $data) {
            $customers[] = Customer::create([
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'last_name_kana' => $data['last_name'],
                'first_name_kana' => $data['first_name'],
                'phone' => $data['phone'],
                'email' => strtolower($data['last_name']) . '@example.com',
                'gender' => array_rand(['male' => 1, 'female' => 1]),
                'birth_date' => Carbon::now()->subYears(rand(20, 60))->subDays(rand(0, 365)),
                'postal_code' => '100-000' . rand(1, 9),
                'address' => '東京都千代田区サンプル町' . rand(1, 10) . '-' . rand(1, 20),
                'is_first_visit' => $data['is_first_visit'],
                'visit_count' => $data['visit_count'],
                'source' => array_rand(['web' => 1, 'referral' => 1, 'walk_in' => 1]),
                'notes' => $data['visit_count'] > 5 ? '常連のお客様です' : null,
                'line_user_id' => rand(0, 1) ? 'LINE_' . uniqid() : null,
            ]);
        }

        return $customers;
    }

    private function createReservationLines($stores)
    {
        $lines = [];

        foreach ($stores as $store) {
            if ($store->status === 'active') {
                // 本ライン作成
                for ($i = 1; $i <= $store->main_lines_count; $i++) {
                    $lines[] = ReservationLine::create([
                        'store_id' => $store->id,
                        'name' => '本ライン' . $i,
                        'type' => 'main',
                        'capacity' => 1,
                        'is_active' => true,
                        'priority' => $i,
                        'description' => '新規顧客用のメインライン',
                    ]);
                }

                // 予備ライン作成
                for ($i = 1; $i <= $store->sub_lines_count; $i++) {
                    $lines[] = ReservationLine::create([
                        'store_id' => $store->id,
                        'name' => '予備ライン' . $i,
                        'type' => 'sub',
                        'capacity' => 1,
                        'is_active' => true,
                        'priority' => 100 + $i,
                        'description' => '既存顧客優先の予備ライン',
                    ]);
                }
            }
        }

        return $lines;
    }

    private function createReservations($stores, $customers, $menus, $lines)
    {
        $reservations = [];
        $activeStores = $stores->filter(function ($store) {
            return $store->status === 'active';
        });

        foreach ($customers as $customer) {
            $numReservations = rand(0, 5);
            
            for ($i = 0; $i < $numReservations; $i++) {
                $store = $activeStores->random();
                $storeMenus = $menus->filter(function ($menu) use ($store) {
                    return $menu->store_id === $store->id;
                });
                
                if ($storeMenus->isEmpty()) continue;
                
                $menu = $storeMenus->random();
                $storeLine = $lines->filter(function ($line) use ($store) {
                    return $line->store_id === $store->id;
                })->random();

                // 日時をランダムに設定（過去60日〜未来30日）
                $daysOffset = rand(-60, 30);
                $reservationDate = Carbon::now()->addDays($daysOffset);
                $hour = rand(10, 18);
                $minute = array_rand([0 => 0, 30 => 30]);
                
                $startTime = $reservationDate->copy()->setTime($hour, $minute);
                $endTime = $startTime->copy()->addMinutes($menu->duration);

                // ステータスを日時に応じて設定
                $status = 'booked';
                if ($daysOffset < -7) {
                    $status = 'completed';
                } elseif ($daysOffset < 0) {
                    $status = rand(0, 10) > 2 ? 'completed' : 'cancelled';
                } elseif ($daysOffset > 7) {
                    $status = rand(0, 10) > 1 ? 'booked' : 'cancelled';
                }

                $reservation = Reservation::create([
                    'reservation_number' => 'R' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'store_id' => $store->id,
                    'customer_id' => $customer->id,
                    'menu_id' => $menu->id,
                    'line_id' => $storeLine->id,
                    'user_id' => null,
                    'reservation_date' => $reservationDate->format('Y-m-d'),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'status' => $status,
                    'total_amount' => $menu->price,
                    'deposit_amount' => 0,
                    'source' => 'web',
                    'notes' => rand(0, 3) === 0 ? 'テスト予約のメモです' : null,
                    'internal_notes' => $status === 'cancelled' ? 'お客様都合によりキャンセル' : null,
                    'cancel_reason' => $status === 'cancelled' ? '都合がつかなくなった' : null,
                    'cancelled_at' => $status === 'cancelled' ? $reservationDate->copy()->subDays(1) : null,
                ]);

                $reservations[] = $reservation;
            }
        }

        return collect($reservations);
    }

    private function createMedicalRecords($reservations, $customers)
    {
        $completedReservations = $reservations->filter(function ($reservation) {
            return $reservation->status === 'completed';
        });

        foreach ($completedReservations as $reservation) {
            $visionRecords = [];
            $numRecords = rand(1, 3);
            
            for ($i = 1; $i <= $numRecords; $i++) {
                $visionRecords[] = [
                    'session_number' => $i,
                    'left_vision' => rand(3, 15) / 10,
                    'right_vision' => rand(3, 15) / 10,
                    'left_corrected' => rand(8, 12) / 10,
                    'right_corrected' => rand(8, 12) / 10,
                    'notes' => '第' . $i . '回目の測定',
                ];
            }

            MedicalRecord::create([
                'customer_id' => $reservation->customer_id,
                'reservation_id' => $reservation->id,
                'store_id' => $reservation->store_id,
                'user_id' => null,
                'service_memo' => 'お客様は' . $reservation->menu->name . 'を受けられました。施術は問題なく完了しました。',
                'customer_management' => [
                    'payment_method' => array_rand(['cash' => 1, 'credit' => 1, 'paypay' => 1]),
                    'visit_purpose' => '視力改善',
                    'referred_by' => rand(0, 1) ? 'Web検索' : '紹介',
                ],
                'vision_records' => $visionRecords,
                'treatment_content' => $reservation->menu->name . 'を実施',
                'prescription' => rand(0, 1) ? '次回は2週間後の来店をおすすめします' : null,
                'next_appointment_date' => rand(0, 1) ? Carbon::parse($reservation->reservation_date)->addDays(14) : null,
            ]);
        }
    }

    private function createSubscriptions($customers)
    {
        // サブスクリプションプラン作成
        $plans = [];
        
        $plans[] = SubscriptionPlan::create([
            'name' => 'ベーシックプラン',
            'code' => 'BASIC_MONTHLY',
            'description' => '月1回の施術が受けられる基本プラン',
            'price' => 8000,
            'billing_cycle' => 'monthly',
            'benefits' => [
                ['benefit' => '月1回の施術', 'value' => '1回'],
                ['benefit' => '優先予約', 'value' => '可能'],
            ],
            'is_active' => true,
        ]);

        $plans[] = SubscriptionPlan::create([
            'name' => 'スタンダードプラン',
            'code' => 'STANDARD_MONTHLY',
            'description' => '月2回の施術と特典が受けられるプラン',
            'price' => 15000,
            'billing_cycle' => 'monthly',
            'benefits' => [
                ['benefit' => '月2回の施術', 'value' => '2回'],
                ['benefit' => '優先予約', 'value' => '可能'],
                ['benefit' => '10%割引', 'value' => '追加施術'],
            ],
            'is_active' => true,
        ]);

        $plans[] = SubscriptionPlan::create([
            'name' => 'プレミアムプラン',
            'code' => 'PREMIUM_MONTHLY',
            'description' => '月4回の施術と全特典が受けられるプラン',
            'price' => 28000,
            'billing_cycle' => 'monthly',
            'benefits' => [
                ['benefit' => '月4回の施術', 'value' => '4回'],
                ['benefit' => '優先予約', 'value' => '可能'],
                ['benefit' => '20%割引', 'value' => '追加施術'],
                ['benefit' => '専用ライン', 'value' => 'あり'],
            ],
            'is_active' => true,
        ]);

        // 一部の顧客にサブスクリプション契約を作成
        $subscribedCustomers = $customers->random(min(5, $customers->count()));
        
        foreach ($subscribedCustomers as $customer) {
            $plan = $plans[array_rand($plans)];
            $startDate = Carbon::now()->subMonths(rand(0, 6));
            
            CustomerSubscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => rand(0, 10) > 2 ? 'active' : 'cancelled',
                'amount' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'start_date' => $startDate,
                'next_billing_date' => $startDate->copy()->addMonth(),
                'auto_renew' => true,
                'payment_method' => array_rand(['credit_card' => 1, 'bank_transfer' => 1]),
            ]);
        }
    }

    private function createLineTemplates($stores)
    {
        foreach ($stores as $store) {
            if ($store->line_enabled) {
                LineMessageTemplate::create([
                    'store_id' => $store->id,
                    'name' => '予約確認メッセージ',
                    'type' => 'reservation_confirmation',
                    'content' => "{{customer_name}}様\n\nご予約ありがとうございます。\n以下の内容で承りました。\n\n日時: {{reservation_date}} {{reservation_time}}\nメニュー: {{menu_name}}\n店舗: {{store_name}}\n\n当日お待ちしております。",
                    'variables' => ['customer_name', 'reservation_date', 'reservation_time', 'menu_name', 'store_name'],
                    'is_active' => true,
                ]);

                LineMessageTemplate::create([
                    'store_id' => $store->id,
                    'name' => 'リマインダーメッセージ',
                    'type' => 'reminder',
                    'content' => "{{customer_name}}様\n\n明日のご予約のお知らせです。\n\n日時: {{reservation_date}} {{reservation_time}}\nメニュー: {{menu_name}}\n\nお気をつけてお越しください。",
                    'variables' => ['customer_name', 'reservation_date', 'reservation_time', 'menu_name'],
                    'is_active' => true,
                ]);

                LineMessageTemplate::create([
                    'store_id' => $store->id,
                    'name' => 'キャンペーンメッセージ',
                    'type' => 'campaign',
                    'content' => "【期間限定キャンペーン】\n\n{{customer_name}}様\n\n今月限定！全メニュー20%OFFキャンペーン実施中です。\n\nご予約はこちら: {{booking_url}}",
                    'variables' => ['customer_name', 'booking_url'],
                    'is_active' => true,
                ]);
            }
        }
    }

    private function displaySummary($stores, $customers, $reservations)
    {
        $this->command->info('');
        $this->command->info('📊 データ作成サマリー');
        $this->command->info('========================');
        $this->command->table(
            ['項目', '件数'],
            [
                ['店舗', $stores->count()],
                ['スタッフ', User::count()],
                ['メニューカテゴリー', MenuCategory::count()],
                ['メニュー', Menu::count()],
                ['顧客', $customers->count()],
                ['予約', $reservations->count()],
                ['カルテ', MedicalRecord::count()],
                ['予約ライン', ReservationLine::count()],
                ['サブスクリプションプラン', SubscriptionPlan::count()],
                ['サブスクリプション契約', CustomerSubscription::count()],
                ['LINEテンプレート', LineMessageTemplate::count()],
            ]
        );

        $this->command->info('');
        $this->command->info('🔑 テストアカウント');
        $this->command->info('========================');
        $this->command->table(
            ['役割', 'メールアドレス', 'パスワード'],
            [
                ['スーパー管理者', 'admin@eye-training.jp', 'password'],
                ['銀座本店 店長', 'manager1@eye-training.jp', 'password'],
                ['新宿店 店長', 'manager2@eye-training.jp', 'password'],
                ['スタッフ', 'staff1_1@eye-training.jp', 'password'],
            ]
        );

        $this->command->info('');
        $this->command->info('📱 テスト顧客（電話番号）');
        $this->command->info('========================');
        $sampleCustomers = $customers->take(5);
        $this->command->table(
            ['名前', '電話番号', '来店回数', 'LINE連携'],
            $sampleCustomers->map(function ($customer) {
                return [
                    $customer->last_name . ' ' . $customer->first_name,
                    $customer->phone,
                    $customer->visit_count,
                    $customer->line_user_id ? '✓' : '-',
                ];
            })
        );
    }
}