<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('目のトレーニング 管理画面')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Blue,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->resources([
                // 不要なリソースを除外
                // \App\Filament\Resources\ReservationLineResource::class,
                // \App\Filament\Resources\CustomerSubscriptionResource::class,
                // \App\Filament\Resources\CustomerAccessTokenResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\MenuManager::class,
                \App\Filament\Pages\ShiftManagement::class,
                \App\Filament\Pages\ImportCustomers::class,
            ])
            ->widgets([
                \App\Filament\Widgets\ReservationTimelineWidget::class,
                \App\Filament\Widgets\PaymentFailedAlertWidget::class,
                \App\Filament\Widgets\ReservationCalendarWidget::class,
                \App\Filament\Widgets\TodayReservationsWidget::class,
                \App\Filament\Widgets\ShiftManagementLinkWidget::class,
                \App\Filament\Widgets\SubscriptionStatsWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentFullCalendarPlugin::make(),
            ])
            ->renderHook(
                'panels::body.end',
                fn () => '<script>
                    console.log("Calendar click handler loading...");

                    function setupCalendarClicks() {
                        const dayNumbers = document.querySelectorAll("a.fc-daygrid-day-number");
                        console.log("Found " + dayNumbers.length + " day numbers");

                        dayNumbers.forEach(function(dayNumber) {
                            if (dayNumber.dataset.clickSetup) return;

                            dayNumber.style.cursor = "pointer";

                            dayNumber.addEventListener("click", function(e) {
                                e.preventDefault();
                                e.stopPropagation();

                                const td = this.closest("td[data-date]");
                                if (td) {
                                    const date = td.getAttribute("data-date");
                                    console.log("Date clicked: " + date);

                                    if (window.Livewire) {
                                        window.Livewire.dispatch("calendar-date-clicked", { date: date });
                                    }
                                }
                            });

                            dayNumber.dataset.clickSetup = "true";
                        });
                    }

                    // 3秒後に実行
                    setTimeout(setupCalendarClicks, 3000);

                    // ページ変更時にも実行
                    document.addEventListener("livewire:navigated", function() {
                        setTimeout(setupCalendarClicks, 3000);
                    });
                </script>',
            )
            ->renderHook(
                'panels::footer',
                fn () => view('components.version-footer')
            )
            ->theme(asset('css/filament/admin/theme.css'));
    }
}
