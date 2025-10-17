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

                    // グローバルスコープに公開（カレンダー月変更時に呼び出せるようにする）
                    window.setupCalendarClicks = function() {
                        const dayNumbers = document.querySelectorAll("a.fc-daygrid-day-number");
                        console.log("🔍 setupCalendarClicks called - Found " + dayNumbers.length + " day numbers");

                        let setupCount = 0;
                        dayNumbers.forEach(function(dayNumber, index) {
                            if (dayNumber.dataset.clickSetup) {
                                console.log("⏭️ Skipping already setup day number at index " + index);
                                return;
                            }

                            const td = dayNumber.closest("td[data-date]");
                            const date = td ? td.getAttribute("data-date") : "no-date";
                            console.log("✅ Setting up click for date: " + date + " (index: " + index + ")");

                            dayNumber.style.cursor = "pointer";

                            dayNumber.addEventListener("click", function(e) {
                                e.preventDefault();
                                e.stopPropagation();

                                const td = this.closest("td[data-date]");
                                if (td) {
                                    const date = td.getAttribute("data-date");
                                    console.log("📅 Date clicked: " + date);

                                    if (window.Livewire) {
                                        console.log("🚀 Dispatching calendar-date-clicked event");
                                        window.Livewire.dispatch("calendar-date-clicked", { date: date });
                                    } else {
                                        console.error("❌ Livewire not found!");
                                    }
                                }
                            });

                            dayNumber.dataset.clickSetup = "true";
                            setupCount++;
                        });
                        console.log("✅ Setup complete - " + setupCount + " day numbers configured");
                    };

                    // 3秒後に実行
                    setTimeout(window.setupCalendarClicks, 3000);

                    // ページ変更時にも実行
                    document.addEventListener("livewire:navigated", function() {
                        setTimeout(window.setupCalendarClicks, 3000);
                    });

                    // FullCalendarの月切り替えボタンをクリックした時に再実行
                    document.addEventListener("click", function(e) {
                        // prev, next, todayボタンのクリックを検知
                        if (e.target.closest(".fc-prev-button, .fc-next-button, .fc-today-button")) {
                            console.log("📅 Calendar navigation button clicked - re-setting up click handlers");
                            setTimeout(window.setupCalendarClicks, 500);
                        }
                    });

                    // タイムラインインジケーター位置更新
                    console.log("⏰ Timeline indicator script loading...");

                    window.updateIndicatorPosition = function() {
                        console.log("updateIndicatorPosition 開始");

                        const indicator = document.getElementById("current-time-indicator");
                        if (!indicator) {
                            console.log("⚠️ インジケーターが存在しません");
                            return;
                        }

                        const table = document.querySelector(".timeline-table");
                        if (!table) {
                            console.log("⚠️ テーブルが存在しません");
                            return;
                        }

                        const firstRow = table.querySelector("tbody tr");
                        if (!firstRow) {
                            console.log("⚠️ 行が存在しません");
                            return;
                        }

                        const cells = firstRow.querySelectorAll("td");
                        if (cells.length < 2) {
                            console.log("⚠️ セルが不足しています");
                            return;
                        }

                        const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                        const jstDate = new Date(now);
                        const currentHour = jstDate.getHours();
                        const currentMinute = jstDate.getMinutes();

                        const timelineStartHour = parseInt(indicator.dataset.timelineStart || "10");
                        const slotDuration = parseInt(indicator.dataset.slotDuration || "30");

                        const firstCellWidth = cells[0].offsetWidth;
                        const cellWidth = cells[1].offsetWidth;

                        console.log("📊 セル幅実測: 1列目=" + firstCellWidth + "px, 2列目=" + cellWidth + "px");

                        if (firstCellWidth === 0 || cellWidth === 0) {
                            console.log("⚠️ セル幅が0です。500ms後に再試行します");
                            setTimeout(window.updateIndicatorPosition, 500);
                            return;
                        }

                        const minutesFromStart = (currentHour - timelineStartHour) * 60 + currentMinute;
                        const cellIndex = Math.floor(minutesFromStart / slotDuration);
                        const percentageIntoCell = (minutesFromStart % slotDuration) / slotDuration;
                        const leftPosition = firstCellWidth + (cellIndex * cellWidth) + (percentageIntoCell * cellWidth);

                        indicator.style.left = leftPosition + "px";
                        indicator.style.visibility = "visible";
                        indicator.style.opacity = "1";

                        console.log("✅ インジケーター位置更新: " + leftPosition.toFixed(1) + "px (" + currentHour + ":" + String(currentMinute).padStart(2, "0") + ")");
                        console.log("   計算式: " + firstCellWidth + " + (" + cellIndex + " × " + cellWidth + ") + (" + (percentageIntoCell * 100).toFixed(1) + "% × " + cellWidth + ")");

                        const timeText = indicator.querySelector(".current-time-text");
                        if (timeText) {
                            timeText.textContent = currentHour.toString().padStart(2, "0") + ":" + currentMinute.toString().padStart(2, "0");
                        }
                    };

                    setTimeout(function() {
                        console.log("⏰ 3秒後の自動実行開始");
                        window.updateIndicatorPosition();
                    }, 3000);

                    setInterval(window.updateIndicatorPosition, 60000);
                </script>',
            )
            ->renderHook(
                'panels::footer',
                fn () => view('components.version-footer')
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('components.help-button')
            )
            ->theme(asset('css/filament/admin/theme.css'));
    }
}
