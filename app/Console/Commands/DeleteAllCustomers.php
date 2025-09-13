<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Reservation;

class DeleteAllCustomers extends Command
{
    protected $signature = 'customer:delete-all';
    protected $description = 'Delete all customers and their related reservations';

    public function handle()
    {
        $customerCount = Customer::count();
        $reservationCount = Reservation::count();
        
        if (!$this->confirm("This will delete {$customerCount} customers and {$reservationCount} reservations. Are you sure?")) {
            $this->info('Operation cancelled.');
            return;
        }

        // Delete reservations first due to foreign key constraints
        $deletedReservations = Reservation::query()->delete();
        
        // Then delete customers
        $deletedCustomers = Customer::query()->delete();
        
        $this->info("Deleted {$deletedCustomers} customers and {$deletedReservations} reservations.");
    }
}