<?php

// 本番環境でシフトのカテゴリーを更新するスクリプト
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Updating shift categories...\n";

$shifts = DB::table('shifts')
    ->whereNull('category')
    ->orWhere('category', 0)
    ->get();

$staffCategories = [];
$categoryIndex = 1;

foreach($shifts as $shift) {
    $staffKey = $shift->staff_name ?? 'default';

    if(!isset($staffCategories[$staffKey])) {
        $staffCategories[$staffKey] = $categoryIndex;
        echo "Assigning category {$categoryIndex} to staff: {$staffKey}\n";
        $categoryIndex++;
        if($categoryIndex > 10) {
            $categoryIndex = 1;
        }
    }

    DB::table('shifts')
        ->where('id', $shift->id)
        ->update(['category' => $staffCategories[$staffKey]]);
}

echo "Updated " . $shifts->count() . " shifts with categories\n";
echo "Staff category assignments:\n";
foreach($staffCategories as $staff => $category) {
    echo "  - {$staff}: Category {$category}\n";
}