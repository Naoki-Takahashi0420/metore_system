CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "store_id" integer,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "role" varchar check("role" in('superadmin', 'admin', 'manager', 'staff')) not null default 'staff',
  "permissions" text,
  "specialties" text,
  "hourly_rate" numeric,
  "is_active" tinyint(1) not null default '1',
  "last_login_at" datetime,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "phone" varchar,
  "is_active_staff" tinyint(1) not null default '1',
  "can_be_nominated" tinyint(1) not null default '1',
  "default_shift_hours" text,
  foreign key("store_id") references "stores"("id") on delete set null
);
CREATE INDEX "users_store_id_role_index" on "users"("store_id", "role");
CREATE INDEX "users_is_active_index" on "users"("is_active");
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "stores"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "name_kana" varchar,
  "postal_code" varchar,
  "prefecture" varchar,
  "city" varchar,
  "address" varchar,
  "phone" varchar not null,
  "email" varchar,
  "opening_hours" text,
  "holidays" text,
  "capacity" integer not null default '1',
  "settings" text,
  "reservation_settings" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "reservation_slot_duration" integer not null default '30',
  "max_advance_days" integer not null default '30',
  "cancellation_deadline_hours" integer not null default '24',
  "require_confirmation" tinyint(1) not null default '0',
  "business_hours" text,
  "image_path" varchar,
  "code" varchar,
  "status" varchar not null default 'active',
  "access" text,
  "payment_methods" text,
  "main_lines_count" integer not null default '1',
  "sub_lines_count" integer not null default '0',
  "use_staff_assignment" tinyint(1) not null default '0',
  "use_equipment_management" tinyint(1) not null default '0',
  "line_allocation_rules" text,
  "line_channel_access_token" varchar,
  "line_channel_secret" varchar,
  "line_official_account_id" varchar,
  "line_basic_id" varchar,
  "line_qr_code_url" varchar,
  "line_add_friend_url" varchar,
  "line_enabled" tinyint(1) not null default '0',
  "line_send_reservation_confirmation" tinyint(1) not null default '1',
  "line_send_reminder" tinyint(1) not null default '1',
  "line_send_followup" tinyint(1) not null default '1',
  "line_send_promotion" tinyint(1) not null default '1',
  "line_reservation_message" text,
  "line_reminder_message" text,
  "line_followup_message_30days" text,
  "line_followup_message_60days" text,
  "line_reminder_time" time not null default '10:00',
  "line_reminder_days_before" integer not null default '1',
  "description" text,
  "sort_order" integer not null default '0',
  "visit_sources" text,
  "shift_based_capacity" integer not null default '1',
  "mode_change_date" date,
  "future_use_staff_assignment" tinyint(1),
  "line_followup_message_7days" text,
  "line_followup_message_15days" text,
  "line_bot_basic_id" varchar
);
CREATE INDEX "stores_is_active_index" on "stores"("is_active");
CREATE INDEX "stores_prefecture_city_index" on "stores"("prefecture", "city");
CREATE UNIQUE INDEX "stores_phone_unique" on "stores"("phone");
CREATE UNIQUE INDEX "stores_email_unique" on "stores"("email");
CREATE TABLE IF NOT EXISTS "shift_schedules"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "staff_id" integer not null,
  "shift_date" date not null,
  "start_time" time not null,
  "end_time" time not null,
  "break_start" time,
  "break_end" time,
  "status" varchar check("status" in('scheduled', 'confirmed', 'working', 'completed', 'cancelled')) not null default 'scheduled',
  "actual_start" time,
  "actual_end" time,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id") on delete cascade,
  foreign key("staff_id") references "users"("id") on delete cascade
);
CREATE INDEX "shift_schedules_store_id_shift_date_index" on "shift_schedules"(
  "store_id",
  "shift_date"
);
CREATE INDEX "shift_schedules_staff_id_shift_date_index" on "shift_schedules"(
  "staff_id",
  "shift_date"
);
CREATE UNIQUE INDEX "unique_staff_shift" on "shift_schedules"(
  "staff_id",
  "shift_date",
  "start_time"
);
CREATE TABLE IF NOT EXISTS "otp_verifications"(
  "id" integer primary key autoincrement not null,
  "phone" varchar not null,
  "otp_code" varchar not null,
  "expires_at" datetime not null,
  "verified_at" datetime,
  "attempts" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "otp_verifications_phone_otp_code_index" on "otp_verifications"(
  "phone",
  "otp_code"
);
CREATE INDEX "otp_verifications_expires_at_index" on "otp_verifications"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "display_name" varchar,
  "description" text
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE IF NOT EXISTS "medical_records"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "staff_id" integer not null,
  "reservation_id" integer,
  "symptoms" text,
  "diagnosis" text,
  "treatment" text,
  "medications" text,
  "notes" text,
  "next_visit_date" date,
  "created_at" datetime,
  "updated_at" datetime,
  "record_date" date,
  "chief_complaint" text,
  "medical_history" text,
  "prescription" text,
  "created_by" integer,
  "actual_reservation_date" date,
  "date_difference_days" integer,
  "reservation_status" varchar check("reservation_status" in('pending', 'booked', 'completed', 'cancelled')) not null default 'pending',
  "reminder_sent_at" datetime,
  "images" text,
  "image_notes" text,
  "service_memo" text,
  "handled_by" varchar,
  "payment_method" varchar,
  "reservation_source" varchar,
  "visit_purpose" text,
  "genetic_possibility" tinyint(1),
  "has_astigmatism" tinyint(1),
  "eye_diseases" text,
  "workplace_address" text,
  "device_usage" text,
  "next_visit_notes" text,
  "session_number" integer,
  "treatment_date" date,
  "vision_records" text,
  foreign key("reservation_id") references reservations("id") on delete set null on update no action,
  foreign key("staff_id") references users("id") on delete cascade on update no action,
  foreign key("customer_id") references customers("id") on delete cascade on update no action,
  foreign key("created_by") references "users"("id")
);
CREATE INDEX "medical_records_reservation_id_index" on "medical_records"(
  "reservation_id"
);
CREATE TABLE IF NOT EXISTS "shifts"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "store_id" integer not null,
  "shift_date" date not null,
  "start_time" time not null,
  "end_time" time not null,
  "break_start" time,
  "break_end" time,
  "status" varchar check("status" in('scheduled', 'working', 'completed', 'cancelled')) not null default 'scheduled',
  "notes" text,
  "is_available_for_reservation" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "actual_start_time" time,
  "actual_break_start" time,
  "actual_break_end" time,
  "actual_end_time" time,
  "additional_breaks" text,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE INDEX "shifts_user_id_shift_date_index" on "shifts"(
  "user_id",
  "shift_date"
);
CREATE INDEX "shifts_store_id_shift_date_index" on "shifts"(
  "store_id",
  "shift_date"
);
CREATE INDEX "shifts_shift_date_index" on "shifts"("shift_date");
CREATE UNIQUE INDEX "shifts_user_id_shift_date_unique" on "shifts"(
  "user_id",
  "shift_date"
);
CREATE TABLE IF NOT EXISTS "sales"(
  "id" integer primary key autoincrement not null,
  "sale_number" varchar not null,
  "reservation_id" integer,
  "customer_id" integer,
  "store_id" integer not null,
  "staff_id" integer,
  "sale_date" date not null,
  "sale_time" time not null,
  "subtotal" numeric not null default '0',
  "tax_amount" numeric not null default '0',
  "discount_amount" numeric not null default '0',
  "total_amount" numeric not null,
  "payment_method" varchar check("payment_method" in('cash', 'credit_card', 'debit_card', 'paypay', 'line_pay', 'other')) not null,
  "receipt_number" varchar,
  "status" varchar check("status" in('completed', 'cancelled', 'refunded', 'partial_refund')) not null default 'completed',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("reservation_id") references "reservations"("id"),
  foreign key("customer_id") references "customers"("id"),
  foreign key("store_id") references "stores"("id"),
  foreign key("staff_id") references "users"("id")
);
CREATE INDEX "sales_store_id_sale_date_index" on "sales"(
  "store_id",
  "sale_date"
);
CREATE INDEX "sales_customer_id_index" on "sales"("customer_id");
CREATE INDEX "sales_staff_id_index" on "sales"("staff_id");
CREATE UNIQUE INDEX "sales_sale_number_unique" on "sales"("sale_number");
CREATE TABLE IF NOT EXISTS "sale_items"(
  "id" integer primary key autoincrement not null,
  "sale_id" integer not null,
  "menu_id" integer,
  "item_type" varchar not null default 'service',
  "item_name" varchar not null,
  "item_description" text,
  "unit_price" numeric not null,
  "quantity" integer not null default '1',
  "discount_amount" numeric not null default '0',
  "tax_rate" numeric not null default '10',
  "tax_amount" numeric not null,
  "amount" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("sale_id") references "sales"("id") on delete cascade,
  foreign key("menu_id") references "menus"("id")
);
CREATE INDEX "sale_items_sale_id_index" on "sale_items"("sale_id");
CREATE INDEX "sale_items_menu_id_index" on "sale_items"("menu_id");
CREATE TABLE IF NOT EXISTS "daily_closings"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "closing_date" date not null,
  "open_time" time,
  "close_time" time,
  "opening_cash" numeric not null default '0',
  "cash_sales" numeric not null default '0',
  "card_sales" numeric not null default '0',
  "digital_sales" numeric not null default '0',
  "total_sales" numeric not null default '0',
  "expected_cash" numeric not null default '0',
  "actual_cash" numeric not null default '0',
  "cash_difference" numeric not null default '0',
  "transaction_count" integer not null default '0',
  "customer_count" integer not null default '0',
  "sales_by_staff" text,
  "sales_by_menu" text,
  "status" varchar check("status" in('open', 'closed', 'verified')) not null default 'open',
  "closed_by" integer,
  "verified_by" integer,
  "closed_at" datetime,
  "verified_at" datetime,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id"),
  foreign key("closed_by") references "users"("id"),
  foreign key("verified_by") references "users"("id")
);
CREATE UNIQUE INDEX "daily_closings_store_id_closing_date_unique" on "daily_closings"(
  "store_id",
  "closing_date"
);
CREATE INDEX "daily_closings_closing_date_index" on "daily_closings"(
  "closing_date"
);
CREATE TABLE IF NOT EXISTS "products"(
  "id" integer primary key autoincrement not null,
  "product_code" varchar not null,
  "name" varchar not null,
  "description" text,
  "category" varchar check("category" in('supplement', 'eyewear', 'accessory', 'book', 'other')) not null,
  "price" numeric not null,
  "cost" numeric not null default '0',
  "unit" varchar not null default '個',
  "barcode" varchar,
  "is_active" tinyint(1) not null default '1',
  "images" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "products_product_code_index" on "products"("product_code");
CREATE INDEX "products_category_index" on "products"("category");
CREATE UNIQUE INDEX "products_product_code_unique" on "products"(
  "product_code"
);
CREATE TABLE IF NOT EXISTS "inventories"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "store_id" integer not null,
  "quantity" integer not null default '0',
  "min_quantity" integer not null default '0',
  "max_quantity" integer,
  "last_purchase_price" numeric,
  "last_purchase_date" date,
  "last_sale_date" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id"),
  foreign key("store_id") references "stores"("id")
);
CREATE UNIQUE INDEX "inventories_product_id_store_id_unique" on "inventories"(
  "product_id",
  "store_id"
);
CREATE INDEX "inventories_quantity_index" on "inventories"("quantity");
CREATE TABLE IF NOT EXISTS "inventory_transactions"(
  "id" integer primary key autoincrement not null,
  "inventory_id" integer not null,
  "type" varchar check("type" in('purchase', 'sale', 'adjustment', 'transfer', 'loss', 'return')) not null,
  "quantity" integer not null,
  "balance_after" integer not null,
  "unit_price" numeric,
  "total_amount" numeric,
  "sale_id" integer,
  "user_id" integer,
  "reference_number" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("inventory_id") references "inventories"("id"),
  foreign key("sale_id") references "sales"("id"),
  foreign key("user_id") references "users"("id")
);
CREATE INDEX "inventory_transactions_inventory_id_created_at_index" on "inventory_transactions"(
  "inventory_id",
  "created_at"
);
CREATE INDEX "inventory_transactions_type_index" on "inventory_transactions"(
  "type"
);
CREATE TABLE IF NOT EXISTS "point_cards"(
  "id" integer primary key autoincrement not null,
  "card_number" varchar not null,
  "customer_id" integer not null,
  "total_points" integer not null default '0',
  "available_points" integer not null default '0',
  "used_points" integer not null default '0',
  "expired_points" integer not null default '0',
  "status" varchar check("status" in('active', 'suspended', 'expired')) not null default 'active',
  "issued_date" date not null,
  "last_used_date" date,
  "expiry_date" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id")
);
CREATE INDEX "point_cards_card_number_index" on "point_cards"("card_number");
CREATE INDEX "point_cards_customer_id_index" on "point_cards"("customer_id");
CREATE UNIQUE INDEX "point_cards_card_number_unique" on "point_cards"(
  "card_number"
);
CREATE TABLE IF NOT EXISTS "point_transactions"(
  "id" integer primary key autoincrement not null,
  "point_card_id" integer not null,
  "type" varchar check("type" in('earned', 'used', 'expired', 'adjusted', 'bonus')) not null,
  "points" integer not null,
  "balance_after" integer not null,
  "sale_id" integer,
  "reservation_id" integer,
  "description" varchar not null,
  "expiry_date" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("point_card_id") references "point_cards"("id"),
  foreign key("sale_id") references "sales"("id"),
  foreign key("reservation_id") references "reservations"("id")
);
CREATE INDEX "point_transactions_point_card_id_created_at_index" on "point_transactions"(
  "point_card_id",
  "created_at"
);
CREATE INDEX "point_transactions_type_index" on "point_transactions"("type");
CREATE TABLE IF NOT EXISTS "point_settings"(
  "id" integer primary key autoincrement not null,
  "store_id" integer,
  "points_per_yen" numeric not null default '1',
  "yen_per_point" numeric not null default '1',
  "minimum_purchase" integer not null default '0',
  "minimum_points_to_use" integer not null default '1',
  "maximum_points_per_use" integer,
  "point_validity_days" integer not null default '365',
  "is_active" tinyint(1) not null default '1',
  "bonus_rules" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id")
);
CREATE INDEX "point_settings_store_id_index" on "point_settings"("store_id");
CREATE UNIQUE INDEX "stores_code_unique" on "stores"("code");
CREATE TABLE IF NOT EXISTS "reservation_menu_options"(
  "id" integer primary key autoincrement not null,
  "reservation_id" integer not null,
  "menu_id" integer not null,
  "price" numeric not null,
  "duration" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("reservation_id") references "reservations"("id") on delete cascade,
  foreign key("menu_id") references "menus"("id") on delete cascade
);
CREATE UNIQUE INDEX "reservation_menu_options_reservation_id_menu_id_unique" on "reservation_menu_options"(
  "reservation_id",
  "menu_id"
);
CREATE TABLE IF NOT EXISTS "store_managers"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "store_id" integer not null,
  "role" varchar check("role" in('owner', 'manager')) not null default 'manager',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE UNIQUE INDEX "store_managers_user_id_store_id_role_unique" on "store_managers"(
  "user_id",
  "store_id",
  "role"
);
CREATE TABLE IF NOT EXISTS "line_message_templates"(
  "id" integer primary key autoincrement not null,
  "key" varchar not null,
  "name" varchar not null,
  "message" text not null,
  "variables" text,
  "store_id" integer,
  "is_active" tinyint(1) not null default '1',
  "category" varchar not null default 'general',
  "description" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE INDEX "line_message_templates_category_store_id_index" on "line_message_templates"(
  "category",
  "store_id"
);
CREATE UNIQUE INDEX "line_message_templates_key_unique" on "line_message_templates"(
  "key"
);
CREATE TABLE IF NOT EXISTS "line_settings"(
  "id" integer primary key autoincrement not null,
  "key" varchar not null,
  "value" text,
  "name" varchar not null,
  "description" text,
  "type" varchar not null default 'text',
  "options" text,
  "category" varchar not null default 'general',
  "sort_order" integer not null default '0',
  "is_system" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "line_settings_category_sort_order_index" on "line_settings"(
  "category",
  "sort_order"
);
CREATE UNIQUE INDEX "line_settings_key_unique" on "line_settings"("key");
CREATE TABLE IF NOT EXISTS "line_message_logs"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "message_type" varchar not null,
  "sent_at" datetime not null,
  "success" tinyint(1) not null default '1',
  "error_message" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id") on delete cascade
);
CREATE INDEX "line_message_logs_customer_id_sent_at_index" on "line_message_logs"(
  "customer_id",
  "sent_at"
);
CREATE INDEX "line_message_logs_message_type_index" on "line_message_logs"(
  "message_type"
);
CREATE TABLE IF NOT EXISTS "menu_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "store_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "image_path" varchar,
  "available_durations" text,
  "duration_prices" text,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE INDEX "menu_categories_store_id_is_active_index" on "menu_categories"(
  "store_id",
  "is_active"
);
CREATE INDEX "menu_categories_sort_order_index" on "menu_categories"(
  "sort_order"
);
CREATE UNIQUE INDEX "menu_categories_slug_unique" on "menu_categories"("slug");
CREATE TABLE IF NOT EXISTS "menus"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "category" varchar,
  "name" varchar not null,
  "description" text,
  "price" numeric not null,
  "is_available" tinyint(1) not null default('1'),
  "max_daily_quantity" integer,
  "sort_order" integer not null default('0'),
  "options" text,
  "tags" text,
  "created_at" datetime,
  "updated_at" datetime,
  "image_path" varchar,
  "is_option" tinyint(1) not null default('0'),
  "show_in_upsell" tinyint(1) not null default('0'),
  "upsell_description" text,
  "customer_type_restriction" varchar not null default('all'),
  "category_id" integer,
  "duration_minutes" integer,
  "is_visible_to_customer" tinyint(1) not null default '1',
  "is_subscription_only" tinyint(1) not null default '0',
  "requires_staff" tinyint(1) not null default '0',
  "is_popular" tinyint(1) not null default '0',
  "reservation_count" integer not null default '0',
  "is_subscription" tinyint(1) not null default '0',
  "subscription_monthly_price" integer,
  "contract_months" integer not null default '1',
  "max_monthly_usage" integer,
  "subscription_plan_ids" text,
  medical_record_only TINYINT(1) NOT NULL DEFAULT 0,
  foreign key("store_id") references stores("id") on delete cascade on update no action,
  foreign key("category_id") references "menu_categories"("id") on delete set null
);
CREATE INDEX "menus_customer_type_restriction_is_available_index" on "menus"(
  "customer_type_restriction",
  "is_available"
);
CREATE INDEX "menus_sort_order_index" on "menus"("sort_order");
CREATE INDEX "menus_store_id_category_index" on "menus"(
  "store_id",
  "category"
);
CREATE INDEX "menus_store_id_is_available_index" on "menus"(
  "store_id",
  "is_available"
);
CREATE INDEX menus_category_id_is_available_index ON menus(
  category_id,
  is_available
);
CREATE INDEX menus_duration_minutes_index ON menus(duration_minutes);
CREATE TABLE IF NOT EXISTS "customer_access_tokens"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "store_id" integer,
  "token" varchar not null,
  "purpose" varchar not null default 'existing_customer',
  "expires_at" datetime,
  "usage_count" integer not null default '0',
  "max_usage" integer,
  "metadata" text,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE INDEX "customer_access_tokens_token_is_active_index" on "customer_access_tokens"(
  "token",
  "is_active"
);
CREATE INDEX "customer_access_tokens_customer_id_is_active_index" on "customer_access_tokens"(
  "customer_id",
  "is_active"
);
CREATE INDEX "customer_access_tokens_expires_at_index" on "customer_access_tokens"(
  "expires_at"
);
CREATE UNIQUE INDEX "customer_access_tokens_token_unique" on "customer_access_tokens"(
  "token"
);
CREATE TABLE IF NOT EXISTS "customer_labels"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "label_key" varchar not null,
  "label_name" varchar not null,
  "assigned_at" datetime not null,
  "auto_assigned" tinyint(1) not null default '1',
  "expires_at" datetime,
  "metadata" text,
  "reason" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id") on delete cascade
);
CREATE INDEX "customer_labels_customer_id_label_key_index" on "customer_labels"(
  "customer_id",
  "label_key"
);
CREATE INDEX "customer_labels_label_key_assigned_at_index" on "customer_labels"(
  "label_key",
  "assigned_at"
);
CREATE INDEX "customer_labels_expires_at_index" on "customer_labels"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "line_reminder_rules"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "description" text,
  "target_labels" text not null,
  "trigger_conditions" text not null,
  "reminder_schedule" text not null,
  "message_template_id" integer not null,
  "is_active" tinyint(1) not null default('1'),
  "priority" integer not null default('1'),
  "max_sends_per_customer" integer not null default('1'),
  "exclusion_conditions" text,
  "created_at" datetime,
  "updated_at" datetime,
  "store_id" integer,
  foreign key("message_template_id") references line_message_templates("id") on delete no action on update no action,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE INDEX "line_reminder_rules_is_active_priority_index" on "line_reminder_rules"(
  "is_active",
  "priority"
);
CREATE INDEX "line_reminder_rules_message_template_id_index" on "line_reminder_rules"(
  "message_template_id"
);
CREATE INDEX "line_reminder_rules_store_id_is_active_index" on "line_reminder_rules"(
  "store_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "customer_store_line_connections"(
  "id" integer primary key autoincrement not null,
  "customer_id" integer not null,
  "store_id" integer not null,
  "line_user_id" varchar not null,
  "connected_at" datetime not null,
  "is_blocked" tinyint(1) not null default '0',
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE UNIQUE INDEX "customer_store_line_connections_customer_id_store_id_unique" on "customer_store_line_connections"(
  "customer_id",
  "store_id"
);
CREATE INDEX "customer_store_line_connections_store_id_line_user_id_index" on "customer_store_line_connections"(
  "store_id",
  "line_user_id"
);
CREATE INDEX "customer_store_line_connections_customer_id_is_blocked_index" on "customer_store_line_connections"(
  "customer_id",
  "is_blocked"
);
CREATE TABLE IF NOT EXISTS "store_line_settings"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "line_channel_id" varchar,
  "line_channel_secret" varchar,
  "line_channel_token" text,
  "line_official_account_id" varchar,
  "use_global_settings" tinyint(1) not null default('1'),
  "reminder_settings" text,
  "campaign_settings" text,
  "is_active" tinyint(1) not null default('1'),
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references stores("id") on delete cascade on update no action
);
CREATE UNIQUE INDEX "store_line_settings_store_id_unique" on "store_line_settings"(
  "store_id"
);
CREATE TABLE IF NOT EXISTS "menu_options"(
  "id" integer primary key autoincrement not null,
  "menu_id" integer not null,
  "name" varchar not null,
  "description" text,
  "price" integer not null default '0',
  "duration_minutes" integer not null default '0',
  "sort_order" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "is_required" tinyint(1) not null default '0',
  "max_quantity" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("menu_id") references "menus"("id") on delete cascade
);
CREATE INDEX "menu_options_menu_id_is_active_index" on "menu_options"(
  "menu_id",
  "is_active"
);
CREATE TABLE IF NOT EXISTS "reservation_options"(
  "id" integer primary key autoincrement not null,
  "reservation_id" integer not null,
  "menu_option_id" integer not null,
  "quantity" integer not null default '1',
  "price" integer not null,
  "duration_minutes" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("reservation_id") references "reservations"("id") on delete cascade,
  foreign key("menu_option_id") references "menu_options"("id") on delete cascade
);
CREATE INDEX "reservation_options_reservation_id_index" on "reservation_options"(
  "reservation_id"
);
CREATE UNIQUE INDEX "reservation_options_reservation_id_menu_option_id_unique" on "reservation_options"(
  "reservation_id",
  "menu_option_id"
);
CREATE TABLE IF NOT EXISTS "subscription_plans"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "description" text,
  "price" integer not null,
  "features" text,
  "max_reservations" integer,
  "discount_rate" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "code" varchar,
  "contract_months" integer not null default '0',
  "max_users" integer,
  "notes" text
);
CREATE INDEX "subscription_plans_is_active_index" on "subscription_plans"(
  "is_active"
);
CREATE TABLE IF NOT EXISTS "subscription_payments"(
  "id" integer primary key autoincrement not null,
  "customer_subscription_id" integer not null,
  "customer_id" integer not null,
  "amount" integer not null,
  "payment_method" varchar not null default 'credit_card',
  "status" varchar not null default 'pending',
  "payment_date" datetime not null,
  "due_date" datetime not null,
  "transaction_id" varchar,
  "payment_details" text,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_subscription_id") references "customer_subscriptions"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id")
);
CREATE INDEX "subscription_payments_customer_id_status_index" on "subscription_payments"(
  "customer_id",
  "status"
);
CREATE INDEX "subscription_payments_payment_date_index" on "subscription_payments"(
  "payment_date"
);
CREATE INDEX "subscription_payments_due_date_index" on "subscription_payments"(
  "due_date"
);
CREATE INDEX "medical_records_customer_id_index" on "medical_records"(
  "customer_id"
);
CREATE INDEX "medical_records_treatment_date_index" on "medical_records"(
  "treatment_date"
);
CREATE TABLE IF NOT EXISTS "reservation_lines"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "line_name" varchar not null,
  "line_type" varchar check("line_type" in('main', 'sub')) not null,
  "line_number" integer not null,
  "capacity" integer not null default '1',
  "is_active" tinyint(1) not null default '1',
  "allow_new_customers" tinyint(1) not null default '1',
  "allow_existing_customers" tinyint(1) not null default '1',
  "requires_staff" tinyint(1) not null default '0',
  "allows_simultaneous" tinyint(1) not null default '0',
  "equipment_id" varchar,
  "equipment_name" varchar,
  "priority" integer not null default '0',
  "availability_rules" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE INDEX "reservation_lines_store_id_line_type_is_active_index" on "reservation_lines"(
  "store_id",
  "line_type",
  "is_active"
);
CREATE UNIQUE INDEX "reservation_lines_store_id_line_name_unique" on "reservation_lines"(
  "store_id",
  "line_name"
);
CREATE TABLE IF NOT EXISTS "reservation_line_schedules"(
  "id" integer primary key autoincrement not null,
  "line_id" integer not null,
  "date" date not null,
  "start_time" time not null,
  "end_time" time not null,
  "is_available" tinyint(1) not null default '1',
  "capacity_override" integer,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("line_id") references "reservation_lines"("id") on delete cascade
);
CREATE UNIQUE INDEX "reservation_line_schedules_line_id_date_start_time_unique" on "reservation_line_schedules"(
  "line_id",
  "date",
  "start_time"
);
CREATE INDEX "reservation_line_schedules_date_is_available_index" on "reservation_line_schedules"(
  "date",
  "is_available"
);
CREATE TABLE IF NOT EXISTS "reservation_line_assignments"(
  "id" integer primary key autoincrement not null,
  "reservation_id" integer not null,
  "line_id" integer not null,
  "start_datetime" datetime not null,
  "end_datetime" datetime not null,
  "assignment_type" varchar check("assignment_type" in('auto', 'manual')) not null default 'auto',
  "assignment_reason" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("reservation_id") references "reservations"("id") on delete cascade,
  foreign key("line_id") references "reservation_lines"("id") on delete cascade
);
CREATE UNIQUE INDEX "reservation_line_assignments_reservation_id_unique" on "reservation_line_assignments"(
  "reservation_id"
);
CREATE INDEX "reservation_line_assignments_line_id_start_datetime_end_datetime_index" on "reservation_line_assignments"(
  "line_id",
  "start_datetime",
  "end_datetime"
);
CREATE TABLE IF NOT EXISTS "staff_line_assignments"(
  "id" integer primary key autoincrement not null,
  "staff_id" integer not null,
  "line_id" integer not null,
  "date" date not null,
  "start_time" time not null,
  "end_time" time not null,
  "is_primary" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("staff_id") references "users"("id") on delete cascade,
  foreign key("line_id") references "reservation_lines"("id") on delete cascade
);
CREATE UNIQUE INDEX "staff_line_assignments_staff_id_line_id_date_start_time_unique" on "staff_line_assignments"(
  "staff_id",
  "line_id",
  "date",
  "start_time"
);
CREATE INDEX "staff_line_assignments_date_staff_id_index" on "staff_line_assignments"(
  "date",
  "staff_id"
);
CREATE TABLE IF NOT EXISTS "shift_patterns"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "name" varchar not null,
  "description" text,
  "pattern_data" text not null,
  "is_default" tinyint(1) not null default '0',
  "usage_count" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE INDEX "shift_patterns_store_id_is_default_index" on "shift_patterns"(
  "store_id",
  "is_default"
);
CREATE TABLE IF NOT EXISTS "customers"(
  "id" integer primary key autoincrement not null,
  "last_name" varchar not null,
  "first_name" varchar not null,
  "last_name_kana" varchar,
  "first_name_kana" varchar,
  "phone" varchar not null,
  "email" varchar,
  "birth_date" date,
  "gender" varchar,
  "postal_code" varchar,
  "address" text,
  "preferences" text,
  "medical_notes" text,
  "is_blocked" tinyint(1) not null default('0'),
  "last_visit_at" datetime,
  "phone_verified_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "sms_notifications_enabled" tinyint(1) not null default('1'),
  "notification_preferences" text,
  "customer_number" varchar,
  "line_registration_source" varchar,
  "line_registration_store_id" integer,
  "line_registration_reservation_id" integer,
  "line_registered_at" datetime,
  "last_campaign_sent_at" datetime,
  "campaign_send_count" integer not null default('0'),
  "line_followup_30d_sent_at" datetime,
  "line_followup_60d_sent_at" datetime,
  "prefecture" varchar,
  "city" varchar,
  "building" varchar,
  "notes" text,
  "store_id" integer,
  "line_user_id" varchar,
  "line_notifications_enabled" tinyint(1) not null default '1',
  "line_linked_at" datetime,
  "line_profile" text,
  "cancellation_count" integer not null default '0',
  "no_show_count" integer not null default '0',
  "change_count" integer not null default '0',
  "last_cancelled_at" datetime,
  "line_followup_7d_sent_at" datetime,
  "line_followup_15d_sent_at" datetime,
  foreign key("line_registration_reservation_id") references reservations("id") on delete set null on update no action,
  foreign key("line_registration_store_id") references stores("id") on delete set null on update no action,
  foreign key("store_id") references "stores"("id") on delete cascade
);
CREATE UNIQUE INDEX "customers_customer_number_unique" on "customers"(
  "customer_number"
);
CREATE UNIQUE INDEX "customers_email_unique" on "customers"("email");
CREATE INDEX "customers_is_blocked_index" on "customers"("is_blocked");
CREATE INDEX "customers_last_name_first_name_index" on "customers"(
  "last_name",
  "first_name"
);
CREATE INDEX "customers_last_visit_at_index" on "customers"("last_visit_at");
CREATE UNIQUE INDEX "customers_phone_unique" on "customers"("phone");
CREATE INDEX "customers_phone_verified_at_index" on "customers"(
  "phone_verified_at"
);
CREATE INDEX "customers_store_id_index" on "customers"("store_id");
CREATE TABLE IF NOT EXISTS "blocked_time_periods"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "blocked_date" date not null,
  "start_time" time,
  "end_time" time,
  "reason" varchar,
  "is_recurring" tinyint(1) not null default('0'),
  "recurrence_pattern" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "seat_number" integer,
  "is_all_day" tinyint(1) not null default('0'),
  foreign key("store_id") references stores("id") on delete cascade on update no action
);
CREATE INDEX "blocked_time_periods_store_id_blocked_date_index" on "blocked_time_periods"(
  "store_id",
  "blocked_date"
);
CREATE TABLE IF NOT EXISTS "reservation_histories"(
  "id" integer primary key autoincrement not null,
  "reservation_id" integer not null,
  "customer_id" integer not null,
  "store_id" integer not null,
  "action" varchar not null,
  "old_date" date,
  "old_start_time" time,
  "old_end_time" time,
  "new_date" date,
  "new_start_time" time,
  "new_end_time" time,
  "changed_by" varchar not null,
  "changed_by_user_id" integer,
  "change_reason" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("reservation_id") references "reservations"("id"),
  foreign key("customer_id") references "customers"("id"),
  foreign key("store_id") references "stores"("id"),
  foreign key("changed_by_user_id") references "users"("id")
);
CREATE INDEX "reservation_histories_reservation_id_created_at_index" on "reservation_histories"(
  "reservation_id",
  "created_at"
);
CREATE INDEX "reservation_histories_store_id_old_date_index" on "reservation_histories"(
  "store_id",
  "old_date"
);
CREATE INDEX "reservation_histories_store_id_new_date_index" on "reservation_histories"(
  "store_id",
  "new_date"
);
CREATE TABLE IF NOT EXISTS "customer_subscriptions"(
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
  "last_reset_at" datetime,
  "payment_failed" tinyint(1) not null default '0',
  "payment_failed_at" datetime,
  "payment_failed_reason" varchar check("payment_failed_reason" in('card_expired', 'limit_exceeded', 'insufficient', 'card_error', 'other')),
  "payment_failed_notes" text,
  "is_paused" tinyint(1) not null default '0',
  "pause_start_date" date,
  "pause_end_date" date,
  "paused_by" integer,
  foreign key(customer_id) references customers(id) on delete cascade,
  foreign key(store_id) references stores(id) on delete set null,
  foreign key(menu_id) references menus(id),
  foreign key(plan_id) references subscription_plans(id)
);
CREATE INDEX "customers_line_user_id_index" on "customers"("line_user_id");
CREATE INDEX "customer_subscriptions_payment_failed_index" on "customer_subscriptions"(
  "payment_failed"
);
CREATE INDEX "customer_subscriptions_is_paused_index" on "customer_subscriptions"(
  "is_paused"
);
CREATE INDEX "customer_subscriptions_pause_end_date_index" on "customer_subscriptions"(
  "pause_end_date"
);
CREATE TABLE IF NOT EXISTS "subscription_pause_histories"(
  "id" integer primary key autoincrement not null,
  "customer_subscription_id" integer not null,
  "pause_start_date" date not null,
  "pause_end_date" date not null,
  "paused_by" integer,
  "paused_at" datetime not null,
  "resumed_at" datetime,
  "resume_type" varchar,
  "cancelled_reservations_count" integer not null default '0',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_subscription_id") references "customer_subscriptions"("id") on delete cascade,
  foreign key("paused_by") references "users"("id") on delete set null
);
CREATE INDEX "subscription_pause_histories_customer_subscription_id_index" on "subscription_pause_histories"(
  "customer_subscription_id"
);
CREATE INDEX "subscription_pause_histories_pause_start_date_pause_end_date_index" on "subscription_pause_histories"(
  "pause_start_date",
  "pause_end_date"
);
CREATE INDEX "customers_cancellation_count_index" on "customers"(
  "cancellation_count"
);
CREATE INDEX "customers_no_show_count_index" on "customers"("no_show_count");
CREATE TABLE IF NOT EXISTS "medical_record_images"(
  "id" integer primary key autoincrement not null,
  "medical_record_id" integer not null,
  "file_path" varchar not null,
  "file_name" varchar not null,
  "mime_type" varchar,
  "file_size" integer,
  "title" varchar,
  "description" text,
  "display_order" integer not null default '0',
  "is_visible_to_customer" tinyint(1) not null default '1',
  "image_type" varchar check("image_type" in('before', 'after', 'progress', 'reference', 'other')) not null default 'other',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("medical_record_id") references "medical_records"("id") on delete cascade
);
CREATE INDEX "medical_record_images_medical_record_id_display_order_index" on "medical_record_images"(
  "medical_record_id",
  "display_order"
);
CREATE INDEX "medical_record_images_is_visible_to_customer_index" on "medical_record_images"(
  "is_visible_to_customer"
);
CREATE TABLE IF NOT EXISTS "reservations"(
  "id" integer primary key autoincrement not null,
  "reservation_number" varchar not null,
  "store_id" integer not null,
  "customer_id" integer not null,
  "staff_id" integer,
  "reservation_date" date not null,
  "start_time" time not null,
  "end_time" time not null,
  "status" varchar not null default('pending'),
  "guest_count" integer not null default('1'),
  "total_amount" numeric not null default('0'),
  "deposit_amount" numeric not null default('0'),
  "payment_method" varchar,
  "payment_status" varchar not null default('unpaid'),
  "menu_items" text,
  "notes" text,
  "cancel_reason" text,
  "confirmed_at" datetime,
  "cancelled_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "menu_id" integer,
  "internal_notes" text,
  "source" varchar not null default('website'),
  "shift_id" integer,
  "reminder_sent_at" datetime,
  "reminder_method" varchar,
  "reminder_count" integer not null default('0'),
  "followup_sent_at" datetime,
  "thank_you_sent_at" datetime,
  "line_confirmation_sent_at" datetime,
  "line_type" varchar not null default('main'),
  "line_number" integer not null default('1'),
  "seat_number" integer,
  "is_sub" tinyint(1) not null default('0'),
  "confirmation_sent_at" datetime,
  "confirmation_method" varchar,
  foreign key("shift_id") references shifts("id") on delete set null on update no action,
  foreign key("staff_id") references users("id") on delete set null on update no action,
  foreign key("customer_id") references customers("id") on delete cascade on update no action,
  foreign key("store_id") references stores("id") on delete cascade on update no action,
  foreign key("menu_id") references menus("id") on delete set null on update no action
);
CREATE INDEX "reservations_customer_id_status_index" on "reservations"(
  "customer_id",
  "status"
);
CREATE UNIQUE INDEX "reservations_reservation_number_unique" on "reservations"(
  "reservation_number"
);
CREATE INDEX "reservations_staff_id_reservation_date_index" on "reservations"(
  "staff_id",
  "reservation_date"
);
CREATE INDEX "reservations_store_id_reservation_date_index" on "reservations"(
  "store_id",
  "reservation_date"
);
CREATE INDEX "reservations_store_id_reservation_date_line_type_index" on "reservations"(
  "store_id",
  "reservation_date",
  "line_type"
);
CREATE INDEX "reservations_store_id_status_reservation_date_index" on "reservations"(
  "store_id",
  "status",
  "reservation_date"
);
CREATE UNIQUE INDEX "unique_staff_time" on "reservations"(
  "staff_id",
  "reservation_date",
  "start_time"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_08_20_070251_create_stores_table',1);
INSERT INTO migrations VALUES(5,'2025_08_20_072347_create_customers_table',1);
INSERT INTO migrations VALUES(6,'2025_08_20_072428_create_menus_table',1);
INSERT INTO migrations VALUES(7,'2025_08_20_072502_create_reservations_table',1);
INSERT INTO migrations VALUES(8,'2025_08_20_072549_create_shift_schedules_table',1);
INSERT INTO migrations VALUES(9,'2025_08_20_072634_create_medical_records_table',1);
INSERT INTO migrations VALUES(10,'2025_08_20_072706_create_otp_verifications_table',1);
INSERT INTO migrations VALUES(11,'2025_08_20_073438_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(12,'2025_08_20_073446_create_permission_tables',1);
INSERT INTO migrations VALUES(13,'2025_08_20_200000_add_menu_id_to_reservations_table',1);
INSERT INTO migrations VALUES(14,'2025_08_21_153822_update_medical_records_table_columns',1);
INSERT INTO migrations VALUES(15,'2025_08_21_160510_add_reservation_settings_to_stores_table',1);
INSERT INTO migrations VALUES(16,'2025_08_21_170006_add_business_hours_to_stores_table',1);
INSERT INTO migrations VALUES(17,'2025_08_21_232025_add_actual_reservation_fields_to_medical_records_table',1);
INSERT INTO migrations VALUES(18,'2025_08_22_001952_remove_visit_date_from_medical_records_table',1);
INSERT INTO migrations VALUES(19,'2025_08_22_082734_add_sms_notification_preference_to_customers_table',1);
INSERT INTO migrations VALUES(20,'2025_08_22_083219_create_shifts_table',1);
INSERT INTO migrations VALUES(21,'2025_08_22_084221_add_shift_id_to_reservations_table',1);
INSERT INTO migrations VALUES(22,'2025_08_22_105505_add_actual_times_to_shifts_table',1);
INSERT INTO migrations VALUES(23,'2025_08_22_150054_create_sales_table',1);
INSERT INTO migrations VALUES(24,'2025_08_22_152354_create_inventory_and_points_tables',1);
INSERT INTO migrations VALUES(25,'2025_08_23_140200_add_customer_number_to_customers_table',1);
INSERT INTO migrations VALUES(26,'2025_08_24_055957_add_image_to_stores_table',1);
INSERT INTO migrations VALUES(27,'2025_08_24_060004_add_image_to_menus_table',1);
INSERT INTO migrations VALUES(28,'2025_08_24_072816_add_code_to_stores_table',1);
INSERT INTO migrations VALUES(29,'2025_08_24_073653_add_status_to_stores_table',1);
INSERT INTO migrations VALUES(30,'2025_08_24_073901_add_missing_columns_to_stores_table',1);
INSERT INTO migrations VALUES(31,'2025_08_24_074834_add_menu_options_and_sorting_to_menus_table',1);
INSERT INTO migrations VALUES(32,'2025_08_24_090533_create_reservation_menu_options_table',1);
INSERT INTO migrations VALUES(33,'2025_08_24_122011_add_customer_type_restriction_to_menus_table',1);
INSERT INTO migrations VALUES(34,'2025_08_24_163744_add_images_to_medical_records_table',1);
INSERT INTO migrations VALUES(35,'2025_08_26_140000_create_blocked_time_periods_table',1);
INSERT INTO migrations VALUES(36,'2025_08_27_095612_create_store_managers_table',1);
INSERT INTO migrations VALUES(37,'2025_08_27_100133_add_display_name_to_roles_table',1);
INSERT INTO migrations VALUES(38,'2025_08_28_004652_add_line_registration_tracking_to_customers_table',1);
INSERT INTO migrations VALUES(39,'2025_08_28_005153_create_line_message_templates_table',1);
INSERT INTO migrations VALUES(40,'2025_08_28_005641_create_line_settings_table',1);
INSERT INTO migrations VALUES(41,'2025_08_28_005927_add_campaign_tracking_to_customers_table',1);
INSERT INTO migrations VALUES(42,'2025_08_28_064125_create_line_message_logs_table',1);
INSERT INTO migrations VALUES(43,'2025_08_28_064619_add_reminder_fields_to_reservations_table',1);
INSERT INTO migrations VALUES(44,'2025_08_28_144133_create_menu_categories_table',1);
INSERT INTO migrations VALUES(45,'2025_08_28_144153_add_category_and_time_to_menus_table',1);
INSERT INTO migrations VALUES(46,'2025_08_28_144221_create_customer_subscriptions_table',1);
INSERT INTO migrations VALUES(47,'2025_08_28_163124_create_customer_access_tokens_table',1);
INSERT INTO migrations VALUES(48,'2025_08_28_170000_create_customer_labels_table',1);
INSERT INTO migrations VALUES(49,'2025_08_28_170100_create_line_reminder_rules_table',1);
INSERT INTO migrations VALUES(50,'2025_08_28_171000_add_store_id_to_line_tables',1);
INSERT INTO migrations VALUES(51,'2025_08_28_172000_update_store_line_settings_columns',1);
INSERT INTO migrations VALUES(52,'2025_08_29_100000_create_menu_options_table',1);
INSERT INTO migrations VALUES(53,'2025_08_29_100100_create_reservation_options_table',1);
INSERT INTO migrations VALUES(54,'2025_08_29_120000_create_subscription_plans_table',1);
INSERT INTO migrations VALUES(55,'2025_08_29_120100_create_subscription_payments_table',1);
INSERT INTO migrations VALUES(56,'2025_08_29_200000_restructure_medical_records_table',1);
INSERT INTO migrations VALUES(57,'2025_08_29_210000_create_reservation_lines_table',1);
INSERT INTO migrations VALUES(58,'2025_08_29_220000_add_line_settings_to_stores_table',1);
INSERT INTO migrations VALUES(59,'2025_08_29_230000_add_line_tracking_fields',1);
INSERT INTO migrations VALUES(60,'2025_08_29_240000_add_image_to_menu_categories',1);
INSERT INTO migrations VALUES(61,'2025_08_30_100000_add_phone_to_users_table',1);
INSERT INTO migrations VALUES(62,'2025_08_30_110000_add_description_to_stores_table',1);
INSERT INTO migrations VALUES(63,'2025_09_01_optimize_category_time_settings',2);
INSERT INTO migrations VALUES(64,'2025_09_02_remove_display_order_from_menus',3);
INSERT INTO migrations VALUES(65,'2025_09_02_remove_duration_from_menus',4);
INSERT INTO migrations VALUES(66,'2025_09_02_add_popularity_to_menus',5);
INSERT INTO migrations VALUES(67,'2025_09_02_add_is_sub_to_reservations',6);
INSERT INTO migrations VALUES(68,'2025_09_02_add_seat_number_to_reservations',7);
INSERT INTO migrations VALUES(69,'2025_09_02_add_seat_number_to_blocked_time_periods',8);
INSERT INTO migrations VALUES(70,'2025_09_02_add_staff_fields_to_users',9);
INSERT INTO migrations VALUES(71,'2025_09_02_create_shift_patterns_table',9);
INSERT INTO migrations VALUES(72,'2025_09_02_add_import_fields_to_customers',10);
INSERT INTO migrations VALUES(73,'2025_09_02_add_store_id_to_customers',11);
INSERT INTO migrations VALUES(74,'2025_09_03_add_sort_order_to_stores',12);
INSERT INTO migrations VALUES(75,'2025_09_04_111115_add_is_all_day_to_blocked_time_periods_table',13);
INSERT INTO migrations VALUES(76,'2025_09_04_113147_make_time_columns_nullable_in_blocked_time_periods_table',14);
INSERT INTO migrations VALUES(77,'2025_09_05_094659_add_line_type_to_reservations_table',15);
INSERT INTO migrations VALUES(78,'2025_09_05_095823_cleanup_reservation_columns',16);
INSERT INTO migrations VALUES(79,'2025_09_05_102322_set_default_sub_lines_count',17);
INSERT INTO migrations VALUES(80,'2025_09_05_110200_update_subscription_plans_add_missing_columns',18);
INSERT INTO migrations VALUES(81,'2025_09_05_111500_simplify_subscription_plans_structure',19);
INSERT INTO migrations VALUES(82,'2025_09_05_120000_update_subscription_billing_dates',20);
INSERT INTO migrations VALUES(83,'2025_09_05_130000_add_subscription_to_menus',21);
INSERT INTO migrations VALUES(84,'2025_09_05_132300_add_subscription_fields_to_menus_table',22);
INSERT INTO migrations VALUES(85,'2025_09_05_133242_remove_auto_renewal_from_menus_table',23);
INSERT INTO migrations VALUES(86,'2025_09_05_133555_remove_medical_record_only_from_menus_table',24);
INSERT INTO migrations VALUES(87,'2025_09_05_142227_add_additional_breaks_to_shifts_table',25);
INSERT INTO migrations VALUES(88,'2025_09_06_093625_add_plan_id_to_customer_subscriptions_table',26);
INSERT INTO migrations VALUES(89,'2025_09_06_093832_add_subscription_plan_ids_to_menus_table',27);
INSERT INTO migrations VALUES(90,'2025_09_07_110336_add_seat_number_to_reservations_table',28);
INSERT INTO migrations VALUES(91,'2025_09_07_153529_create_reservation_histories_table',29);
INSERT INTO migrations VALUES(92,'2025_09_07_201622_add_billing_and_service_dates_to_customer_subscriptions_table',30);
INSERT INTO migrations VALUES(93,'2025_09_07_210324_make_plan_fields_nullable_in_customer_subscriptions',31);
INSERT INTO migrations VALUES(94,'2025_09_07_add_reset_tracking_to_customer_subscriptions',32);
INSERT INTO migrations VALUES(95,'2025_09_07_add_custom_options_to_stores_table',33);
INSERT INTO migrations VALUES(96,'2025_09_08_010656_add_line_fields_to_customers_table',34);
INSERT INTO migrations VALUES(97,'2025_09_08_203842_add_shift_based_capacity_to_stores_table',35);
INSERT INTO migrations VALUES(98,'2025_09_10_085333_add_payment_and_pause_fields_to_customer_subscriptions_table',36);
INSERT INTO migrations VALUES(99,'2025_09_10_085410_create_subscription_pause_histories_table',36);
INSERT INTO migrations VALUES(100,'2025_09_10_100000_rename_default_contract_months_in_menus',37);
INSERT INTO migrations VALUES(101,'2025_09_10_105956_rename_default_contract_months_to_contract_months_in_menus_table',38);
INSERT INTO migrations VALUES(102,'2025_09_11_135025_add_cancellation_tracking_to_customers_table',39);
INSERT INTO migrations VALUES(103,'2025_09_11_135753_create_medical_record_images_table',40);
INSERT INTO migrations VALUES(104,'2025_09_12_153749_add_7d_15d_followup_fields_to_customers_table',41);
INSERT INTO migrations VALUES(105,'2025_09_12_153810_add_7d_15d_followup_message_fields_to_stores_table',41);
INSERT INTO migrations VALUES(106,'2025_09_12_154003_add_line_bot_basic_id_to_stores_table',42);
INSERT INTO migrations VALUES(107,'2025_09_12_162720_add_is_sub_to_reservations_table',43);
INSERT INTO migrations VALUES(108,'2025_09_12_170407_make_seat_number_nullable_in_reservations_table',44);
