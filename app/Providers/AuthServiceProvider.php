<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Store;
use App\Models\Reservation;
use App\Models\FcInvoice;
use App\Models\FcOrder;
use App\Policies\UserPolicy;
use App\Policies\StorePolicy;
use App\Policies\ReservationPolicy;
use App\Policies\FcInvoicePolicy;
use App\Policies\FcOrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Store::class => StorePolicy::class,
        Reservation::class => ReservationPolicy::class,
        FcInvoice::class => FcInvoicePolicy::class,
        FcOrder::class => FcOrderPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}