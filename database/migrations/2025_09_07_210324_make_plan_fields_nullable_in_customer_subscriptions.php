<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLiteの制約を回避するため、直接SQLを実行
        DB::statement('
            CREATE TABLE customer_subscriptions_new (
                id integer primary key autoincrement not null,
                customer_id integer not null,
                store_id integer,
                menu_id integer,
                plan_id integer,
                plan_type varchar,
                plan_name varchar,
                monthly_limit integer,
                monthly_price numeric not null,
                billing_date date,
                billing_start_date date,
                service_start_date date,
                start_date date,
                contract_months integer default 1,
                end_date date,
                next_billing_date date,
                payment_method varchar default "robopay",
                payment_reference varchar,
                current_month_visits integer default 0,
                last_visit_date date,
                reset_day integer default 1,
                status varchar default "active",
                notes text,
                created_at datetime,
                updated_at datetime,
                foreign key(customer_id) references customers(id) on delete cascade,
                foreign key(store_id) references stores(id) on delete set null,
                foreign key(menu_id) references menus(id),
                foreign key(plan_id) references subscription_plans(id)
            )
        ');
        
        // 既存データをコピー
        DB::statement('INSERT INTO customer_subscriptions_new SELECT * FROM customer_subscriptions');
        
        // テーブルを入れ替え
        DB::statement('DROP TABLE customer_subscriptions');
        DB::statement('ALTER TABLE customer_subscriptions_new RENAME TO customer_subscriptions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 元に戻すのは複雑なので省略
    }
};
