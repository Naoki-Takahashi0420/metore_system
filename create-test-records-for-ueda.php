<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\MedicalRecord;
use App\Models\User;
use Carbon\Carbon;

$customer = Customer::where('last_name', 'うえだ')
    ->where('first_name', 'あつこ')
    ->first();

if (!$customer) {
    echo "Customer not found\n";
    exit(1);
}

// Get first user as staff
$staff = User::first();
if (!$staff) {
    echo "No users found\n";
    exit(1);
}

echo "Creating test medical records for {$customer->last_name} {$customer->first_name} (ID: {$customer->id})\n";

// Create multiple records with different dates
$dates = [
    Carbon::now()->subDays(30),
    Carbon::now()->subDays(20),
    Carbon::now()->subDays(10),
    Carbon::now()->subDays(5),
    Carbon::now(),
];

$visionData = [
    [ // 30 days ago
        'before_naked_left' => 0.3,
        'after_naked_left' => 0.5,
        'before_naked_right' => 0.4,
        'after_naked_right' => 0.6,
    ],
    [ // 20 days ago
        'before_naked_left' => 0.5,
        'after_naked_left' => 0.7,
        'before_naked_right' => 0.6,
        'after_naked_right' => 0.8,
    ],
    [ // 10 days ago
        'before_naked_left' => 0.7,
        'after_naked_left' => 0.9,
        'before_naked_right' => 0.8,
        'after_naked_right' => 1.0,
    ],
    [ // 5 days ago
        'before_naked_left' => 0.9,
        'after_naked_left' => 1.0,
        'before_naked_right' => 1.0,
        'after_naked_right' => 1.2,
    ],
    [ // today
        'before_naked_left' => 1.0,
        'after_naked_left' => 1.2,
        'before_naked_right' => 1.2,
        'after_naked_right' => 1.5,
    ],
];

foreach ($dates as $index => $date) {
    $record = MedicalRecord::create([
        'customer_id' => $customer->id,
        'staff_id' => $staff->id,
        'treatment_date' => $date->format('Y-m-d'),
        'chief_complaint' => 'テストデータ',
        'vision_records' => json_encode([$visionData[$index]]),
    ]);
    
    echo "Created record for {$date->format('Y-m-d')}\n";
}

echo "✅ Created " . count($dates) . " test medical records\n";