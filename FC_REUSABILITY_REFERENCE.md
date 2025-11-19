# FC System - Reusability Reference Matrix

## Quick Lookup: What to Reuse vs Build

---

## TABLE 1: MODELS

| Existing Model | Reuse For FC | % Reusable | Copy/Adapt | Key Changes |
|---|---|---|---|---|
| **Sale** | FcOrder | 80% | ✅ COPY | Rename number/dates, extend status, add store IDs |
| **SaleItem** | FcOrderItem | 85% | ✅ COPY | Rename IDs, keep calculations |
| **Menu** | FcProduct | 70% | ✅ ADAPT | Add SKU/barcode, remove subscription fields |
| **Store** | FcStore logic | 90% | ✅ EXTEND | Add type field, parent_id, cut-off times |
| **User** | FC Staff | 95% | ✅ EXTEND | Add fc_buyer, fc_manager roles only |
| **Customer** | NOT USED | 0% | ❌ SKIP | Different business entity |
| **DailyClosing** | FcInvoice | 60% | ✅ ADAPT | Different structure, add PDF path |
| **SubscriptionPlan** | FcProductCategory | 70% | ✅ ADAPT | Remove subscription logic, keep pricing |
| **Shift** | NOT USED | 0% | ❌ SKIP | Not applicable |
| **Reservation** | NOT USED | 0% | ❌ SKIP | Different domain |

**Summary**: 3 direct copies, 3 adaptations, 4 skip

---

## TABLE 2: FILAMENT RESOURCES

| Existing Resource | Reuse For FC | % Reusable | Copy/Adapt | Focus Reuse |
|---|---|---|---|---|
| **SaleResource** | FcOrderResource | 80% | ✅ COPY | Form structure, line items, search |
| **StoreResource** | FcStoreResource | 90% | ✅ EXTEND | Navigation group, form sections |
| **UserResource** | FcUserResource | 95% | ✅ EXTEND | Role management, permissions |
| **MenuResource** | FcProductResource | 70% | ✅ ADAPT | Search, filter, image upload patterns |
| **CustomerResource** | NOT USED | 0% | ❌ SKIP | Different data model |
| **SubscriptionPlanResource** | FcCategoryResource | 70% | ✅ ADAPT | Form structure, pricing fields |
| **ReservationResource** | NOT USED | 0% | ❌ SKIP | Complex calendar not needed |
| **ShiftResource** | NOT USED | 0% | ❌ SKIP | Not applicable |

**Summary**: Reuse 2, Extend 2, Adapt 2, Skip 3

---

## TABLE 3: SERVICE CLASSES

| Existing Service | Reuse For FC | % Reusable | Copy/Adapt | Key Pattern |
|---|---|---|---|---|
| **SalePostingService** | FcOrderService | 85% | ✅ COPY | Calculation, validation, event firing |
| **DailyClosingService** | FcInvoiceService | 70% | ✅ ADAPT | Number generation, reconciliation |
| **SubscriptionService** | FcOrderCycle | 70% | ✅ ADAPT | Cycle management patterns |
| **AdminNotificationService** | FcNotificationService | 80% | ✅ ADAPT | Delivery logic, multiple channels |
| **SmsService** | FC SMS Alerts | 90% | ✅ REUSE | AWS SNS integration unchanged |
| **EmailService** | FC Email Invoices | 90% | ✅ REUSE | AWS SES integration unchanged |
| **OtpService** | FC 2FA | 90% | ✅ REUSE | No changes needed |
| **CustomerNotificationService** | FcNotificationService | 60% | ✅ ADAPT | Template patterns only |

**Summary**: 3 direct reuse, 4 adapt, 1 copy

---

## TABLE 4: DATABASE MIGRATIONS (153 Total)

### Core Infrastructure (Can Reuse As-Is)
| Table | Purpose | FC Needed? | Status |
|---|---|---|---|
| users | Staff authentication | ✅ YES | REUSE |
| roles | Spatie permission | ✅ YES | REUSE |
| permissions | Spatie permission | ✅ YES | REUSE |
| model_has_roles | Spatie mapping | ✅ YES | REUSE |
| model_has_permissions | Spatie mapping | ✅ YES | REUSE |
| stores | Multi-location | ✅ YES | EXTEND |
| personal_access_tokens | API auth | ✅ YES (maybe) | REUSE |

### Tables to Extend
| Table | Add Fields | Reason |
|---|---|---|
| stores | type, parent_id, order_cutoff_time | FC store distinction |
| users | fc_permissions (JSON) | FC-specific permissions |

### Tables NOT Needed for FC
```
- customers (different entity)
- reservations (different workflow)
- medical_records (salon-specific)
- menus (will create fc_products)
- shifts (not applicable)
- blocked_time_periods (not applicable)
- otp_verifications (if no 2FA needed)
- customer_subscriptions (different model)
- customer_tickets (different model)
- announcements (could reuse for FC)
```

---

## TABLE 5: KEY PATTERNS TO REPLICATE

### Pattern 1: Number Generation
**From**: Sale::generateSaleNumber()
**Use For**: FcOrder::generateOrderNumber(), FcInvoice::generateInvoiceNumber()
```php
// Template
public static function generateNumber($prefix): string {
    $date = now()->format('ymd');
    $lastRecord = self::whereDate('created_at', today())
        ->orderBy('id', 'desc')->first();
    $sequence = $lastRecord ? (intval(substr($lastRecord->number, -4)) + 1) : 1;
    return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
```

### Pattern 2: Calculation Methods
**From**: Sale::calculateTax(), SaleItem::calculateAmount()
**Use For**: FcOrderItem calculations, tax logic
```php
// Keep as-is
public function calculateAmount(): void {
    $subtotal = $this->unit_price * $this->quantity - $this->discount_amount;
    $this->tax_amount = round($subtotal * ($this->tax_rate / 100), 2);
    $this->amount = $subtotal + $this->tax_amount;
}
```

### Pattern 3: Filament Form Structure
**From**: SaleResource form sections
**Use For**: All FC Filament resources
```php
// Keep structure:
Forms\Components\Section::make('title')
    ->schema([
        Forms\Components\Grid::make(2)->schema([...]),
        Forms\Components\Grid::make(2)->schema([...]),
    ])
```

### Pattern 4: Notification Service
**From**: AdminNotificationService, CustomerNotificationService
**Use For**: FcNotificationService
```php
// Pattern:
public function notify($users, $template, $data): void {
    foreach ($users as $user) {
        // SMS if phone
        // Email if email
        // Log in NotificationLog
    }
}
```

### Pattern 5: Store-Aware Filtering
**From**: Filament resources with auth()->user()->store_id
**Use For**: All FC resources
```php
// Keep pattern:
->default(fn () => auth()->user()->store_id ?? 1)
->where('store_id', auth()->user()->store_id)
```

---

## TABLE 6: DEPENDENCIES & PREREQUISITES

### Already Installed (Don't Reinstall)
```
✅ Laravel 11.x
✅ Filament 3.x
✅ Livewire 3.x
✅ Spatie Laravel Permission
✅ Laravel Sanctum (API tokens)
✅ AWS SDK (SNS/SES)
```

### You'll Need to Add (If Missing)
```
? barryvdh/laravel-dompdf (for PDF invoices)
? spatie/laravel-media-library (for product images)
? laravel/horizon (for better queue management)
```

### Configuration Already Done
```
✅ Database connection (SQLite)
✅ Mail driver (AWS SES)
✅ SMS driver (AWS SNS)
✅ Authentication (Filament)
✅ Timezone (JST)
✅ Logging (Daily rotation)
```

---

## TABLE 7: ESTIMATED EFFORT

### By Component Type

| Component | New? | Source | Effort | Days |
|---|---|---|---|---|
| **Migrations** | 5 new | From templates | 2 hrs | 0.25 |
| **Models** | 6 new | Copy + adapt | 4 hrs | 0.5 |
| **Services** | 4 new | Copy + adapt | 6 hrs | 0.75 |
| **Filament Resources** | 5 new | Copy + adapt | 8 hrs | 1 |
| **Tests** | Unit + Integration | Write | 6 hrs | 0.75 |
| **API Endpoints** | 3-5 new | Write | 4 hrs | 0.5 |
| **Documentation** | 1 new | Write | 2 hrs | 0.25 |

**Total Estimated**: 32 hours = **4 days** (half-time) or **2 days** (full-time focused)

### What Takes Most Time
1. **Filament Resources** (UI complexity) - 8 hours
2. **Services** (business logic) - 6 hours
3. **Tests** (coverage) - 6 hours

### What's Fastest
1. **Migrations** (template-based) - 2 hours
2. **Models** (straightforward) - 4 hours

---

## TABLE 8: GOTCHAS & WARNINGS

### ⚠️ CRITICAL - Don't Do This

| Mistake | Why | Fix |
|---|---|---|
| Use Sale for FC orders | Different status workflows, customer context | Create FcOrder |
| Merge with Customers | FC stores ≠ customers | Create FcStore relationship |
| Reuse ReservationLine | Seat management not applicable | Ignore completely |
| Ignore tax handling | Taxes are complex in Japan | Use existing calculateTax pattern |
| Create single Order table | Will create confusion | Separate FcOrder from Sale |
| Forget store_id filtering | Security issue | Always filter by store_id |
| Reuse subscription pause logic | Different requirement | Reference only |

### ✅ DO THIS INSTEAD

| Pattern | Reason | Location |
|---|---|---|
| Create FcOrder (not extend Sale) | Clean separation | /app/Models/FcOrder.php |
| Use Store.type field | Efficient filtering | Migration: add_type_to_stores |
| Extend User roles | No code duplication | Spatie roles only |
| Reference Service patterns | Proven patterns | Copy SalePostingService structure |
| Always use store_id | Multi-tenancy safety | Every query |
| Keep notifications separate | FC vs customer different | FcNotificationService |

---

## TABLE 9: CHECKLIST - WHAT TO COPY TODAY

### Files to Copy (Ctrl+C to Ctrl+V)

**Models (Copy entire file, then adapt)**
- [ ] /app/Models/Sale.php → /app/Models/FcOrder.php
- [ ] /app/Models/SaleItem.php → /app/Models/FcOrderItem.php
- [ ] /app/Models/Menu.php → /app/Models/FcProduct.php

**Services (Copy structure, adapt logic)**
- [ ] /app/Services/SalePostingService.php → /app/Services/FcOrderService.php
- [ ] /app/Services/AdminNotificationService.php → /app/Services/FcNotificationService.php

**Filament Resources (Copy form/table structure)**
- [ ] /app/Filament/Resources/SaleResource.php → /app/Filament/Resources/FcOrderResource.php
- [ ] /app/Filament/Resources/MenuResource.php → /app/Filament/Resources/FcProductResource.php

### Files to Reference (Don't copy, understand pattern)

- [ ] /app/Models/Store.php - Multi-store pattern
- [ ] /app/Models/User.php - Auth pattern
- [ ] /app/Models/DailyClosing.php - Reconciliation pattern
- [ ] /app/Services/SmsService.php - Notification pattern
- [ ] /app/Filament/Resources/StoreResource.php - Resource pattern

### Files to Create (From scratch, using templates in quick-start guide)

- [ ] /app/Models/FcProductCategory.php
- [ ] /app/Models/FcInvoice.php
- [ ] /app/Models/FcPayment.php
- [ ] /app/Services/FcInvoiceService.php
- [ ] /app/Services/FcPaymentService.php
- [ ] /app/Filament/Resources/FcInvoiceResource.php
- [ ] /app/Filament/Resources/FcPaymentResource.php

---

## TABLE 10: FOLDER STRUCTURE AFTER MIGRATION

```
app/
├── Models/
│   ├── Sale.php                 (unchanged)
│   ├── SaleItem.php            (unchanged)
│   ├── Store.php               (EXTENDED: add type field)
│   ├── User.php                (unchanged)
│   ├── FcOrder.php             (NEW: copied from Sale)
│   ├── FcOrderItem.php         (NEW: copied from SaleItem)
│   ├── FcProduct.php           (NEW: adapted from Menu)
│   ├── FcProductCategory.php   (NEW: from template)
│   ├── FcInvoice.php           (NEW: from template)
│   └── FcPayment.php           (NEW: from template)
│
├── Services/
│   ├── SalePostingService.php       (unchanged, reference)
│   ├── AdminNotificationService.php (unchanged, reference)
│   ├── SmsService.php              (unchanged, reuse)
│   ├── EmailService.php            (unchanged, reuse)
│   ├── OtpService.php              (unchanged, reuse)
│   ├── FcOrderService.php          (NEW: copied from SalePostingService)
│   ├── FcInvoiceService.php        (NEW: from template)
│   ├── FcPaymentService.php        (NEW: from template)
│   └── FcNotificationService.php   (NEW: adapted from AdminNotificationService)
│
└── Filament/Resources/
    ├── SaleResource.php              (unchanged, reference)
    ├── StoreResource.php             (EXTENDED: add FC fields)
    ├── UserResource.php              (unchanged, reference)
    ├── MenuResource.php              (unchanged, reference)
    ├── FcOrderResource.php           (NEW: copied from SaleResource)
    ├── FcOrderItemResource.php       (NEW: from template)
    ├── FcProductResource.php         (NEW: adapted from MenuResource)
    ├── FcProductCategoryResource.php (NEW: from template)
    ├── FcInvoiceResource.php         (NEW: from template)
    └── FcPaymentResource.php         (NEW: from template)
```

**NEW** = 9 files to create
**EXTENDED** = 2 files to modify
**UNCHANGED** = Many reference files

---

## FINAL DECISION TABLE: START HERE

### Option 1: Start Minimal (MVP)
**Timeline**: 2 days
**Includes**:
- FcOrder + FcOrderItem
- FcProduct + FcProductCategory  
- FcOrderResource
- FcOrderService
- Basic notifications

**Skip**: FcInvoice, FcPayment (use Sale model for now)

### Option 2: Start Complete (Recommended)
**Timeline**: 4 days
**Includes**: Everything above PLUS
- FcInvoice + FcInvoiceService
- FcPayment + FcPaymentService
- FcInvoiceResource
- FcPaymentResource
- PDF generation

### Option 3: Enterprise (Future-Proof)
**Timeline**: 1 week
**Includes**: Everything in Option 2 PLUS
- FcReportService
- Recurring order management
- Inventory integration
- Advanced approval workflows
- Payment gateway webhooks

---

**Start with Option 2 (Complete)** - only 4 days and sets you up for success.

**Next Step**: Read FC_IMPLEMENTATION_QUICK_START.md and start copying files.
