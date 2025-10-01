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
        {{-- ステップインジケーター --}}
        {{-- モバイル版：シンプルな表示 --}}
        <div class="block sm:hidden mb-6">
            <div class="flex justify-center items-center">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                    <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                    <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                    <div class="w-8 h-8 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">4</div>
                </div>
            </div>
            <p class="text-center text-sm mt-2 font-bold">ステップ4: 日時選択</p>
        </div>

        {{-- PC版：詳細表示 --}}
        <div class="hidden sm:block mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center">
                    <div class="flex items-center">
                        <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">✓</div>
                        <span class="ml-2 text-base text-gray-500">店舗</span>
                    </div>
                    <div class="mx-3 text-gray-400">→</div>
                    <div class="flex items-center">
                        <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">✓</div>
                        <span class="ml-2 text-base text-gray-500">コース</span>
                    </div>
                    <div class="mx-3 text-gray-400">→</div>
                    <div class="flex items-center">
                        <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">✓</div>
                        <span class="ml-2 text-base text-gray-500">時間・料金</span>
                    </div>
                    <div class="mx-3 text-gray-400">→</div>
                    <div class="flex items-center">
                        <div class="rounded-full h-12 w-12 bg-blue-500 text-white flex items-center justify-center text-lg font-bold">4</div>
                        <span class="ml-2 text-base font-bold">日時選択</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ヘッダー -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">日時を選択</h1>
            <p class="text-gray-600">ご希望の日時をお選びください</p>
        </div>

        <!-- 選択中のメニュー表示 -->
        @if(Session::has('is_subscription_booking'))
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-600 mb-1 font-medium">サブスクリプション予約</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $selectedMenu->name }}</p>
                    @php
                        $subscription = \App\Models\CustomerSubscription::where('customer_id', Session::get('customer_id'))
                            ->where('status', 'active')
                            ->first();
                        $monthlyPrice = $subscription ? $subscription->monthly_price : 0;
                    @endphp
                    <p class="text-sm text-gray-600">{{ $selectedMenu->duration_minutes }}分 / <span class="text-blue-600 font-medium">{{ number_format($monthlyPrice) }}円/月</span></p>
                </div>
                <div class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">
                    サブスク
                </div>
            </div>
        </div>
        @else
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-600 mb-1">選択中のメニュー</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $selectedMenu->name }}</p>
                    <p class="text-sm text-gray-600">{{ $selectedMenu->duration_minutes }}分 / ¥{{ number_format($selectedMenu->price) }}</p>
                </div>
                @if(!Session::has('is_reservation_change'))
                <a href="{{ route('reservation.menu') }}" class="text-blue-500 hover:text-blue-700 text-sm underline">
                    メニューを変更
                </a>
                @endif
            </div>
        </div>
        @endif
        
        <!-- 予約変更の場合の案内表示 -->
        @if(Session::has('is_reservation_change'))
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="text-sm font-semibold text-yellow-800 mb-1">予約日時の変更</p>
                    <p class="text-sm text-yellow-700">
                        現在の予約日時は<span class="font-semibold bg-yellow-200 px-1 rounded">黄色</span>で表示されています。
                        新しい日時を選択してください。
                    </p>
                    @if(Session::has('original_reservation_date') && Session::has('original_reservation_time'))
                        @php
                            $originalDate = Session::get('original_reservation_date');
                            $originalTime = Session::get('original_reservation_time');
                            $originalDateStr = is_string($originalDate) ? explode(' ', $originalDate)[0] : $originalDate->format('Y-m-d');
                            $originalTimeStr = is_string($originalTime) ? substr($originalTime, 0, 5) : $originalTime->format('H:i');
                        @endphp
                        <p class="text-sm text-yellow-700 mt-2">
                            現在の予約: <span class="font-semibold">{{ \Carbon\Carbon::parse($originalDateStr)->format('Y年n月j日') }} {{ $originalTimeStr }}</span>
                        </p>
                    @endif
                </div>
            </div>
        </div>
        @endif

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

        <!-- 凡例（サブスク予約時のみ表示） -->
        @if(request()->query('type') === 'subscription')
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">カレンダー凡例</h3>
            <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-green-500 text-white font-bold flex items-center justify-center text-xs mr-2">○</div>
                    <span class="text-gray-700">予約可能</span>
                </div>
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-orange-500 text-white font-bold flex items-center justify-center text-xs mr-2 border-2 border-orange-600">予</div>
                    <span class="text-gray-700">同じメニューで予約済み</span>
                </div>
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-red-500 text-white font-bold flex items-center justify-center text-xs mr-2 border-2 border-red-600">×</div>
                    <span class="text-gray-700">他メニューで予約済み</span>
                </div>
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-gray-400 text-white font-bold flex items-center justify-center text-xs mr-2 border-2 border-gray-500">△</div>
                    <span class="text-gray-700">前回予約から5日以内（予約不可）</span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-400 text-lg mr-2">×</span>
                    <span class="text-gray-700">予約不可（過去の時間）</span>
                </div>
            </div>
        </div>
        @endif

        <!-- 週間ナビゲーション -->
        <div class="flex justify-between items-center mb-6">
            @php
                $queryParams = request()->except('week');
                $prevWeekParams = http_build_query(array_merge($queryParams, ['week' => $weekOffset - 1]));
                $nextWeekParams = http_build_query(array_merge($queryParams, ['week' => $weekOffset + 1]));
            @endphp
            <a href="?{{ $prevWeekParams }}" 
               class="px-2 sm:px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300 text-xs sm:text-sm {{ $weekOffset <= 0 ? 'invisible' : '' }}">
                <span class="hidden sm:inline">← 前の一週間</span>
                <span class="sm:hidden">← 前週</span>
            </a>
            
            <h2 class="text-sm sm:text-2xl font-bold text-center">
                {{ $dates[0]['date']->format('Y年n月') }}
            </h2>
            
            <a href="?{{ $nextWeekParams }}" 
               class="px-2 sm:px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300 text-xs sm:text-sm {{ $weekOffset >= ($maxWeeks - 1) ? 'invisible' : '' }}">
                <span class="hidden sm:inline">次の一週間 →</span>
                <span class="sm:hidden">次週 →</span>
            </a>
        </div>

        <!-- 予約可能時間テーブル -->
        <div class="bg-white rounded-lg shadow-sm overflow-x-auto">
            <table class="w-full availability-table min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-2 px-1 sm:py-3 sm:px-2 text-xs sm:text-sm font-medium text-gray-700 border-r sticky left-0 bg-gray-100 z-10 min-w-16"></th>
                        @foreach($dates as $date)
                            <th class="py-2 px-1 sm:px-2 text-center min-w-12 sm:min-w-16 {{ $date['is_today'] ? 'bg-blue-50' : '' }}">
                                <div class="text-xs font-normal {{ $date['date']->dayOfWeek == 0 ? 'text-red-500' : ($date['date']->dayOfWeek == 6 ? 'text-blue-500' : 'text-gray-500') }}">
                                    {{ $date['day_jp'] }}
                                </div>
                                <div class="text-sm sm:text-lg font-bold {{ $date['date']->dayOfWeek == 0 ? 'text-red-500' : ($date['date']->dayOfWeek == 6 ? 'text-blue-500' : 'text-gray-700') }}">
                                    {{ $date['formatted'] }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeSlots as $slot)
                        <tr class="border-t">
                            <td class="py-2 px-1 sm:py-3 sm:px-2 text-xs sm:text-sm font-medium text-gray-700 bg-gray-50 border-r sticky left-0 z-10">
                                {{ $slot }}
                            </td>
                            @foreach($dates as $date)
                                @php
                                    $dateStr = $date['date']->format('Y-m-d');
                                    $availabilityData = $availability[$dateStr][$slot] ?? false;

                                    // 新しい形式（連想配列）か古い形式（boolean）かを判定
                                    if (is_array($availabilityData)) {
                                        $isAvailable = $availabilityData['available'] ?? false;
                                        $withinFiveDays = $availabilityData['within_five_days'] ?? false;
                                        $isSubscription = $availabilityData['is_subscription'] ?? false;
                                    } else {
                                        $isAvailable = $availabilityData;
                                        $withinFiveDays = false;
                                        $isSubscription = false;
                                    }
                                    
                                    // 元の予約日時かチェック（日程変更の場合）
                                    $isOriginalReservation = false;
                                    if (Session::has('is_reservation_change')) {
                                        $originalDate = Session::get('original_reservation_date');
                                        $originalTime = Session::get('original_reservation_time');
                                        
                                        // 日付を正規化して比較
                                        if ($originalDate) {
                                            $originalDateStr = is_string($originalDate) ? 
                                                explode(' ', $originalDate)[0] : 
                                                $originalDate->format('Y-m-d');
                                            
                                            $originalTimeStr = is_string($originalTime) ? 
                                                substr($originalTime, 0, 5) : 
                                                $originalTime->format('H:i');
                                                
                                            $isOriginalReservation = ($dateStr === $originalDateStr && $slot === $originalTimeStr);
                                        }
                                    }
                                @endphp
                                <td class="py-2 px-1 sm:py-3 sm:px-2 text-center {{ $date['is_today'] ? 'bg-blue-50' : '' }} {{ $isOriginalReservation ? 'bg-yellow-100 ring-2 ring-yellow-400' : '' }}">
                                    @if($isOriginalReservation)
                                        <div class="relative">
                                            <span class="absolute -top-1 -right-1 bg-yellow-500 text-white text-xs px-1 rounded">現在</span>
                                            <button type="button" 
                                                    class="time-slot w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-yellow-500 text-white font-bold hover:bg-yellow-600 text-xs sm:text-base"
                                                    data-date="{{ $dateStr }}"
                                                    data-time="{{ $slot }}"
                                                    onclick="selectTimeSlot(this)">
                                                ●
                                            </button>
                                        </div>
                                    @elseif($isAvailable)
                                        <button type="button"
                                                class="time-slot w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-green-500 text-white font-bold hover:bg-green-600 text-xs sm:text-base"
                                                data-date="{{ $dateStr }}"
                                                data-time="{{ $slot }}"
                                                onclick="selectTimeSlot(this)">
                                            ○
                                        </button>
                                    @elseif($withinFiveDays && $isSubscription)
                                        {{-- サブスク予約で5日間制限内の場合は△を表示 --}}
                                        <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-gray-400 text-white font-bold flex items-center justify-center border-2 border-gray-500 shadow-md text-xs sm:text-base mx-auto"
                                             title="前回予約から5日以内のため予約できません">
                                            △
                                        </div>
                                    @else
                                        <span class="text-gray-400 text-lg sm:text-xl">×</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- エラーメッセージ表示（UX改善：シンプルな通知） -->
        @if(session('error'))
            <div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50 max-w-md" role="alert">
                <div class="flex">
                    <div class="py-1">
                        <svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold">エラーが発生しました</p>
                        <p class="text-sm">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
            <script>
                // 5秒後に自動で消す
                setTimeout(() => {
                    const errorMsg = document.querySelector('[role="alert"]');
                    if (errorMsg) {
                        errorMsg.style.display = 'none';
                    }
                }, 5000);
            </script>
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
                {{-- コンテキストをフォームに含める --}}
                @if(request()->has('ctx'))
                    <input type="hidden" name="ctx" value="{{ request('ctx') }}">
                @endif
                <input type="hidden" name="store_id" value="{{ $selectedStore->id }}">
                <input type="hidden" name="menu_id" value="{{ $selectedMenu->id }}">
                <input type="hidden" id="selectedDate" name="date">
                <input type="hidden" id="selectedTime" name="time">
                @if(Session::has('selected_staff_id'))
                    <input type="hidden" name="staff_id" value="{{ Session::get('selected_staff_id') }}">
                @endif
                
                <!-- 選択された日時表示 -->
                <div class="mb-4 p-4 bg-blue-50 rounded">
                    <p class="text-sm text-gray-600">選択された日時</p>
                    <p id="selectedDateTime" class="text-lg font-semibold"></p>
                </div>

                <!-- メニュー表示（変更不可） -->
                <div class="mb-4 p-4 bg-gray-50 rounded">
                    <p class="text-sm text-gray-600 mb-1">メニュー</p>
                    <p class="text-lg font-semibold">{{ $selectedMenu->name }}</p>
                    <p class="text-sm text-gray-600">{{ $selectedMenu->duration_minutes }}分 / ¥{{ number_format($selectedMenu->price) }}</p>

                    @if(Session::has('selected_staff_id'))
                        @php
                            $selectedStaff = \App\Models\User::find(Session::get('selected_staff_id'));
                        @endphp
                        @if($selectedStaff)
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <p class="text-sm text-gray-600">担当スタッフ: <span class="font-semibold">{{ $selectedStaff->name }}</span></p>
                            </div>
                        @endif
                    @endif
                </div>

                @if(Session::has('selected_staff_id'))
                    @php
                        $selectedStaff = App\Models\User::find(Session::get('selected_staff_id'));
                    @endphp
                    @if($selectedStaff)
                        <!-- 担当スタッフ表示 -->
                        <div class="mb-4 p-4 bg-blue-50 rounded">
                            <p class="text-sm text-gray-600 mb-1">担当スタッフ</p>
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <p class="text-lg font-semibold">{{ $selectedStaff->name }}</p>
                            </div>
                        </div>
                    @endif
                @endif
                
                <!-- お客様情報 -->
                @if($isExistingCustomer && $existingCustomer)
                    {{-- 既存顧客の場合：情報を表示のみ --}}
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <h4 class="text-sm font-medium text-green-800 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            登録済みお客様情報
                        </h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">お名前:</span>
                                <span class="font-medium ml-2">{{ $existingCustomer->last_name }} {{ $existingCustomer->first_name }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">電話番号:</span>
                                <span class="font-medium ml-2">{{ $existingCustomer->phone }}</span>
                            </div>
                            @if($existingCustomer->email)
                            <div class="col-span-2">
                                <span class="text-gray-600">メールアドレス:</span>
                                <span class="font-medium ml-2">{{ $existingCustomer->email }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    {{-- 隠しフィールドで顧客情報を送信 --}}
                    <input type="hidden" name="customer_id" value="{{ $existingCustomer->id }}">
                    <input type="hidden" name="last_name" value="{{ $existingCustomer->last_name }}">
                    <input type="hidden" name="first_name" value="{{ $existingCustomer->first_name }}">
                    <input type="hidden" name="phone" value="{{ $existingCustomer->phone }}">
                    <input type="hidden" name="email" value="{{ $existingCustomer->email }}">
                @else
                    {{-- 新規顧客の場合：通常の入力フォーム --}}
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
                @endif
                
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
        
        let existingReservations = []; // 既存予約を格納
        
        // ページ読み込み時に既存顧客情報をチェック
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('🚀 ページ読み込み開始');

            checkExistingCustomer();

            // サーバー側から渡されたサブスク予約フラグを使用
            const isSubscriptionBooking = @json($isSubscriptionBooking ?? false);
            const subscriptionId = @json($subscriptionId ?? null);

            console.log('🔍 サブスク予約情報:', {
                isSubscriptionBooking,
                subscriptionId,
                fullUrl: window.location.href
            });

            if (isSubscriptionBooking) {
                console.log('📋 サブスク予約モード - 既存予約を取得開始');
                await fetchExistingReservations();
                console.log('🔄 カレンダー更新開始');
                updateCalendarWithReservations();
                console.log('✅ カレンダー更新完了');
            } else {
                console.log('📅 通常予約モード');
            }
        });
        
        // 既存予約を取得する関数
        async function fetchExistingReservations() {
            try {
                const token = localStorage.getItem('customer_token');
                if (!token) return;
                
                console.log('既存予約を取得中...');
                const response = await fetch('/api/customer/reservations', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    existingReservations = data.data || [];
                    console.log('既存予約:', existingReservations);
                }
            } catch (error) {
                console.error('既存予約の取得に失敗:', error);
            }
        }
        
        // カレンダーに既存予約を表示する関数
        function updateCalendarWithReservations() {
            console.log('🎯 updateCalendarWithReservations開始');
            console.log('既存予約数:', existingReservations.length);

            if (existingReservations.length === 0) {
                console.log('⚠️ 既存予約なし - 処理をスキップ');
                return;
            }

            console.log('既存予約の表示更新開始', existingReservations);

            // サーバー側から渡されたサブスク予約フラグを使用
            const isSubscriptionBooking = @json($isSubscriptionBooking ?? false);
            console.log('サブスク予約モード:', isSubscriptionBooking);

            // 現在のメニューIDを取得
            const currentMenuId = @json($selectedMenu->id);
            console.log('現在のメニューID:', currentMenuId);

            // 5日間隔制限のために既存予約の日付を取得
            const reservationDates = getExistingReservationDates();
            console.log('予約日リスト:', reservationDates);
            
            // カレンダーの各セルをチェック（予約可能なボタンのみ）
            const buttons = document.querySelectorAll('button[data-date][data-time].time-slot');
            console.log(`🔍 チェック対象ボタン数: ${buttons.length}`);

            if (buttons.length === 0) {
                console.log('⚠️ 予約可能なボタンが見つかりません');
                console.log('すべてのボタン要素:', document.querySelectorAll('button').length);
                console.log('data-date属性を持つ要素:', document.querySelectorAll('[data-date]').length);
                console.log('time-slotクラスを持つ要素:', document.querySelectorAll('.time-slot').length);

                // 全ボタンの詳細を出力
                document.querySelectorAll('button').forEach((btn, i) => {
                    console.log(`ボタン${i}: class="${btn.className}", data-date="${btn.getAttribute('data-date')}", data-time="${btn.getAttribute('data-time')}"`);
                });

                // より広範囲で検索
                const allCells = document.querySelectorAll('td');
                console.log(`テーブルセル数: ${allCells.length}`);

                let greenButtons = 0;
                let redButtons = 0;
                allCells.forEach(cell => {
                    if (cell.textContent === '○') greenButtons++;
                    if (cell.textContent === '×') redButtons++;
                });
                console.log(`○のセル数: ${greenButtons}, ×のセル数: ${redButtons}`);
            }

            buttons.forEach((button, index) => {
                const dateStr = button.getAttribute('data-date');
                const timeStr = button.getAttribute('data-time');

                console.log(`📌 ボタン ${index + 1}/${buttons.length}: ${dateStr} ${timeStr}`);

                // この日時に既存予約があるかチェック
                const existingReservation = findExistingReservation(dateStr, timeStr);

                // 5日間隔制限チェック
                const isWithinFiveDays = isDateWithinFiveDaysOfReservation(dateStr, reservationDates);

                // デバッグ情報を出力
                console.log(`チェック中: ${dateStr}`, {
                    isWithinFiveDays: isWithinFiveDays,
                    isSubscriptionBooking: isSubscriptionBooking,
                    reservationDates: reservationDates,
                    existingReservation: existingReservation
                });

                if (isWithinFiveDays) {
                    console.log(`${dateStr} は5日以内: true, サブスク予約: ${isSubscriptionBooking}`);
                }
                
                if (existingReservation) {
                    const isSameMenu = existingReservation.menu_id &&
                                     existingReservation.menu_id.toString() === currentMenuId.toString();
                    
                    // ボタンを置き換え
                    const td = button.parentElement;
                    td.innerHTML = ''; // 既存のボタンを削除
                    
                    if (isSameMenu) {
                        // 同じメニューの予約
                        const reservedDiv = document.createElement('div');
                        reservedDiv.className = 'w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-orange-500 text-white font-bold flex items-center justify-center border-2 border-orange-600 shadow-md text-xs sm:text-base mx-auto';
                        reservedDiv.innerHTML = '予';
                        reservedDiv.title = `既に予約済み: ${existingReservation.menu?.name || 'メニュー'}`;
                        td.appendChild(reservedDiv);
                    } else {
                        // 違うメニューの予約
                        const reservedDiv = document.createElement('div');
                        reservedDiv.className = 'w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-red-500 text-white font-bold flex items-center justify-center border-2 border-red-600 shadow-md text-xs sm:text-base mx-auto';
                        reservedDiv.innerHTML = '×';
                        reservedDiv.title = `他の予約あり: ${existingReservation.menu?.name || 'メニュー'}`;
                        td.appendChild(reservedDiv);
                    }
                } else if (isWithinFiveDays && isSubscriptionBooking && @json($isExistingCustomer ?? false)) {
                    // 既存顧客のサブスク予約でのみ5日制限を適用
                    console.log(`5日制限適用: ${dateStr} ${timeStr} - blocked by reservations:`, reservationDates);

                    const td = button.parentElement;
                    td.innerHTML = ''; // 既存のボタンを削除

                    const blockedDiv = document.createElement('div');
                    blockedDiv.className = 'w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-gray-400 text-white font-bold flex items-center justify-center border-2 border-gray-500 shadow-md text-xs sm:text-base mx-auto';
                    blockedDiv.innerHTML = '△';
                    blockedDiv.title = '前回予約から5日以内のため予約できません';
                    td.appendChild(blockedDiv);
                }
            });
        }
        
        // 既存予約を検索する関数
        function findExistingReservation(dateStr, timeStr) {
            return existingReservations.find(reservation => {
                if (['cancelled', 'canceled'].includes(reservation.status)) {
                    return false;
                }
                
                // 日付を比較 - 'T'区切りと' '区切りの両方に対応
                const reservationDate = reservation.reservation_date.split(/[T ]/)[0];
                if (reservationDate !== dateStr) {
                    return false;
                }
                
                // 時間を比較（HH:MM 形式に正規化）
                const reservationTime = reservation.start_time.substring(0, 5);
                return reservationTime === timeStr;
            });
        }
        
        // 既存予約の日付を取得する関数
        function getExistingReservationDates() {
            console.log('=== 既存予約データ確認 ===');
            console.log('existingReservations type:', typeof existingReservations);
            console.log('existingReservations length:', existingReservations ? existingReservations.length : 'null/undefined');
            console.log('existingReservations full data:', JSON.stringify(existingReservations, null, 2));

            if (!existingReservations || existingReservations.length === 0) {
                console.log('⚠️ 既存予約データなし');
                return [];
            }

            const dates = existingReservations
                .filter(reservation => {
                    const isActive = !['cancelled', 'canceled'].includes(reservation.status);
                    console.log(`予約ID ${reservation.id}: status=${reservation.status}, active=${isActive}, date=${reservation.reservation_date}`);
                    return isActive;
                })
                .map(reservation => {
                    // 'Y-m-d H:i:s' または 'Y-m-dTH:i:s' 形式から日付部分のみ抽出
                    const dateStr = reservation.reservation_date.split(/[T ]/)[0];
                    console.log('予約日付抽出:', reservation.reservation_date, '->', dateStr);
                    return dateStr;
                });

            console.log('✅ 最終的な有効予約日一覧:', dates);
            return dates;
        }
        
        // 指定した日付が既存予約から5日以内かチェックする関数
        function isDateWithinFiveDaysOfReservation(dateStr, reservationDates) {
            console.log(`\n🔍 ===== 5日制限チェック開始: ${dateStr} =====`);

            if (!reservationDates || reservationDates.length === 0) {
                console.log('❌ 既存予約データなし → 制限なし');
                return false;
            }

            // 日付文字列を日本時間で処理するために、時刻を含まない日付として扱う
            const [year, month, day] = dateStr.split('-').map(Number);
            const targetDate = new Date(year, month - 1, day); // monthは0ベースなので-1
            targetDate.setHours(0, 0, 0, 0);

            console.log(`📅 対象日付: ${dateStr}`);
            console.log(`🕐 対象Date (Local): ${targetDate.toLocaleDateString('ja-JP')} ${targetDate.toLocaleTimeString('ja-JP')}`);
            console.log(`📋 既存予約日リスト: [${reservationDates.join(', ')}]`);

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            console.log(`📆 今日: ${today.toLocaleDateString('ja-JP')}`);

            let minDaysSinceReservation = Infinity;
            let closestReservation = null;

            const result = reservationDates.some(reservationDateStr => {
                const [resYear, resMonth, resDay] = reservationDateStr.split('-').map(Number);
                const reservationDate = new Date(resYear, resMonth - 1, resDay);
                reservationDate.setHours(0, 0, 0, 0);

                const diffTime = targetDate.getTime() - reservationDate.getTime();
                const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

                console.log(`\n  📊 比較: ${dateStr} vs ${reservationDateStr}`);
                console.log(`    🔗 予約Date (Local): ${reservationDate.toLocaleDateString('ja-JP')}`);
                console.log(`    ⏱️  時間差(ms): ${diffTime}`);
                console.log(`    📏 日数差: ${diffDays}日`);
                console.log(`    ✅ 条件(1-5日): ${diffDays > 0 && diffDays <= 5}`);

                if (diffDays > 0 && diffDays < minDaysSinceReservation) {
                    minDaysSinceReservation = diffDays;
                    closestReservation = reservationDateStr;
                }

                // 既存予約日から前後5日以内（計6日間）をチェック
                // 例: 19日の予約がある場合、14-24日は不可、13日以前と25日以降は可
                return Math.abs(diffDays) <= 5;
            });

            console.log(`\n📈 サマリー:`);
            console.log(`  🎯 最も近い予約: ${closestReservation} (${minDaysSinceReservation}日前)`);
            console.log(`  🚫 制限適用: ${result ? 'YES' : 'NO'}`);
            console.log(`🏁 ===== 5日制限チェック終了: ${dateStr} =====\n`);

            return result;
        }
        
        function checkExistingCustomer() {
            // セッションストレージから既存顧客情報を取得
            const existingCustomerId = sessionStorage.getItem('existing_customer_id');
            const fromMypage = sessionStorage.getItem('from_mypage');
            const isSubscriptionBooking = sessionStorage.getItem('is_subscription_booking');
            
            if (existingCustomerId && fromMypage) {
                // LocalStorageから顧客データを取得
                const customerData = localStorage.getItem('customer_data');
                if (customerData) {
                    try {
                        const customer = JSON.parse(customerData);
                        // 顧客情報をフォームに自動入力
                        fillCustomerForm(customer);
                        
                        // サブスク予約の場合は追加情報を表示
                        if (isSubscriptionBooking) {
                            showSubscriptionInfo();
                        }
                    } catch (e) {
                        console.error('Customer data parse error:', e);
                    }
                }
            }
        }
        
        function fillCustomerForm(customer) {
            // 顧客情報をフォームに自動入力
            const form = document.querySelector('form');
            if (form) {
                const lastNameInput = form.querySelector('input[name="last_name"]');
                const firstNameInput = form.querySelector('input[name="first_name"]');
                const phoneInput = form.querySelector('input[name="phone"]');
                const emailInput = form.querySelector('input[name="email"]');
                
                if (lastNameInput) lastNameInput.value = customer.last_name || '';
                if (firstNameInput) firstNameInput.value = customer.first_name || '';
                if (phoneInput) phoneInput.value = customer.phone || '';
                if (emailInput) emailInput.value = customer.email || '';
                
                // 入力フィールドを読み取り専用にする（既存顧客の場合）
                if (lastNameInput) lastNameInput.readOnly = true;
                if (firstNameInput) firstNameInput.readOnly = true;
                if (phoneInput) phoneInput.readOnly = true;
                
                // 背景色を変更して読み取り専用であることを示す
                const readOnlyStyle = 'background-color: #f9fafb; cursor: not-allowed;';
                if (lastNameInput) lastNameInput.style.cssText = readOnlyStyle;
                if (firstNameInput) firstNameInput.style.cssText = readOnlyStyle;
                if (phoneInput) phoneInput.style.cssText = readOnlyStyle;
            }
        }
        
        function showSubscriptionInfo() {
            // サブスク予約であることを示すメッセージを表示
            const menuDiv = document.querySelector('.bg-gray-50.rounded');
            if (menuDiv) {
                const subscriptionBadge = document.createElement('div');
                subscriptionBadge.className = 'bg-green-100 border border-green-200 rounded p-2 mt-2';
                subscriptionBadge.innerHTML = '<p class="text-sm text-green-700 font-medium">🎉 サブスクリプション予約</p>';
                menuDiv.appendChild(subscriptionBadge);
            }
        }
        
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

    </script>

    {{-- マイページ誘導モーダル --}}
    {{-- ctxパラメータからモーダル表示フラグをチェック --}}
    @php
        $showModal = isset($context['show_mypage_modal']) && $context['show_mypage_modal'];
        $customerPhone = $context['customer_phone'] ?? '';
    @endphp

    @if($showModal)
    <div id="mypageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md mx-4">
            <div class="text-center">
                <div class="mb-4">
                    <svg class="mx-auto h-12 w-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">すでに予約があります</h3>
                <p class="text-sm text-gray-600 mb-6">
                    この電話番号（{{ $customerPhone }}）で過去にご予約履歴があります。<br>
                    2回目以降のお客様は、マイページから予約の変更・追加を行ってください。
                </p>
                <div class="flex space-x-3 justify-center">
                    <button onclick="closeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        閉じる
                    </button>
                    <a href="/customer/dashboard" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                        マイページへ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function closeModal() {
            document.getElementById('mypageModal').style.display = 'none';
        }

        // モーダル外をクリックした時も閉じる
        document.getElementById('mypageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
    @endif

</body>
</html>