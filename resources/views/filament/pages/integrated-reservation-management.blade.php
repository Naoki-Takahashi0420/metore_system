<x-filament-panels::page>
    <div class="space-y-6">
        <!-- 店舗選択 -->
        @if(count($this->getStores()) > 1)
        <div class="bg-white rounded-lg shadow p-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">店舗選択</label>
            <select wire:model.live="selectedStoreId" class="w-full md:w-64 rounded-lg border-gray-300">
                @foreach($this->getStores() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <!-- カレンダーセクション -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">予約カレンダー</h2>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="flex items-center gap-1">
                            <span class="w-4 h-4 rounded" style="background-color: #86efac;"></span>
                            空き
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-4 h-4 rounded" style="background-color: #fde047;"></span>
                            普通
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-4 h-4 rounded" style="background-color: #fb923c;"></span>
                            混雑
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-4 h-4 rounded" style="background-color: #dc2626;"></span>
                            満員
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <div id="reservation-calendar"></div>
            </div>
        </div>

        <!-- 選択日の予約詳細 -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold">
                    @if($selectedDate)
                        {{ \Carbon\Carbon::parse($selectedDate)->format('Y年m月d日') }}の予約詳細
                    @else
                        予約詳細
                    @endif
                </h2>
            </div>

            <div class="p-4">
                {{ $this->table }}
            </div>
        </div>
    </div>

    @push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('reservation-calendar');

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                height: 'auto',
                firstDay: 1, // 月曜始まり

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth'
                },

                buttonText: {
                    today: '今日',
                    month: '月',
                },

                // 日付クリック
                dateClick: function(info) {
                    // 選択日をハイライト
                    document.querySelectorAll('.fc-daygrid-day').forEach(el => {
                        el.classList.remove('selected-date');
                    });
                    info.dayEl.classList.add('selected-date');

                    // Livewireコンポーネントに通知
                    @this.call('selectDate', info.dateStr);
                },

                // イベント取得
                events: function(info, successCallback, failureCallback) {
                    const start = info.startStr;
                    const end = info.endStr;

                    // PHPメソッドを呼び出してイベントを取得
                    @this.getCalendarEvents(start, end).then(events => {
                        const fcEvents = events.map(event => ({
                            title: event.count + '件',
                            date: event.date,
                            backgroundColor: event.color,
                            borderColor: event.color,
                            textColor: event.count === 0 ? '#9ca3af' : '#000',
                        }));
                        successCallback(fcEvents);
                    });
                },

                // イベント表示設定
                displayEventTime: false,
                eventDisplay: 'block',

                // 今日の日付をハイライト
                dayCellDidMount: function(arg) {
                    if (arg.isToday) {
                        arg.el.style.backgroundColor = '#fef3c7';
                    }
                }
            });

            calendar.render();

            // 初期選択
            const today = new Date().toISOString().split('T')[0];
            const todayEl = document.querySelector(`[data-date="${today}"]`);
            if (todayEl) {
                todayEl.classList.add('selected-date');
            }

            // 店舗変更時にカレンダーを更新
            window.addEventListener('store-changed', function(e) {
                calendar.refetchEvents();
            });
        });
    </script>

    <style>
        /* カレンダーのスタイル */
        .fc-theme-standard th,
        .fc-theme-standard td {
            border-color: #e5e7eb;
        }

        .fc-daygrid-day:hover {
            background-color: #f3f4f6;
            cursor: pointer;
        }

        .fc-daygrid-day.selected-date {
            background-color: #dbeafe !important;
            border: 2px solid #3b82f6;
        }

        .fc-day-today {
            background-color: #fef3c7 !important;
        }

        .fc-event {
            cursor: pointer;
            padding: 2px 4px;
            font-weight: bold;
            border-radius: 4px;
        }

        .fc-daygrid-day-frame {
            min-height: 80px;
        }

        /* 土日の色 */
        .fc-day-sat {
            background-color: #f0f9ff;
        }

        .fc-day-sun {
            background-color: #fef2f2;
        }
    </style>
    @endpush
</x-filament-panels::page>