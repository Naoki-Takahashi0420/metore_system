<x-filament-widgets::widget>
    <div wire:poll.30s="loadTimelineData">
        <!-- 30秒ごとに自動更新 -->
    </div>
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

    <!-- メニュー変更用JavaScript -->
    <script>
        // メニュー変更用のグローバル変数
        window.menusData = window.menusData || [];
        window.optionsData = window.optionsData || [];
        window.currentReservationId = window.currentReservationId || null;

        // メニュー変更編集モードの切り替え
        window.toggleMenuEdit = async function(reservationId, storeId) {
            console.log('🍽️ toggleMenuEdit called:', { reservationId, storeId });

            const menuDisplay = document.getElementById('menuDisplay');
            const menuEdit = document.getElementById('menuEdit');
            const menuChangeBtn = document.getElementById('menuChangeBtn');

            if (!menuDisplay || !menuEdit) {
                console.error('Menu change elements not found');
                alert('エラー: メニュー変更エリアが見つかりません');
                return;
            }

            // 編集モードに切り替え
            menuDisplay.style.display = 'none';
            menuEdit.style.display = 'block';

            if (menuChangeBtn) {
                menuChangeBtn.textContent = '💾 保存';
                menuChangeBtn.style.background = '#10b981';
                menuChangeBtn.onclick = function() { saveMenuChange(reservationId); };
            }

            window.currentReservationId = reservationId;

            try {
                // メニュー一覧を取得
                await loadMenus(storeId);

                // オプション一覧を取得
                await loadOptions(storeId);

            } catch (error) {
                console.error('Error loading menus/options:', error);
                alert('メニュー情報の取得に失敗しました');
            }
        }

        // メニュー一覧を取得
        async function loadMenus(storeId) {
            try {
                const response = await fetch(`/api/admin/stores/${storeId}/menus`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                const result = await response.json();

                if (result.success) {
                    window.menusData = result.data;
                    const menuSelect = document.getElementById('menuSelect');
                    menuSelect.innerHTML = '<option value="">メニューを選択...</option>';

                    window.menusData.forEach(menu => {
                        const option = document.createElement('option');
                        option.value = menu.id;
                        option.textContent = `${menu.name} (¥${menu.price.toLocaleString()} / ${menu.duration_minutes}分)`;
                        menuSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading menus:', error);
                alert('メニュー一覧の取得に失敗しました');
            }
        }

        // オプション一覧を取得
        async function loadOptions(storeId) {
            try {
                const response = await fetch(`/api/admin/stores/${storeId}/options`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                const result = await response.json();

                if (result.success && result.data.length > 0) {
                    window.optionsData = result.data;
                    const optionSection = document.getElementById('optionSection');
                    const optionCheckboxes = document.getElementById('optionCheckboxes');

                    optionSection.style.display = 'block';
                    optionCheckboxes.innerHTML = '';

                    window.optionsData.forEach(option => {
                        const div = document.createElement('div');
                        div.style.marginBottom = '8px';

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.id = `option_${option.id}`;
                        checkbox.value = option.id;
                        checkbox.style.marginRight = '8px';

                        const label = document.createElement('label');
                        label.htmlFor = `option_${option.id}`;
                        label.textContent = `${option.name} (+¥${option.price.toLocaleString()} / +${option.duration_minutes}分)`;
                        label.style.cursor = 'pointer';

                        div.appendChild(checkbox);
                        div.appendChild(label);
                        optionCheckboxes.appendChild(div);
                    });
                } else {
                    const optionSection = document.getElementById('optionSection');
                    if (optionSection) {
                        optionSection.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error loading options:', error);
                const optionSection = document.getElementById('optionSection');
                if (optionSection) {
                    optionSection.style.display = 'none';
                }
            }
        }

        // メニュー変更を保存
        window.saveMenuChange = async function(reservationId) {
            const menuSelect = document.getElementById('menuSelect');
            const selectedMenuId = menuSelect.value;

            if (!selectedMenuId) {
                alert('メニューを選択してください');
                return;
            }

            // 選択されたオプションを取得
            const selectedOptionIds = [];
            const optionCheckboxes = document.querySelectorAll('#optionCheckboxes input[type="checkbox"]:checked');
            optionCheckboxes.forEach(checkbox => {
                selectedOptionIds.push(parseInt(checkbox.value));
            });

            // 確認ダイアログ
            const selectedMenu = window.menusData.find(m => m.id == selectedMenuId);
            let confirmMessage = `メニューを「${selectedMenu.name}」に変更します。\n\n`;

            if (selectedOptionIds.length > 0) {
                confirmMessage += 'オプション:\n';
                selectedOptionIds.forEach(optionId => {
                    const option = window.optionsData.find(o => o.id == optionId);
                    if (option) {
                        confirmMessage += `  - ${option.name}\n`;
                    }
                });
                confirmMessage += '\n';
            }

            confirmMessage += 'よろしいですか？';

            if (!confirm(confirmMessage)) {
                return;
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const response = await fetch(`/api/admin/reservations/${reservationId}/change-menu`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        menu_id: selectedMenuId,
                        option_menu_ids: selectedOptionIds
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('メニューを変更しました\n\n' +
                          `合計時間: ${result.details.total_duration}\n` +
                          `新しい終了時刻: ${result.details.new_end_time}`);
                    window.location.reload();
                } else {
                    // エラーメッセージを表示
                    let errorMsg = result.message;
                    if (result.details) {
                        errorMsg += '\n\n詳細:\n';
                        errorMsg += `新しい終了時刻: ${result.details.new_end_time}\n`;
                        errorMsg += `重複する予約: ${result.details.conflicting_times}\n`;
                        errorMsg += `合計時間: ${result.details.total_duration}`;
                    }
                    alert(errorMsg);
                }
            } catch (error) {
                console.error('Menu change error:', error);
                alert('メニュー変更中にエラーが発生しました');
            }
        }

        console.log('✅ Menu change functions loaded:', {
            toggleMenuEdit: typeof window.toggleMenuEdit,
            saveMenuChange: typeof window.saveMenuChange
        });
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
                z-index: 10;  /* サイドバーより下に配置（サイドバーは40-50） */
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

                    // タイムラインの開始時刻を取得（デフォルトは10:00）
                    $timelineStartHour = $timelineData['startHour'] ?? 10;
                    $timelineEndHour = $timelineData['endHour'] ?? 21;
                    $slotDuration = $timelineData['slotDuration'] ?? 30;

                    $shouldShowIndicator = false;

                    // デバッグ情報をJavaScriptコンソールに出力
                    echo "<script>console.log('🐘 PHP: JST現在時刻: {$currentHour}:{$currentMinute}');</script>";
                    echo "<script>console.log('🐘 PHP: タイムライン開始時刻: {$timelineStartHour}:00, 終了時刻: {$timelineEndHour}:00, スロット: {$slotDuration}分');</script>";
                    echo "<script>console.log('🐘 PHP Debug: shouldShow={$shouldShowIndicator}, isToday=" . ($isToday ? 'true' : 'false') . "');</script>";
                    $timelineKeys = !empty($timelineData) ? implode(', ', array_keys($timelineData)) : 'empty';
                    echo "<script>console.log('🐘 PHP: timelineData keys: {$timelineKeys}');</script>";

                    // タイムライン範囲内の場合のみ表示フラグを設定
                    $shouldShowIndicator = ($currentHour >= $timelineStartHour && $currentHour < $timelineEndHour);
                @endphp
                <div id="current-time-indicator"
                     class="current-time-indicator{{ ($currentHour < $timelineStartHour || $currentHour >= $timelineEndHour) ? ' outside-business-hours' : '' }}"
                     style="visibility: hidden; left: 0px; transition: opacity 0.5s ease-in-out;"
                     data-timeline-start="{{ $timelineStartHour }}"
                     data-timeline-end="{{ $timelineEndHour }}"
                     data-slot-duration="{{ $slotDuration }}">
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
                                        // 全体ブロックまたはライン別ブロックをチェック
                                        $isBlocked = in_array($index, $timelineData['blockedSlots']);
                                        $seatKey = $seat['key'] ?? null;
                                        if (!$isBlocked && $seatKey && isset($timelineData['lineBlockedSlots'][$seatKey])) {
                                            $isBlocked = in_array($index, $timelineData['lineBlockedSlots'][$seatKey]);
                                        }

                                        // 予約可否の詳細情報を取得
                                        $availabilityResult = null;
                                        $tooltipMessage = '';

                                        // クリック可否判定: スロット開始時点で空きがあればクリック可能
                                        // （予約時間は後でユーザーが選択するため、ここでは厳密にチェックしない）
                                        $canClickSlot = !$hasReservation && !$isBlocked;

                                        if ($canClickSlot && isset($currentStore)) {
                                            // 参考情報として最小予約時間での可否をチェック（ツールチップ表示用）
                                            $minDuration = $currentStore->reservation_slot_duration ?? 30;
                                            $endTime = \Carbon\Carbon::parse($slot)->addMinutes($minDuration)->format('H:i');

                                            // ライン種別を判定して渡す
                                            $checkLineType = null;
                                            if (isset($seat['type'])) {
                                                if ($seat['type'] === 'sub') {
                                                    $checkLineType = 'sub';
                                                } elseif ($seat['type'] === 'main' || in_array($seatKey, range(1, $mainSeats ?? 3))) {
                                                    $checkLineType = 'main';
                                                }
                                            }
                                            $availabilityResult = $this->canReserveAtTimeSlot($slot, $endTime, $currentStore, \Carbon\Carbon::parse($selectedDate), $checkLineType);

                                            if (!$availabilityResult['can_reserve']) {
                                                // 最小予約時間では入らないが、短い時間なら入る可能性がある
                                                $tooltipMessage = "クリックして予約時間を選択してください";
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
                                                    // シフト時刻に日付を追加
                                                    $shiftStart = \Carbon\Carbon::parse($selectedDate . ' ' . $shift->start_time);
                                                    $shiftEnd = \Carbon\Carbon::parse($selectedDate . ' ' . $shift->end_time);

                                                    // シフト時間外は不可（開始時刻がシフト内にあればOK）
                                                    if ($slotTime->lt($shiftStart) || $slotTime->gte($shiftEnd)) {
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
                                            // スタッフシフトモードでは、スタッフ不在の場合のみクリック不可
                                            if (isset($timelineData['useStaffAssignment']) && $timelineData['useStaffAssignment']) {
                                                // スタッフ不在チェックのみ（容量チェックはモーダルで行う）
                                                if ($hasNoStaff) {
                                                    $isClickable = false;
                                                } else {
                                                    $isClickable = true;  // スタッフがいればクリック可能
                                                }
                                            } else {
                                                // 営業時間ベースモードは常にクリック可能（容量チェックはモーダルで行う）
                                                $isClickable = $canClickSlot && !$hasNoStaff;
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
                                            onclick="alert('この時間帯は予約できません。\n\n理由: {{ $tooltipMessage ?: ($hasNoStaff ? 'スタッフのシフトがありません' : '予約枠が満席です') }}')"
                                            style="cursor: not-allowed; position: relative; opacity: 0.6;"
                                            title="{{ $tooltipMessage ?: ($hasNoStaff ? 'スタッフのシフトがありません' : '予約不可') }}"
                                        @endif>
                                        @if($isBlocked)
                                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #9e9e9e; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; pointer-events: none;">
                                                BRK
                                            </div>
                                        @else
                                            @foreach($seat['reservations'] as $reservation)
                                                @php
                                                    // 🔍 詳細デバッグログ
                                                    $shouldDisplay = floor($reservation['start_slot']) == $index;
                                                    $logData = [
                                                        'reservation_id' => $reservation['id'],
                                                        'customer' => $reservation['customer_name'] ?? 'unknown',
                                                        'start_slot' => $reservation['start_slot'],
                                                        'start_slot_floor' => floor($reservation['start_slot']),
                                                        'span' => $reservation['span'],
                                                        'index' => $index,
                                                        'should_display' => $shouldDisplay,
                                                        'old_condition' => ($reservation['start_slot'] == $index),
                                                    ];
                                                @endphp
                                                <script>
                                                    console.log('🔍 [RESERVATION DISPLAY]', @json($logData));
                                                </script>
                                                @if(floor($reservation['start_slot']) == $index)
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

            // 🚨 EMERGENCY: タイムライン範囲外の強制削除（完全版）
            function emergencyRemoveIndicator() {
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();

                // data属性からタイムライン範囲を取得
                const indicator = document.getElementById('current-time-indicator');
                const timelineStartHour = indicator ? parseInt(indicator.dataset.timelineStart || '10') : 10;
                const timelineEndHour = indicator ? parseInt(indicator.dataset.timelineEnd || '21') : 21;

                console.log('🚨 EMERGENCY CHECK: JST時刻=' + currentHour + '時, タイムライン範囲=' + timelineStartHour + '-' + timelineEndHour + '時');

                if (currentHour < timelineStartHour || currentHour >= timelineEndHour) {
                    console.log('🚨 EMERGENCY: タイムライン範囲外で強制削除実行');
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
                    console.log('✅ EMERGENCY CHECK: タイムライン範囲内のため削除しない');
                }
            }

            // 即座に実行
            emergencyRemoveIndicator();

            // 定期実行
            setInterval(emergencyRemoveIndicator, 5000);

            function createTimeIndicator() {
                console.log('createTimeIndicator 実行開始');

                // 既存のインジケーターがある場合は位置を更新するだけ
                const existingIndicator = document.getElementById('current-time-indicator');
                if (existingIndicator) {
                    console.log('✅ 既存インジケーター発見 - 位置更新のみ実行');
                    updateIndicatorPosition();
                    return;
                }

                // 日本時間で現在時刻を取得
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();
                const currentMinute = jstDate.getMinutes();

                // タイムライン開始時刻をdata属性から取得（デフォルト10:00）
                const timelineStartHour = 10; // デフォルト値
                const timelineEndHour = 21;
                const slotDuration = 30;

                console.log(`🕒 JST現在時刻: ${currentHour}:${String(currentMinute).padStart(2, '0')}`);
                console.log(`📅 タイムライン範囲: ${timelineStartHour}:00 - ${timelineEndHour}:00`);
                console.log(`⏱️  スロット間隔: ${slotDuration}分`);

                // タイムライン範囲外の場合は何もしない
                if (currentHour < timelineStartHour || currentHour >= timelineEndHour) {
                    console.log('🚫 createTimeIndicator: タイムライン範囲外のため処理停止');
                    return;
                }

                console.log('✅ タイムライン範囲内のためインジケーター表示処理を続行');

                // 要素を探す
                const table = document.querySelector('.timeline-table');
                const container = document.querySelector('.overflow-x-auto');

                if (!table || !container) {
                    console.log('必要な要素が見つかりません', { table, container });
                    return;
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
                    z-index: 10;  /* サイドバーより下に配置 */
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
                        console.log('⚠️ セル幅が0です。さらに遅延して再試行します...');
                        // さらに遅延して再試行
                        setTimeout(() => {
                            const retryFirstCellWidth = cells[0].offsetWidth;
                            const retryCellWidth = cells[1].offsetWidth;

                            if (retryFirstCellWidth === 0 || retryCellWidth === 0) {
                                console.error('❌ 再試行後もセル幅が0です。インジケーターを非表示にします。');
                                indicator.style.display = 'none';
                                return;
                            }

                            const minutesFromStart = (currentHour - timelineStartHour) * 60 + currentMinute;
                            const cellIndex = Math.floor(minutesFromStart / slotDuration);
                            const percentageIntoCell = (minutesFromStart % slotDuration) / slotDuration;
                            const leftPosition = retryFirstCellWidth + (cellIndex * retryCellWidth) + (percentageIntoCell * retryCellWidth);

                            indicator.style.left = leftPosition + 'px';
                            console.log(`✅ 再試行成功: 左位置=${leftPosition.toFixed(1)}px (席幅=${retryFirstCellWidth}px, セル幅=${retryCellWidth}px)`);
                        }, 1000);
                        return;
                    }

                    // 時間計算（タイムライン開始時刻からの経過時間）
                    const minutesFromStart = (currentHour - timelineStartHour) * 60 + currentMinute;
                    const cellIndex = Math.floor(minutesFromStart / slotDuration);
                    const percentageIntoCell = (minutesFromStart % slotDuration) / slotDuration;
                    const leftPosition = firstCellWidth + (cellIndex * cellWidth) + (percentageIntoCell * cellWidth);

                    console.log(`\n=== 🎯 位置計算結果 ===`);
                    console.log(`現在時刻: ${currentHour}:${String(currentMinute).padStart(2, '0')}`);
                    console.log(`開始時刻: ${timelineStartHour}:00`);
                    console.log(`開始からの分数: ${minutesFromStart}分`);
                    console.log(`スロット間隔: ${slotDuration}分`);
                    console.log(`セルインデックス: ${cellIndex}`);
                    console.log(`セル内割合: ${(percentageIntoCell * 100).toFixed(1)}%`);
                    console.log(`席幅: ${firstCellWidth}px`);
                    console.log(`セル幅: ${cellWidth}px`);
                    console.log(`計算式: ${firstCellWidth} + (${cellIndex} × ${cellWidth}) + (${(percentageIntoCell * 100).toFixed(1)}% × ${cellWidth})`);
                    console.log(`最終位置: ${leftPosition.toFixed(1)}px`);

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

            // インジケーターの位置だけを更新する関数
            function updateIndicatorPosition() {
                const indicator = document.getElementById('current-time-indicator');
                if (!indicator) {
                    console.log('⚠️ updateIndicatorPosition: インジケーターが存在しません');
                    return;
                }

                const table = document.querySelector('.timeline-table');
                if (!table) {
                    console.log('⚠️ updateIndicatorPosition: テーブルが存在しません');
                    return;
                }

                const firstRow = table.querySelector('tbody tr');
                if (!firstRow) {
                    console.log('⚠️ updateIndicatorPosition: 行が存在しません');
                    return;
                }

                const cells = firstRow.querySelectorAll('td');
                if (cells.length < 2) {
                    console.log('⚠️ updateIndicatorPosition: セルが不足しています');
                    return;
                }

                // 現在時刻取得
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();
                const currentMinute = jstDate.getMinutes();

                // タイムライン設定を取得
                const timelineStartHour = parseInt(indicator.dataset.timelineStart || '10');
                const slotDuration = parseInt(indicator.dataset.slotDuration || '30');

                // セル幅を実測
                const firstCellWidth = cells[0].offsetWidth;
                const cellWidth = cells[1].offsetWidth;

                console.log(`📊 セル幅実測: 1列目=${firstCellWidth}px, 2列目=${cellWidth}px`);

                if (firstCellWidth === 0 || cellWidth === 0) {
                    console.log('⚠️ セル幅が0です。500ms後に再試行します');
                    setTimeout(updateIndicatorPosition, 500);
                    return;
                }

                // 位置計算
                const minutesFromStart = (currentHour - timelineStartHour) * 60 + currentMinute;
                const cellIndex = Math.floor(minutesFromStart / slotDuration);
                const percentageIntoCell = (minutesFromStart % slotDuration) / slotDuration;
                const leftPosition = firstCellWidth + (cellIndex * cellWidth) + (percentageIntoCell * cellWidth);

                // 位置を適用
                indicator.style.left = leftPosition + 'px';

                console.log(`✅ インジケーター位置更新: ${leftPosition.toFixed(1)}px (${currentHour}:${String(currentMinute).padStart(2, '0')})`);
                console.log(`   計算式: ${firstCellWidth} + (${cellIndex} × ${cellWidth}) + (${(percentageIntoCell * 100).toFixed(1)}% × ${cellWidth})`);

                // 時刻テキストも更新
                const timeText = indicator.querySelector('.current-time-text');
                if (timeText) {
                    timeText.textContent = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
                }
            }

            // リアルタイム更新用の関数
            function updateTimeIndicator() {
                // 日本時間で現在時刻を取得
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();
                const currentMinute = jstDate.getMinutes();

                // 既存のインジケーターがある場合は位置と時刻を更新
                const indicator = document.getElementById('current-time-indicator');
                if (!indicator) {
                    return; // インジケーターがない場合は何もしない
                }

                // data属性からタイムライン設定を取得
                const timelineStartHour = parseInt(indicator.dataset.timelineStart || '10');
                const timelineEndHour = parseInt(indicator.dataset.timelineEnd || '21');
                const slotDuration = parseInt(indicator.dataset.slotDuration || '30');

                console.log(`🔄 updateTimeIndicator: JST現在時刻: ${currentHour}:${String(currentMinute).padStart(2, '0')}`);
                console.log(`🔄 タイムライン範囲: ${timelineStartHour}:00 - ${timelineEndHour}:00`);

                // タイムライン範囲外の場合はインジケーターを削除
                if (currentHour < timelineStartHour || currentHour >= timelineEndHour) {
                    console.log('🔄 🚫 updateTimeIndicator: タイムライン範囲外のためインジケーター削除');
                    indicator.remove();
                    return;
                }

                const table = document.querySelector('.timeline-table');
                if (table) {
                    const firstRow = table.querySelector('tbody tr');
                    if (firstRow) {
                        const cells = firstRow.querySelectorAll('td');
                        if (cells.length >= 2) {
                            const firstCellWidth = cells[0].offsetWidth;
                            const cellWidth = cells[1].offsetWidth;

                            if (firstCellWidth === 0 || cellWidth === 0) {
                                console.warn('🔄 ⚠️ updateTimeIndicator: セル幅が0です。次回の更新で再計算します。');
                                return;
                            }

                            const minutesFromStart = (currentHour - timelineStartHour) * 60 + currentMinute;
                            const cellIndex = Math.floor(minutesFromStart / slotDuration);
                            const percentageIntoCell = (minutesFromStart % slotDuration) / slotDuration;
                            const leftPosition = firstCellWidth + (cellIndex * cellWidth) + (percentageIntoCell * cellWidth);

                            indicator.style.left = leftPosition + 'px';

                            console.log(`🔄 ✅ 位置更新: ${leftPosition.toFixed(1)}px (時刻: ${currentHour}:${String(currentMinute).padStart(2, '0')})`);

                            const timeText = indicator.querySelector('.current-time-text');
                            if (timeText) {
                                timeText.textContent = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
                            }
                        }
                    }
                }
            }

            // 実行
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOMContentLoaded - タイムライン範囲チェック後にインジケーター作成開始');

                // タイムライン範囲チェック
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();

                console.log('🕒 DOMContentLoaded: JST現在時刻=' + currentHour + '時');

                // PHP側で作成されていればJavaScriptで制御
                const indicator = document.getElementById('current-time-indicator');
                if (indicator) {
                    const timelineStartHour = parseInt(indicator.dataset.timelineStart || '10');
                    const timelineEndHour = parseInt(indicator.dataset.timelineEnd || '21');

                    console.log('📅 タイムライン範囲: ' + timelineStartHour + ':00 - ' + timelineEndHour + ':00');

                    if (currentHour < timelineStartHour || currentHour >= timelineEndHour) {
                        console.log('❌ タイムライン範囲外のため赤線を非表示');
                        indicator.classList.add('outside-business-hours');
                        return;
                    } else {
                        console.log('✅ タイムライン範囲内のため赤線を表示');
                        indicator.classList.remove('outside-business-hours');
                    }
                }

                console.log('✅ DOMContentLoaded: タイムライン範囲内のためインジケーター作成');
                setTimeout(createTimeIndicator, 1000);

                // 1分ごとにリアルタイム更新
                setInterval(updateTimeIndicator, 60000);
            });

            // 即座にも実行（タイムライン範囲チェック付き）
            setTimeout(function() {
                console.log('即座実行 - タイムライン範囲チェック後にインジケーター作成');

                // タイムライン範囲チェック
                const now = new Date().toLocaleString("en-US", {timeZone: "Asia/Tokyo"});
                const jstDate = new Date(now);
                const currentHour = jstDate.getHours();
                const indicator = document.getElementById('current-time-indicator');
                const timelineStartHour = indicator ? parseInt(indicator.dataset.timelineStart || '10') : 10;
                const timelineEndHour = indicator ? parseInt(indicator.dataset.timelineEnd || '21') : 21;

                console.log('🕒 即座実行: JST現在時刻=' + currentHour + '時');
                console.log('📅 タイムライン範囲: ' + timelineStartHour + ':00 - ' + timelineEndHour + ':00');

                if (currentHour < timelineStartHour || currentHour >= timelineEndHour) {
                    console.log('🚫 即座実行: タイムライン範囲外のため作成しない');
                    return;
                }

                console.log('✅ 即座実行: タイムライン範囲内のためインジケーター作成');
                createTimeIndicator();
            }, 2000);

            // グローバルに公開
            window.createTimeIndicator = createTimeIndicator;
            window.updateTimeIndicator = updateTimeIndicator;
            window.updateIndicatorPosition = updateIndicatorPosition;

            // Livewireイベント対応
            document.addEventListener('livewire:load', function () {
                console.log('📡 Livewire loaded - インジケーター初期化');
                setTimeout(createTimeIndicator, 1000);
            });

            document.addEventListener('livewire:navigated', function () {
                console.log('📡 Livewire navigated - インジケーター再作成');
                setTimeout(createTimeIndicator, 1000);
            });

            // Livewire v3対応
            if (window.Livewire) {
                Livewire.hook('morph.updated', ({ el, component }) => {
                    if (el.querySelector('.timeline-table')) {
                        console.log('📡 Livewire morph updated - インジケーター再作成');
                        setTimeout(createTimeIndicator, 500);
                    }
                });
            }

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
                    document.body.style.overflow = '';
                    setTimeout(() => {
                        @this.closeReservationDetailModal();
                    }, 300);
                }
            }"
            x-show="show"
            x-init="document.body.style.overflow = 'hidden'"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="close()"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black bg-opacity-50 overflow-y-auto"
            style="padding: 24px;"
        >
            <div
                x-on:click.stop
                class="bg-white rounded-lg shadow-xl w-full max-w-4xl flex flex-col"
                style="max-height: 85vh;"
            >
                <!-- ヘッダー -->
                <div class="flex-shrink-0 bg-white border-b border-gray-200 p-6 rounded-t-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-1">予約詳細</h3>
                            <p class="text-sm text-gray-500">
                                @if($selectedReservation->is_new_customer ?? false)
                                    <span class="inline-block px-2 py-1 bg-red-100 text-red-700 rounded text-xs mr-2">NEW</span>
                                @endif
                                {{ $selectedReservation->customer->last_name ?? '' }} {{ $selectedReservation->customer->first_name ?? '' }} 様
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                予約ID: #{{ $selectedReservation->id }}
                            </p>
                        </div>
                        <button
                            x-on:click="close()"
                            class="text-gray-400 hover:text-gray-600 text-2xl"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- 基本情報 -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">基本情報</h4>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">予約日時</p>
                                <p class="text-base font-semibold text-gray-900">
                                    {{ \Carbon\Carbon::parse($selectedReservation->reservation_date)->isoFormat('M月D日（ddd）') }}
                                    {{ \Carbon\Carbon::parse($selectedReservation->start_time)->format('H:i') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">座席</p>
                                <p class="text-base font-semibold text-gray-900">
                                    @if($selectedReservation->is_sub)
                                        <span class="inline-block px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">サブ枠</span>
                                    @else
                                        席{{ $selectedReservation->seat_number }}
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">ステータス</p>
                                <p class="text-base font-semibold text-gray-900">
                                    @php
                                        $statusColors = [
                                            'booked' => 'bg-blue-100 text-blue-700',
                                            'completed' => 'bg-green-100 text-green-700',
                                            'cancelled' => 'bg-red-100 text-red-700',
                                            'canceled' => 'bg-red-100 text-red-700',
                                            'no_show' => 'bg-gray-100 text-gray-700',
                                        ];
                                        $statusLabels = [
                                            'booked' => '予約済',
                                            'completed' => '完了',
                                            'cancelled' => 'キャンセル',
                                            'canceled' => 'キャンセル',
                                            'no_show' => '無断欠席',
                                        ];
                                    @endphp
                                    <span class="inline-block px-2 py-1 rounded text-xs {{ $statusColors[$selectedReservation->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $statusLabels[$selectedReservation->status] ?? $selectedReservation->status }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 顧客情報 -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">顧客情報</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">氏名</p>
                                <p class="text-base text-gray-700">{{ $selectedReservation->customer->last_name ?? '' }} {{ $selectedReservation->customer->first_name ?? '' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">電話番号</p>
                                <p class="text-base text-gray-700">{{ $selectedReservation->customer->phone ?? '未登録' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">メールアドレス</p>
                                <p class="text-base text-gray-700 truncate">{{ $selectedReservation->customer->email ?? '未登録' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">年齢 / 生年月日</p>
                                <p class="text-base text-gray-700">
                                    @if($selectedReservation->customer->birth_date)
                                        {{ \Carbon\Carbon::parse($selectedReservation->customer->birth_date)->age }}歳 /
                                        {{ \Carbon\Carbon::parse($selectedReservation->customer->birth_date)->format('Y年n月j日') }}
                                    @else
                                        未登録
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 契約状況 -->
                    @php
                        $activeSubscription = \App\Models\CustomerSubscription::where('customer_id', $selectedReservation->customer_id)
                            ->where('status', 'active')
                            ->first();
                        $activeTicket = \App\Models\CustomerTicket::where('customer_id', $selectedReservation->customer_id)
                            ->where('status', 'active')
                            ->first();
                    @endphp
                    @if($activeSubscription || $activeTicket)
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">契約状況</h4>
                            <div class="grid grid-cols-2 gap-4">
                                @if($activeSubscription)
                                    <!-- サブスク -->
                                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-sm font-semibold text-gray-900">サブスクリプション</p>
                                            <span class="px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded">契約中</span>
                                        </div>
                                        <p class="text-base text-gray-700 mb-3">{{ $activeSubscription->plan_name ?? '月額プラン' }}</p>
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="text-gray-500">利用状況</span>
                                                <span class="font-semibold text-gray-900">{{ $activeSubscription->current_month_visits ?? 0 }}/{{ $activeSubscription->monthly_limit ?? 0 }}回</span>
                                            </div>
                                            @php
                                                $limit = $activeSubscription->monthly_limit ?? 1;
                                                $used = $activeSubscription->current_month_visits ?? 0;
                                                $percentage = ($limit > 0) ? min(($used / $limit) * 100, 100) : 0;
                                            @endphp
                                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                                <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $percentage }}%"></div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">
                                                次回更新:
                                                @if($activeSubscription->reset_day)
                                                    毎月{{ $activeSubscription->reset_day }}日
                                                @elseif($activeSubscription->next_billing_date)
                                                    {{ \Carbon\Carbon::parse($activeSubscription->next_billing_date)->format('Y年n月j日') }}
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endif

                                @if($activeTicket)
                                    <!-- 回数券 -->
                                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-sm font-semibold text-gray-900">回数券</p>
                                            <span class="px-2 py-1 bg-green-600 text-white text-xs font-medium rounded">有効</span>
                                        </div>
                                        <p class="text-base text-gray-700 mb-3">{{ $activeTicket->plan_name ?? '回数券' }}</p>
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="text-gray-500">残り回数</span>
                                                <span class="font-semibold text-gray-900">{{ $activeTicket->remaining_count ?? 0 }}/{{ $activeTicket->total_count ?? 0 }}回</span>
                                            </div>
                                            @php
                                                $total = $activeTicket->total_count ?? 1;
                                                $remaining = $activeTicket->remaining_count ?? 0;
                                                $usedPercentage = ($total > 0) ? min((($total - $remaining) / $total) * 100, 100) : 0;
                                            @endphp
                                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                                <div class="bg-green-600 h-1.5 rounded-full" style="width: {{ $usedPercentage }}%"></div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">
                                                有効期限:
                                                @if($activeTicket->expires_at)
                                                    {{ \Carbon\Carbon::parse($activeTicket->expires_at)->format('Y年n月j日') }}
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endif

                                @if(!$activeSubscription && !$activeTicket)
                                    <div class="col-span-2 text-center py-4 text-gray-500 text-sm">
                                        契約中のプランはありません
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- 予約内容 -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">予約内容</h4>
                        <div class="grid grid-cols-3 gap-4">
                            <div x-data="{
                                menuEdit: false,
                                menus: [],
                                filteredMenus: [],
                                menuSearch: '',
                                options: [],
                                selectedMenuId: null,
                                selectedMenu: null,
                                selectedOptionIds: [],

                                async startEdit() {
                                    console.log('🍽️ メニュー編集開始');
                                    this.menuEdit = true;
                                    await this.loadMenus();
                                    await this.loadOptions();
                                },

                                filterMenus() {
                                    if (!this.menuSearch || this.menuSearch.trim() === '') {
                                        this.filteredMenus = this.menus.slice(0, 10);
                                    } else {
                                        const searchLower = this.menuSearch.toLowerCase();
                                        this.filteredMenus = this.menus.filter(menu =>
                                            menu.name.toLowerCase().includes(searchLower) ||
                                            (menu.category && menu.category.toLowerCase().includes(searchLower))
                                        ).slice(0, 10);
                                    }
                                },

                                selectMenu(menu) {
                                    this.selectedMenuId = menu.id;
                                    this.selectedMenu = menu;
                                    this.menuSearch = menu.name;
                                    this.filteredMenus = [];
                                },

                                async loadMenus() {
                                    try {
                                        console.log('📡 メニュー読み込み開始（Livewire）...');

                                        const result = await $wire.call('getMenusForStore', {{ $selectedReservation->store_id }});
                                        console.log('Response:', result);

                                        if (result.success) {
                                            this.menus = result.data;
                                            this.filteredMenus = result.data.slice(0, 10);
                                            console.log('✅ メニュー読み込み完了:', this.menus.length, '件');
                                        } else {
                                            console.error('❌ 失敗:', result);
                                            alert('メニュー一覧の取得に失敗しました: ' + (result.message || 'Unknown error'));
                                        }
                                    } catch (error) {
                                        console.error('❌ メニュー取得エラー:', error);
                                        alert('メニュー一覧の取得に失敗しました: ' + error.message);
                                    }
                                },

                                async loadOptions() {
                                    try {
                                        console.log('📡 オプション読み込み開始（Livewire）...');

                                        const result = await $wire.call('getOptionsForStore', {{ $selectedReservation->store_id }});
                                        console.log('Response:', result);

                                        if (result.success && result.data.length > 0) {
                                            this.options = result.data;
                                            console.log('✅ オプション読み込み完了:', this.options.length, '件');
                                        } else {
                                            console.log('ℹ️ オプションなし');
                                        }
                                    } catch (error) {
                                        console.error('❌ オプション取得エラー:', error);
                                    }
                                },

                                async saveMenu() {
                                    if (!this.selectedMenuId) {
                                        alert('メニューを選択してください');
                                        return;
                                    }

                                    const selectedMenu = this.menus.find(m => m.id == this.selectedMenuId);
                                    let confirmMessage = `メニューを「${selectedMenu.name}」に変更します。\n\n`;

                                    if (this.selectedOptionIds.length > 0) {
                                        confirmMessage += 'オプション:\n';
                                        this.selectedOptionIds.forEach(optionId => {
                                            const option = this.options.find(o => o.id == optionId);
                                            if (option) {
                                                confirmMessage += `  - ${option.name}\n`;
                                            }
                                        });
                                        confirmMessage += '\n';
                                    }

                                    confirmMessage += 'よろしいですか？';

                                    if (!confirm(confirmMessage)) {
                                        return;
                                    }

                                    try {
                                        console.log('💾 メニュー保存中（Livewire）...');

                                        const result = await $wire.call('changeReservationMenu',
                                            {{ $selectedReservation->id }},
                                            this.selectedMenuId,
                                            this.selectedOptionIds
                                        );

                                        console.log('Response:', result);

                                        if (result.success) {
                                            alert('メニューを変更しました\n\n' +
                                                  `合計時間: ${result.details.total_duration}\n` +
                                                  `新しい終了時刻: ${result.details.new_end_time}`);
                                            window.location.reload();
                                        } else {
                                            let errorMsg = result.message;
                                            if (result.details) {
                                                errorMsg += '\n\n詳細:\n';
                                                errorMsg += `新しい終了時刻: ${result.details.new_end_time}\n`;
                                                errorMsg += `重複する予約: ${result.details.conflicting_times}\n`;
                                                errorMsg += `合計時間: ${result.details.total_duration}`;
                                            }
                                            alert(errorMsg);
                                        }
                                    } catch (error) {
                                        console.error('メニュー変更エラー:', error);
                                        alert('メニュー変更中にエラーが発生しました: ' + error.message);
                                    }
                                }
                            }">
                                <p class="text-xs text-gray-500 mb-1">メニュー</p>

                                <!-- 表示モード -->
                                <div x-show="!menuEdit">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-base text-gray-700">{{ $selectedReservation->menu->name ?? 'なし' }}</p>
                                            @if($selectedReservation->menu)
                                                <p class="text-xs text-gray-500 mt-1">所要時間: {{ $selectedReservation->menu->duration_minutes }}分</p>
                                            @endif
                                        </div>
                                        <button
                                            @click="startEdit()"
                                            class="text-xs text-blue-600 hover:text-blue-700 font-medium ml-2"
                                        >
                                            変更
                                        </button>
                                    </div>
                                </div>

                                <!-- 編集モード -->
                                <div x-show="menuEdit" style="display: none;">
                                    <!-- 変更前後の比較表示 -->
                                    <div class="mb-3 p-3 bg-gray-50 rounded-md border border-gray-200">
                                        <!-- 変更前 -->
                                        <div class="mb-2">
                                            <p class="text-xs text-gray-500 mb-1">変更前</p>
                                            <div class="font-medium text-sm text-gray-900">{{ $selectedReservation->menu->name ?? 'なし' }}</div>
                                            <div class="text-xs text-gray-600 mt-0.5">
                                                <span>¥{{ number_format($selectedReservation->menu->price ?? 0) }}</span>
                                                <span class="mx-1">•</span>
                                                <span>{{ $selectedReservation->menu->duration_minutes ?? 0 }}分</span>
                                            </div>
                                        </div>

                                        <!-- 矢印 -->
                                        <div class="flex justify-center my-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                            </svg>
                                        </div>

                                        <!-- 変更後 -->
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <p class="text-xs text-gray-500">変更後</p>
                                                <button
                                                    x-show="selectedMenu"
                                                    type="button"
                                                    @click="selectedMenuId = null; selectedMenu = null; menuSearch = ''; filteredMenus = menus.slice(0, 10)"
                                                    class="text-xs text-blue-600 hover:text-blue-700 font-medium"
                                                >
                                                    変更
                                                </button>
                                            </div>
                                            <div x-show="!selectedMenu" class="text-sm text-gray-400 italic">メニューを検索して選択してください</div>
                                            <div x-show="selectedMenu">
                                                <div class="font-medium text-sm text-blue-700" x-text="selectedMenu?.name"></div>
                                                <div class="text-xs text-blue-600 mt-0.5">
                                                    <span x-text="selectedMenu ? `¥${Math.floor(selectedMenu.price).toLocaleString()}` : ''"></span>
                                                    <span class="mx-1">•</span>
                                                    <span x-text="selectedMenu ? `${selectedMenu.duration_minutes}分` : ''"></span>
                                                </div>
                                                <!-- 差分表示 -->
                                                <div class="text-xs mt-1">
                                                    <template x-if="selectedMenu && selectedMenu.price !== {{ $selectedReservation->menu->price ?? 0 }}">
                                                        <span :class="selectedMenu.price > {{ $selectedReservation->menu->price ?? 0 }} ? 'text-red-600' : 'text-green-600'">
                                                            <span x-text="selectedMenu.price > {{ $selectedReservation->menu->price ?? 0 }} ? '+' : ''"></span>
                                                            <span x-text="`¥${Math.floor(Math.abs(selectedMenu.price - {{ $selectedReservation->menu->price ?? 0 }})).toLocaleString()}`"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="selectedMenu && selectedMenu.duration_minutes !== {{ $selectedReservation->menu->duration_minutes ?? 0 }}">
                                                        <span class="ml-2" :class="selectedMenu.duration_minutes > {{ $selectedReservation->menu->duration_minutes ?? 0 }} ? 'text-orange-600' : 'text-blue-600'">
                                                            <span x-text="selectedMenu.duration_minutes > {{ $selectedReservation->menu->duration_minutes ?? 0 }} ? '+' : ''"></span>
                                                            <span x-text="`${selectedMenu.duration_minutes - {{ $selectedReservation->menu->duration_minutes ?? 0 }}}分`"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- メニュー検索 -->
                                    <div class="relative">
                                        <input
                                            type="text"
                                            x-model="menuSearch"
                                            @input="filterMenus()"
                                            @focus="filteredMenus = menus.slice(0, 10)"
                                            placeholder="メニューを検索..."
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                        />

                                        <!-- 検索結果ドロップダウン -->
                                        <div
                                            x-show="filteredMenus.length > 0 && !selectedMenuId"
                                            @click.away="filteredMenus = []"
                                            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto"
                                            style="display: none;"
                                        >
                                            <template x-for="menu in filteredMenus" :key="menu.id">
                                                <button
                                                    type="button"
                                                    @click="selectMenu(menu)"
                                                    class="w-full px-3 py-2 text-left hover:bg-blue-50 border-b border-gray-100 last:border-0"
                                                >
                                                    <div class="font-medium text-sm text-gray-900" x-text="menu.name"></div>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <span x-text="`¥${Math.floor(menu.price).toLocaleString()}`"></span>
                                                        <span class="mx-1">•</span>
                                                        <span x-text="`${menu.duration_minutes}分`"></span>
                                                        <template x-if="menu.category">
                                                            <span>
                                                                <span class="mx-1">•</span>
                                                                <span x-text="menu.category"></span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- オプション -->
                                    <div x-show="options.length > 0" class="mt-3" style="display: none;">
                                        <p class="text-xs text-gray-600 mb-2">オプション（複数選択可）</p>
                                        <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-md p-2 bg-gray-50">
                                            <template x-for="option in options" :key="option.id">
                                                <label class="flex items-center gap-2 mb-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        :value="option.id"
                                                        x-model="selectedOptionIds"
                                                        class="rounded"
                                                    />
                                                    <span x-text="`${option.name} (+¥${Math.floor(option.price).toLocaleString()} / +${option.duration_minutes}分)`" class="text-sm"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- ボタン -->
                                    <div class="mt-3 flex gap-2">
                                        <button
                                            @click="saveMenu()"
                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors"
                                        >
                                            保存
                                        </button>
                                        <button
                                            @click="menuEdit = false; selectedMenuId = null; selectedOptionIds = []"
                                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-md transition-colors"
                                        >
                                            キャンセル
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">担当スタッフ</p>
                                <p class="text-base text-gray-700">
                                    @if($selectedReservation->staff)
                                        {{ $selectedReservation->staff->name }}
                                    @else
                                        未割当
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">予約タイプ</p>
                                <p class="text-base text-gray-700">
                                    @if($selectedReservation->customer_ticket_id)
                                        <span class="inline-block px-2 py-1 bg-green-100 text-green-700 rounded text-xs">回数券</span>
                                    @elseif($selectedReservation->customer_subscription_id)
                                        <span class="inline-block px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">サブスク</span>
                                    @else
                                        <span class="inline-block px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs">通常</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="text-xs text-gray-500 mb-1">追加オプション</p>
                            @php
                                $hasOptions = false;
                                try {
                                    $hasOptions = $selectedReservation && method_exists($selectedReservation, 'getOptionMenusSafely') && $selectedReservation->getOptionMenusSafely()->count() > 0;
                                } catch (\Exception $e) {
                                    \Log::error('Error checking optionMenus in timeline modal', ['error' => $e->getMessage()]);
                                }
                            @endphp
                            @if($hasOptions)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($selectedReservation->getOptionMenusSafely() as $option)
                                        <span class="inline-block px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded text-sm">
                                            {{ $option->name ?? '' }} <span class="text-blue-600 font-semibold">+¥{{ number_format($option->pivot->price ?? 0) }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">なし</span>
                            @endif
                        </div>
                    </div>

                    <!-- カルテ情報 -->
                    @php
                        // 前回のカルテを取得
                        $previousMedicalRecord = \App\Models\MedicalRecord::where('customer_id', $selectedReservation->customer_id)
                            ->where('treatment_date', '<=', $selectedReservation->reservation_date)
                            ->orderBy('treatment_date', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->first();

                        $latestVision = null;
                        $intensity = null;
                        if ($previousMedicalRecord) {
                            $latestVision = $previousMedicalRecord->getLatestVisionRecord();
                            // 強度を取得（vision_recordsから）
                            if ($latestVision && isset($latestVision['intensity'])) {
                                $intensity = $latestVision['intensity'];
                            }
                        }
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-200">
                            <h4 class="text-sm font-bold text-gray-900">カルテ情報</h4>
                            <button
                                wire:click="$set('showMedicalHistoryModal', true)"
                                class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                            >
                                カルテ履歴を見る →
                            </button>
                        </div>

                        @if($previousMedicalRecord)
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-xs text-blue-800 font-semibold mb-3">
                                    📋 前回の施術結果（{{ \Carbon\Carbon::parse($previousMedicalRecord->treatment_date)->isoFormat('YYYY年M月D日（ddd）') }}）
                                </p>
                                @if($latestVision)
                                    <div class="space-y-4">
                                        <!-- 強度 -->
                                        <div class="pb-3 border-b border-blue-200">
                                            <p class="text-xs text-gray-600 mb-1">強度</p>
                                            <p class="text-lg font-bold text-gray-900">{{ $intensity ?? '-' }}</p>
                                        </div>

                                        <!-- 裸眼視力 -->
                                        <div>
                                            <p class="text-xs text-blue-700 font-semibold mb-2">裸眼視力</p>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <p class="text-xs text-gray-500 mb-1">右目</p>
                                                    <p class="text-base font-bold text-gray-900">
                                                        {{ $latestVision['after_naked_right'] ?? $latestVision['before_naked_right'] ?? '-' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500 mb-1">左目</p>
                                                    <p class="text-base font-bold text-gray-900">
                                                        {{ $latestVision['after_naked_left'] ?? $latestVision['before_naked_left'] ?? '-' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 矯正視力 -->
                                        <div>
                                            <p class="text-xs text-blue-700 font-semibold mb-2">矯正視力</p>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <p class="text-xs text-gray-500 mb-1">右目</p>
                                                    <p class="text-base font-bold text-gray-900">
                                                        {{ $latestVision['after_corrected_right'] ?? $latestVision['before_corrected_right'] ?? '-' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500 mb-1">左目</p>
                                                    <p class="text-base font-bold text-gray-900">
                                                        {{ $latestVision['after_corrected_left'] ?? $latestVision['before_corrected_left'] ?? '-' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 老眼視力 -->
                                        @if(isset($latestVision['reading_vision_right']) || isset($latestVision['reading_vision_left']) || isset($previousMedicalRecord->reading_vision_right) || isset($previousMedicalRecord->reading_vision_left))
                                        <div>
                                            <p class="text-xs text-blue-700 font-semibold mb-2">老眼視力</p>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <p class="text-xs text-gray-500 mb-1">右目</p>
                                                    <p class="text-base font-bold text-gray-900">
                                                        {{ $latestVision['reading_vision_right'] ?? $previousMedicalRecord->reading_vision_right ?? '-' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500 mb-1">左目</p>
                                                    <p class="text-base font-bold text-gray-900">
                                                        {{ $latestVision['reading_vision_left'] ?? $previousMedicalRecord->reading_vision_left ?? '-' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="text-center py-2 text-gray-600 text-sm">
                                        施術記録あり（視力データなし）
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-4 text-gray-500 text-sm bg-gray-50 rounded-lg">
                                前回のカルテ記録がありません
                            </div>
                        @endif
                    </div>

                    <!-- 次回予約 -->
                    @php
                        $nextReservation = \App\Models\Reservation::where('customer_id', $selectedReservation->customer_id)
                            ->where('reservation_date', '>', $selectedReservation->reservation_date)
                            ->where('status', 'booked')
                            ->orderBy('reservation_date', 'asc')
                            ->orderBy('start_time', 'asc')
                            ->first();
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-200">
                            <h4 class="text-sm font-bold text-gray-900">次回予約</h4>
                            <button
                                wire:click="$set('showReservationHistoryModal', true)"
                                class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                            >
                                予約履歴を見る →
                            </button>
                        </div>
                        @if($nextReservation)
                            <button
                                wire:click="selectReservation({{ $nextReservation->id }})"
                                class="w-full text-left border-2 border-blue-300 rounded-lg p-4 bg-blue-50 hover:bg-blue-100 transition-colors"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-xl font-bold text-gray-900 mb-1">
                                            {{ \Carbon\Carbon::parse($nextReservation->reservation_date)->isoFormat('M月D日（ddd）') }}
                                            {{ \Carbon\Carbon::parse($nextReservation->start_time)->format('H:i') }}
                                        </p>
                                        <p class="text-sm text-gray-600 mb-2">{{ $nextReservation->menu->name ?? 'メニュー未設定' }}</p>
                                        <p class="text-sm text-gray-500">
                                            席{{ $nextReservation->seat_number ?? '-' }} |
                                            担当: {{ $nextReservation->staff->name ?? '未割当' }}
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded">
                                        {{ \Carbon\Carbon::parse($nextReservation->reservation_date->format('Y-m-d') . ' ' . $nextReservation->start_time)->diffForHumans() }}
                                    </span>
                                </div>
                            </button>
                        @else
                            <div class="text-center py-4 text-gray-500 text-sm bg-gray-50 rounded-lg">
                                次回の予約はありません
                            </div>
                        @endif
                    </div>

                    {{-- カルテ引き継ぎ情報 --}}
                    @php
                        $latestMedicalRecord = null;
                        if ($selectedReservation->customer_id) {
                            $latestMedicalRecord = \App\Models\MedicalRecord::where('customer_id', $selectedReservation->customer_id)
                                ->where(function($q) {
                                    $q->whereNotNull('next_visit_notes')
                                      ->where('next_visit_notes', '!=', '')
                                      ->orWhere(function($q2) {
                                          $q2->whereNotNull('notes')
                                             ->where('notes', '!=', '');
                                      });
                                })
                                ->orderBy('created_at', 'desc')
                                ->first();
                        }
                    @endphp
                    @if($latestMedicalRecord && ($latestMedicalRecord->next_visit_notes || $latestMedicalRecord->notes))
                        <div class="border-t pt-4 mt-4">
                            <p class="text-xs text-gray-500 mb-2">📋 カルテ引き継ぎ情報</p>

                            @if($latestMedicalRecord->next_visit_notes)
                                <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-3">
                                    <p class="text-xs font-semibold text-yellow-800 mb-1">⚠️ 次回引き継ぎ事項</p>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $latestMedicalRecord->next_visit_notes }}</p>
                                </div>
                            @endif

                            @if($latestMedicalRecord->notes)
                                <div class="bg-blue-50 border border-blue-200 rounded p-3">
                                    <p class="text-xs font-semibold text-blue-800 mb-1">📝 その他メモ</p>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $latestMedicalRecord->notes }}</p>
                                </div>
                            @endif

                            <p class="text-xs text-gray-400 mt-2">
                                記録日: {{ \Carbon\Carbon::parse($latestMedicalRecord->created_at)->format('Y/m/d H:i') }}
                            </p>
                        </div>
                    @endif

                    {{-- 座席移動セクション --}}
                    @include('filament.widgets.reservation-detail-modal-movement')
                </div>
            </div>

            <!-- メニュー変更用JavaScript（モーダル内で実行） -->
            <script>
                (function() {
                    console.log('🍽️ Menu change script executing in modal...');

                    // メニュー変更用のグローバル変数
                    window.menusData = window.menusData || [];
                    window.optionsData = window.optionsData || [];
                    window.currentReservationId = window.currentReservationId || null;

                    // メニュー変更編集モードの切り替え
                    window.toggleMenuEdit = async function(reservationId, storeId) {
                        console.log('🍽️ toggleMenuEdit called:', { reservationId, storeId });

                        const menuDisplay = document.getElementById('menuDisplay');
                        const menuEdit = document.getElementById('menuEdit');

                        if (!menuDisplay || !menuEdit) {
                            console.error('Menu change elements not found');
                            alert('エラー: メニュー変更エリアが見つかりません');
                            return;
                        }

                        // 編集モードに切り替え
                        menuDisplay.style.display = 'none';
                        menuEdit.style.display = 'block';

                        window.currentReservationId = reservationId;

                        try {
                            // メニュー一覧を取得
                            await loadMenus(storeId);

                            // オプション一覧を取得
                            await loadOptions(storeId);

                        } catch (error) {
                            console.error('Error loading menus/options:', error);
                            alert('メニュー情報の取得に失敗しました');
                        }
                    }

                    // メニュー一覧を取得
                    window.loadMenus = async function(storeId) {
                        try {
                            const response = await fetch(`/api/admin/stores/${storeId}/menus`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                }
                            });
                            const result = await response.json();

                            if (result.success) {
                                window.menusData = result.data;
                                const menuSelect = document.getElementById('menuSelect');
                                menuSelect.innerHTML = '<option value="">メニューを選択...</option>';

                                window.menusData.forEach(menu => {
                                    const option = document.createElement('option');
                                    option.value = menu.id;
                                    option.textContent = `${menu.name} (¥${menu.price.toLocaleString()} / ${menu.duration_minutes}分)`;
                                    menuSelect.appendChild(option);
                                });
                            }
                        } catch (error) {
                            console.error('Error loading menus:', error);
                            alert('メニュー一覧の取得に失敗しました');
                        }
                    }

                    // オプション一覧を取得
                    window.loadOptions = async function(storeId) {
                        try {
                            const response = await fetch(`/api/admin/stores/${storeId}/options`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                }
                            });
                            const result = await response.json();

                            if (result.success && result.data.length > 0) {
                                window.optionsData = result.data;
                                const optionSection = document.getElementById('optionSection');
                                const optionCheckboxes = document.getElementById('optionCheckboxes');

                                optionSection.style.display = 'block';
                                optionCheckboxes.innerHTML = '';

                                window.optionsData.forEach(option => {
                                    const div = document.createElement('div');
                                    div.style.marginBottom = '8px';

                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.id = `option_${option.id}`;
                                    checkbox.value = option.id;
                                    checkbox.style.marginRight = '8px';

                                    const label = document.createElement('label');
                                    label.htmlFor = `option_${option.id}`;
                                    label.textContent = `${option.name} (+¥${option.price.toLocaleString()} / +${option.duration_minutes}分)`;
                                    label.style.cursor = 'pointer';

                                    div.appendChild(checkbox);
                                    div.appendChild(label);
                                    optionCheckboxes.appendChild(div);
                                });
                            } else {
                                const optionSection = document.getElementById('optionSection');
                                if (optionSection) {
                                    optionSection.style.display = 'none';
                                }
                            }
                        } catch (error) {
                            console.error('Error loading options:', error);
                            const optionSection = document.getElementById('optionSection');
                            if (optionSection) {
                                optionSection.style.display = 'none';
                            }
                        }
                    }

                    // メニュー変更を保存
                    window.saveMenuChange = async function(reservationId) {
                        const menuSelect = document.getElementById('menuSelect');
                        const selectedMenuId = menuSelect.value;

                        if (!selectedMenuId) {
                            alert('メニューを選択してください');
                            return;
                        }

                        // 選択されたオプションを取得
                        const selectedOptionIds = [];
                        const optionCheckboxes = document.querySelectorAll('#optionCheckboxes input[type="checkbox"]:checked');
                        optionCheckboxes.forEach(checkbox => {
                            selectedOptionIds.push(parseInt(checkbox.value));
                        });

                        // 確認ダイアログ
                        const selectedMenu = window.menusData.find(m => m.id == selectedMenuId);
                        let confirmMessage = `メニューを「${selectedMenu.name}」に変更します。\n\n`;

                        if (selectedOptionIds.length > 0) {
                            confirmMessage += 'オプション:\n';
                            selectedOptionIds.forEach(optionId => {
                                const option = window.optionsData.find(o => o.id == optionId);
                                if (option) {
                                    confirmMessage += `  - ${option.name}\n`;
                                }
                            });
                            confirmMessage += '\n';
                        }

                        confirmMessage += 'よろしいですか？';

                        if (!confirm(confirmMessage)) {
                            return;
                        }

                        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                        try {
                            const response = await fetch(`/api/admin/reservations/${reservationId}/change-menu`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    menu_id: selectedMenuId,
                                    option_menu_ids: selectedOptionIds
                                })
                            });

                            const result = await response.json();

                            if (result.success) {
                                alert('メニューを変更しました\n\n' +
                                      `合計時間: ${result.details.total_duration}\n` +
                                      `新しい終了時刻: ${result.details.new_end_time}`);
                                window.location.reload();
                            } else {
                                // エラーメッセージを表示
                                let errorMsg = result.message;
                                if (result.details) {
                                    errorMsg += '\n\n詳細:\n';
                                    errorMsg += `新しい終了時刻: ${result.details.new_end_time}\n`;
                                    errorMsg += `重複する予約: ${result.details.conflicting_times}\n`;
                                    errorMsg += `合計時間: ${result.details.total_duration}`;
                                }
                                alert(errorMsg);
                            }
                        } catch (error) {
                            console.error('Menu change error:', error);
                            alert('メニュー変更中にエラーが発生しました');
                        }
                    }

                    console.log('✅ Menu change functions loaded in modal:', {
                        toggleMenuEdit: typeof window.toggleMenuEdit,
                        saveMenuChange: typeof window.saveMenuChange,
                        loadMenus: typeof window.loadMenus,
                        loadOptions: typeof window.loadOptions
                    });
                })();
            </script>
        </div>
    @endif

    {{-- カルテ履歴モーダル --}}
    @if($showMedicalHistoryModal && $selectedReservation)
        <div
            x-data="{
                show: true,
                close() {
                    this.show = false;
                    document.body.style.overflow = '';
                    setTimeout(() => {
                        @this.set('showMedicalHistoryModal', false);
                    }, 300);
                }
            }"
            x-show="show"
            x-init="document.body.style.overflow = 'hidden'"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="close()"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black bg-opacity-50 overflow-y-auto"
            style="padding: 24px;"
        >
            <div
                x-on:click.stop
                class="bg-white rounded-lg shadow-xl w-full max-w-5xl flex flex-col my-6"
                style="max-height: calc(100vh - 48px);"
            >
                <!-- ヘッダー -->
                <div class="flex-shrink-0 bg-white border-b border-gray-200 p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-1">カルテ履歴</h3>
                            <p class="text-sm text-gray-500">{{ $selectedReservation->customer->last_name ?? '' }} {{ $selectedReservation->customer->first_name ?? '' }} 様</p>
                        </div>
                        <button x-on:click="close()" class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
                    </div>
                </div>

                <!-- コンテンツ（スクロール可能） -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    @php
                        // この顧客の全カルテを取得
                        $allMedicalRecords = \App\Models\MedicalRecord::where('customer_id', $selectedReservation->customer_id)
                            ->with(['presbyopiaMeasurements'])
                            ->orderBy('treatment_date', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->get();
                    @endphp

                    {{-- 視力推移グラフ --}}
                    @if($allMedicalRecords && $allMedicalRecords->count() > 0)
                        @php
                            // カルテデータからグラフ用データを準備
                            $chartLabels = [];
                            $leftBeforeData = [];
                            $leftAfterData = [];
                            $rightBeforeData = [];
                            $rightAfterData = [];
                            
                            foreach($allMedicalRecords->sortBy('treatment_date') as $record) {
                                $visionRecords = is_string($record->vision_records) 
                                    ? json_decode($record->vision_records, true) 
                                    : $record->vision_records;
                                    
                                if($visionRecords && count($visionRecords) > 0) {
                                    foreach($visionRecords as $vision) {
                                        $date = \Carbon\Carbon::parse($record->treatment_date)->format('m/d');
                                        $chartLabels[] = $date;
                                        
                                        // 左眼
                                        $leftBeforeData[] = isset($vision['before_naked_left']) ? (float)$vision['before_naked_left'] : null;
                                        $leftAfterData[] = isset($vision['after_naked_left']) ? (float)$vision['after_naked_left'] : null;
                                        
                                        // 右眼
                                        $rightBeforeData[] = isset($vision['before_naked_right']) ? (float)$vision['before_naked_right'] : null;
                                        $rightAfterData[] = isset($vision['after_naked_right']) ? (float)$vision['after_naked_right'] : null;
                                    }
                                }
                            }
                        @endphp
                        
                        @php
                            // チャートデータをJavaScript用に準備
                            $chartLabelsJS = json_encode($chartLabels ?? ['9/22', '10/2', '10/12', '10/17', '10/22']);
                            $leftAfterDataJS = json_encode($leftAfterData ?? [0.5, 0.7, 0.9, 1.0, 1.2]);
                            $rightAfterDataJS = json_encode($rightAfterData ?? [0.6, 0.8, 1.0, 1.2, 1.5]);
                        @endphp
                        
                        <div id="modal-vision-chart-container" class="mb-6"
                             x-data="{
                                 loadChart() {
                                     if (typeof Chart === 'undefined') {
                                         const script = document.createElement('script');
                                         script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                                         script.onload = () => this.drawChart();
                                         document.head.appendChild(script);
                                     } else {
                                         this.drawChart();
                                     }
                                 },
                                 drawChart() {
                                     const canvas = document.getElementById('modalSimpleChart');
                                     if (!canvas) return;
                                     
                                     const ctx = canvas.getContext('2d');
                                     new Chart(ctx, {
                                         type: 'line',
                                         data: {
                                             labels: {!! $chartLabelsJS !!},
                                             datasets: [{
                                                 label: '左眼（施術後）',
                                                 data: {!! $leftAfterDataJS !!},
                                                 borderColor: 'rgb(59, 130, 246)',
                                                 backgroundColor: 'rgba(59, 130, 246, 0.1)'
                                             }, {
                                                 label: '右眼（施術後）',
                                                 data: {!! $rightAfterDataJS !!},
                                                 borderColor: 'rgb(239, 68, 68)',
                                                 backgroundColor: 'rgba(239, 68, 68, 0.1)'
                                             }]
                                         },
                                         options: {
                                             responsive: true,
                                             maintainAspectRatio: false
                                         }
                                     });
                                 }
                             }"
                             x-init="setTimeout(() => loadChart(), 500)">
                            <div class="bg-white rounded-lg border border-gray-200 p-6">
                                <h3 class="text-lg font-semibold mb-4 text-gray-900">視力推移グラフ</h3>
                                
                                <!-- タブナビゲーション -->
                                <div class="mb-6 border-b border-gray-200">
                                    <nav class="flex space-x-4" aria-label="グラフ切り替え">
                                        <button 
                                            @click="loadChart()"
                                            id="tab-naked" 
                                            class="vision-tab px-4 py-2 text-sm font-medium border-b-2 border-primary-500 text-primary-600">
                                            裸眼視力
                                        </button>
                                        <button 
                                            @click="loadChart()"
                                            id="tab-corrected" 
                                            class="vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            矯正視力
                                        </button>
                                        <button 
                                            @click="loadChart()"
                                            id="tab-presbyopia" 
                                            class="vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            老眼測定
                                        </button>
                                    </nav>
                                </div>
                                
                                <!-- グラフコンテンツ -->
                                <div wire:ignore class="relative" style="height: 300px;">
                                    <canvas id="modalSimpleChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                    @endif

                    <div class="border-t border-gray-200 pt-4">
                        <h4 class="text-lg font-bold text-gray-900 mb-4">カルテ一覧 (全{{ $allMedicalRecords->count() }}件)</h4>
                    </div>

                    <!-- カルテリスト -->
                    @forelse($allMedicalRecords as $index => $record)
                        @php
                            // 新しい順にソートされているので、逆順で回数を計算
                            $totalCount = $allMedicalRecords->count();
                            $sessionNumber = $totalCount - $index;

                            // デバッグログ
                            \Log::info('カルテ回数表示', [
                                'customer_id' => $selectedReservation->customer_id,
                                'record_id' => $record->id,
                                'index' => $index,
                                'total_count' => $totalCount,
                                'session_number' => $sessionNumber,
                                'treatment_date' => $record->treatment_date
                            ]);
                        @endphp
                        <div class="border border-gray-200 rounded-lg p-4 {{ $index === 0 ? 'bg-blue-50 border-blue-200' : 'bg-gray-50' }}">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="text-lg font-bold text-gray-900">
                                        {{ $sessionNumber }}回目 - {{ \Carbon\Carbon::parse($record->treatment_date)->isoFormat('YYYY年M月D日（ddd）') }}
                                    </p>
                                    <p class="text-sm text-gray-500">{{ $record->staff->name ?? '担当者なし' }}</p>
                                </div>
                                @if($index === 0)
                                    <span class="px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded">最新</span>
                                @endif
                            </div>

                            @php
                                $latestVision = $record->getLatestVisionRecord();
                            @endphp

                            @if($latestVision)
                                <div class="grid grid-cols-3 gap-4 mb-3">
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">強度</p>
                                        <p class="text-base font-semibold text-gray-900">{{ $latestVision['intensity'] ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">右目視力</p>
                                        <p class="text-base font-semibold text-gray-900">
                                            {{ $latestVision['before_naked_right'] ?? '-' }} → {{ $latestVision['after_naked_right'] ?? '-' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">左目視力</p>
                                        <p class="text-base font-semibold text-gray-900">
                                            {{ $latestVision['before_naked_left'] ?? '-' }} → {{ $latestVision['after_naked_left'] ?? '-' }}
                                        </p>
                                    </div>
                                </div>
                            @endif

                            @if($record->notes)
                                <div class="mt-2 p-2 bg-white rounded border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">メモ</p>
                                    <p class="text-sm text-gray-700">{{ $record->notes }}</p>
                                </div>
                            @endif

                            <div class="mt-3">
                                <a
                                    href="{{ route('filament.admin.resources.medical-records.view', ['record' => $record->id]) }}"
                                    target="_blank"
                                    class="inline-flex items-center text-sm text-blue-600 hover:text-blue-700 font-medium"
                                >
                                    詳細を見る →
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            カルテ記録がありません
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- 予約履歴モーダル --}}
    @if($showReservationHistoryModal && $selectedReservation)
        <div
            x-data="{
                show: true,
                close() {
                    this.show = false;
                    document.body.style.overflow = '';
                    setTimeout(() => {
                        @this.set('showReservationHistoryModal', false);
                    }, 300);
                }
            }"
            x-show="show"
            x-init="document.body.style.overflow = 'hidden'"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="close()"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black bg-opacity-50 overflow-y-auto"
            style="padding: 24px;"
        >
            <div
                x-on:click.stop
                class="bg-white rounded-lg shadow-xl w-full max-w-4xl flex flex-col"
                style="max-height: 85vh;"
            >
                <!-- ヘッダー -->
                <div class="flex-shrink-0 bg-white border-b border-gray-200 p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-1">予約履歴</h3>
                            <p class="text-sm text-gray-500">{{ $selectedReservation->customer->last_name ?? '' }} {{ $selectedReservation->customer->first_name ?? '' }} 様</p>
                        </div>
                        <button x-on:click="close()" class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
                    </div>
                </div>

                <!-- コンテンツ（スクロール可能） -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    @php
                        // この顧客の全予約を取得（最新50件）
                        $allReservations = \App\Models\Reservation::where('customer_id', $selectedReservation->customer_id)
                            ->with(['menu', 'staff', 'store'])
                            ->orderBy('reservation_date', 'desc')
                            ->orderBy('start_time', 'desc')
                            ->take(50)
                            ->get();

                        // 今日の日付
                        $today = \Carbon\Carbon::today();

                        // 未来と過去に分ける
                        $futureReservations = $allReservations->filter(function($r) use ($today) {
                            return \Carbon\Carbon::parse($r->reservation_date)->isAfter($today) && $r->status === 'booked';
                        });

                        $pastReservations = $allReservations->filter(function($r) use ($today) {
                            return \Carbon\Carbon::parse($r->reservation_date)->isSameDay($today) || \Carbon\Carbon::parse($r->reservation_date)->isBefore($today);
                        });
                    @endphp

                    <!-- 未来の予約 -->
                    @if($futureReservations->count() > 0)
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">今後の予約</h4>
                            <div class="space-y-3">
                                @foreach($futureReservations as $reservation)
                                    <button
                                        wire:click="selectReservation({{ $reservation->id }})"
                                        class="w-full text-left border-2 border-blue-300 rounded-lg p-4 bg-blue-50 hover:bg-blue-100 transition-colors"
                                    >
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <p class="text-xl font-bold text-gray-900 mb-1">
                                                    {{ \Carbon\Carbon::parse($reservation->reservation_date)->isoFormat('M月D日（ddd）') }}
                                                    {{ \Carbon\Carbon::parse($reservation->start_time)->format('H:i') }}
                                                </p>
                                                <p class="text-sm text-gray-600 mb-2">{{ $reservation->menu->name ?? 'メニュー未設定' }}</p>
                                                <p class="text-sm text-gray-500">
                                                    {{ $reservation->store->name ?? '' }} |
                                                    席{{ $reservation->seat_number ?? '-' }} |
                                                    担当: {{ $reservation->staff->name ?? '未割当' }}
                                                </p>
                                            </div>
                                            <span class="px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded">
                                                {{ \Carbon\Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->start_time)->diffForHumans() }}
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- 過去の予約 -->
                    <div>
                        <h4 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">過去の予約</h4>
                        <div class="space-y-3">
                            @forelse($pastReservations as $reservation)
                                <button
                                    wire:click="selectReservation({{ $reservation->id }})"
                                    class="w-full text-left border border-gray-200 rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition-colors"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <p class="text-lg font-bold text-gray-900 mb-1">
                                                {{ \Carbon\Carbon::parse($reservation->reservation_date)->isoFormat('YYYY年M月D日（ddd）') }}
                                                {{ \Carbon\Carbon::parse($reservation->start_time)->format('H:i') }}
                                            </p>
                                            <p class="text-sm text-gray-600 mb-2">{{ $reservation->menu->name ?? 'メニュー未設定' }}</p>
                                            <p class="text-sm text-gray-500">
                                                {{ $reservation->store->name ?? '' }} |
                                                席{{ $reservation->seat_number ?? '-' }} |
                                                担当: {{ $reservation->staff->name ?? '未割当' }}
                                            </p>
                                        </div>
                                        @php
                                            $statusColors = [
                                                'booked' => 'bg-blue-100 text-blue-700',
                                                'completed' => 'bg-green-100 text-green-700',
                                                'cancelled' => 'bg-red-100 text-red-700',
                                                'canceled' => 'bg-red-100 text-red-700',
                                                'no_show' => 'bg-gray-100 text-gray-700',
                                            ];
                                            $statusLabels = [
                                                'booked' => '予約済',
                                                'completed' => '完了',
                                                'cancelled' => 'キャンセル',
                                                'canceled' => 'キャンセル',
                                                'no_show' => '無断欠席',
                                            ];
                                        @endphp
                                        <span class="px-2 py-1 rounded text-xs font-medium {{ $statusColors[$reservation->status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $statusLabels[$reservation->status] ?? $reservation->status }}
                                        </span>
                                    </div>
                                </button>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    過去の予約がありません
                                </div>
                            @endforelse
                        </div>
                    </div>
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
                            @if(!empty($blockSettings['selected_lines']) && count($blockSettings['selected_lines']) > 0)
                                @php
                                    $seatKey = $blockSettings['selected_lines'][0];
                                    $lineLabel = '';

                                    if (strpos($seatKey, 'staff_') === 0) {
                                        $staffId = intval(substr($seatKey, 6));
                                        $staff = \App\Models\User::find($staffId);
                                        $lineLabel = '👤 ' . ($staff ? $staff->name : 'スタッフ');
                                    } elseif ($seatKey === 'unassigned') {
                                        $lineLabel = '未割当ライン';
                                    } elseif (strpos($seatKey, 'sub_') === 0) {
                                        $lineNumber = intval(substr($seatKey, 4));
                                        $lineLabel = 'サブライン ' . $lineNumber;
                                    } elseif (strpos($seatKey, 'seat_') === 0) {
                                        $lineNumber = intval(substr($seatKey, 5));
                                        $lineLabel = 'メインライン ' . $lineNumber;
                                    }
                                @endphp
                                <div class="text-sm text-red-700 mt-1">
                                    ブロック対象: {{ $lineLabel }}
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">終了時間 <span class="text-red-500">*</span></label>
                            <select
                                wire:model="blockSettings.end_time"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                                required>
                                <option value="">選択してください</option>
                                @foreach($this->getBlockEndTimeOptions() as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            @if(empty($this->getBlockEndTimeOptions()))
                                <p class="text-gray-500 text-sm mt-1">開始時間を選択すると、終了時間の選択肢が表示されます</p>
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
                                wire:click="startNewCustomerRegistration"
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
                        <!-- 顧客重複の確認画面 -->
                        @if($showCustomerConflictConfirmation && $conflictingCustomer)
                            <div class="space-y-4">
                                <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 text-red-500 text-2xl">⚠️</div>
                                        <div class="flex-1">
                                            <div class="font-bold text-red-900 text-lg mb-2">電話番号が重複しています</div>
                                            <div class="text-sm text-red-800 space-y-2">
                                                <p>入力された電話番号 <strong>{{ $newCustomer['phone'] }}</strong> は既に登録されています。</p>
                                                <div class="bg-white rounded p-3 space-y-2 border border-red-200">
                                                    <div>
                                                        <span class="text-gray-600">入力された名前：</span>
                                                        <strong class="text-blue-700">{{ $newCustomer['last_name'] }} {{ $newCustomer['first_name'] }}</strong>
                                                    </div>
                                                    <div>
                                                        <span class="text-gray-600">既存の顧客名：</span>
                                                        <strong class="text-green-700">{{ $conflictingCustomer->last_name }} {{ $conflictingCustomer->first_name }}</strong>
                                                    </div>
                                                </div>
                                                <p class="font-medium">どちらで予約を作成しますか？</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <button
                                        wire:click="confirmUseExistingCustomer"
                                        class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                                        ✅ 既存顧客（{{ $conflictingCustomer->last_name }} {{ $conflictingCustomer->first_name }} 様）で予約を作成
                                    </button>
                                    <button
                                        wire:click="cancelCustomerConflict"
                                        class="w-full px-4 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                                        ← キャンセルして電話番号・名前を修正
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- 通常の新規顧客登録フォーム -->
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
                        @endif
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
                        <div wire:key="menu-selection-{{ $newReservation['customer_id'] ?? 'none' }}">
                            <label class="block text-sm font-medium mb-2">メニュー</label>

                            <!-- 顧客の契約中プラン（回数券・サブスク）を取得 -->
                            @php
                                $customerContractMenus = collect();

                                if (isset($newReservation['customer_id']) && $newReservation['customer_id']) {
                                    $customer = \App\Models\Customer::find($newReservation['customer_id']);

                                    if ($customer) {
                                        \Log::info('🔍 [DEBUG] 契約メニュー取得開始', [
                                            'customer_id' => $customer->id,
                                            'customer_name' => $customer->full_name
                                        ]);

                                        // アクティブなサブスクリプション
                                        $activeSubscriptions = \App\Models\CustomerSubscription::where('customer_id', $customer->id)
                                            ->where('status', 'active')
                                            ->where('is_paused', false)
                                            ->with('menu')
                                            ->get();

                                        \Log::info('📊 サブスク取得結果', [
                                            'count' => $activeSubscriptions->count()
                                        ]);

                                        foreach ($activeSubscriptions as $sub) {
                                            \Log::info('🔄 サブスクチェック', [
                                                'sub_id' => $sub->id,
                                                'has_menu' => $sub->menu ? 'Yes' : 'No',
                                                'menu_available' => $sub->menu ? ($sub->menu->is_available ? 'Yes' : 'No') : 'N/A'
                                            ]);

                                            if ($sub->menu && $sub->menu->is_available) {
                                                $menu = $sub->menu;
                                                $menu->contract_label = '契約中のサブスク';
                                                $menu->remaining_info = "{$sub->remaining_visits}/{$sub->monthly_limit}回";
                                                $customerContractMenus->push($menu);
                                            }
                                        }

                                        // アクティブな回数券
                                        $activeTickets = \App\Models\CustomerTicket::where('customer_id', $customer->id)
                                            ->where('status', 'active')
                                            ->where('remaining_count', '>', 0)
                                            ->with(['ticketPlan.menu'])
                                            ->get();

                                        \Log::info('🎫 回数券取得結果', [
                                            'count' => $activeTickets->count()
                                        ]);

                                        foreach ($activeTickets as $ticket) {
                                            \Log::info('🎫 回数券チェック', [
                                                'ticket_id' => $ticket->id,
                                                'has_plan' => $ticket->ticketPlan ? 'Yes' : 'No',
                                                'has_menu' => ($ticket->ticketPlan && $ticket->ticketPlan->menu) ? 'Yes' : 'No',
                                                'menu_available' => ($ticket->ticketPlan && $ticket->ticketPlan->menu) ? ($ticket->ticketPlan->menu->is_available ? 'Yes' : 'No') : 'N/A'
                                            ]);

                                            if ($ticket->ticketPlan && $ticket->ticketPlan->menu && $ticket->ticketPlan->menu->is_available) {
                                                $menu = $ticket->ticketPlan->menu;
                                                $menu->contract_label = '契約中の回数券';
                                                $menu->remaining_info = "{$ticket->remaining_count}回分";
                                                $customerContractMenus->push($menu);
                                            }
                                        }

                                        \Log::info('✅ 契約メニュー取得完了', [
                                            'total_contract_menus' => $customerContractMenus->count()
                                        ]);
                                    }
                                }

                                // よく使うメニュー（契約がない場合のみ表示）
                                $popularMenus = collect();
                                if ($customerContractMenus->isEmpty()) {
                                    $popularMenus = \App\Models\Menu::where('is_available', true)
                                        ->where('is_visible_to_customer', true);

                                    if ($selectedStore) {
                                        $popularMenus->where('store_id', $selectedStore);
                                    }

                                    $popularMenus = $popularMenus->whereIn('name', ['視力回復コース(60分)', '水素吸入コース(90分)', 'サブスク60分'])
                                        ->orderBy('is_subscription', 'desc')
                                        ->limit(3)
                                        ->get();
                                }
                            @endphp

                            <!-- 契約中メニュー -->
                            @if($customerContractMenus->count() > 0)
                                <div class="mb-3 p-3 bg-blue-50 border-2 border-blue-300 rounded-lg">
                                    <p class="text-sm font-semibold text-blue-800 mb-2 flex items-center gap-2">
                                        <i class="fas fa-star"></i>
                                        この顧客の契約中プラン
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($customerContractMenus as $menu)
                                            <button
                                                type="button"
                                                wire:click="selectMenu({{ $menu->id }})"
                                                class="px-4 py-3 text-sm border-2 rounded-lg transition-all {{ $newReservation['menu_id'] == $menu->id ? 'bg-blue-500 border-blue-600 text-white shadow-md' : 'bg-white border-blue-400 text-blue-900 hover:bg-blue-100' }}">
                                                <div class="flex flex-col items-start">
                                                    <div class="text-xs font-medium text-blue-700 {{ $newReservation['menu_id'] == $menu->id ? 'text-blue-100' : '' }} flex items-center gap-1">
                                                        @if(str_contains($menu->contract_label, 'サブスク'))
                                                            <i class="fas fa-sync-alt"></i>
                                                        @else
                                                            <i class="fas fa-ticket-alt"></i>
                                                        @endif
                                                        {{ $menu->contract_label }}
                                                    </div>
                                                    <div class="font-semibold mt-1">
                                                        {{ Str::limit($menu->name, 20) }}
                                                    </div>
                                                    <div class="text-xs mt-1 flex items-center gap-2">
                                                        <span><i class="far fa-clock"></i> {{ $menu->duration_minutes }}分</span>
                                                        <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded {{ $newReservation['menu_id'] == $menu->id ? 'bg-green-200' : '' }}">
                                                            残り{{ $menu->remaining_info }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

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
                                            if ($menuSearch) {
                                                $displayMenus = $this->getFilteredMenus();
                                            } else {
                                                $displayMenusQuery = \App\Models\Menu::where('is_available', true)
                                                    ->where('is_visible_to_customer', true)
                                                    ->where('is_option', false)  // オプションメニューを除外
                                                    ->where('show_in_upsell', false);  // アップセル用メニューを除外

                                                // 選択された店舗のメニューのみ表示
                                                if ($selectedStore) {
                                                    $displayMenusQuery->where('store_id', $selectedStore);
                                                }

                                                $displayMenus = $displayMenusQuery->orderBy('is_subscription', 'desc')
                                                    ->orderBy('sort_order')
                                                    ->get();
                                            }
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
                                                                        {{ $menu->duration_minutes }}分 - ¥{{ number_format($menu->subscription_monthly_price) }}<span class="text-xs">/月</span>
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
                                                                        {{ $menu->duration_minutes }}分 - ¥{{ number_format($menu->is_subscription ? $menu->subscription_monthly_price : $menu->price) }}
                                                                        @if($menu->is_subscription)
                                                                            <span class="text-xs">/月</span>
                                                                        @endif
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
                                                    {{ $selectedMenu->duration_minutes }}分 - ¥{{ number_format($selectedMenu->is_subscription ? $selectedMenu->subscription_monthly_price : $selectedMenu->price) }}
                                                    @if($selectedMenu->is_subscription)
                                                        <span class="text-xs">/月</span>
                                                    @endif
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
                                    wire:model.lazy="newReservation.date"
                                    value="{{ $selectedDate }}"
                                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <input
                                    type="time"
                                    wire:model.lazy="newReservation.start_time"
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

                        <!-- オプションメニュー選択 -->
                        @if($newReservation['menu_id'] && !empty($availableOptions))
                            <div class="border-t pt-4">
                                <label class="block text-sm font-medium mb-2">オプションメニュー（任意）</label>
                                <p class="text-xs text-gray-500 mb-3">追加で受けられるオプションを選択できます</p>

                                <!-- 選択済みオプション -->
                                @if(!empty($selectedOptions))
                                    <div class="mb-3 space-y-2">
                                        <p class="text-xs font-medium text-green-700">選択中のオプション：</p>
                                        @foreach($selectedOptions as $optionId => $option)
                                            <div class="flex items-center justify-between p-2 bg-green-50 border border-green-200 rounded-lg">
                                                <div class="flex-1">
                                                    <div class="font-medium text-sm text-green-900">{{ $option['name'] }}</div>
                                                    <div class="text-xs text-green-700">
                                                        ¥{{ number_format($option['price']) }}
                                                        @if($option['duration_minutes'] > 0)
                                                            - {{ $option['duration_minutes'] }}分
                                                        @endif
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="removeOption({{ $optionId }})"
                                                    class="ml-2 text-red-600 hover:text-red-800">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach

                                        <!-- 合計表示 -->
                                        <div class="p-2 bg-blue-50 border border-blue-200 rounded-lg">
                                            <div class="text-sm text-blue-900">
                                                <span class="font-medium">オプション合計：</span>
                                                ¥{{ number_format($this->getOptionsTotalPrice()) }}
                                                @if($this->getOptionsTotalDuration() > 0)
                                                    （+{{ $this->getOptionsTotalDuration() }}分）
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- 選択可能なオプション -->
                                <div class="space-y-2 max-h-60 overflow-y-auto">
                                    @foreach($availableOptions as $option)
                                        @php
                                            $isSelected = in_array($option['id'], $newReservation['option_menu_ids']);
                                        @endphp
                                        @if(!$isSelected)
                                            <button
                                                type="button"
                                                wire:click="addOption({{ $option['id'] }})"
                                                class="w-full p-3 text-left border border-gray-200 rounded-lg hover:border-blue-400 hover:bg-blue-50 transition-colors">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex-1">
                                                        <div class="font-medium text-sm">{{ $option['name'] }}</div>
                                                        <div class="text-xs text-gray-600">
                                                            ¥{{ number_format($option['price']) }}
                                                            @if(!empty($option['duration_minutes']))
                                                                - {{ $option['duration_minutes'] }}分
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                    </svg>
                                                </div>
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
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

        // Chart.js CDNを動的に読み込む
        if (typeof Chart === 'undefined' && !window.chartJsLoading) {
            window.chartJsLoading = true;
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            script.onload = function() {
                console.log('[DEBUG] Chart.js loaded successfully');
                window.chartJsLoaded = true;
                // カスタムイベントを発火
                window.dispatchEvent(new Event('chartjs:loaded'));
            };
            document.head.appendChild(script);
        }
        
        // カルテ履歴モーダル用のグラフ描画
        document.addEventListener('livewire:load', function() {
            Livewire.on('medical-history-modal-opened', () => {
                setTimeout(() => {
                    const canvas = document.getElementById('modalVisionChart');
                    if (!canvas || typeof Chart === 'undefined') return;
                    
                    // 既にグラフがある場合はスキップ
                    if (canvas.chart) return;
                    
                    const ctx = canvas.getContext('2d');
                    canvas.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['1月', '2月', '3月', '4月', '5月'],
                            datasets: [{
                                label: '左眼',
                                data: [0.5, 0.7, 0.9, 1.2, 1.5],
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            }, {
                                label: '右眼',
                                data: [0.6, 0.8, 1.0, 1.1, 1.4],
                                borderColor: 'rgb(255, 99, 132)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    console.log('[DEBUG] Chart created in modal');
                }, 500);
            });
        });
        
        // MutationObserverでモーダルの表示を監視
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    const modalChart = document.getElementById('modalVisionChart');
                    if (modalChart && !modalChart.chart && typeof Chart !== 'undefined') {
                        const ctx = modalChart.getContext('2d');
                        modalChart.chart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: ['1日', '5日', '10日', '15日', '20日'],
                                datasets: [{
                                    label: '視力推移',
                                    data: [0.5, 0.7, 0.9, 1.2, 1.5],
                                    borderColor: 'rgb(75, 192, 192)',
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    tension: 0.1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: '視力の推移'
                                    }
                                }
                            }
                        });
                        console.log('[DEBUG] Chart created via MutationObserver');
                    }
                }
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
        
        // カルテ履歴モーダルのグラフ描画
        window.drawMedicalHistoryChart = function() {
            console.log('[DEBUG] drawMedicalHistoryChart called');
            
            // Chart.jsをロード
            if (typeof Chart === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                script.onload = function() {
                    renderChart();
                };
                document.head.appendChild(script);
            } else {
                renderChart();
            }
            
            function renderChart() {
                const canvas = document.getElementById('modalSimpleChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['1週目', '2週目', '3週目', '4週目', '5週目'],
                            datasets: [{
                                label: '左眼視力',
                                data: [0.5, 0.7, 0.9, 1.2, 1.5],
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                tension: 0.1
                            }, {
                                label: '右眼視力',
                                data: [0.4, 0.6, 0.8, 1.0, 1.3],
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: '視力の推移'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 2.0
                                }
                            }
                        }
                    });
                    console.log('[DEBUG] Chart rendered!', chart);
                }
            }
        };
        
        // MutationObserverでモーダルチャートを検出
        const chartObserver = new MutationObserver(function(mutations) {
            const canvas = document.getElementById('modalSimpleChart');
            if (canvas && !canvas.chartRendered) {
                canvas.chartRendered = true;
                console.log('[DEBUG] Canvas detected, drawing chart...');
                setTimeout(window.drawMedicalHistoryChart, 500);
            }
        });
        chartObserver.observe(document.body, { childList: true, subtree: true });
        
        // Chart.jsを動的に読み込んで初期化
        function initMedicalChart() {
            if (typeof Chart === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                script.onload = function() {
                    drawMedicalChart();
                };
                document.head.appendChild(script);
            } else {
                drawMedicalChart();
            }
        }
        
        // 実際のデータでグラフを描画
        function drawMedicalChart() {
            const canvas = document.getElementById('modalSimpleChart');
            if (!canvas) return;
            
            // 既存のチャートがあれば破棄
            if (window.modalVisionChart) {
                window.modalVisionChart.destroy();
            }
            
            const ctx = canvas.getContext('2d');
            
            // PHPから渡されたデータを使用
            @if(isset($chartLabels) && count($chartLabels) > 0)
                const labels = {!! json_encode($chartLabels) !!};
                const leftBeforeData = {!! json_encode($leftBeforeData) !!};
                const leftAfterData = {!! json_encode($leftAfterData) !!};
                const rightBeforeData = {!! json_encode($rightBeforeData) !!};
                const rightAfterData = {!! json_encode($rightAfterData) !!};
            @else
                // テストデータ
                const labels = ['9/22', '10/2', '10/12', '10/17', '10/22'];
                const leftAfterData = [0.5, 0.7, 0.9, 1.0, 1.2];
                const rightAfterData = [0.6, 0.8, 1.0, 1.2, 1.5];
            @endif
            
            window.modalVisionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '左眼（施術後）',
                        data: leftAfterData,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.1
                    }, {
                        label: '右眼（施術後）',
                        data: rightAfterData,
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 2.0
                        }
                    }
                }
            });
        }
        
        // タブ切り替え機能
        window.switchVisionTab = function(tabName) {
            // タブのアクティブ状態を切り替え
            document.querySelectorAll('.vision-tab').forEach(tab => {
                tab.classList.remove('border-primary-500', 'text-primary-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            const activeTab = document.getElementById('tab-' + tabName);
            if (activeTab) {
                activeTab.classList.remove('border-transparent', 'text-gray-500');
                activeTab.classList.add('border-primary-500', 'text-primary-600');
            }
            
            // TODO: タブごとに異なるデータを表示
            drawMedicalChart();
        };
        
        // テスト用関数（ボタンクリック時）
        window.testChart = function() {
            initMedicalChart();
        };
        
        // モーダルが開いたら自動的にチャートを描画
        const modalObserver = new MutationObserver((mutations) => {
            const chartContainer = document.getElementById('modal-vision-chart-container');
            if (chartContainer && !window.modalVisionChart) {
                setTimeout(initMedicalChart, 500);
                modalObserver.disconnect();
            }
        });
        modalObserver.observe(document.body, { childList: true, subtree: true });
    </script>
</x-filament-widgets::widget>