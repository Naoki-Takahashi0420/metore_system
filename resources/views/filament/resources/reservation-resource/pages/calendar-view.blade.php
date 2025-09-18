<x-filament-panels::page>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="bg-white rounded-lg shadow p-4">
        <div id="calendar" class="fc-calendar"></div>
    </div>

    <!-- ツールチップ用のdiv -->
    <div id="event-tooltip" class="hidden absolute z-50 bg-gray-900 text-white p-3 rounded-lg shadow-lg text-sm max-w-xs"></div>

    @push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/locales/ja.global.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var tooltip = document.getElementById('event-tooltip');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'ja',
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: '今日',
                    month: '月',
                    week: '週',
                    day: '日',
                    list: 'リスト'
                },
                slotMinTime: '09:00:00',
                slotMaxTime: '22:00:00',
                slotDuration: '00:30:00',
                allDaySlot: false,
                weekends: true,
                editable: true,
                droppable: true,
                nowIndicator: true,
                eventMaxStack: 3,
                dayMaxEvents: true,
                events: @json($this->getReservations()),
                eventClick: function(info) {
                    // 編集ページへ遷移
                    info.jsEvent.preventDefault();
                    window.location.href = info.event.url;
                },
                eventDrop: function(info) {
                    // ドラッグ&ドロップで日程変更
                    var newStart = info.event.start;
                    var newEnd = info.event.end || new Date(newStart.getTime() + (60 * 60 * 1000)); // 終了時間がない場合は1時間後

                    var confirmMessage = info.event.extendedProps.customer + '様の予約を\n' +
                                       newStart.toLocaleDateString('ja-JP') + ' ' +
                                       newStart.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) + ' - ' +
                                       newEnd.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) +
                                       '\nに変更しますか？';

                    if (confirm(confirmMessage)) {
                        // 更新中の表示
                        info.el.style.opacity = '0.5';

                        // Ajaxで更新
                        fetch('/admin/reservations/update-datetime', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                id: info.event.id,
                                date: newStart.toISOString().split('T')[0],
                                start_time: newStart.toTimeString().substring(0, 5),
                                end_time: newEnd.toTimeString().substring(0, 5)
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            info.el.style.opacity = '1';
                            if (data.success) {
                                // 成功メッセージを表示（Filamentの通知システムを使う）
                                new Notification('成功', {
                                    body: data.message,
                                    icon: 'heroicon-o-check-circle'
                                });
                            } else {
                                alert('日程変更に失敗しました: ' + data.message);
                                info.revert();
                            }
                        })
                        .catch((error) => {
                            info.el.style.opacity = '1';
                            console.error('Error:', error);
                            alert('エラーが発生しました');
                            info.revert();
                        });
                    } else {
                        info.revert();
                    }
                },
                eventMouseEnter: function(info) {
                    // ホバー時に詳細情報表示
                    var content = '<div class="font-semibold mb-2">' + info.event.title + '</div>' +
                        '<div class="space-y-1">' +
                        '<div><span class="font-medium">顧客:</span> ' + info.event.extendedProps.customer + '</div>' +
                        '<div><span class="font-medium">電話:</span> ' + info.event.extendedProps.phone + '</div>' +
                        '<div><span class="font-medium">メニュー:</span> ' + info.event.extendedProps.menu + '</div>' +
                        '<div><span class="font-medium">担当:</span> ' + info.event.extendedProps.staff + '</div>' +
                        '<div><span class="font-medium">店舗:</span> ' + info.event.extendedProps.store + '</div>' +
                        '<div><span class="font-medium">状態:</span> <span class="' + getStatusClass(info.event.extendedProps.status) + '">' + getStatusLabel(info.event.extendedProps.status) + '</span></div>' +
                        '</div>';

                    tooltip.innerHTML = content;
                    tooltip.classList.remove('hidden');

                    // ツールチップの位置を設定
                    var rect = info.el.getBoundingClientRect();
                    tooltip.style.left = (rect.left + window.scrollX) + 'px';
                    tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
                },
                eventMouseLeave: function(info) {
                    // ツールチップを隠す
                    tooltip.classList.add('hidden');
                },
                eventResize: function(info) {
                    // リサイズ時も日程変更と同じ処理
                    var newStart = info.event.start;
                    var newEnd = info.event.end;

                    var confirmMessage = info.event.extendedProps.customer + '様の予約時間を\n' +
                                       newStart.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) + ' - ' +
                                       newEnd.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) +
                                       '\nに変更しますか？';

                    if (confirm(confirmMessage)) {
                        fetch('/admin/reservations/update-datetime', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                id: info.event.id,
                                date: newStart.toISOString().split('T')[0],
                                start_time: newStart.toTimeString().substring(0, 5),
                                end_time: newEnd.toTimeString().substring(0, 5)
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                alert('時間変更に失敗しました: ' + data.message);
                                info.revert();
                            }
                        })
                        .catch(() => {
                            alert('エラーが発生しました');
                            info.revert();
                        });
                    } else {
                        info.revert();
                    }
                }
            });

            calendar.render();

            // ステータスのラベルを取得
            function getStatusLabel(status) {
                switch(status) {
                    case 'booked': return '予約済';
                    case 'completed': return '完了';
                    case 'no_show': return '無断欠席';
                    case 'arrived': return '来店済';
                    case 'cancelled':
                    case 'canceled': return 'キャンセル';
                    default: return status;
                }
            }

            // ステータスのクラスを取得
            function getStatusClass(status) {
                switch(status) {
                    case 'booked': return 'text-yellow-300';
                    case 'completed': return 'text-green-300';
                    case 'no_show': return 'text-red-300';
                    case 'arrived': return 'text-blue-300';
                    default: return 'text-gray-300';
                }
            }

            // Livewireイベントリスナー（店舗フィルター変更時）
            if (window.Livewire) {
                Livewire.on('refreshCalendar', () => {
                    calendar.refetchEvents();
                });
            }
        });
    </script>
    @endpush

    <style>
        .fc-calendar {
            max-width: 100%;
            margin: 0 auto;
        }
        .fc-event {
            cursor: move;
            border: none;
            padding: 2px 4px;
            font-size: 0.875rem;
        }
        .fc-event:hover {
            opacity: 0.85;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .fc-event-title {
            font-weight: 600;
        }
        .fc-daygrid-event {
            white-space: normal;
        }
        .fc-time-grid-event .fc-event-title {
            white-space: normal;
        }
        /* 今日の日付を強調 */
        .fc-day-today {
            background-color: rgba(59, 130, 246, 0.05) !important;
        }
        /* ドラッグ中のイベント */
        .fc-event-dragging {
            opacity: 0.75;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.3);
        }
        /* 現在時刻のインジケーター */
        .fc-timegrid-now-indicator-line {
            border-color: #ef4444;
            border-width: 2px;
        }
    </style>
</x-filament-panels::page>