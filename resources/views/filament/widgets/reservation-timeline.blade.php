<x-filament-widgets::widget>
    <!-- スロットクリックハンドラー（最初に定義） -->
    <script>
        window.handleSlotClick = function(seatKey, timeSlot) {
            console.log('🎯 Slot clicked:', { seatKey, timeSlot });

            // デバッグ：Livewireの状態を確認
            console.log('Livewire available:', !!window.Livewire);
            console.log('Livewire.find available:', !!(window.Livewire && window.Livewire.find));

            try {
                // $wireを直接使う（Livewire 3の新しい方法）
                if (window.$wire) {
                    console.log('✅ Using $wire directly');
                    window.$wire.call('openNewReservationFromSlot', seatKey, timeSlot);
                    return;
                }

                // Alpine.jsの$wireを探す
                const alpineElement = document.querySelector('[x-data]');
                if (alpineElement && alpineElement._x_dataStack) {
                    console.log('🔍 Looking for Alpine $wire');
                    const alpineData = Alpine.$data(alpineElement);
                    if (alpineData.$wire) {
                        console.log('✅ Found Alpine $wire');
                        alpineData.$wire.call('openNewReservationFromSlot', seatKey, timeSlot);
                        return;
                    }
                }

                // Livewire 3のコンポーネントを取得
                const wireElements = document.querySelectorAll('[wire\\:id]');
                console.log('📊 Found wire:id elements:', wireElements.length);

                for (const wireElement of wireElements) {
                    const wireId = wireElement.getAttribute('wire:id');
                    console.log('📍 Trying wire:id:', wireId);

                    if (window.Livewire && window.Livewire.find) {
                        const component = window.Livewire.find(wireId);
                        if (component) {
                            console.log('✅ Found component, calling method');
                            component.call('openNewReservationFromSlot', seatKey, timeSlot);
                            return;
                        }
                    }
                }

                console.error('❌ Could not find a way to call Livewire method');

            } catch (error) {
                console.error('❌ Error in handleSlotClick:', error);
            }
        }

        // グローバルに確実に登録
        if (typeof window.handleSlotClick === 'undefined') {
            console.log('⚠️ handleSlotClick was not defined, defining now');
        }
    </script>

    <x-filament::card>
        <!-- Tom Select CSS -->
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">

        <style>
            .timeline-table {
                border-collapse: collapse;
                width: 100%;
                min-width: 1200px;
                position: relative;
            }

            .current-time-indicator {
                position: absolute;
                top: 60px;  /* ヘッダーの高さ分下げる */
                bottom: 0;
                width: 2px;
                background: #ef4444;
                z-index: 100;
                pointer-events: none;
                box-shadow: 0 0 10px rgba(239, 68, 68, 0.8),
                            0 0 5px rgba(239, 68, 68, 0.6);
                animation: pulse 2s ease-in-out infinite;
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.7;
                }
            }

            /* 営業時間外での非表示（JavaScriptで動的制御） */
            .current-time-indicator.outside-business-hours,
            #current-time-indicator.outside-business-hours {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }

            .current-time-indicator::before {
                content: '';
                position: absolute;
                top: -8px;
                left: -7px;
                width: 0;
                height: 0;
                border-left: 9px solid transparent;
                border-right: 9px solid transparent;
                border-bottom: 12px solid #ef4444;
                filter: drop-shadow(0 -2px 2px rgba(239, 68, 68, 0.3));
            }

            .current-time-text {
                position: absolute;
                top: -30px;
                left: 50%;
                transform: translateX(-50%);
                color: #ef4444;
                font-size: 13px;
                font-weight: bold;
                background: white;
                padding: 3px 8px;
                border: 2px solid #ef4444;
                border-radius: 6px;
                white-space: nowrap;
                box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                z-index: 101;
            }
            
            .timeline-table th,
            .timeline-table td {
                border-top: 1px solid #e0e0e0;
                border-bottom: 1px solid #e0e0e0;
                border-left: 1px solid #e0e0e0;
                padding: 0;
                height: 60px;
                position: relative;
            }
            
            .timeline-table th:last-child,
            .timeline-table td:last-child {
                border-right: 1px solid #e0e0e0;
            }
            
            .timeline-table th {
                background: #f8f8f8;
                font-weight: normal;
                font-size: 14px;
                text-align: center;
                min-width: 20px;
            }
            
            .timeline-table th[colspan] {
                min-width: 80px;
                border-right: 1px solid #e0e0e0;
            }
            
            .timeline-table td {
                width: 20px;
                min-width: 20px;
                cursor: pointer;
            }
            
            
            .timeline-table td:hover {
                background: #f5f5f5;
            }
            
            .seat-label {
                background: #f8f8f8;
                text-align: center;
                font-size: 14px;
                padding: 8px 12px;
                min-width: 120px;
                font-weight: 600;
                white-space: nowrap;
                position: sticky;
                left: 0;
                z-index: 10;
                border-right: 2px solid #d0d0d0 !important;
                box-shadow: 2px 0 4px rgba(0,0,0,0.05);
            }
            
            .sub-time-label {
                background: #e8f4f8;
                font-weight: bold;
            }

            /* スタッフベースモード用スタイル */
            .staff-unassigned-label {
                background: linear-gradient(90deg, #fef3c7 0%, #fef3c7 95%, transparent 100%);
                border-left: 4px solid #f59e0b;
                font-weight: bold;
                color: #92400e;
            }

            .staff-assigned-label {
                background: linear-gradient(90deg, #d1fae5 0%, #d1fae5 95%, transparent 100%);
                border-left: 4px solid #10b981;
                font-weight: bold;
                color: #065f46;
            }

            .staff-no-shift {
                background: linear-gradient(90deg, #f3f4f6 0%, #f3f4f6 95%, transparent 100%);
                border-left: 4px solid #9ca3af;
                color: #6b7280;
                font-style: italic;
            }
            
            .booking-block {
                position: absolute;
                top: 2px;
                bottom: 2px;
                left: 2px;
                padding: 4px;
                border-radius: 4px;
                font-size: 12px;
                cursor: pointer;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: center;
                z-index: 10;
                transition: all 0.2s;
            }
            
            .booking-block:hover {
                transform: scale(1.02);
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }
            
            .booking-block.span-1 { width: calc(20px - 4px); }
            .booking-block.span-2 { width: calc(40px - 4px); }
            .booking-block.span-3 { width: calc(60px - 4px); }
            .booking-block.span-4 { width: calc(80px - 4px); }
            .booking-block.span-5 { width: calc(100px - 4px); }
            .booking-block.span-6 { width: calc(120px - 4px); }
            .booking-block.span-7 { width: calc(140px - 4px); }
            .booking-block.span-8 { width: calc(160px - 4px); }
            
            .booking-name {
                font-weight: bold;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .booking-menu {
                font-size: 11px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .course-care {
                background: #e3f2fd;
                border-left: 3px solid #2196f3;
            }
            
            .course-hydrogen {
                background: #f3e5f5;
                border-left: 3px solid #9c27b0;
            }
            
            .course-training {
                background: #fff3e0;
                border-left: 3px solid #ff9800;
            }
            
            .course-special {
                background: #e8f5e9;
                border-left: 3px solid #4caf50;
            }
            
            .course-premium {
                background: #ffebee;
                border-left: 3px solid #f44336;
            }
            
            .course-vip {
                background: #fffde7;
                border-left: 3px solid #ffc107;
            }
            
            .course-default {
                background: #f5f5f5;
                border-left: 3px solid #9e9e9e;
            }
            
            .break-block {
                background: #757575 !important;
                color: white;
                text-align: center;
                line-height: 56px;
                font-weight: bold;
            }
            
            .blocked-cell {
                background: #f5f5f5 !important;
                cursor: not-allowed !important;
            }
            
            .no-staff-cell {
                background: #ffecb3 !important;
                cursor: not-allowed !important;
                position: relative;
            }
            
            .no-staff-cell::after {
                content: '❌';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 12px;
                opacity: 0.7;
            }

            .past-time-cell {
                background: #e5e7eb !important;
                cursor: not-allowed !important;
                opacity: 0.6;
            }

            .past-time-cell:hover {
                background: #d1d5db !important;
            }
            
            .conflicting-reservation {
                border: 2px solid red !important;
                background: #ffe5e5 !important;
            }
            
            .time-cell {
                position: relative;
            }
            
            .time-cell::after {
                content: '';
                position: absolute;
                left: 50%;
                top: 0;
                bottom: 0;
                width: 1px;
                background: #f0f0f0;
            }

            /* クリック可能なスロットの視覚効果 */
            .clickable-slot {
                transition: all 0.2s ease;
                position: relative;
            }

            .clickable-slot:hover {
                box-shadow: inset 0 0 0 2px #2563eb;
                z-index: 10;
            }

            /* 予約不可スロットの視覚効果 */
            .time-cell[style*="cursor: not-allowed"]:not(.blocked-cell):not(.past-time-cell):not(.no-staff-cell) {
                background: repeating-linear-gradient(
                    45deg,
                    transparent,
                    transparent 10px,
                    rgba(0,0,0,0.02) 10px,
                    rgba(0,0,0,0.02) 20px
                );
            }

            /* ホバー時の追加ボタン表示 */
            .clickable-slot::before {
                content: "+";
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 20px;
                color: #2563eb;
                opacity: 0;
                transition: opacity 0.2s ease;
                pointer-events: none;
                z-index: 5;
            }

            .clickable-slot:hover::before {
                opacity: 0.3;
            }
        </style>
        
        @php
            // タイムラインデータから動的に判定
            $useStaffAssignment = $timelineData['useStaffAssignment'] ?? false;
            $shiftBasedCapacity = $timelineData['shiftBasedCapacity'] ?? 1;
        @endphp

        <!-- 操作説明 -->
        <div class="bg-blue-50 border border-blue-200 rounded p-2 mb-4 text-sm">
            💡 <strong>操作方法:</strong>
            @if($useStaffAssignment)
                スタッフ別モード - 空きスロットをクリックで予約作成、予約ブロッククリックで詳細表示
            @else
                予約ブロックをクリックすると詳細画面が開き、通常席⇔サブ枠の移動ができます
            @endif
        </div>
        
        <!-- 競合警告 -->
        @if(!empty($timelineData['conflictingReservations']))
            <div class="bg-red-50 border border-red-300 rounded p-3 mb-4">
                <div class="flex items-start">
                    <div class="text-red-600 mr-2">⚠️</div>
                    <div>
                        <p class="font-bold text-red-700 mb-2">予約ブロック時間帯に予約が入っています！</p>
                        <ul class="text-sm text-red-600 space-y-1">
                            @foreach($timelineData['conflictingReservations'] as $conflict)
                                <li>• {{ $conflict['customer_name'] }} - {{ $conflict['time'] }}</li>
                            @endforeach
                        </ul>
                        <p class="text-xs text-red-500 mt-2">これらの予約を別の時間に移動してください。</p>
                    </div>
                </div>
            </div>
        @endif
        
        <!-- ヘッダー -->
        <div class="flex justify-between items-center mb-4">
            {{-- 店舗選択（柔軟な表示方式） --}}
            @php
                $storeCount = $stores->count();
                $currentStore = $stores->firstWhere('id', $selectedStore);
                $useStaffAssignment = $currentStore->use_staff_assignment ?? false;
                $shiftBasedCapacity = $currentStore->shift_based_capacity ?? 1;
            @endphp
            
            @if($storeCount <= 3)
                {{-- 3店舗以下：ボタン形式 --}}
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    @foreach($stores as $store)
                        <button
                            wire:click="$set('selectedStore', {{ $store->id }})"
                            class="px-3 py-1 text-sm rounded-lg transition-colors {{ $selectedStore == $store->id ? 'bg-primary-600 text-white' : 'bg-gray-100 hover:bg-gray-200' }}"
                        >
                            {{ $store->name }}
                        </button>
                    @endforeach
                </div>
            @elseif($storeCount <= 8)
                {{-- 4-8店舗：ドロップダウン --}}
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    <x-filament::dropdown placement="bottom-start">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-3 py-1 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700">
                                <x-heroicon-o-building-storefront class="w-4 h-4" />
                                <span>{{ $currentStore ? $currentStore->name : '店舗を選択' }}</span>
                                <x-heroicon-m-chevron-down class="w-3 h-3" />
                            </button>
                        </x-slot>
                        
                        <div class="py-1">
                            @foreach($stores as $store)
                                @if($store->id != $selectedStore)
                                <button 
                                    wire:click="$set('selectedStore', {{ $store->id }})"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    @if($store->is_active)
                                        <x-heroicon-m-check-circle class="w-4 h-4 text-green-500" />
                                    @else
                                        <x-heroicon-m-x-circle class="w-4 h-4 text-gray-400" />
                                    @endif
                                    {{ $store->name }}
                                </button>
                                @endif
                            @endforeach
                        </div>
                    </x-filament::dropdown>
                </div>
            @else
                {{-- 9店舗以上：検索可能な選択 --}}
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    <select wire:model.live="selectedStore" class="border rounded px-3 py-1 text-sm">
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            
            {{-- 予約管理モード表示 --}}
            <div class="flex items-center gap-2 px-3 py-1 rounded-lg text-sm {{ $useStaffAssignment ? 'bg-blue-50 text-blue-700' : 'bg-gray-50 text-gray-700' }}">
                @if($useStaffAssignment)
                    <x-heroicon-m-user-group class="w-4 h-4" />
                    <span>シフトベース（スタッフ別）</span>
                    <span class="font-medium">（最大{{ $shiftBasedCapacity }}席）</span>
                @else
                    <x-heroicon-m-clock class="w-4 h-4" />
                    <span>営業時間ベース</span>
                    <span class="font-medium">（{{ $currentStore->main_lines_count ?? 3 }}席）</span>
                @endif
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <button wire:click="changeDate('prev')" class="px-3 py-1 border rounded hover:bg-gray-100">
                        ◀
                    </button>
                    <div class="font-bold px-4">
                        {{ \Carbon\Carbon::parse($selectedDate)->format('Y年n月j日') }}
                        ({{ ['日', '月', '火', '水', '木', '金', '土'][\Carbon\Carbon::parse($selectedDate)->dayOfWeek] }})
                    </div>
                    <button wire:click="changeDate('next')" class="px-3 py-1 border rounded hover:bg-gray-100">
                        ▶
                    </button>
                </div>
            </div>
        </div>


        <!-- タイムライン -->
        <div class="overflow-x-auto" style="position: relative;">
            <!-- 現在時刻インジケーター -->
            @php
                $isToday = \Carbon\Carbon::parse($selectedDate)->isToday();
            @endphp
            @if($isToday)
                @php
                    // 明示的に日本時間を取得
                    $now = \Carbon\Carbon::now('Asia/Tokyo');
                    $currentHour = $now->hour;
                    $currentMinute = $now->minute;
                    $shouldShowIndicator = false;

                    // デバッグ情報をJavaScriptコンソールに出力
                    echo "<script>console.log('🐘 PHP: JST現在時刻: {$currentHour}:{$currentMinute} - 営業時間内？" . ($currentHour >= 9 && $currentHour < 22 ? 'YES' : 'NO') . "');</script>";
                    echo "<script>console.log('🐘 PHP Debug: shouldShow={$shouldShowIndicator}, isToday=" . ($isToday ? 'true' : 'false') . "');</script>";

                    // 営業時間内の場合のみ位置計算（9:00 - 22:00）テスト用に9時から
                    $leftPosition = 0;
                    if ($currentHour >= 9 && $currentHour < 22) { // 22:00以降は表示しない
                        $shouldShowIndicator = true;
                        $minutesFromStart = ($currentHour - 9) * 60 + $currentMinute;
                        $cellIndex = floor($minutesFromStart / 30);
                        $percentageIntoCell = ($minutesFromStart % 30) / 30;
                        $firstCellWidth = 36; // 席ラベルの幅
                        $cellWidth = 48; // 各セルの幅
                        $leftPosition = $firstCellWidth + ($cellIndex * $cellWidth) + ($percentageIntoCell * $cellWidth);
                    }
                @endphp
                @php
                    // 営業時間に関係なく位置計算を行う（JSで制御）
                    if (!$shouldShowIndicator) {
                        $minutesFromStart = ($currentHour - 9) * 60 + $currentMinute;
                        $cellIndex = floor($minutesFromStart / 30);
                        $percentageIntoCell = ($minutesFromStart % 30) / 30;
                        $firstCellWidth = 36;
                        $cellWidth = 48;
                        $leftPosition = $firstCellWidth + ($cellIndex * $cellWidth) + ($percentageIntoCell * $cellWidth);
                    }
                @endphp
                <div id="current-time-indicator" class="current-time-indicator{{ ($currentHour < 9 || $currentHour >= 22) ? ' outside-business-hours' : '' }}" style="left: {{ $leftPosition }}px;">
                    <span class="current-time-text">{{ $now->format('H:i') }}</span>
                </div>
            @endif

            @if(!empty($timelineData))
                <table class="timeline-table">
                    <thead>
                        <tr>
                            <th style="vertical-align: middle;">{{ $useStaffAssignment ? 'スタッフ/ライン' : '席数' }}</th>
                            @php
                                $hourGroups = [];
                                foreach($timelineData['slots'] as $index => $slot) {
                                    $hour = substr($slot, 0, 2);
                                    if (!isset($hourGroups[$hour])) {
                                        $hourGroups[$hour] = 0;
                                    }
                                    $hourGroups[$hour]++;
                                }
                            @endphp
                            @foreach($hourGroups as $hour => $count)
                                <th colspan="{{ $count }}" style="font-weight: bold;">{{ $hour }}:00</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // シフトベースモードの場合はソート順を変更
                            $sortedTimeline = $timelineData['timeline'];
                            if ($useStaffAssignment) {
                                $sortedTimeline = collect($timelineData['timeline'])->sortBy(function($seat, $key) {
                                    // 未指定を最初に、その後スタッフをアルファベット順
                                    if ($seat['type'] === 'unassigned') return '0';
                                    if ($seat['type'] === 'staff') return '1_' . $seat['label'];
                                    return '2_' . $key;
                                })->toArray();
                            }
                        @endphp
                        @foreach($sortedTimeline as $key => $seat)
                            <tr>
                                <td class="seat-label {{ $seat['type'] === 'sub' ? 'sub-time-label' : '' }} {{ $seat['type'] === 'unassigned' ? 'bg-yellow-50 border-yellow-200' : '' }} {{ $seat['type'] === 'staff' ? (($seat['has_shift'] ?? false) ? 'bg-green-50 border-green-200' : 'bg-gray-100 border-gray-300') : '' }}">
                                    @if($seat['type'] === 'unassigned')
                                        <span class="text-yellow-700 font-medium">{{ $seat['label'] }}</span>
                                    @elseif($seat['type'] === 'staff')
                                        @if($seat['has_shift'] ?? false)
                                            <span class="text-green-700 font-medium">👤 {{ $seat['label'] }}</span>
                                        @else
                                            <span class="text-gray-500">👤 {{ $seat['label'] }}<br><small class="text-xs">シフトなし</small></span>
                                        @endif
                                    @else
                                        {{ $seat['label'] }}
                                    @endif
                                </td>
                                @foreach($timelineData['slots'] as $index => $slot)
                                    @php
                                        $hasReservation = false;
                                        foreach($seat['reservations'] as $reservation) {
                                            if($reservation['start_slot'] <= $index && $index < $reservation['start_slot'] + $reservation['span']) {
                                                $hasReservation = true;
                                                break;
                                            }
                                        }
                                        $isBlocked = in_array($index, $timelineData['blockedSlots']);

                                        // 予約可否の詳細情報を取得
                                        $availabilityResult = null;
                                        $tooltipMessage = '';
                                        if (!$hasReservation && !$isBlocked && isset($currentStore)) {
                                            $endTime = \Carbon\Carbon::parse($slot)->addMinutes($currentStore->reservation_slot_duration ?? 30)->format('H:i');
                                            $availabilityResult = $this->canReserveAtTimeSlot($slot, $endTime, $currentStore, \Carbon\Carbon::parse($selectedDate));

                                            if (!$availabilityResult['can_reserve']) {
                                                $tooltipMessage = $availabilityResult['reason'] ?: '予約不可';
                                            } else {
                                                $tooltipMessage = "予約可能（空き: {$availabilityResult['available_slots']}/{$availabilityResult['total_capacity']}席）";
                                            }
                                        }

                                        // シフトベースモードでスタッフ不在チェック
                                        $hasNoStaff = false;
                                        if (isset($timelineData['useStaffAssignment']) && $timelineData['useStaffAssignment']) {
                                            // スタッフラインの場合
                                            if ($seat['type'] === 'staff') {
                                                if (!isset($seat['has_shift']) || !$seat['has_shift']) {
                                                    // シフトがないスタッフは全時間帯不可
                                                    $hasNoStaff = true;
                                                } elseif (isset($seat['shift'])) {
                                                    $shift = $seat['shift'];
                                                    $slotTime = \Carbon\Carbon::parse($selectedDate . ' ' . $slot);
                                                    $shiftStart = \Carbon\Carbon::parse($shift->start_time);
                                                    $shiftEnd = \Carbon\Carbon::parse($shift->end_time);

                                                    // シフト時間外は不可
                                                    if (!$slotTime->between($shiftStart, $shiftEnd)) {
                                                        $hasNoStaff = true;
                                                    }
                                                }
                                            }
                                            // 未指定ラインの場合、availabilityResultで判定（スタッフがいない時間は不可）
                                            elseif ($seat['type'] === 'unassigned' && $availabilityResult && !$availabilityResult['can_reserve']) {
                                                // canReserveAtTimeSlotがfalseなら、スタッフ不在として扱う
                                                if (strpos($availabilityResult['reason'] ?? '', 'スタッフ') !== false) {
                                                    $hasNoStaff = true;
                                                }
                                            }
                                            // サブラインは独立して利用可能
                                        }
                                        
                                        // 過去の時間帯かチェック（現在時刻から1時間前まで許可）
                                        $slotDateTime = \Carbon\Carbon::parse($selectedDate . ' ' . $slot);
                                        $minimumTime = \Carbon\Carbon::now()->subHours(1);
                                        $isPast = $slotDateTime->lt($minimumTime);

                                        // 統合的な予約可能性判定を使用（容量制限も考慮）
                                        $isClickable = false;

                                        if (!$hasReservation && !$isBlocked && !$isPast) {
                                            // スタッフシフトモードでは、availabilityResultの判定を優先
                                            if (isset($timelineData['useStaffAssignment']) && $timelineData['useStaffAssignment']) {
                                                if ($availabilityResult) {
                                                    $isClickable = $availabilityResult['can_reserve'] ?? false;
                                                    // スタッフ不在の場合は、どのラインもクリック不可
                                                    if (!$isClickable && strpos($availabilityResult['reason'] ?? '', 'スタッフ') !== false) {
                                                        $hasNoStaff = true;
                                                    }
                                                }
                                            } else {
                                                // 営業時間ベースモードの判定
                                                try {
                                                    if ($availabilityResult) {
                                                        $isClickable = $availabilityResult['can_reserve'] ?? false;
                                                    }
                                                } catch (\Exception $e) {
                                                    // エラーの場合は従来の個別判定にフォールバック
                                                    $isWithinBusinessHours = true;
                                                    $store = $currentStore;
                                                    if ($store) {
                                                        $dayOfWeek = $slotDateTime->format('l');
                                                        $closingTime = '20:00'; // デフォルト

                                                        if (isset($store->business_hours[$dayOfWeek])) {
                                                            $closingTime = $store->business_hours[$dayOfWeek]['close'] ?? '20:00';
                                                        } elseif (isset($store->business_hours['close'])) {
                                                            $closingTime = $store->business_hours['close'];
                                                        }

                                                        $closingDateTime = \Carbon\Carbon::parse($selectedDate . ' ' . $closingTime);
                                                        $minEndTime = $slotDateTime->copy()->addMinutes(60);
                                                        $isWithinBusinessHours = $minEndTime->lte($closingDateTime);
                                                    }
                                                    $isClickable = !$hasNoStaff && $isWithinBusinessHours;
                                                }
                                            }
                                        }
                                        $isPastClickable = !$hasReservation && !$isBlocked && $isPast && !$hasNoStaff;
                                    @endphp
                                    <td class="time-cell {{ $isBlocked ? 'blocked-cell' : '' }} {{ $hasNoStaff ? 'no-staff-cell' : '' }} {{ $isPast ? 'past-time-cell' : '' }} {{ $isClickable ? 'empty-slot clickable-slot' : ($isPastClickable ? 'past-clickable' : '') }}"
                                        @if($isClickable)
                                            wire:click="openNewReservationFromSlot('{{ $key }}', '{{ $slot }}')"
                                            style="cursor: pointer; position: relative;"
                                            onmouseover="this.style.backgroundColor='{{ $seat['type'] === 'unassigned' ? '#fef3c7' : ($seat['type'] === 'staff' ? '#d1fae5' : '#e3f2fd') }}'"
                                            onmouseout="this.style.backgroundColor=''"
                                            title="{{ $tooltipMessage ?: 'クリックして予約を作成' }}{{ $seat['type'] === 'staff' ? ' (' . $seat['label'] . ')' : '' }}"
                                        @elseif($isPastClickable)
                                            onclick="alert('過去の時間帯です。\n予約は開始時刻の1時間前まで受け付けています。')"
                                            style="cursor: not-allowed; position: relative;"
                                            title="過去の時間帯です（予約開始1時間前まで受付）"
                                        @elseif(!$hasReservation && !$isBlocked)
                                            style="cursor: not-allowed; position: relative; opacity: 0.6;"
                                            title="{{ $tooltipMessage ?: ($hasNoStaff ? 'スタッフのシフトがありません' : '予約不可') }}"
                                        @endif>
                                        @if($isBlocked)
                                            <div style="background: #9e9e9e; color: white; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                                                BRK
                                            </div>
                                        @else
                                            @foreach($seat['reservations'] as $reservation)
                                                @if($reservation['start_slot'] == $index)
                                                    <div class="booking-block 
                                                        course-{{ $reservation['course_type'] }}
                                                        span-{{ ceil($reservation['span']) }}
                                                        {{ $reservation['is_conflicting'] ?? false ? 'conflicting-reservation' : '' }}"
                                                        wire:click="openReservationDetail({{ $reservation['id'] }})">
                                                        <div class="booking-name">
                                                            @if($reservation['is_new_customer'] ?? false)
                                                                <span style="background: #ff6b6b; color: white; padding: 1px 4px; border-radius: 3px; font-size: 10px; margin-right: 3px;">NEW</span>
                                                            @endif
                                                            {{ $reservation['customer_name'] }}
                                                        </div>
                                                        <div class="booking-menu">{{ $reservation['menu_name'] }}</div>
                                                        @if($reservation['staff_name'])
                                                            <div style="font-size: 10px; color: #666; margin-top: 2px;">
                                                                👤 {{ $reservation['staff_name'] }}
                                                            </div>
                                                        @endif
                                                        @if($reservation['is_conflicting'] ?? false)
                                                            <div style="color: red; font-size: 10px; font-weight: bold;">⚠️ 競合</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="text-center py-8 text-gray-500">
                    データがありません
                </div>
            @endif
        </div>
        
        <!-- 凡例（店舗フィルター適用） -->
        <div class="flex flex-wrap gap-4 mt-4 text-sm">
            @if(!empty($categories))
                @foreach($categories as $category)
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded course-{{ $category['color_class'] }} border"></div>
                        <span>{{ $category['name'] }}</span>
                    </div>
                @endforeach
            @endif
        </div>

        <!-- JavaScript for Current Time Indicator -->
        <script>
            console.log('タイムラインインジケータースクリプト読み込み開始');

            // 🚨 EMERGENCY: 営業時間外の強制削除（完全版）
            function emergencyRemoveIndicator() {
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();

                console.log('🚨 EMERGENCY CHECK: JST時刻=' + currentHour + '時');

                if (currentHour < 10 || currentHour >= 22) {
                    console.log('🚨 EMERGENCY: 営業時間外で強制削除実行');
                    // より包括的な削除
                    const selectors = [
                        '#current-time-indicator',
                        '.current-time-indicator',
                        '[class*="current-time"]',
                        '[style*="background: #ef4444"]',
                        '[style*="background:#ef4444"]',
                        'div[style*="position: absolute"][style*="width: 2px"]'
                    ];

                    selectors.forEach(selector => {
                        const elements = document.querySelectorAll(selector);
                        elements.forEach(el => {
                            console.log('🚨 要素削除:', selector, el);
                            el.remove();
                        });
                    });
                } else {
                    console.log('✅ EMERGENCY CHECK: 営業時間内のため削除しない');
                }
            }

            // 即座に実行
            emergencyRemoveIndicator();

            // 定期実行
            setInterval(emergencyRemoveIndicator, 5000);

            function createTimeIndicator() {
                console.log('createTimeIndicator 実行開始');

                // 日本時間で現在時刻を取得
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();
                const currentMinute = jstDate.getMinutes();

                // 🚨 緊急停止: 営業時間外は何もしない
                if (currentHour < 10 || currentHour >= 22) {
                    console.log('🚫 createTimeIndicator: 営業時間外のため処理停止');
                    return;
                }

                console.log(`🕒 JST現在時刻: ${currentHour}:${String(currentMinute).padStart(2, '0')}`);
                console.log(`🕒 ローカル時刻（参考）: ${new Date().getHours()}:${String(new Date().getMinutes()).padStart(2, '0')}`);
                console.log(`📋 営業時間判定: 10時以前？${currentHour < 10} / 22時以降？${currentHour >= 22}`);

                // 営業時間チェック（22:00以降は表示しない）
                if (currentHour < 10 || currentHour >= 22) {
                    console.log('🚫 営業時間外のためインジケーター削除');
                    // 既存のインジケーターを削除
                    const existing = document.getElementById('current-time-indicator');
                    if (existing) {
                        console.log('🗑️ 既存インジケーター削除実行');
                        existing.remove();
                    } else {
                        console.log('ℹ️ 削除対象のインジケーターが見つからない');
                    }
                    return;
                }

                console.log('✅ 営業時間内のためインジケーター表示処理を続行');

                // 要素を探す
                const table = document.querySelector('.timeline-table');
                const container = document.querySelector('.overflow-x-auto');

                if (!table || !container) {
                    console.log('必要な要素が見つかりません', { table, container });
                    return;
                }

                // 既存のインジケーターを削除
                const existing = document.getElementById('current-time-indicator');
                if (existing) {
                    existing.remove();
                }

                // インジケーター作成
                const indicator = document.createElement('div');
                indicator.id = 'current-time-indicator';
                indicator.style.cssText = `
                    position: absolute;
                    left: 0px;
                    top: 60px;
                    width: 2px;
                    height: calc(100% - 60px);
                    background: #ef4444;
                    z-index: 1000;
                    pointer-events: none;
                    box-shadow: 0 0 10px rgba(239, 68, 68, 0.8);
                `;

                container.style.position = 'relative';
                container.appendChild(indicator);

                // 位置計算と更新を遅延実行
                setTimeout(() => {
                    const firstRow = table.querySelector('tbody tr');
                    if (!firstRow) {
                        console.log('データ行が見つかりません');
                        return;
                    }

                    const cells = firstRow.querySelectorAll('td');
                    if (cells.length < 2) {
                        console.log('十分なセルがありません');
                        return;
                    }

                    const firstCellWidth = cells[0].offsetWidth;
                    const cellWidth = cells[1].offsetWidth;

                    console.log(`実測値: 席幅=${firstCellWidth}px, セル幅=${cellWidth}px`);

                    if (firstCellWidth === 0 || cellWidth === 0) {
                        console.log('セル幅が0、再試行します');
                        // さらに遅延して再試行
                        setTimeout(() => {
                            const retryFirstCellWidth = cells[0].offsetWidth || 36;
                            const retryCellWidth = cells[1].offsetWidth || 48;

                            const minutesFromStart = (currentHour - 10) * 60 + currentMinute;
                            const cellIndex = Math.floor(minutesFromStart / 30);
                            const percentageIntoCell = (minutesFromStart % 30) / 30;
                            const leftPosition = retryFirstCellWidth + (cellIndex * retryCellWidth) + (percentageIntoCell * retryCellWidth);

                            indicator.style.left = leftPosition + 'px';
                            console.log(`再試行結果: 左位置=${leftPosition}px`);
                        }, 500);
                        return;
                    }

                    // 時間計算
                    const minutesFromStart = (currentHour - 10) * 60 + currentMinute;
                    const cellIndex = Math.floor(minutesFromStart / 30);
                    const percentageIntoCell = (minutesFromStart % 30) / 30;
                    const leftPosition = firstCellWidth + (cellIndex * cellWidth) + (percentageIntoCell * cellWidth);

                    console.log(`計算結果: 左位置=${leftPosition}px, セルインデックス=${cellIndex}`);

                    indicator.style.left = leftPosition + 'px';

                    // 時刻テキストも更新
                    const timeText = indicator.querySelector('.current-time-text');
                    if (timeText) {
                        timeText.textContent = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
                    }

                    console.log('インジケーター位置更新完了');
                }, 200);

                console.log('インジケーター作成完了');
            }

            // リアルタイム更新用の関数
            function updateTimeIndicator() {
                // 日本時間で現在時刻を取得
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();
                const currentMinute = jstDate.getMinutes();

                console.log(`🔄 updateTimeIndicator: JST現在時刻: ${currentHour}:${String(currentMinute).padStart(2, '0')}`);
                console.log(`🔄 updateTimeIndicator: 営業時間判定: 10時以前？${currentHour < 10} / 22時以降？${currentHour >= 22}`);

                // 営業時間外の場合はインジケーターを削除
                if (currentHour < 10 || currentHour >= 22) {
                    console.log('🔄 🚫 updateTimeIndicator: 営業時間外のためインジケーター削除');
                    const existing = document.getElementById('current-time-indicator');
                    if (existing) {
                        console.log('🔄 🗑️ updateTimeIndicator: 既存インジケーター削除実行');
                        existing.remove();
                    }
                    return;
                }

                // 既存のインジケーターがある場合は位置と時刻を更新
                const indicator = document.getElementById('current-time-indicator');
                if (indicator) {
                    const table = document.querySelector('.timeline-table');
                    if (table) {
                        const firstRow = table.querySelector('tbody tr');
                        if (firstRow) {
                            const cells = firstRow.querySelectorAll('td');
                            if (cells.length >= 2) {
                                const firstCellWidth = cells[0].offsetWidth || 36;
                                const cellWidth = cells[1].offsetWidth || 48;

                                const minutesFromStart = (currentHour - 10) * 60 + currentMinute;
                                const cellIndex = Math.floor(minutesFromStart / 30);
                                const percentageIntoCell = (minutesFromStart % 30) / 30;
                                const leftPosition = firstCellWidth + (cellIndex * cellWidth) + (percentageIntoCell * cellWidth);

                                indicator.style.left = leftPosition + 'px';

                                const timeText = indicator.querySelector('.current-time-text');
                                if (timeText) {
                                    timeText.textContent = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
                                }
                            }
                        }
                    }
                } else {
                    // インジケーターがない場合は作成
                    createTimeIndicator();
                }
            }

            // 実行
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOMContentLoaded - 営業時間チェック後にインジケーター作成開始');

                // 営業時間チェック
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();

                console.log('🕒 DOMContentLoaded: JST現在時刻=' + currentHour + '時');

                // 営業時間外でもPHP側で作成されていればJavaScriptで制御
                const indicator = document.getElementById('current-time-indicator');
                if (indicator) {
                    if (currentHour < 9 || currentHour >= 22) {
                        console.log('❌ 営業時間外のため赤線を非表示');
                        indicator.classList.add('outside-business-hours');
                        return;
                    } else {
                        console.log('✅ 営業時間内のため赤線を表示');
                        indicator.classList.remove('outside-business-hours');
                    }
                }

                console.log('✅ DOMContentLoaded: 営業時間内のためインジケーター作成');
                setTimeout(createTimeIndicator, 1000);

                // 1分ごとにリアルタイム更新
                setInterval(updateTimeIndicator, 60000);
            });

            // 即座にも実行（営業時間チェック付き）
            setTimeout(function() {
                console.log('即座実行 - 営業時間チェック後にインジケーター作成');

                // 営業時間チェック
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();

                console.log('🕒 即座実行: JST現在時刻=' + currentHour + '時');

                if (currentHour < 10 || currentHour >= 22) {
                    console.log('🚫 即座実行: 営業時間外のため作成しない');
                    return;
                }

                console.log('✅ 即座実行: 営業時間内のためインジケーター作成');
                createTimeIndicator();
            }, 2000);

            // グローバルに公開
            window.createTimeIndicator = createTimeIndicator;
            window.updateTimeIndicator = updateTimeIndicator;

        </script>

        <!-- Tom Select JavaScript -->
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
        <script>
            // Tom Selectの初期化関数
            function initializeMenuSelect() {
                // セレクトボックスを探す
                const menuSelect = document.querySelector('select[wire\\:model="newReservation.menu_id"]');

                if (menuSelect) {
                    // 既存のTomSelectインスタンスがある場合は破棄
                    if (menuSelect.tomselect) {
                        menuSelect.tomselect.destroy();
                    }

                    // Tom Selectを初期化
                    try {
                        new TomSelect(menuSelect, {
                            searchField: ['text'],
                            placeholder: 'メニューを検索・選択...',
                            maxOptions: null,
                            create: false,
                            allowEmptyOption: true,
                            render: {
                                option: function(data, escape) {
                                    return '<div>' + escape(data.text) + '</div>';
                                },
                                item: function(data, escape) {
                                    return '<div>' + escape(data.text) + '</div>';
                                },
                                no_results: function(data, escape) {
                                    return '<div class="no-results">該当するメニューがありません</div>';
                                }
                            },
                            onChange: function(value) {
                                // Livewireのモデルを更新
                                menuSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                        console.log('✅ Tom Select initialized successfully');
                    } catch (error) {
                        console.error('❌ Tom Select initialization error:', error);
                    }
                }
            }

            // DOMContentLoadedイベント
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOMContentLoaded - initializing Tom Select');
                setTimeout(initializeMenuSelect, 500);
            });

            // Livewireイベント
            if (typeof Livewire !== 'undefined') {
                document.addEventListener('livewire:load', function() {
                    console.log('Livewire loaded');

                // modal-openedイベントをリッスン
                window.Livewire.on('modal-opened', () => {
                    console.log('Modal opened event received');
                    setTimeout(initializeMenuSelect, 300);
                });

                // Livewireの更新後
                window.Livewire.hook('message.processed', (message, component) => {
                    // reservationStep が 3 の時のみ初期化
                    if (component.fingerprint && component.fingerprint.name === 'app.filament.widgets.reservation-timeline-widget') {
                        const stepElement = document.querySelector('[wire\\:model="reservationStep"]');
                        if (stepElement && stepElement.value === '3') {
                            setTimeout(initializeMenuSelect, 300);
                        }
                    }
                });
            });

            // MutationObserverでモーダルの表示を監視
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // 新規予約モーダルが追加されたか確認
                        const menuSelect = document.querySelector('select[wire\\:model="newReservation.menu_id"]');
                        if (menuSelect && !menuSelect.tomselect) {
                            console.log('Menu select detected by MutationObserver');
                            setTimeout(initializeMenuSelect, 100);
                        }
                    }
                });
            });

            // body要素を監視
            document.addEventListener('DOMContentLoaded', function() {
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });

            // 手動初期化用のグローバル関数
            window.initMenuSelect = initializeMenuSelect;
        </script>

        <!-- Alpine.jsコンテキストの分離 -->
        <script>
            document.addEventListener('alpine:init', () => {
                // タイムラインウィジェット専用のAlpineコンポーネントを定義
                Alpine.data('timelineWidget', () => ({
                    init() {
                        // タイムラインウィジェットの初期化
                        console.log('Timeline widget initialized');
                    },
                    // Filamentテーブルコンポーネントの関数をダミーで定義（エラー回避）
                    isRecordSelected: () => false,
                    isGroupCollapsed: () => false,
                    table: null
                }));
            });

            // グローバルにもダミー関数を定義（フォールバック）
            if (typeof window.isRecordSelected === 'undefined') {
                window.isRecordSelected = () => false;
            }
            if (typeof window.isGroupCollapsed === 'undefined') {
                window.isGroupCollapsed = () => false;
            }

            // 予約データクリアイベント
            window.addEventListener('clear-reservation-data', () => {
                console.log('Clearing reservation data from session/local storage');
                // セッションストレージをクリア
                sessionStorage.removeItem('selectedCustomer');
                sessionStorage.removeItem('phoneSearch');
                sessionStorage.removeItem('reservationStep');
                sessionStorage.removeItem('newCustomer');
                sessionStorage.removeItem('newReservation');

                // ローカルストレージもクリア
                localStorage.removeItem('lastSelectedCustomer');
                localStorage.removeItem('lastPhoneSearch');
            });

            // モーダル開閉イベントのリスナー
            window.addEventListener('modal-opened', () => {
                console.log('Modal opened event received');
                // Alpine.jsコンポーネントを再初期化
                if (typeof Alpine !== 'undefined') {
                    Alpine.nextTick(() => {
                        console.log('Alpine components refreshed');
                    });
                }
            });

            window.addEventListener('modal-closed', () => {
                console.log('Modal closed event received');
                // モーダルが閉じた後のクリーンアップ
                setTimeout(() => {
                    // Tom Selectの再初期化が必要な場合
                    if (typeof initMenuSelect !== 'undefined') {
                        initMenuSelect();
                    }
                }, 100);
            });
        </script>
    </x-filament::card>
    
    <!-- 予約詳細パネル -->
    @if($selectedReservation)
        <div
            x-data="{
                show: true,
                close() {
                    this.show = false;
                    setTimeout(() => {
                        @this.closeReservationDetailModal();
                    }, 300);
                }
            }"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="close()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        >
            <div 
                x-on:click.stop
                class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg"
            >
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">予約詳細</h3>
                    <button
                        x-on:click="close()"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        ✕
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-gray-50 p-3 rounded">
                        <p class="text-xs text-gray-500 mb-1">予約番号</p>
                        <p class="font-mono text-sm">{{ $selectedReservation->reservation_number }}</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">顧客名</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->is_new_customer ?? false)
                                    <span style="background: #ff6b6b; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 4px;">NEW</span>
                                @endif
                                {{ $selectedReservation->customer->last_name ?? '' }} {{ $selectedReservation->customer->first_name ?? '' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">顧客ステータス</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->is_new_customer ?? false)
                                    <span class="inline-block px-2 py-1 bg-red-100 text-red-700 rounded text-xs">新規顧客</span>
                                @else
                                    <span class="inline-block px-2 py-1 bg-green-100 text-green-700 rounded text-xs">
                                        既存顧客（{{ $selectedReservation->customer_visit_count ?? 0 }}回目）
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">メニュー</p>
                            <p class="text-sm font-medium">{{ $selectedReservation->menu->name ?? 'なし' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">日時</p>
                            <p class="text-sm font-medium">
                                {{ \Carbon\Carbon::parse($selectedReservation->reservation_date)->format('m/d') }}
                                {{ \Carbon\Carbon::parse($selectedReservation->start_time)->format('H:i') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">担当スタッフ</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->staff)
                                    <span class="inline-block px-2 py-1 bg-green-100 text-green-700 rounded text-xs">
                                        👤 {{ $selectedReservation->staff->name }}
                                    </span>
                                @else
                                    <span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">未割当</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">現在の配置</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->is_sub)
                                    <span class="inline-block px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">サブ枠</span>
                                @else
                                    <span class="inline-block px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">席{{ $selectedReservation->seat_number }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- 座席移動セクション --}}
                    @include('filament.widgets.reservation-detail-modal-movement')
                </div>
            </div>
        </div>
    @endif

    {{-- 新規予約モーダル --}}
    @if($showNewReservationModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto py-6 px-4" wire:click="closeNewReservationModal">
            <div class="bg-white rounded-lg p-6 max-w-2xl mx-auto relative" @click.stop="" style="min-height: min-content;">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">
                        @if($modalMode === 'block')
                            予約ブロック設定
                        @else
                            新規予約作成
                        @endif
                    </h2>
                    <button wire:click="closeNewReservationModal" class="text-gray-500 hover:text-gray-700">
                        <x-heroicon-s-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <!-- モード選択タブ -->
                @php
                    $user = auth()->user();
                    $canCreateBlock = $user->hasRole(['super_admin', 'owner', 'manager']);
                @endphp
                @if($reservationStep === 1 || $modalMode === 'block')
                    <div class="flex gap-2 mb-6 border-b border-gray-200">
                        <button
                            wire:click="$set('modalMode', 'reservation')"
                            class="px-4 py-2 -mb-px {{ $modalMode === 'reservation' ? 'border-b-2 border-primary-600 text-primary-600 font-medium' : 'text-gray-600 hover:text-gray-900' }} transition">
                            <x-heroicon-o-calendar class="w-5 h-5 inline mr-1" />
                            予約作成
                        </button>
                        @if($canCreateBlock)
                            <button
                                wire:click="$set('modalMode', 'block')"
                                class="px-4 py-2 -mb-px {{ $modalMode === 'block' ? 'border-b-2 border-red-600 text-red-600 font-medium' : 'text-gray-600 hover:text-gray-900' }} transition">
                                <x-heroicon-o-no-symbol class="w-5 h-5 inline mr-1" />
                                予約ブロック
                            </button>
                        @endif
                    </div>
                @endif

                @if($modalMode === 'block')
                    <!-- 予約ブロック設定フォーム -->
                    <div class="space-y-4">
                        <!-- 選択された時間と席の情報 -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <div class="text-sm font-medium text-red-900">
                                ブロック開始: {{ $blockSettings['date'] }} {{ $blockSettings['start_time'] }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">終了時間 <span class="text-red-500">*</span></label>
                            <input
                                type="time"
                                wire:model="blockSettings.end_time"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                                required>
                            @if($blockSettings['end_time'] && $blockSettings['end_time'] <= $blockSettings['start_time'])
                                <p class="text-red-500 text-sm mt-1">終了時間は開始時間より後に設定してください</p>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">理由 <span class="text-red-500">*</span></label>
                            <select
                                wire:model="blockSettings.reason"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="休憩">休憩</option>
                                <option value="清掃">清掃</option>
                                <option value="メンテナンス">メンテナンス</option>
                                <option value="研修">研修</option>
                                <option value="その他">その他</option>
                            </select>
                        </div>

                        <div class="border-t pt-4">
                            <p class="text-sm text-gray-600 mb-2">
                                <x-heroicon-o-information-circle class="w-4 h-4 inline" />
                                設定した時間帯は予約を受け付けられなくなります
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <button
                                wire:click="createBlockedTime"
                                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                                ブロックを設定
                            </button>
                        </div>
                    </div>
                @else
                    <!-- 予約作成モード -->
                    @if($reservationStep === 1)
                    <!-- Step 1: 顧客選択 -->
                    <div class="space-y-4">
                        <!-- 選択された時間と席の情報 -->
                        @if(!empty($newReservation['start_time']))
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="text-sm font-medium text-blue-900">
                                    予約時間: {{ $newReservation['date'] }} {{ $newReservation['start_time'] }}
                                    @if($useStaffAssignment)
                                        @if($newReservation['line_type'] === 'staff')
                                            @php
                                                $selectedStaff = \App\Models\User::find($newReservation['staff_id']);
                                            @endphp
                                            （👤 {{ $selectedStaff ? $selectedStaff->name : 'スタッフ' }}）
                                        @elseif($newReservation['line_type'] === 'unassigned')
                                            （未指定ライン）
                                        @endif
                                    @else
                                        @if($newReservation['line_type'] === 'main')
                                            （席{{ $newReservation['line_number'] }}）
                                        @else
                                            （サブライン）
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif
                        
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <button 
                                wire:click="$set('customerSelectionMode', 'existing')"
                                class="px-4 py-2 {{ $customerSelectionMode === 'existing' ? 'bg-primary-600 text-white' : 'bg-gray-100' }} rounded-lg transition">
                                既存顧客
                            </button>
                            <button 
                                wire:click="$set('customerSelectionMode', 'new')"
                                class="px-4 py-2 {{ $customerSelectionMode === 'new' ? 'bg-primary-600 text-white' : 'bg-gray-100' }} rounded-lg transition">
                                新規顧客
                            </button>
                        </div>
                        
                        @if($customerSelectionMode === 'existing')
                            <div>
                                <label class="block text-sm font-medium mb-2">電話番号・名前で検索</label>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="phoneSearch"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="電話番号または名前を入力"
                                    autofocus>
                            </div>
                        @else
                            <button 
                                wire:click="$set('reservationStep', 2); $set('newCustomer.phone', phoneSearch)"
                                class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                                新規顧客情報を入力
                            </button>
                        @endif
                        
                        @if(strlen($phoneSearch) >= 2)
                            @if(count($searchResults) > 0)
                                <div class="border rounded-lg divide-y">
                                    <div class="bg-gray-50 px-4 py-2 font-medium text-sm">
                                        検索結果 ({{ count($searchResults) }}件)
                                    </div>
                                    @foreach($searchResults as $customer)
                                        <div 
                                            wire:click="selectCustomer({{ $customer->id }})"
                                            class="px-4 py-3 hover:bg-blue-50 cursor-pointer transition">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <div class="font-medium">
                                                        {{ $customer->last_name }} {{ $customer->first_name }}
                                                        <span class="text-sm text-gray-500">({{ $customer->last_name_kana }} {{ $customer->first_name_kana }})</span>
                                                    </div>
                                                    <div class="text-sm text-gray-600">
                                                        📞 {{ $customer->phone }}
                                                        @if($customer->email)
                                                            | ✉️ {{ $customer->email }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-right text-sm">
                                                    <div class="text-gray-500">来店回数: {{ $customer->reservations_count ?? 0 }}回</div>
                                                    @if($customer->last_visit_date)
                                                        <div class="text-gray-500">最終: {{ \Carbon\Carbon::parse($customer->last_visit_date)->format('n/j') }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600" />
                                        <span class="font-medium">該当する顧客が見つかりません</span>
                                    </div>
                                    <button 
                                        wire:click="startNewCustomerRegistration"
                                        class="w-full mt-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                                        新規顧客として登録
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
                
                <!-- Step 2: 新規顧客登録 -->
                @if($reservationStep === 2)
                    <div class="space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                            <div class="font-medium text-blue-900">新規顧客登録</div>
                            <div class="text-sm text-blue-700">電話番号: {{ $phoneSearch }}</div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">姓 <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    wire:model="newCustomer.last_name"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="山田">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">名 <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    wire:model="newCustomer.first_name"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="太郎">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium mb-1">電話番号 <span class="text-red-500">*</span></label>
                                <input 
                                    type="tel" 
                                    wire:model="newCustomer.phone"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="090-1234-5678">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium mb-1">メールアドレス</label>
                                <input 
                                    type="email" 
                                    wire:model="newCustomer.email"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="yamada@example.com">
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <button 
                                wire:click="$set('reservationStep', 1)"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                                戻る
                            </button>
                            <button 
                                wire:click="createNewCustomer"
                                class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                                登録して予約作成へ
                            </button>
                        </div>
                    </div>
                @endif
                
                <!-- Step 3: 予約詳細入力 -->
                @if($reservationStep === 3)
                    <div class="space-y-4">
                        @if($selectedCustomer)
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="font-medium text-green-900">
                                    {{ $selectedCustomer->last_name }} {{ $selectedCustomer->first_name }} 様
                                </div>
                                <div class="text-sm text-green-700">
                                    📞 {{ $selectedCustomer->phone }}
                                    @if($selectedCustomer->email)
                                        | ✉️ {{ $selectedCustomer->email }}
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- メニュー選択を先に配置 -->
                        <div>
                            <label class="block text-sm font-medium mb-2">メニュー</label>

                            <!-- よく使うメニューのクイック選択ボタン -->
                            @php
                                $popularMenus = \App\Models\Menu::where('is_available', true)
                                    ->where('is_visible_to_customer', true)
                                    ->whereIn('name', ['視力回復コース(60分)', '水素吸入コース(90分)', 'サブスク60分'])
                                    ->orderBy('is_subscription', 'desc')
                                    ->limit(3)
                                    ->get();
                            @endphp

                            @if($popularMenus->count() > 0)
                                <div class="mb-3">
                                    <p class="text-xs text-gray-500 mb-2">よく使うメニュー：</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($popularMenus as $menu)
                                            <button
                                                type="button"
                                                wire:click="selectMenu({{ $menu->id }})"
                                                class="px-3 py-2 text-xs border rounded-lg hover:bg-blue-50 hover:border-blue-400 transition-colors {{ $newReservation['menu_id'] == $menu->id ? 'bg-blue-50 border-blue-500 text-blue-700' : 'bg-white border-gray-300' }}">
                                                <div class="font-medium">
                                                    {{ $menu->is_subscription ? '🔄 ' : '' }}{{ Str::limit($menu->name, 20) }}
                                                </div>
                                                <div class="text-gray-500 text-xs mt-1">
                                                    {{ $menu->duration_minutes }}分
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- 検索ボックス改良版 -->
                            <div class="relative">
                                <div class="relative">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.200ms="menuSearch"
                                        wire:focus="$set('showAllMenus', true)"
                                        placeholder="クリックで全メニュー表示 / 入力で検索"
                                        class="w-full px-3 py-2 pl-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 cursor-pointer">
                                    <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                                    </svg>
                                </div>

                                @if($menuSearch || $showAllMenus)
                                    <!-- 検索結果/全メニューのドロップダウン -->
                                    <div class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-xl max-h-80 overflow-y-auto"
                                         x-data
                                         @click.outside="@this.set('showAllMenus', false)">
                                        @php
                                            $displayMenus = $menuSearch ? $this->getFilteredMenus() : \App\Models\Menu::where('is_available', true)
                                                ->where('is_visible_to_customer', true)
                                                ->orderBy('is_subscription', 'desc')
                                                ->orderBy('sort_order')
                                                ->get();
                                        @endphp

                                        @if($displayMenus->count() > 0)
                                            {{-- サブスクメニュー --}}
                                            @php
                                                $subscriptionMenus = $displayMenus->where('is_subscription', true);
                                            @endphp
                                            @if($subscriptionMenus->count() > 0)
                                                <div class="border-b border-gray-200">
                                                    <div class="px-4 py-2 bg-blue-50 text-xs font-semibold text-blue-700 sticky top-0">
                                                        サブスクリプション
                                                    </div>
                                                    @foreach($subscriptionMenus as $menu)
                                                        <button
                                                            type="button"
                                                            wire:click="selectMenu({{ $menu->id }})"
                                                            class="w-full px-4 py-3 text-left hover:bg-blue-50 focus:bg-blue-50 focus:outline-none transition-colors">
                                                            <div class="flex items-center justify-between">
                                                                <div>
                                                                    <div class="font-medium text-blue-600">
                                                                        🔄 {{ $menu->name }}
                                                                    </div>
                                                                    <div class="text-sm text-gray-600">
                                                                        {{ $menu->duration_minutes }}分 - サブスク
                                                                    </div>
                                                                </div>
                                                                @if($newReservation['menu_id'] == $menu->id)
                                                                    <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- 通常メニュー --}}
                                            @php
                                                $regularMenus = $displayMenus->where('is_subscription', false);
                                            @endphp
                                            @if($regularMenus->count() > 0)
                                                <div>
                                                    <div class="px-4 py-2 bg-gray-50 text-xs font-semibold text-gray-600 sticky top-0">
                                                        通常メニュー
                                                    </div>
                                                    @foreach($regularMenus as $menu)
                                                        <button
                                                            type="button"
                                                            wire:click="selectMenu({{ $menu->id }})"
                                                            class="w-full px-4 py-3 text-left hover:bg-gray-50 focus:bg-gray-50 focus:outline-none border-b border-gray-100 last:border-b-0 transition-colors">
                                                            <div class="flex items-center justify-between">
                                                                <div>
                                                                    <div class="font-medium text-gray-900">
                                                                        {{ $menu->name }}
                                                                    </div>
                                                                    <div class="text-sm text-gray-600">
                                                                        {{ $menu->duration_minutes }}分 - ¥{{ number_format($menu->price) }}
                                                                    </div>
                                                                </div>
                                                                @if($newReservation['menu_id'] == $menu->id)
                                                                    <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            <div class="px-4 py-3 text-gray-500 text-center">
                                                該当するメニューが見つかりません
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- 選択されたメニューの表示 -->
                            @if($newReservation['menu_id'])
                                @php
                                    $selectedMenu = \App\Models\Menu::find($newReservation['menu_id']);
                                @endphp
                                @if($selectedMenu)
                                    <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-medium text-blue-900">{{ $selectedMenu->name }}</div>
                                                <div class="text-sm text-blue-700">
                                                    {{ $selectedMenu->duration_minutes }}分 - ¥{{ number_format($selectedMenu->price) }}
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="$set('newReservation.menu_id', '')"
                                                class="text-blue-600 hover:text-blue-800">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <p class="mt-2 text-sm text-gray-500">メニューを選択してください</p>
                            @endif
                        </div>

                        <!-- 予約日時セクション -->
                        <div>
                            <label class="block text-sm font-medium mb-1">予約日時</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    type="date"
                                    wire:model="newReservation.date"
                                    value="{{ $selectedDate }}"
                                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <input
                                    type="time"
                                    wire:model="newReservation.start_time"
                                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                            </div>

                            <!-- 所要時間の表示（メニュー選択後のみ） -->
                            @if($newReservation['menu_id'])
                                @php
                                    $selectedMenuDuration = \App\Models\Menu::find($newReservation['menu_id']);
                                @endphp
                                @if($selectedMenuDuration)
                                    <div class="mt-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">
                                        <span class="text-sm text-gray-600">所要時間：</span>
                                        <span class="font-medium">{{ $selectedMenuDuration->duration_minutes }}分</span>
                                        @if($newReservation['start_time'])
                                            @php
                                                $endTime = \Carbon\Carbon::parse($newReservation['start_time'])
                                                    ->addMinutes($selectedMenuDuration->duration_minutes)
                                                    ->format('H:i');
                                            @endphp
                                            <span class="text-sm text-gray-600 ml-2">
                                                ({{ $newReservation['start_time'] }} 〜 {{ $endTime }})
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @else
                                <p class="mt-2 text-sm text-amber-600">
                                    ※ メニューを選択すると所要時間が自動設定されます
                                </p>
                            @endif
                        </div>

                        <!-- スタッフ選択（シフトベースモードの場合のみ） -->
                        @if($useStaffAssignment)
                            <div>
                                <label class="block text-sm font-medium mb-1">担当スタッフ</label>
                                @php
                                    $availableStaff = $this->getAvailableStaff();
                                @endphp
                                <select
                                    wire:model="newReservation.staff_id"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="">未指定</option>
                                    @foreach($availableStaff as $staff)
                                        <option value="{{ $staff['id'] }}">
                                            👤 {{ $staff['name'] }} ({{ \Carbon\Carbon::parse($staff['start_time'])->format('H:i') }}-{{ \Carbon\Carbon::parse($staff['end_time'])->format('H:i') }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">※ 未指定の場合、「未指定」ラインに配置されます</p>
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-medium mb-1">ライン（席）</label>
                                <select
                                    wire:model="newReservation.line_type"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="main">メインライン</option>
                                    <option value="sub">サブライン</option>
                                </select>
                            </div>

                            @if($newReservation['line_type'] === 'main')
                                <div>
                                    <label class="block text-sm font-medium mb-1">席番号</label>
                                    <select
                                        wire:model="newReservation.line_number"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        @for($i = 1; $i <= 3; $i++)
                                            <option value="{{ $i }}">席{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                            @endif
                        @endif
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">備考</label>
                            <textarea 
                                wire:model="newReservation.notes"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                rows="3"
                                placeholder="電話予約、特記事項など"></textarea>
                        </div>
                        
                        <div class="flex gap-2">
                            <button 
                                wire:click="$set('reservationStep', 1)"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                                戻る
                            </button>
                            <button 
                                wire:click="createReservation"
                                class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                                予約を作成
                            </button>
                        </div>
                    </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <!-- デバッグ用JavaScript -->
    <script>
        document.addEventListener('livewire:load', function () {
            // デバッグログイベントをリッスン
            window.Livewire.on('debug-log', (data) => {
                console.group('🔍 ReservationTimelineWidget Debug');
                console.log('Message:', data.message);
                if (data.selectedStore !== undefined) {
                    console.log('Selected Store:', data.selectedStore);
                }
                if (data.hasSelectedStore !== undefined) {
                    console.log('Has Selected Store:', data.hasSelectedStore);
                }
                if (data.storeId !== undefined) {
                    console.log('Store ID for filter:', data.storeId);
                }
                if (data.count !== undefined) {
                    console.log('Category Count:', data.count);
                }
                if (data.categories !== undefined) {
                    console.log('Categories:', data.categories);
                }
                console.groupEnd();
            });

            // 店舗選択変更時のデバッグ
            document.addEventListener('change', function(e) {
                if (e.target.matches('select[wire\\:model\\.live="selectedStore"]')) {
                    console.log('🏪 Store selection changed to:', e.target.value);
                }
            });
        });
    </script>
</x-filament-widgets::widget>