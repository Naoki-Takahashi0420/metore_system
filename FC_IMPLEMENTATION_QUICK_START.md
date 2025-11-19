# FC Headquarters Management System - Implementation Quick Start Guide

**Quick Reference**: Copy/Paste This Architecture Into Your FC System

---

## SECTION 1: Files to Copy/Reference

### 1. Models to Base FcOrder On
```
SOURCE: /app/Models/Sale.php
COPY TO: /app/Models/FcOrder.php
CHANGES:
- Rename sale_number → order_number
- Rename sale_date/sale_time → order_date (single timestamp)
- Extend status: add 'pending', 'approved', 'processing', 'shipped', 'delivered'
- Add fc_store_id (FK) - the ordering store
- Add headquarters_id (FK) - constant headquarters store
- Remove customer_id, customer_subscription_id, customer_ticket_id
- Keep payment_method, payment_source, tax calculation logic
- Modify grantPoints() to trackOrderHistory()
```

### 2. Models to Base FcOrderItem On
```
SOURCE: /app/Models/SaleItem.php
COPY TO: /app/Models/FcOrderItem.php
CHANGES:
- Rename sale_id → fc_order_id
- Rename menu_id → fc_product_id
- Keep all calculation logic (calculateAmount)
- Keep item_type, item_name, unit_price, quantity
```

### 3. Models to Base FcProduct On
```
SOURCE: /app/Models/Menu.php
COPY TO: /app/Models/FcProduct.php
CHANGES:
- Add sku, barcode fields
- Add stock_quantity, reorder_level
- Add category_id (FK to FcProductCategory)
- Keep unit_price, tax_rate
- Modify relationships for FC context
- Remove subscription-related fields
```

### 4. Store Model Extension
```
SOURCE: /app/Models/Store.php
EXTEND: Add new fields to migration
FIELDS TO ADD:
- type: enum('headquarters', 'fc_store')
- parent_store_id: nullable FK
- order_cutoff_time: time (nullable)
- tax_rate: decimal(5,2) = 10.00
- delivery_address: text (nullable)

LOGIC: 
- Modify relationships to support parent/child stores
- Add scopeHeadquarters(), scopeFcStores() 
```

---

## SECTION 2: Filament Resources to Copy

### Template: Adapt SaleResource to FcOrderResource
```
SOURCE: /app/Filament/Resources/SaleResource.php
COPY TO: /app/Filament/Resources/FcOrderResource.php

FORM CHANGES:
✅ Keep: Store selector, staff selector, date/time picker, totals calculation
✅ CHANGE: "Reservation" section → "FC Store Order" section
✅ ADD: Approval workflow section (approve/reject buttons)
✅ ADD: Delivery date picker
✅ CHANGE: Line items to reference FcProduct instead of Reservation

TABLE CHANGES:
✅ Keep: Search, filter, sort patterns
✅ CHANGE: Display order_number instead of sale_number
✅ ADD: FC store name column
✅ ADD: Order status column with color indicators
✅ ADD: Action buttons: View, Edit, Approve, Ship, Cancel
```

### Template: Create FcProductResource from MenuResource
```
SOURCE: /app/Filament/Resources/MenuResource.php
COPY TO: /app/Filament/Resources/FcProductResource.php

FORM CHANGES:
✅ Keep: Name, description, price, image fields
✅ ADD: SKU, barcode fields
✅ ADD: Stock quantity, reorder level fields
✅ CHANGE: Remove subscription-specific fields
✅ CHANGE: Category selector to FcProductCategory

TABLE CHANGES:
✅ Keep: Search by name/description
✅ ADD: SKU column
✅ ADD: Stock level column (with color: red if < reorder_level)
✅ ADD: Unit price column
```

### New Resource: FcInvoiceResource
```
USE TEMPLATE: Similar to SaleResource
FORM SECTIONS:
- Invoice header (number, date, due date)
- From: Headquarters store info
- To: FC store info
- Line items (read-only, from FcOrder)
- Payment tracking (status, method, reference)
- Approval stamps (issued_by, sent_at, viewed_at)

TABLE COLUMNS:
- Invoice number
- FC Store
- Issue date
- Due date
- Total amount
- Status (draft/issued/sent/viewed/partial_paid/paid/overdue)
- PDF download action
```

---

## SECTION 3: Database Migrations Template

### Migration: Create FC Product Catalog
```php
// 2025_11_17_000001_create_fc_products_table.php
Schema::create('fc_products', function (Blueprint $table) {
    $table->id();
    $table->string('sku')->unique();
    $table->string('barcode')->nullable();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('unit_price', 10, 2);
    $table->decimal('tax_rate', 5, 2)->default(10.00);
    $table->integer('stock_quantity')->default(0);
    $table->integer('reorder_level')->default(10);
    $table->foreignId('category_id')->constrained('fc_product_categories');
    $table->string('image_path')->nullable();
    $table->boolean('is_active')->default(true);
    $table->enum('status', ['available', 'discontinued', 'out_of_stock'])->default('available');
    $table->timestamps();
    
    $table->index(['sku', 'is_active']);
    $table->index(['category_id']);
});
```

### Migration: Create FC Orders
```php
// 2025_11_17_000002_create_fc_orders_table.php
Schema::create('fc_orders', function (Blueprint $table) {
    $table->id();
    $table->string('order_number')->unique();
    $table->foreignId('fc_store_id')->constrained('stores');
    $table->foreignId('headquarters_id')->constrained('stores')->default(1);
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamp('order_date');
    $table->timestamp('delivery_date')->nullable();
    
    // Amount fields
    $table->decimal('subtotal', 10, 2)->default(0);
    $table->decimal('tax_amount', 10, 2)->default(0);
    $table->decimal('discount_amount', 10, 2)->default(0);
    $table->decimal('total_amount', 10, 2);
    
    // Payment
    $table->enum('payment_method', ['bank_transfer', 'credit_card', 'cash', 'other'])->default('bank_transfer');
    $table->enum('payment_status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
    
    // Status workflow
    $table->enum('status', ['draft', 'pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'])->default('draft');
    
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamp('approved_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index(['fc_store_id', 'order_date']);
    $table->index(['status']);
    $table->index(['payment_status']);
});
```

### Migration: Create FC Order Items
```php
// 2025_11_17_000003_create_fc_order_items_table.php
Schema::create('fc_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fc_order_id')->constrained('fc_orders')->onDelete('cascade');
    $table->foreignId('fc_product_id')->constrained('fc_products');
    $table->integer('quantity');
    $table->decimal('unit_price', 10, 2);
    $table->decimal('discount_amount', 10, 2)->default(0);
    $table->decimal('tax_rate', 5, 2)->default(10.00);
    $table->decimal('tax_amount', 10, 2);
    $table->decimal('total_amount', 10, 2);
    $table->timestamps();
});
```

### Migration: Create FC Invoices
```php
// 2025_11_17_000004_create_fc_invoices_table.php
Schema::create('fc_invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique();
    $table->foreignId('fc_order_id')->constrained('fc_orders');
    $table->foreignId('fc_store_id')->constrained('stores');
    $table->foreignId('headquarters_id')->constrained('stores')->default(1);
    
    $table->date('issue_date');
    $table->date('due_date');
    $table->decimal('total_amount', 10, 2);
    $table->decimal('paid_amount', 10, 2)->default(0);
    
    $table->enum('status', ['draft', 'issued', 'sent', 'viewed', 'partial_paid', 'paid', 'overdue', 'cancelled'])->default('draft');
    
    $table->foreignId('issued_by')->nullable()->constrained('users');
    $table->timestamp('issued_at')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('viewed_at')->nullable();
    
    $table->string('pdf_path')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index(['fc_store_id', 'issue_date']);
    $table->index(['status']);
    $table->index(['due_date']);
});
```

### Migration: Create FC Payments
```php
// 2025_11_17_000005_create_fc_payments_table.php
Schema::create('fc_payments', function (Blueprint $table) {
    $table->id();
    $table->string('payment_number')->unique();
    $table->foreignId('fc_invoice_id')->constrained('fc_invoices');
    $table->foreignId('fc_order_id')->constrained('fc_orders');
    
    $table->date('payment_date');
    $table->decimal('amount', 10, 2);
    $table->enum('payment_method', ['bank_transfer', 'credit_card', 'cash', 'check', 'other'])->default('bank_transfer');
    $table->enum('status', ['pending', 'confirmed', 'failed', 'refunded'])->default('pending');
    
    $table->string('reference_number')->nullable();
    $table->foreignId('recorded_by')->nullable()->constrained('users');
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->index(['fc_invoice_id', 'payment_date']);
    $table->index(['status']);
});
```

---

## SECTION 4: Service Classes to Create

### FcOrderService Template
```php
// /app/Services/FcOrderService.php

namespace App\Services;

use App\Models\FcOrder;
use App\Models\FcOrderItem;
use App\Models\FcProduct;
use Exception;

class FcOrderService
{
    /**
     * Create a new order from items
     */
    public function createOrder(array $data): FcOrder
    {
        // Validate FC store exists
        // Validate products exist and are in stock
        // Calculate totals with tax
        // Create order + line items
        // Trigger notifications
        return $order;
    }

    /**
     * Approve an order (HQ staff)
     */
    public function approveOrder(FcOrder $order, int $approvedBy): void
    {
        $order->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        // Notify FC store that order is approved
        // Update inventory
    }

    /**
     * Ship order (HQ staff)
     */
    public function shipOrder(FcOrder $order): void
    {
        $order->update(['status' => 'shipped']);
        // Notify FC store with tracking info
    }

    /**
     * Calculate order totals
     */
    public function calculateTotals(FcOrder $order): void
    {
        $subtotal = $order->items->sum('total_amount');
        $tax = round($subtotal * 0.10, 2); // 10% tax
        $total = $subtotal + $tax;

        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
        ]);
    }
}
```

### FcInvoiceService Template
```php
// /app/Services/FcInvoiceService.php

namespace App\Services;

use App\Models\FcInvoice;
use App\Models\FcOrder;

class FcInvoiceService
{
    /**
     * Generate invoice from order
     */
    public function generateInvoice(FcOrder $order): FcInvoice
    {
        $invoice = FcInvoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'fc_order_id' => $order->id,
            'fc_store_id' => $order->fc_store_id,
            'headquarters_id' => $order->headquarters_id,
            'issue_date' => now()->date(),
            'due_date' => now()->addDays(30)->date(),
            'total_amount' => $order->total_amount,
            'status' => 'draft',
        ]);

        // Generate PDF
        // Send email
        return $invoice;
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('ymd');
        $lastInvoice = FcInvoice::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastInvoice ? (intval(substr($lastInvoice->invoice_number, -4)) + 1) : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mark invoice as paid
     */
    public function recordPayment(FcInvoice $invoice, float $amount): void
    {
        $invoice->update([
            'paid_amount' => $invoice->paid_amount + $amount,
        ]);

        if ($invoice->paid_amount >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid']);
        } else {
            $invoice->update(['status' => 'partial_paid']);
        }
    }
}
```

---

## SECTION 5: Key Decision Points

### Should You Extend Store Model or Create FcStore?
**ANSWER**: Extend Store model with type field

**Reason**:
- Reuses all existing relationships (users, menus potentially)
- Minimal code duplication
- Single authentication/authorization system
- Can query all stores with simple where('type', 'fc_store')

**Implementation**:
```php
// Migration to add to stores table
$table->enum('type', ['headquarters', 'salon', 'fc_store'])->default('salon');
$table->unsignedBigInteger('parent_store_id')->nullable();
```

### Should You Reuse Sale Model or Create FcOrder?
**ANSWER**: Create FcOrder (different status workflows)

**Reason**:
- Sale has status: (completed/cancelled/refunded/partial_refund)
- FcOrder needs: (draft/pending/approved/processing/shipped/delivered)
- Keep domains separate for clarity
- Can share SalePostingService patterns, not inheritance

### Where Should FcProduct Images Go?
**ANSWER**: Reuse Media library or create fc_product_images table

**Recommendation**:
- Use Spatie Media Library if already installed
- Otherwise create simple fc_product_images table
- Store in /storage/app/fc_products/

---

## SECTION 6: Testing Checklist

### Unit Tests to Write
- [ ] FcOrderService::createOrder
- [ ] FcOrderService::approveOrder
- [ ] FcOrderService::calculateTotals
- [ ] FcInvoiceService::generateInvoice
- [ ] FcInvoiceService::recordPayment
- [ ] FcProduct stock validation

### Integration Tests to Write
- [ ] Create FC store, then create order for it
- [ ] Create order → Approve → Generate invoice → Record payment
- [ ] Test order calculations with multiple items + tax
- [ ] Test notification sending when order approved

### Manual Testing Steps
1. Create FC store (type='fc_store')
2. Create FC product catalog
3. Create order from HQ panel
4. View order in FC store panel (read-only)
5. Approve order (HQ panel)
6. Generate invoice
7. Send invoice via email
8. Record payment
9. Check order status transitions
10. Verify calculations (subtotal + tax = total)

---

## SECTION 7: Deployment Checklist

- [ ] All migrations created and tested
- [ ] All models created with relationships
- [ ] Filament resources created
- [ ] Service classes implemented
- [ ] Tests passing (units + integration)
- [ ] FC roles added to Spatie Permission
- [ ] Email templates created for invoices
- [ ] PDF generation configured
- [ ] Payment gateway webhook URLs configured
- [ ] Notification templates created
- [ ] Documentation updated
- [ ] Staging environment tested
- [ ] Production data migration plan (if existing FC)

---

## SECTION 8: Quick Copy-Paste Commands

### Create FC Models
```bash
php artisan make:model FcOrder -m
php artisan make:model FcOrderItem -m
php artisan make:model FcProduct -m
php artisan make:model FcProductCategory -m
php artisan make:model FcInvoice -m
php artisan make:model FcPayment -m
```

### Create Filament Resources
```bash
php artisan make:filament-resource FcOrder
php artisan make:filament-resource FcOrderItem
php artisan make:filament-resource FcProduct
php artisan make:filament-resource FcInvoice
php artisan make:filament-resource FcPayment
```

### Create Services
```bash
php artisan make:request CreateFcOrderRequest
php artisan make:service FcOrderService
php artisan make:service FcInvoiceService
php artisan make:service FcNotificationService
```

---

## APPENDIX: File Structure After Implementation

```
app/
├── Models/
│   ├── FcOrder.php ⭐
│   ├── FcOrderItem.php ⭐
│   ├── FcProduct.php ⭐
│   ├── FcProductCategory.php ⭐
│   ├── FcInvoice.php ⭐
│   ├── FcPayment.php ⭐
│   ├── Store.php (EXTENDED)
│   └── User.php (unchanged)
├── Services/
│   ├── FcOrderService.php ⭐
│   ├── FcInvoiceService.php ⭐
│   ├── FcNotificationService.php ⭐
│   └── FcPaymentService.php ⭐
├── Filament/
│   └── Resources/
│       ├── FcOrderResource.php ⭐
│       ├── FcOrderItemResource.php ⭐
│       ├── FcProductResource.php ⭐
│       ├── FcInvoiceResource.php ⭐
│       ├── FcPaymentResource.php ⭐
│       └── StoreResource.php (EXTENDED)
└── Http/
    └── Requests/
        ├── CreateFcOrderRequest.php ⭐
        ├── UpdateFcOrderRequest.php ⭐
        └── CreateFcPaymentRequest.php ⭐

database/
└── migrations/
    ├── 2025_11_17_000001_create_fc_products_table.php ⭐
    ├── 2025_11_17_000002_create_fc_orders_table.php ⭐
    ├── 2025_11_17_000003_create_fc_order_items_table.php ⭐
    ├── 2025_11_17_000004_create_fc_invoices_table.php ⭐
    ├── 2025_11_17_000005_create_fc_payments_table.php ⭐
    └── 2025_11_17_000006_add_type_to_stores_table.php ⭐
```

⭐ = New files to create
= Modified files

---

**Ready to Start?** Begin with Section 1, copying Sale.php to FcOrder.php and adapting it step-by-step.

**Questions?** Refer to the full analysis document: FC_HEADQUARTERS_SYSTEM_ANALYSIS.md
