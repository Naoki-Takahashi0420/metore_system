<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約日程変更 - 管理画面</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .availability-table td {
            width: 14.28%;
            text-align: center;
            vertical-align: middle;
        }
        .time-slot {
            cursor: pointer;
            transition: all 0.2s;
        }
        .time-slot:hover:not(.unavailable) {
            transform: scale(1.1);
        }
        .unavailable {
            cursor: not-allowed;
            opacity: 0.5;
        }
        .selected {
            background-color: #3b82f6 !important;
            color: white !important;
        }
        .current-reservation {
            background-color: #fbbf24 !important;
            color: white !important;
            position: relative;
        }
        .current-reservation::after {
            content: '現在';
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- ヘッダー -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">予約日程変更</h1>
                    <p class="text-gray-600">顧客: {{ $reservation->customer->last_name }} {{ $reservation->customer->first_name }} 様</p>
                    <p class="text-sm text-gray-500">予約番号: {{ $reservation->reservation_number }}</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="/admin/reservations" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        戻る
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.reservations.reschedule.update', $reservation) }}" method="POST" id="reschedule-form">
            @csrf

            <!-- 現在の予約情報 -->
            <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-yellow-800 mb-3 flex items-center">
                    <span class="bg-yellow-500 text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-xs mr-2">現</span>
                    現在の予約情報
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-yellow-700 font-medium">店舗:</span>
                        <span class="font-bold text-yellow-900">{{ $reservation->store->name }}</span>
                    </div>
                    <div>
                        <span class="text-yellow-700 font-medium">メニュー:</span>
                        <span class="font-bold text-yellow-900">{{ $reservation->menu->name }}</span>
                    </div>
                    <div>
                        <span class="text-yellow-700 font-medium">日時:</span>
                        <span class="font-bold text-yellow-900 text-lg">
                            📅 {{ $reservation->reservation_date->format('n月j日') }}（{{ ['日', '月', '火', '水', '木', '金', '土'][$reservation->reservation_date->dayOfWeek] }}）
                            ⏰ {{ substr($reservation->start_time, 0, 5) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-yellow-700 font-medium">料金:</span>
                        <span class="font-bold text-yellow-900">¥{{ number_format($reservation->total_amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- 店舗・メニュー情報（変更不可） -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">予約内容（変更不可）</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- 店舗表示 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">店舗</label>
                        <div class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-gray-700">
                            {{ $reservation->store->name }}
                        </div>
                    </div>

                    <!-- メニュー表示 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">メニュー</label>
                        <div class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-gray-700">
                            {{ $reservation->menu->name }} ({{ $reservation->menu->duration }}分)
                        </div>
                    </div>
                </div>
            </div>

            <!-- 週ナビゲーション -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="flex items-center justify-between">
                    <button type="button" onclick="changeWeek(-1)"
                            class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg"
                            {{ $weekOffset <= 0 ? 'disabled' : '' }}>
                        ← 前週
                    </button>
                    <h3 class="text-lg font-semibold">
                        {{ $dates[0]['date']->format('Y年n月') }}
                    </h3>
                    <button type="button" onclick="changeWeek(1)"
                            class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg"
                            {{ $weekOffset >= $maxWeeks - 1 ? 'disabled' : '' }}>
                        次週 →
                    </button>
                </div>
            </div>

            <!-- カレンダー -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                <div class="p-4 border-b">
                    <h3 class="text-lg font-semibold">空き状況カレンダー</h3>
                    <div class="flex items-center space-x-4 mt-2 text-sm">
                        <div class="flex items-center">
                            <span class="w-6 h-6 bg-yellow-500 text-white rounded-full flex items-center justify-center text-xs mr-2 font-bold">現</span>
                            <span class="font-medium">現在の予約</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs mr-2">○</span>
                            <span>予約可能</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-6 h-6 bg-gray-400 text-white rounded-full flex items-center justify-center text-xs mr-2">×</span>
                            <span>予約不可</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full availability-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-3 px-2 text-xs font-medium text-gray-700 sticky left-0 bg-gray-50">時間</th>
                                @foreach($dates as $date)
                                    <th class="py-3 px-2 text-center {{ $date['is_today'] ? 'bg-blue-50' : '' }}">
                                        <div class="text-xs {{ $date['date']->dayOfWeek == 0 ? 'text-red-500' : ($date['date']->dayOfWeek == 6 ? 'text-blue-500' : 'text-gray-500') }}">
                                            {{ $date['day'] }}
                                        </div>
                                        <div class="text-sm font-bold {{ $date['date']->dayOfWeek == 0 ? 'text-red-500' : ($date['date']->dayOfWeek == 6 ? 'text-blue-500' : 'text-gray-700') }}">
                                            {{ $date['formatted'] }}
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($timeSlots as $slot)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="py-2 px-2 text-xs font-medium text-gray-700 bg-gray-50 sticky left-0">
                                        {{ $slot }}
                                    </td>
                                    @foreach($dates as $date)
                                        @php
                                            $dateStr = $date['date']->format('Y-m-d');
                                            $isAvailable = $availability[$dateStr][$slot] ?? false;
                                            $isCurrentReservation =
                                                $reservation->reservation_date->format('Y-m-d') == $dateStr &&
                                                substr($reservation->start_time, 0, 5) == $slot;
                                        @endphp
                                        <td class="py-2 px-2 {{ $date['is_today'] ? 'bg-blue-50' : '' }} {{ $isCurrentReservation ? 'relative' : '' }}">
                                            @if($isCurrentReservation)
                                                <button type="button"
                                                        class="time-slot w-8 h-8 current-reservation rounded-full text-xs font-bold relative"
                                                        style="background-color: #fbbf24 !important;"
                                                        onclick="selectTimeSlot('{{ $dateStr }}', '{{ $slot }}')">
                                                    現
                                                </button>
                                            @elseif($isAvailable && !$date['is_past'])
                                                <button type="button"
                                                        class="time-slot w-8 h-8 bg-green-500 text-white rounded-full text-xs font-bold hover:bg-green-600"
                                                        onclick="selectTimeSlot('{{ $dateStr }}', '{{ $slot }}')">
                                                    ○
                                                </button>
                                            @else
                                                <span class="unavailable text-gray-400 text-lg">×</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 選択された日時表示 -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">選択された日時 <span class="text-red-500">*</span></h3>
                <div id="selected-info" class="text-gray-500">
                    カレンダーから日時を選択してください
                </div>
                <input type="hidden" name="reservation_date" id="selected-date" required>
                <input type="hidden" name="start_time" id="selected-time" required>
                <input type="hidden" name="staff_id" value="{{ $reservation->staff_id }}">
                @if($errors->has('reservation_date'))
                    <p class="text-red-500 text-xs mt-1">{{ $errors->first('reservation_date') }}</p>
                @endif
                @if($errors->has('start_time'))
                    <p class="text-red-500 text-xs mt-1">{{ $errors->first('start_time') }}</p>
                @endif
            </div>

            <!-- 送信ボタン -->
            <div class="flex justify-end space-x-3">
                <a href="/admin/reservations" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors">
                    キャンセル
                </a>
                <button type="submit" id="submit-btn" class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-colors disabled:bg-gray-300" disabled>
                    予約を変更
                </button>
            </div>
        </form>
    </div>

    @if($errors->any())
        <div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <script>
        let selectedDate = null;
        let selectedTime = null;

        function selectTimeSlot(date, time) {
            // 既に選択されているスロットの選択解除
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });

            // 新しいスロットを選択
            event.target.classList.add('selected');

            selectedDate = date;
            selectedTime = time;

            // 隠しフィールドに値を設定
            document.getElementById('selected-date').value = date;
            document.getElementById('selected-time').value = time;

            // 選択情報を表示
            const dateObj = new Date(date);
            const dateStr = dateObj.getMonth() + 1 + '月' + dateObj.getDate() + '日';

            document.getElementById('selected-info').innerHTML = `
                <div class="text-lg font-semibold text-gray-800">
                    ${dateStr} ${time}〜
                </div>
            `;

            // 送信ボタンを有効化
            document.getElementById('submit-btn').disabled = false;
        }

        function changeWeek(direction) {
            const currentWeek = {{ $weekOffset }};
            const newWeek = currentWeek + direction;
            window.location.href = `{{ route('admin.reservations.reschedule', $reservation) }}?week=${newWeek}`;
        }

        // メニュー選択時の処理
        document.getElementById('menu-select').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const duration = selected.dataset.duration;
            const price = selected.dataset.price;

            console.log('選択されたメニュー:', {
                id: this.value,
                duration: duration,
                price: price
            });

            // カレンダーを再読み込みする場合はここで実装
            // 現在は簡易版のため省略
        });

        // フォーム送信時のデバッグ
        document.getElementById('reschedule-form').addEventListener('submit', function(e) {
            const formData = new FormData(this);
            console.log('フォーム送信データ:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }

            const menuId = document.getElementById('menu-select').value;
            if (!menuId) {
                e.preventDefault();
                alert('メニューを選択してください');
                return false;
            }

            const selectedDate = document.getElementById('selected-date').value;
            const selectedTime = document.getElementById('selected-time').value;

            if (!selectedDate || !selectedTime) {
                e.preventDefault();
                alert('カレンダーから日時を選択してください');
                return false;
            }
        });
    </script>
</body>
</html>