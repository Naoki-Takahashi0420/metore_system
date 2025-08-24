<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>オンライン予約 - 目のトレーニング</title>
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- ヘッダー -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">日時を選択</h1>
            <p class="text-gray-600">ご希望の日時をお選びください</p>
        </div>

        <!-- 進捗インジケーター -->
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-gray-400 text-white rounded-full flex items-center justify-center font-bold">✓</div>
                <div class="w-20 h-1 bg-blue-500"></div>
                <div class="w-10 h-10 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold">2</div>
                <div class="w-20 h-1 bg-gray-300"></div>
                <div class="w-10 h-10 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center font-bold">3</div>
            </div>
        </div>
        <div class="flex justify-center mb-8 text-sm">
            <div class="text-center px-4">
                <div class="text-gray-500">メニュー選択</div>
            </div>
            <div class="text-center px-4">
                <div class="text-blue-500 font-semibold">日時選択</div>
            </div>
            <div class="text-center px-4">
                <div class="text-gray-400">情報入力</div>
            </div>
        </div>

        <!-- 選択中のメニュー表示 -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-600 mb-1">選択中のメニュー</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $selectedMenu->name }}</p>
                    <p class="text-sm text-gray-600">{{ $selectedMenu->duration }}分 / ¥{{ number_format($selectedMenu->price) }}</p>
                </div>
                <a href="{{ route('reservation.menu') }}" class="text-blue-500 hover:text-blue-700 text-sm underline">
                    メニューを変更
                </a>
            </div>
        </div>

        <!-- 選択済み店舗の表示 -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">選択中の店舗</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $selectedStore->name }}</p>
                    <p class="text-sm text-gray-600">{{ $selectedStore->address ?? '' }}</p>
                </div>
                <a href="{{ url('/stores') }}" class="text-blue-500 hover:text-blue-700 text-sm underline">
                    店舗を変更
                </a>
            </div>
        </div>

        <!-- 週間ナビゲーション -->
        <div class="flex justify-between items-center mb-6">
            <a href="?week={{ $weekOffset - 1 }}" 
               class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300 {{ $weekOffset <= 0 ? 'invisible' : '' }}">
                ← 前の一週間
            </a>
            
            <h2 class="text-xl font-semibold">
                {{ $dates[0]['date']->format('Y年n月') }}
            </h2>
            
            <a href="?week={{ $weekOffset + 1 }}" 
               class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300">
                次の一週間 →
            </a>
        </div>

        <!-- 予約可能時間テーブル -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full availability-table">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-2 text-sm font-medium text-gray-700 border-r">時間</th>
                        @foreach($dates as $date)
                            <th class="py-3 px-2 text-sm font-medium {{ $date['is_today'] ? 'bg-blue-50' : '' }} {{ $date['date']->dayOfWeek == 0 ? 'text-red-500' : ($date['date']->dayOfWeek == 6 ? 'text-blue-500' : 'text-gray-700') }}">
                                <div>{{ $date['formatted'] }}</div>
                                <div class="text-xs">{{ $date['day_jp'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeSlots as $slot)
                        <tr class="border-t">
                            <td class="py-3 px-2 text-sm font-medium text-gray-700 bg-gray-50 border-r">
                                {{ $slot }}
                            </td>
                            @foreach($dates as $date)
                                @php
                                    $dateStr = $date['date']->format('Y-m-d');
                                    $isAvailable = $availability[$dateStr][$slot] ?? false;
                                @endphp
                                <td class="py-3 px-2 {{ $date['is_today'] ? 'bg-blue-50' : '' }}">
                                    @if($isAvailable)
                                        <button type="button" 
                                                class="time-slot w-8 h-8 rounded-full bg-green-500 text-white font-bold hover:bg-green-600"
                                                data-date="{{ $dateStr }}"
                                                data-time="{{ $slot }}"
                                                onclick="selectTimeSlot(this)">
                                            ○
                                        </button>
                                    @else
                                        <span class="text-gray-400 text-xl">×</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- エラーメッセージ表示 -->
        @if(session('error'))
            <div id="existingCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-8 max-w-md mx-4 shadow-xl">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 15.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">既にご予約があります</h3>
                        <p class="text-gray-600 mb-6">{{ session('error') }}</p>
                    </div>
                    
                    <div class="space-y-3">
                        <a href="{{ url('/customer/login') }}" class="block w-full bg-blue-600 text-white text-center py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                            電話番号でログイン（予約変更・確認）
                        </a>
                        <button onclick="closeExistingCustomerModal()" class="block w-full bg-gray-200 text-gray-700 text-center py-3 px-4 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                            閉じる
                        </button>
                    </div>
                </div>
            </div>
        @endif
        
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <!-- 選択されていません表示 -->
        <div id="noSelection" class="text-center py-8 text-gray-500">
            選択されていません。
        </div>

        <!-- 予約フォーム（初期は非表示） -->
        <div id="reservationForm" class="hidden bg-white rounded-lg shadow-sm p-6 mt-6">
            <h3 class="text-lg font-semibold mb-4">予約情報入力</h3>
            
            <form action="{{ route('reservation.store') }}" method="POST">
                @csrf
                <input type="hidden" name="store_id" value="{{ $selectedStore->id }}">
                <input type="hidden" name="menu_id" value="{{ $selectedMenu->id }}">
                <input type="hidden" id="selectedDate" name="date">
                <input type="hidden" id="selectedTime" name="time">
                
                <!-- 選択された日時表示 -->
                <div class="mb-4 p-4 bg-blue-50 rounded">
                    <p class="text-sm text-gray-600">選択された日時</p>
                    <p id="selectedDateTime" class="text-lg font-semibold"></p>
                </div>
                
                <!-- メニュー表示（変更不可） -->
                <div class="mb-4 p-4 bg-gray-50 rounded">
                    <p class="text-sm text-gray-600 mb-1">メニュー</p>
                    <p class="text-lg font-semibold">{{ $selectedMenu->name }}</p>
                    <p class="text-sm text-gray-600">{{ $selectedMenu->duration }}分 / ¥{{ number_format($selectedMenu->price) }}</p>
                </div>
                
                <!-- お客様情報 -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">姓 <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" required class="w-full border border-gray-300 rounded-md px-4 py-2" placeholder="山田">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">名 <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" required class="w-full border border-gray-300 rounded-md px-4 py-2" placeholder="太郎">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">電話番号 <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" required class="w-full border border-gray-300 rounded-md px-4 py-2" placeholder="090-1234-5678">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">メールアドレス</label>
                    <input type="email" name="email" class="w-full border border-gray-300 rounded-md px-4 py-2" placeholder="example@email.com">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">備考</label>
                    <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-md px-4 py-2"></textarea>
                </div>
                
                <div class="flex gap-4">
                    <button type="button" onclick="cancelSelection()" class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        キャンセル
                    </button>
                    <button type="submit" class="flex-1 px-4 py-3 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                        予約する
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedSlot = null;
        
        function selectTimeSlot(button) {
            // 以前の選択を解除
            if (selectedSlot) {
                selectedSlot.classList.remove('selected');
                selectedSlot.classList.add('bg-green-500');
                selectedSlot.classList.remove('bg-blue-600');
            }
            
            // 新しい選択を設定
            selectedSlot = button;
            button.classList.add('selected');
            button.classList.remove('bg-green-500');
            button.classList.add('bg-blue-600');
            
            // フォームに値を設定
            const date = button.dataset.date;
            const time = button.dataset.time;
            document.getElementById('selectedDate').value = date;
            document.getElementById('selectedTime').value = time;
            
            // 日付を日本語形式で表示
            const dateObj = new Date(date);
            const options = { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' };
            const formattedDate = dateObj.toLocaleDateString('ja-JP', options);
            document.getElementById('selectedDateTime').textContent = `${formattedDate} ${time}`;
            
            // フォームを表示
            document.getElementById('noSelection').classList.add('hidden');
            document.getElementById('reservationForm').classList.remove('hidden');
            
            // フォームまでスクロール
            document.getElementById('reservationForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function cancelSelection() {
            if (selectedSlot) {
                selectedSlot.classList.remove('selected');
                selectedSlot.classList.add('bg-green-500');
                selectedSlot.classList.remove('bg-blue-600');
                selectedSlot = null;
            }
            
            document.getElementById('noSelection').classList.remove('hidden');
            document.getElementById('reservationForm').classList.add('hidden');
        }
        
        function closeExistingCustomerModal() {
            const modal = document.getElementById('existingCustomerModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        // ESCキーでモーダルを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeExistingCustomerModal();
            }
        });
        
        // モーダル外クリックで閉じる
        document.getElementById('existingCustomerModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeExistingCustomerModal();
            }
        });
    </script>
</body>
</html>