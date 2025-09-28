<x-filament-panels::page>
    @if ($this->hasInfolist())
        {{ $this->infolist }}
    @else
        <x-filament::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
        />
    @endif

    <x-filament-actions::modals />

    {{-- 予約変更完了通知モーダル --}}
    @if(session('reservation_updated'))
        <div x-data="{ open: true }" x-show="open" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="open = false"></div>

                <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">
                                予約変更完了
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-600 whitespace-pre-line">{{ session('success') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <a href="{{ route('filament.admin.resources.reservations.view', session('reservation_id')) }}"
                           class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm">
                            予約詳細を見る
                        </a>
                        <button type="button" @click="open = false"
                                class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-1 sm:mt-0 sm:text-sm">
                            閉じる
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // 5秒後に自動的にモーダルを閉じる
            setTimeout(function() {
                document.querySelector('[x-data]').__x.$data.open = false;
            }, 5000);
        </script>
    @endif

    {{-- 通常のフラッシュメッセージ（モーダル非表示の場合） --}}
    @if(session('success') && !session('reservation_updated'))
        <div x-data="{ show: true }" x-show="show"
             class="fixed top-4 right-4 z-50 max-w-sm p-4 bg-green-100 border border-green-400 rounded-lg shadow-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
                <div class="ml-auto">
                    <button @click="show = false" class="text-green-400 hover:text-green-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- リアルタイム予約更新音声通知システム --}}
    <script>
        // 予約件数の変化を監視するための変数
        let lastReservationCount = 0;
        let isInitialized = false;

        // シンプルなピッ音を再生する関数
        function playNotificationSound() {
            try {
                // Web Audio APIを使用してシンプルなビープ音を生成
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.setValueAtTime(800, audioContext.currentTime); // 800Hz
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.3);
            } catch (e) {
                // Web Audio API が使用できない場合のフォールバック
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqF');
                    audio.volume = 0.3;
                    audio.play().catch(() => {}); // エラーを無視
                } catch (fallbackError) {
                    // 音声再生に完全に失敗した場合も無視（サイレント）
                    console.log('音声通知をスキップしました');
                }
            }
        }

        // 予約件数を取得する関数
        function getCurrentReservationCount() {
            try {
                // TodayReservationsWidget内のテーブル行数をカウント
                const reservationRows = document.querySelectorAll('[wire\\:id*="today-reservations"] table tbody tr, .fi-wi-table table tbody tr');
                return reservationRows.length;
            } catch (e) {
                return 0;
            }
        }

        // 予約更新を監視する関数
        function checkForReservationUpdates() {
            const currentCount = getCurrentReservationCount();

            // 初回読み込み時はカウントを記録するだけ
            if (!isInitialized) {
                lastReservationCount = currentCount;
                isInitialized = true;
                console.log('予約監視システム初期化完了 - 現在の件数:', currentCount);
                return;
            }

            // 予約が増加した場合のみ通知音を再生
            if (currentCount > lastReservationCount) {
                const newReservations = currentCount - lastReservationCount;
                console.log(`新規予約検出: ${newReservations}件 (${lastReservationCount} → ${currentCount})`);
                playNotificationSound();

                // 複数件追加された場合は少し間隔を空けて再度通知
                if (newReservations > 1) {
                    setTimeout(() => {
                        playNotificationSound();
                    }, 500);
                }
            }

            lastReservationCount = currentCount;
        }

        // Livewire更新イベントを監視
        document.addEventListener('livewire:navigated', function() {
            console.log('Livewire navigated - 予約監視システム再初期化');
            isInitialized = false;
            setTimeout(checkForReservationUpdates, 1000);
        });

        // DOM更新を監視（MutationObserver）
        function initializeMutationObserver() {
            const observer = new MutationObserver(function(mutations) {
                let shouldCheck = false;

                mutations.forEach(function(mutation) {
                    // テーブル内容の変更を検出
                    if (mutation.type === 'childList' &&
                        (mutation.target.tagName === 'TBODY' ||
                         mutation.target.closest('table') ||
                         mutation.target.closest('[wire\\:id]'))) {
                        shouldCheck = true;
                    }
                });

                if (shouldCheck) {
                    // 少し遅延させてからチェック（DOM更新完了を待つ）
                    setTimeout(checkForReservationUpdates, 500);
                }
            });

            // ページ全体を監視
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        // Livewire準備完了後に初期化
        document.addEventListener('livewire:load', function() {
            console.log('Livewire loaded - 予約監視システム開始');
            setTimeout(() => {
                checkForReservationUpdates();
                initializeMutationObserver();
            }, 2000);
        });

        // ページ読み込み完了後のフォールバック初期化
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                if (!isInitialized) {
                    console.log('フォールバック初期化実行');
                    checkForReservationUpdates();
                    initializeMutationObserver();
                }
            }, 3000);
        });

        // 定期的なポーリングチェック（フォールバック）
        setInterval(checkForReservationUpdates, 35000); // 35秒間隔（ウィジェットのポーリングより少し長め）
    </script>
</x-filament-panels::page>