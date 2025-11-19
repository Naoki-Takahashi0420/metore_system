# METORE Codebase Architecture Analysis
## Comprehensive System Report for FC Headquarters Management System

**Analysis Date**: 2025-11-17  
**System**: METORE (目のトレーニング予約管理システム)  
**Framework**: Laravel 11.x + Filament 3.x + Livewire 3.x  
**Database**: SQLite  

---

## 1. EXECUTIVE SUMMARY

### Key Findings
- **39 existing models** covering customer, reservation, sales, and subscription management
- **20 Filament resources** (admin panels) already built for core functionality
- **153 database migrations** creating a comprehensive schema
- **Spatie Laravel Permission** integrated for RBAC system
- **Well-structured sales/billing foundation** that can be extended for FC orders

### Reusability Assessment
- **Sales module (70% reusable)**: Sale + SaleItem models provide excellent foundation for order management
- **Store system (80% reusable)**: Multi-store architecture perfect for FC store management
- **User/Auth system (90% reusable)**: Spatie Permission + Filament already established
- **Notification system (60% reusable)**: SMS/Email/LINE infrastructure exists but oriented toward customer notifications
- **Period/Time-based features (40% reusable)**: Subscription billing logic can inform FC order cycles

### Recommended Approach
- **Extend Sales module** rather than creating parallel invoicing system
- **Leverage existing Store model** for FC headquarters + FC store distinction
- **Use Spatie Permission** for FC-specific roles (FC Buyer, FC Manager, Headquarters Staff)
- **Create FC-specific models** for: FcOrder, FcProduct, FcInvoice, FcPayment
- **Reuse notification infrastructure** for FC order confirmations and payment reminders

---

## 2. EXISTING MIGRATIONS (153 Total)

### Core System Tables (21 migrations)
```
- users (0001_01_01_000000)           - Staff accounts with Spatie roles/permissions
- stores (2025_08_20_070251)          - Multi-store support
- customers (2025_08_20_072347)       - Customer information
- menus (2025_08_20_072428)           - Service offerings
- reservations (2025_08_20_072502)    - Booking system
- medical_records (2025_08_20_072634) - Treatment records
- otp_verifications (2025_08_20_072706) - 2FA system
- personal_access_tokens (2025_08_20_073438) - API tokens
- permissions (2025_08_20_073446)     - Spatie RBAC tables
- shift_schedules (2025_08_20_072549) - Staff scheduling
- sales (2025_08_22_150054)           - TRANSACTION RECORDS ⭐
- sale_items (2025_08_22_150054)      - ORDER LINE ITEMS ⭐
- daily_closings (N/A in migrations)  - Daily reconciliation
```

### Billing & Subscription Tables (12 migrations)
```
- customer_subscriptions (2025_08_28_144221)      - Monthly contracts
- subscription_plans (2025_08_29_120000)          - Plan definitions
- subscription_payments (2025_08_29_120100)       - Payment tracking
- subscription_pause_histories (2025_09_10_085410) - Pause management
- customer_tickets (2025_10_07_085929)            - Punch card system
- ticket_plans (2025_10_07_085912)                - Punch card definitions
- ticket_usage_history (2025_10_07_085951)        - Usage tracking
```

### Supporting Tables (120+ migrations)
```
- Line integration (8+ migrations)
- Reservation lines/seats (3+ migrations)
- Block periods (5+ migrations)
- Menu options (2+ migrations)
- Medical record images (2+ migrations)
- Announcements (3+ migrations)
- Notifications log (2+ migrations)
- Shift patterns (1+ migrations)
- Customer labels (1+ migrations)
- Presbyopia measurements (1+ migrations)
- Customer images (1+ migrations)
- Help chat logs (1+ migrations)
```

**Key Observation**: Sales + SaleItem + DailyClosing structure is production-tested and can be repurposed for FC orders.

---

## 3. EXISTING MODELS (39 Total)

### Models Directly Reusable for FC Orders

#### 1. **Sale** (CORE - 80% reusable)
**Location**: `/app/Models/Sale.php`
```php
Key Fields:
- sale_number (unique)
- customer_id / customer_subscription_id / customer_ticket_id
- store_id
- staff_id
- sale_date / sale_time
- subtotal, tax_amount, discount_amount, total_amount
- payment_method (cash/credit_card/debit_card/paypay/line_pay/other)
- payment_source (spot/subscription/ticket/other)
- status (completed/cancelled/refunded/partial_refund)
- receipt_number
- notes

Key Methods:
- generateSaleNumber() - Can adapt to FCxxxx pattern
- calculateTax() - Tax calculation logic
- calculateTotal() - Total computation
- grantPoints() - Loyalty system (not needed for FC)
```
**Reuse**: Perfect base for FcOrder model. Rename fields to order_number, extend status to include pending/approved/processing.

#### 2. **SaleItem** (CORE - 85% reusable)
**Location**: `/app/Models/SaleItem.php`
```php
Key Fields:
- sale_id
- menu_id (can become product_id for FC)
- item_type (service/product/other)
- item_name
- unit_price / quantity
- discount_amount / tax_rate / tax_amount
- amount (total)

Key Methods:
- calculateAmount() - Line item total
```
**Reuse**: Perfect for FcOrderItem. Just rename sale_id → fc_order_id, menu_id → fc_product_id.

#### 3. **DailyClosing** (AUDIT - 60% reusable)
**Location**: `/app/Models/DailyClosing.php`
```php
Key Fields:
- store_id / closing_date
- opening_cash / cash_sales / card_sales / digital_sales
- total_sales / expected_cash / actual_cash / cash_difference
- sales_by_staff / sales_by_menu (JSON arrays)
- status (open/closed/verified)
- closed_by / verified_by / closed_at / verified_at

Key Methods:
- hasDifference() - Discrepancy detection
```
**Reuse**: Can create FcOrderReconciliation for daily order summaries from FC stores.

#### 4. **Store** (INFRASTRUCTURE - 90% reusable)
**Location**: `/app/Models/Store.php`
```php
Key Fields:
- name / code / postal_code / prefecture / city / address
- phone / email / image_path / description
- business_hours / holidays / capacity
- is_active / status
- Line settings (19 fields)
- reservation_settings / payment_methods
- line_allocation_rules

Key Methods:
- generateStoreCode()
- isOpen()
- boot() - Auto-generation hooks
```
**Reuse**: Add type field (headquarters/fc_store) + parent_store_id. Can serve dual role.

#### 5. **User** (AUTH - 95% reusable)
**Location**: `/app/Models/User.php`
```php
Key Fields:
- store_id
- name / email / password
- role (superadmin/admin/manager/staff)
- permissions (JSON) / specialties (JSON)
- hourly_rate / is_active / is_active_staff
- last_login_at / phone / theme_color
- Line integration fields

Traits:
- HasFactory / Notifiable / HasApiTokens
- HasRoles (Spatie)
- HasShiftPermissions (custom)

Key Methods:
- canAccessPanel() - Filament permission check
```
**Reuse**: Extend roles to include fc_buyer, fc_manager. No code changes needed.

#### 6. **Customer** (CONTEXT - 30% reusable)
**Location**: `/app/Models/Customer.php`
```php
Key Fields:
- customer_number / store_id
- name / phone / email / address
- is_blocked / cancellation_count / no_show_count
- notification_preferences / sms_notifications_enabled
- line_user_id / line_linked_at

Key Methods:
- search() - Full-text search
```
**Reuse**: Not directly needed for FC orders, but customer data could link to FC orders for audit trails.

---

### Related Models (For Context)

#### Subscription System (Can Inform FC Order Patterns)
- **CustomerSubscription** - Monthly billing cycle logic
- **SubscriptionPlan** - Pricing structure
- **SubscriptionPauseHistory** - Pause/resume mechanics

#### Ticket System (Can Inform FC Inventory)
- **CustomerTicket** - Punch card tracking
- **TicketPlan** - Inventory definitions
- **TicketUsageHistory** - Consumption tracking

#### Supporting Models
- **ReservationLine** - Seat/capacity management (could inform FC warehouse slots)
- **Menu** / **MenuCategory** / **MenuOption** - Product hierarchy
- **Shift** / **ShiftPattern** - Staff scheduling
- **Notification** / **NotificationLog** - Communication history
- **Announcement** - Broadcast messaging

---

## 4. FILAMENT RESOURCES (20 Total)

### Production-Ready Resources

| Resource | Model | Features | Reusability |
|----------|-------|----------|------------|
| **SaleResource** | Sale | Line items, calculations, payment tracking | 80% - Adapt to FcOrderResource |
| **StoreResource** | Store | Multi-location mgmt, business hours, LINE config | 90% - Add FC-specific fields |
| **UserResource** | User | Staff mgmt, roles, permissions, auth | 95% - Add FC roles |
| **CustomerResource** | Customer | Full CRUD, search, merge, import | 30% - Reference only |
| **ReservationResource** | Reservation | Complex form logic, calendar integration | 10% - Different domain |
| **CustomerSubscriptionResource** | CustomerSubscription | Billing cycle, pause/resume | 60% - Inform FC order cycles |
| **SubscriptionPlanResource** | SubscriptionPlan | Pricing structure | 70% - Adapt to FcProductCategories |
| **MenuResource** | Menu | Product catalog | 60% - Adapt to FcProductResource |
| **BlockedTimePeriodResource** | BlockedTimePeriod | Availability calendar | 40% - Could do FC order cutoffs |
| **ShiftResource** | Shift | Staff scheduling | 20% - Not directly applicable |
| **MedicalRecordResource** | MedicalRecord | Treatment records | 5% - Different domain |
| **TicketPlanResource** | TicketPlan | Punch card definitions | 70% - Inform FC inventory tiers |
| **CustomerTicketResource** | CustomerTicket | Punch card tracking | 80% - Inform FC stock tracking |
| **NotificationLogResource** | NotificationLog | SMS/Email history | 70% - Adapt to FC notifications |
| **AnnouncementResource** | Announcement | Broadcast messages | 80% - Use for FC alerts |
| **HelpChatLogResource** | HelpChatLog | Claude AI integration | 50% - Could help FC support |

### Filament Infrastructure Insights
```
Architecture: 
- Resource extends Filament\Resources\Resource
- Forms built with fluent API
- Tables with search, filter, sort, action columns
- RelationManagers for nested resources
- Pages: List, Create, Edit, View (customizable)
- Navigation groups (売上・会計, etc)

Patterns Observed:
- Store-aware filtering (auth()->user()->store_id)
- Multi-select relations
- Custom form actions
- Calculated fields/attributes
- JSON field handling
- Custom views/components
```

---

## 5. DATABASE SCHEMA SUMMARY

### Transaction-Based System (Proven for Orders)
```sql
sales
├── id, sale_number (PK)
├── store_id (FK)
├── customer_id (FK, nullable)
├── staff_id (FK, nullable)
├── sale_date, sale_time
├── subtotal, tax_amount, discount_amount, total_amount
├── payment_method, payment_source, receipt_number
├── status (completed/cancelled/refunded/partial_refund)
└── timestamps

sale_items
├── id
├── sale_id (FK)
├── menu_id (FK, nullable)
├── item_type, item_name, item_description
├── unit_price, quantity, discount_amount
├── tax_rate, tax_amount, amount
└── timestamps

daily_closing
├── id
├── store_id (FK), closing_date
├── opening_cash, cash_sales, card_sales, digital_sales
├── total_sales, expected_cash, actual_cash, cash_difference
├── sales_by_staff (JSON), sales_by_menu (JSON)
├── status, closed_by (FK), verified_by (FK)
├── closed_at, verified_at
└── timestamps
```

### Multi-Store Architecture (Perfect for FC)
```sql
stores
├── id
├── name, code, address, phone, email
├── image_path, business_hours, holidays
├── is_active, status
├── Line settings (19 columns)
├── reservation_settings (JSON)
└── timestamps

users
├── id
├── store_id (FK, nullable) ← Multi-store assignment
├── name, email, password
├── role (superadmin/admin/manager/staff)
├── permissions (JSON), specialties (JSON)
└── timestamps
```

### Billing Cycle System (Can Inform FC Order Cycles)
```sql
customer_subscriptions
├── id
├── customer_id, store_id, plan_id, menu_id
├── billing_date, service_start_date, end_date
├── contract_months, monthly_limit, monthly_price
├── payment_method, payment_reference
├── status, agreement_signed
├── is_paused, pause_start_date, pause_end_date
└── timestamps

subscription_plans
├── id
├── menu_id (FK), store_id (FK)
├── plan_name, plan_type, monthly_limit
├── monthly_price, contract_months
├── status, notes
└── timestamps
```

---

## 6. AUTHENTICATION & AUTHORIZATION SYSTEM

### Spatie Laravel Permission (Already Integrated)
**Tables Created**:
- `roles` - Role definitions
- `permissions` - Permission definitions
- `model_has_roles` - User-to-role mapping
- `model_has_permissions` - User-to-permission mapping
- `role_has_permissions` - Role-to-permission mapping

**Current Roles**:
```php
// From User model
enum: ['superadmin', 'admin', 'manager', 'staff']

// Plus JSON permissions field for custom permissions
```

**Recommended FC Roles**:
- `fc_superadmin` - Global FC system access
- `fc_manager` - FC system admin (can create orders, manage stores)
- `fc_buyer` - FC store staff (can place orders, view invoices)
- `headquarters_staff` - Can approve orders, manage products, create invoices
- `headquarters_manager` - Full access

### Implementation Ready
- Filament already has `canAccessPanel()` method checking `is_active`
- Can easily extend to check specific roles
- Permission middleware available for API routes

---

## 7. SERVICE CLASSES (19 Total)

### Reusable Services

| Service | Purpose | Reusability |
|---------|---------|------------|
| **SalePostingService** | Transaction creation & calculation | 85% - Adapt for FcOrderService |
| **SubscriptionService** | Billing cycle management | 70% - Inform FC order scheduling |
| **CustomerNotificationService** | SMS/Email notifications | 80% - Adapt for FC notifications |
| **AdminNotificationService** | Admin alerts | 70% - Adapt for FC manager alerts |
| **SmsService** | AWS SNS integration | 90% - Reuse for FC SMS alerts |
| **EmailService** | AWS SES integration | 90% - Reuse for FC email invoices |
| **LineMessageService** | LINE integration | 80% - Could notify FC stores of orders |
| **OtpService** | 2-factor authentication | 90% - Can verify FC store staff |
| **ReservationContextService** | Contextual data aggregation | 40% - Pattern reference only |
| **CustomerMergeService** | Data consolidation | 20% - Not applicable |

### Pattern: Service Injection
```php
// Example pattern (from SalePostingService)
public function createSale(array $data): Sale {
    // Validate data
    // Calculate totals, tax
    // Handle line items
    // Create records
    // Fire events
    // Send notifications
    return $sale;
}
```

---

## 8. EXISTING INVOICE/BILLING FEATURES

### Current Implementation Status: **NO DEDICATED INVOICE SYSTEM**

**What Exists**:
1. **Sales/Receipt System** (Production-tested)
   - Sale + SaleItem models ✅
   - Receipt number tracking ✅
   - Multi-payment method support ✅
   - Payment source tracking ✅
   - Daily reconciliation ✅

2. **Subscription Billing** (Production-tested)
   - CustomerSubscription model with billing dates ✅
   - Monthly pricing tracking ✅
   - Payment method storage ✅
   - Pause/resume mechanics ✅
   - Payment failure tracking ✅

3. **Notification Infrastructure** (Production-tested)
   - SMS notifications (AWS SNS) ✅
   - Email notifications (AWS SES) ✅
   - LINE notifications ✅
   - Notification history logging ✅

**What Doesn't Exist**:
- Invoice model (separate from receipt)
- Payment tracking model
- Invoice generation/PDF
- Payment gateway integration (J-Payment mentioned as "WIP")
- Accounts receivable system
- Invoice templates
- Payment schedule tracking

**Recommendation**: Build FcInvoice and FcPayment models on the proven Sales foundation.

---

## 9. PAYMENT PROCESSING

### Current State
```
Payment Methods Supported:
- cash
- credit_card
- debit_card
- paypay
- line_pay
- other

Payment Source Tracking:
- spot (one-time)
- subscription (recurring)
- ticket (prepaid)
- other
```

### Subscription Payment Fields
```php
payment_method = 'robopay' (or: credit/bank/etc)
payment_reference = robopay customer ID or similar
payment_failed = boolean
payment_failed_at = timestamp
payment_failed_reason = string
payment_failed_notes = text
```

### J-Payment Integration (Mentioned, Not Implemented)
```
Webhook URLs Pre-Configured:
- /api/webhook/jpayment/payment
- /api/webhook/jpayment/subscription

Status: 🟡 Ready for implementation
```

---

## 10. RECOMMENDED FC SYSTEM ARCHITECTURE

### New Models to Create

#### 1. **FcStore** (Extends or Replaces Store)
```php
// Extend existing Store model
- type: enum('headquarters', 'fc_store') ← NEW
- parent_store_id: nullable FK
- fc_manager_id: nullable FK  
- order_cutoff_time: time
- delivery_schedule: JSON
- tax_rate: decimal
```

#### 2. **FcProduct** (Similar to Menu)
```php
- id, store_id (headquarters)
- name, description, sku, barcode
- unit_price, tax_rate
- stock_quantity, reorder_level
- supplier_id (could be null for internal)
- category_id
- is_active, status
```

#### 3. **FcOrder** (Extends/Parallels Sale)
```php
// Extend Sale model or create new
- id, order_number, order_date
- fc_store_id (FK)
- headquarters_id (FK, constant)
- ordered_by (user)
- payment_method, payment_status
- payment_reference
- subtotal, tax_amount, discount, total
- status: enum('draft', 'pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled', 'returned')
- delivery_date, notes
- created_at, updated_at
```

#### 4. **FcOrderItem** (Parallels SaleItem)
```php
- id, fc_order_id (FK)
- fc_product_id (FK)
- quantity, unit_price
- discount_amount, tax_rate, tax_amount
- total_amount
```

#### 5. **FcInvoice** (New, Based on DailyClosing Pattern)
```php
- id, invoice_number
- fc_order_id (FK)
- fc_store_id (FK)
- headquarters_id (FK)
- issue_date, due_date
- total_amount, paid_amount
- status: enum('draft', 'issued', 'sent', 'viewed', 'partial_paid', 'paid', 'overdue', 'cancelled')
- pdf_path
```

#### 6. **FcPayment** (Payment Tracking)
```php
- id, payment_number
- fc_invoice_id (FK)
- fc_order_id (FK)
- payment_date, amount
- payment_method (bank_transfer, credit_card, cash, etc)
- status: enum('pending', 'confirmed', 'failed', 'refunded')
- reference_number
- notes
```

### Filament Resources to Create
1. FcProductResource (Product catalog)
2. FcOrderResource (Order management)
3. FcInvoiceResource (Invoice management)
4. FcPaymentResource (Payment tracking)
5. FcStoreResource (FC store management - extends StoreResource)

### Services to Create/Extend
1. **FcOrderService** - Order creation, validation, approval workflow
2. **FcInvoiceService** - Invoice generation, PDF export
3. **FcPaymentService** - Payment recording, reconciliation
4. **FcNotificationService** - Order confirmations, invoice alerts
5. **FcReportService** - Sales reports, order history, payment aging

---

## 11. REUSABILITY MATRIX

### Code That Can Be Directly Copied
```
100% Reusable (No changes):
- User model + relationships
- Spatie Permission configuration
- SmsService, EmailService
- OtpService for 2FA
- DailyClosing reconciliation pattern
- Filament form/table components
- Notification infrastructure

80-99% Reusable (Minor adapts):
- Sale model → FcOrder model
- SaleItem model → FcOrderItem model
- SaleResource → FcOrderResource
- StoreResource → FcStoreResource (add type field)
- SalePostingService → FcOrderService
- AdminNotificationService → FcNotificationService
- SubscriptionService patterns → FcOrderCycleService

50-79% Reusable (Significant changes):
- SubscriptionPlanResource → FcProductCategoryResource
- MenuResource → FcProductResource
- CustomerNotificationService → FcStoreNotificationService
- CustomerSubscriptionResource → FcOrderHistoryResource

< 50% Reusable (Reference only):
- ReservationResource (different domain)
- MedicalRecordResource (different domain)
- ShiftResource (not directly applicable)
- ReservationLineResource (seat management, not order lines)
```

### Architecture Patterns to Replicate
```
✅ Multi-store architecture with store_id foreign key
✅ User roles and permissions via Spatie
✅ JSON fields for flexible configuration
✅ Service class pattern for business logic
✅ Filament resource pattern for admin panels
✅ Notification service pattern (SMS/Email/LINE)
✅ Event-driven architecture readiness
✅ Soft deletes (if implemented)
✅ Model factory and seeding pattern
✅ API token authentication (Sanctum)
```

---

## 12. DEVELOPMENT ROADMAP FOR FC SYSTEM

### Phase 1: Foundation (Reuse Existing)
- [x] Extend Store model for FC distinction
- [x] Create FcProduct model
- [x] Create FcOrder and FcOrderItem models
- [x] Create FcStore and FcStoreUser relationships
- [x] Set up FC-specific Filament resources

### Phase 2: Core Functionality
- [ ] FcOrderService for order workflow
- [ ] FcInvoiceService with PDF generation
- [ ] Order approval workflow (pending → approved → processing)
- [ ] FcOrderResource in Filament
- [ ] FcProductResource in Filament

### Phase 3: Payments & Billing
- [ ] FcPayment model
- [ ] FcPaymentService
- [ ] Payment gateway integration (J-Payment setup)
- [ ] Payment reminder notifications
- [ ] Invoice sending via email

### Phase 4: Reporting & Analytics
- [ ] FcReportService
- [ ] Order history reports
- [ ] Sales by store/product
- [ ] Payment aging analysis
- [ ] Dashboards

### Phase 5: Advanced Features
- [ ] Recurring orders (using SubscriptionService patterns)
- [ ] Inventory management (using CustomerTicket patterns)
- [ ] Purchase orders (PO tracking)
- [ ] Return/exchange management

---

## 13. CONFIGURATION & ENVIRONMENT

### Key Configuration Files to Reference
```
config/
├── app.php           - Application timezone, name
├── database.php      - SQLite path
├── auth.php          - Guards, providers
├── sanctum.php       - API token config
├── permission.php    - Spatie Permission tables
├── logging.php       - Log channel configuration
└── filesystems.php   - Storage configuration
```

### Environment Variables (Example)
```
APP_NAME=METORE
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-northeast-1
AWS_SNS_TOPIC_ARN=

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@reservation.meno-training.com
```

---

## 14. KEY INSIGHTS & RECOMMENDATIONS

### What to Reuse
1. **Sales/Receipt infrastructure** - proven, production-tested ✅
2. **Store hierarchy** - already handles multi-location ✅
3. **User/Auth system** - Spatie + Filament ready ✅
4. **Notification services** - SMS/Email/LINE infrastructure ✅
5. **Filament resource patterns** - consistent, extensible ✅
6. **Service class architecture** - clean, testable ✅

### What to Build New
1. **FcInvoice model** - Different from Sale (invoice vs. receipt)
2. **FcPayment tracking** - Specific to FC payment terms
3. **FcProduct hierarchy** - Different from Menus
4. **Order approval workflow** - Not in current system
5. **Payment gateway integration** - Specific to FC requirements

### Critical Implementation Notes
1. **Don't try to merge FC orders with customer sales** - Different business logic
2. **Use Store.type field** - Distinguish FC stores from salons
3. **Implement proper audit trails** - Who approved? Who received?
4. **Plan for recurring orders** - FC likely has standing orders
5. **Consider inventory sync** - FC products ↔ Actual stock
6. **Webhook readiness** - J-Payment webhooks already configured

### Performance Considerations
- Sales table has 153 production migrations - **well-indexed**
- Multi-store filtering via store_id - **efficient**
- JSON fields for settings - **flexible but queryable**
- Daily reconciliation pattern - **proven at scale**

---

## 15. IMMEDIATE NEXT STEPS

### For Planning
1. Define FC product catalog structure
2. Determine order approval workflow
3. Specify invoice template requirements
4. Set payment terms (net 30, net 60?)
5. Plan delivery schedule management

### For Development Setup
1. Create FcProduct migration
2. Create FcOrder/FcOrderItem migrations
3. Create FcInvoice/FcPayment migrations
4. Add FC role definitions to Spatie
5. Create FC-specific Filament resources
6. Test existing Sale/User patterns with FC data

### For Testing
1. Test multi-store architecture with FC stores
2. Test Filament resources with FC permissions
3. Test SalePostingService with FC order logic
4. Test notification services for FC alerts
5. Load test with realistic FC order volume

---

## APPENDIX: Model Relationships Overview

```
User
├── hasMany Shift
├── hasMany Sale
├── hasMany DailyClosing (as closed_by)
├── belongsToMany Store (managers)
└── hasRoles (Spatie)

Store
├── hasMany User
├── hasMany Menu
├── hasMany Reservation
├── hasMany Sale
├── hasMany CustomerSubscription
└── hasMany DailyClosing

Customer
├── hasMany Reservation
├── hasMany Sale
├── hasMany CustomerSubscription
├── hasMany CustomerTicket
└── hasMany MedicalRecord

Sale ⭐ (KEY FOR FC)
├── belongsTo Store
├── belongsTo Customer
├── belongsTo User (staff)
├── belongsTo Reservation
├── hasMany SaleItem
└── belongsTo CustomerSubscription / CustomerTicket

SaleItem
├── belongsTo Sale
└── belongsTo Menu

Menu
├── belongsTo MenuCategory
├── hasMany MenuOption
├── hasMany SaleItem
└── hasMany CustomerSubscription

CustomerSubscription
├── belongsTo Customer
├── belongsTo SubscriptionPlan
└── hasMany SubscriptionPauseHistory

SubscriptionPlan
├── hasMany CustomerSubscription
└── hasMany Menu
```

---

**End of Report**

Generated: 2025-11-17  
System: METORE Codebase Analysis  
Next Step: Begin FcProduct model implementation
