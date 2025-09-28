<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Store;
use App\Observers\ReservationObserver;
use App\Policies\UserPolicy;
use App\Policies\StorePolicy;
use App\Policies\ReservationPolicy;
use App\Events\ReservationCreated;
use App\Events\ReservationCancelled;
use App\Events\ReservationChanged;
use App\Listeners\AdminNotificationListener;
use App\Listeners\SendCustomerReservationNotification;
use App\Listeners\SendCustomerReservationChangeNotification;

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
        
        // イベントリスナーを登録
        Event::listen(
            ReservationCreated::class,
            [AdminNotificationListener::class, 'handleReservationCreated']
        );
        
        Event::listen(
            ReservationCancelled::class,
            [AdminNotificationListener::class, 'handleReservationCancelled']
        );
        
        Event::listen(
            ReservationChanged::class,
            [AdminNotificationListener::class, 'handleReservationChanged']
        );
        
        // 顧客通知リスナーを登録
        Event::listen(
            ReservationCreated::class,
            SendCustomerReservationNotification::class
        );

        // 日程変更時の顧客通知リスナーを登録
        Event::listen(
            ReservationChanged::class,
            SendCustomerReservationChangeNotification::class
        );
    }
}
