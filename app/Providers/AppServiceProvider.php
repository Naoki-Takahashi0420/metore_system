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
use App\Listeners\SendCustomerReservationCancellationNotification;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

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
        // タイムゾーンをAsia/Tokyoに統一（SQLiteはDB接続レベルでTZ設定不可）
        date_default_timezone_set('Asia/Tokyo');
        \Carbon\Carbon::setTestNow(null);

        // デバッグ用: 予約INSERT時のログ（一時的）
        \DB::listen(function ($query) {
            if (str_contains($query->sql, 'reservations') && str_contains($query->sql, 'INSERT')) {
                \Log::debug('📅 DB INSERT (reservations)', [
                    'sql' => substr($query->sql, 0, 200),
                    'bindings_count' => count($query->bindings),
                    'time' => $query->time . 'ms'
                ]);
            }
        });

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

        // キャンセル時の顧客通知リスナーを登録
        Event::listen(
            ReservationCancelled::class,
            SendCustomerReservationCancellationNotification::class
        );

        // Filament: 未保存変更の警告を追加
        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => Blade::render(<<<'HTML'
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        console.log('🔍 Unsaved changes script loaded');
                        console.log('📍 Current path:', window.location.pathname);

                        // 作成・編集ページでのみ動作
                        const isFormPage = window.location.pathname.includes('/create') ||
                                         window.location.pathname.includes('/edit');

                        console.log('📝 Is form page?', isFormPage);

                        if (!isFormPage) return;

                        let formSubmitted = false;
                        let navigationAllowed = false;

                        // フォームの初期値を保存
                        let initialFormState = null;

                        function captureFormState() {
                            const state = {};
                            const inputs = document.querySelectorAll('input, textarea, select');
                            console.log('📸 Capturing form state - Found inputs:', inputs.length);

                            inputs.forEach((input, index) => {
                                const id = input.id || input.name || `input-${index}`;
                                if (input.type === 'checkbox' || input.type === 'radio') {
                                    state[id] = input.checked;
                                } else {
                                    state[id] = input.value;
                                }
                            });

                            console.log('💾 Captured state:', Object.keys(state).length, 'fields');
                            return state;
                        }

                        function hasFormChanged() {
                            if (!initialFormState) return false;

                            const currentState = captureFormState();

                            // 初期状態と現在の状態を比較
                            for (const key in currentState) {
                                if (currentState[key] !== initialFormState[key]) {
                                    console.log('🔄 Change detected in:', key);
                                    console.log('  - Initial:', initialFormState[key]);
                                    console.log('  - Current:', currentState[key]);
                                    return true;
                                }
                            }

                            return false;
                        }

                        // ページロード直後に初期状態を保存（少し遅延させる）
                        setTimeout(function() {
                            initialFormState = captureFormState();
                            console.log('✅ Initial state captured');
                        }, 1000);

                        // フォーム送信を検知
                        const form = document.querySelector('form');
                        if (form) {
                            form.addEventListener('submit', function() {
                                formSubmitted = true;
                                navigationAllowed = true;
                                console.log('💾 Form submitted');
                            });
                        }

                        // Filamentの保存ボタンをクリックした時も検知
                        document.addEventListener('click', function(e) {
                            const target = e.target;
                            if (target.matches('button[type="submit"]') ||
                                target.closest('button[type="submit"]')) {
                                formSubmitted = true;
                                navigationAllowed = true;
                                console.log('💾 Save button clicked');
                            }
                        });

                        // ブラウザバック対策：ダミーの履歴を追加
                        history.pushState(null, '', location.href);
                        console.log('📚 Dummy history added');

                        // ブラウザバック検知
                        window.addEventListener('popstate', function(e) {
                            console.log('⬅️ Popstate event fired');

                            const changed = hasFormChanged();
                            console.log('  - formChanged:', changed);
                            console.log('  - formSubmitted:', formSubmitted);
                            console.log('  - navigationAllowed:', navigationAllowed);

                            if (changed && !formSubmitted && !navigationAllowed) {
                                // 確認ダイアログを表示
                                const confirmLeave = confirm('入力内容が保存されていません。このページを離れてもよろしいですか？');
                                console.log('🚨 User choice:', confirmLeave ? 'OK' : 'Cancel');

                                if (!confirmLeave) {
                                    // キャンセルの場合、再度履歴を追加して元の位置に戻る
                                    history.pushState(null, '', location.href);
                                    console.log('🔙 Stayed on page');
                                } else {
                                    // OKの場合、移動を許可
                                    navigationAllowed = true;
                                    history.back();
                                    console.log('👋 Leaving page');
                                }
                            } else {
                                console.log('✅ Navigation allowed without prompt');
                            }
                        });

                        // タブを閉じる時の警告
                        window.addEventListener('beforeunload', function(e) {
                            const changed = hasFormChanged();
                            if (changed && !formSubmitted && !navigationAllowed) {
                                e.preventDefault();
                                e.returnValue = '';
                                console.log('⚠️ Beforeunload triggered');
                                return '';
                            }
                        });
                    });
                </script>
            HTML)
        );
    }
}
