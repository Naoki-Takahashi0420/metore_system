<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Store;
use App\Observers\ReservationObserver;
use App\Policies\UserPolicy;
use App\Policies\StorePolicy;
use App\Policies\ReservationPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Reservation::observe(ReservationObserver::class);
        
        // ポリシーを登録
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
    }
}
