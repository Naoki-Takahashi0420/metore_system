<x-filament-widgets::widget>
    <div x-data="{
        init() {
            // カレンダーの縦書き処理を実行
            const processCalendar = () => {
                document.querySelectorAll('.fc-event-title').forEach(titleEl => {
                    if (titleEl.getAttribute('data-vertical') === 'true') return;

                    const text = titleEl.textContent;
                    if (text && text.includes('\n')) {
                        const lines = text.split('\n');
                        let html = '';
                        lines.forEach((line, index) => {
                            if (line.trim()) {
                                if (index === 0) {
                                    html += '<div style=\'font-weight: bold; margin-bottom: 2px;\'>' + line + '</div>';
                                } else {
                                    html += '<div style=\'font-size: 0.9em; line-height: 1.2;\'>' + line + '</div>';
                                }
                            }
                        });
                        titleEl.innerHTML = html;
                        titleEl.setAttribute('data-vertical', 'true');
                    }
                });
            };

            // 複数回実行
            setTimeout(processCalendar, 100);
            setTimeout(processCalendar, 500);
            setTimeout(processCalendar, 1000);
            setTimeout(processCalendar, 2000);

            // クリックイベントでも実行
            document.addEventListener('click', () => {
                setTimeout(processCalendar, 100);
            });
        }
    }">
        <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <span>{{ $this->getHeading() }}</span>
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-xs text-gray-500">予約状況：</span>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #86efac; color: #166534;">🟢 空き(0-2)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #bef264; color: #365314;">🟢 余裕(3-4)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #fde047; color: #713f12;">🟡 普通(5-6)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #fb923c; color: #7c2d12;">🟠 混雑(7-8)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #f87171; color: #991b1b;">🔴 満員(9-10)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #dc2626; color: #ffffff;">🔥 超満員(11+)</span>
                    </div>
                </div>
            </div>
        </x-slot>

        <div>
            {!! $this->calendar() !!}
        </div>

        <style>
            /* カレンダーイベントのスタイル調整 */
            .fc-event {
                border: none !important;
                border-radius: 6px !important;
                padding: 2px 6px !important;
                font-weight: 600 !important;
                font-size: 13px !important;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
                transition: all 0.2s ease !important;
            }

            /* 顧客名イベントのスタイル */
            .fc-event-customer {
                background-color: transparent !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 6px !important;
                font-size: 11px !important;
                font-weight: normal !important;
                margin-top: -4px !important;
            }

            /* メインイベント（件数）のマージン調整 */
            .fc-event-main {
                margin-bottom: 2px !important;
            }

            /* イベントタイトルの改行を有効にする */
            .fc-event-title,
            .fc-event-main,
            .fc-event-title-container,
            .fc-daygrid-event-harness .fc-event {
                white-space: pre-line !important;
                line-height: 1.3 !important;
            }

            /* より強力な改行強制 */
            .fc-event-title.fc-sticky {
                white-space: pre-wrap !important;
                word-break: break-word !important;
                display: block !important;
            }

            .fc-daygrid-block-event .fc-event-title {
                white-space: pre-wrap !important;
                overflow: visible !important;
                text-overflow: initial !important;
            }

            .fc-event:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            }

            /* 予約数に応じたアニメーション */
            .reservation-heat-9,
            .reservation-heat-10 {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.8;
                }
            }

            /* カレンダーの日付セルのスタイル */
            .fc-daygrid-day-frame {
                min-height: 60px !important;
            }

            /* 今日の日付を強調 */
            .fc-day-today {
                background-color: rgba(59, 130, 246, 0.05) !important;
            }

            /* カレンダーヘッダーのスタイル */
            .fc-col-header-cell {
                background-color: #f9fafb;
                font-weight: 600;
            }
        </style>
    </x-filament::section>
    </div>

    <script>
        // カレンダーイベントのタイトルを縦書き表示に変換する関数
        function processCalendarTitles() {
            document.querySelectorAll('.fc-event-title').forEach(function(titleEl) {
                // すでに処理済みの場合はスキップ
                if (titleEl.getAttribute('data-processed') === 'true') {
                    return;
                }

                const text = titleEl.textContent;
                if (text && text.includes('\n')) {
                    // テキストを改行で分割
                    const lines = text.split('\n');

                    // 新しいHTML構造を作成
                    let html = '';
                    lines.forEach(function(line, index) {
                        if (index === 0) {
                            // 最初の行（件数）は太字
                            html += '<div style="font-weight: bold; margin-bottom: 2px;">' + line + '</div>';
                        } else if (line.trim() !== '') {
                            // その他の行（顧客名）
                            html += '<div style="font-size: 0.9em; line-height: 1.2;">' + line + '</div>';
                        }
                    });

                    // HTMLを設定
                    titleEl.innerHTML = html;
                    titleEl.setAttribute('data-processed', 'true');
                }
            });
        }

        // 複数のタイミングで実行を試みる
        document.addEventListener('DOMContentLoaded', function() {
            // 初回実行
            setTimeout(processCalendarTitles, 500);
            setTimeout(processCalendarTitles, 1000);
            setTimeout(processCalendarTitles, 2000);

            // MutationObserverでカレンダーの変更を監視
            const observer = new MutationObserver(function(mutations) {
                processCalendarTitles();
            });

            // カレンダーコンテナを監視
            setTimeout(function() {
                const calendarContainer = document.querySelector('.fc');
                if (calendarContainer) {
                    observer.observe(calendarContainer, {
                        childList: true,
                        subtree: true
                    });
                }
            }, 100);
        });

        // Livewireの更新時にも実行
        if (typeof Livewire !== 'undefined') {
            Livewire.hook('message.processed', (message, component) => {
                setTimeout(processCalendarTitles, 100);
            });
        }

        // Alpineの初期化後にも実行
        document.addEventListener('alpine:init', () => {
            setTimeout(processCalendarTitles, 100);
        });
    </script>
</x-filament-widgets::widget>