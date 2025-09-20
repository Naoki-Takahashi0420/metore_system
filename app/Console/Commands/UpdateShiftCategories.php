<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateShiftCategories extends Command
{
    protected $signature = 'shifts:update-categories';
    protected $description = 'Update existing shifts with category assignments for color coding';

    public function handle()
    {
        $this->info('Updating shift categories...');

        $shifts = DB::table('shifts')
            ->whereNull('category')
            ->orWhere('category', 0)
            ->get();

        if ($shifts->isEmpty()) {
            $this->info('No shifts need updating.');
            return;
        }

        $staffCategories = [];
        $categoryIndex = 1;

        foreach($shifts as $shift) {
            $staffKey = $shift->staff_name ?? 'default';

            if(!isset($staffCategories[$staffKey])) {
                $staffCategories[$staffKey] = $categoryIndex;
                $this->info("Assigning category {$categoryIndex} to staff: {$staffKey}");
                $categoryIndex++;
                if($categoryIndex > 10) {
                    $categoryIndex = 1;
                }
            }

            DB::table('shifts')
                ->where('id', $shift->id)
                ->update(['category' => $staffCategories[$staffKey]]);
        }

        $this->info("Updated {$shifts->count()} shifts with categories");
        $this->info("Staff category assignments:");
        foreach($staffCategories as $staff => $category) {
            $this->info("  - {$staff}: Category {$category}");
        }
    }
}