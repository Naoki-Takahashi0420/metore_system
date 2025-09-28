<?php

// Test script to verify menu filtering from medical records

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Store;

$storeId = 1;

echo "=== Testing Menu Filtering for Store $storeId ===\n\n";

// Test 1: New customer from regular booking
echo "1. NEW CUSTOMER (Regular Booking):\n";
echo "   Should see: all, new, new_only menus (NOT medical_record_only)\n";
$menus = Menu::where('store_id', $storeId)
    ->where('is_available', true)
    ->whereIn('customer_type_restriction', ['all', 'new', 'new_only'])
    ->where('medical_record_only', 0)
    ->get(['id', 'name', 'customer_type_restriction', 'medical_record_only']);
echo "   Found " . $menus->count() . " menus:\n";
foreach ($menus as $menu) {
    echo "   - {$menu->name} (type: {$menu->customer_type_restriction}, medical: {$menu->medical_record_only})\n";
}

echo "\n";

// Test 2: Existing customer from regular booking
echo "2. EXISTING CUSTOMER (Regular Booking):\n";
echo "   Should see: all, existing menus (NOT medical_record_only)\n";
$menus = Menu::where('store_id', $storeId)
    ->where('is_available', true)
    ->whereIn('customer_type_restriction', ['all', 'existing'])
    ->where('medical_record_only', 0)
    ->get(['id', 'name', 'customer_type_restriction', 'medical_record_only']);
echo "   Found " . $menus->count() . " menus:\n";
foreach ($menus as $menu) {
    echo "   - {$menu->name} (type: {$menu->customer_type_restriction}, medical: {$menu->medical_record_only})\n";
}

echo "\n";

// Test 3: From medical record (カルテから)
echo "3. FROM MEDICAL RECORD (カルテから):\n";
echo "   Should see: all, existing menus (INCLUDING medical_record_only)\n";
echo "   Should NOT see: new, new_only menus\n";
$menus = Menu::where('store_id', $storeId)
    ->where('is_available', true)
    ->whereIn('customer_type_restriction', ['all', 'existing'])
    // No filter on medical_record_only when from medical record
    ->get(['id', 'name', 'customer_type_restriction', 'medical_record_only']);
echo "   Found " . $menus->count() . " menus:\n";
foreach ($menus as $menu) {
    $badge = $menu->medical_record_only ? ' [MEDICAL RECORD ONLY]' : '';
    echo "   - {$menu->name} (type: {$menu->customer_type_restriction}, medical: {$menu->medical_record_only}){$badge}\n";
}

echo "\n";

// Test 4: Verify specific problem menus
echo "4. VERIFYING SPECIFIC MENUS:\n";
$problemMenus = [
    '新規予約のみ' => ['expected' => 'Should NOT show from medical records', 'type' => 'new_only'],
    'カルテからの予約のみ' => ['expected' => 'Should ONLY show from medical records', 'type' => 'existing', 'medical' => true],
];

foreach ($problemMenus as $menuName => $expected) {
    $menu = Menu::where('name', 'LIKE', "%{$menuName}%")->first();
    if ($menu) {
        echo "   - {$menu->name}:\n";
        echo "     customer_type: {$menu->customer_type_restriction}\n";
        echo "     medical_record_only: " . ($menu->medical_record_only ? 'YES' : 'NO') . "\n";
        echo "     Expected: {$expected['expected']}\n";

        // Check if it would show from medical record
        $wouldShowFromMedical = in_array($menu->customer_type_restriction, ['all', 'existing']);
        echo "     Would show from medical record: " . ($wouldShowFromMedical ? 'YES' : 'NO') . "\n";

        // Check if it would show from regular booking (new customer)
        $wouldShowForNew = in_array($menu->customer_type_restriction, ['all', 'new', 'new_only']) && !$menu->medical_record_only;
        echo "     Would show for new customer: " . ($wouldShowForNew ? 'YES' : 'NO') . "\n";

        echo "\n";
    }
}