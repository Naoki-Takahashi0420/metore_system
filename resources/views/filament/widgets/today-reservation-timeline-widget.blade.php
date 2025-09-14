<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <!-- ヘッダー -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ $this->getData()['todayDate'] }} の予約スケジュール
                    @if($this->getData()['isToday'])
                        <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">本日</span>
                    @endif
                </h2>
                
                <div class="flex items-center space-x-4">
                    <!-- 日付ナビゲーション -->
                    <div class="flex items-center space-x-2">
                        <button 
                            wire:click="goToPreviousDay" 
                            @if(!$this->getData()['canNavigateBack']) disabled @endif
                            class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 text-sm">
                            ← 前日
                        </button>
                        
                        <input 
                            type="date" 
                            wire:model.live="selectedDate"
                            class="px-3 py-1 border border-gray-300 rounded text-sm"
                        />
                        
                        <button 
                            wire:click="goToNextDay"
                            @if(!$this->getData()['canNavigateForward']) disabled @endif
                            class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 text-sm">
                            翌日 →
                        </button>
                        
                        @if(!$this->getData()['isToday'])
                            <button 
                                wire:click="goToToday"
                                class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                今日
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            @php
                $stores = $this->getData()['stores'];
                $reservations = $this->getData()['reservations'];
                $timeSlots = $this->getData()['timeSlots'];
                $currentTime = now()->format('H:i');
                $isToday = $this->getData()['isToday'];
            @endphp

            <!-- エクセル風スケジュール表 -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border-2 border-gray-800" style="min-width: 1400px;">
                    <!-- 時間軸ヘッダー -->
                    <thead>
                        <tr class="bg-blue-100">
                            <th class="border-2 border-gray-800 px-4 py-3 text-left font-bold text-gray-900 bg-gray-200" style="width: 200px;">
                                店舗名
                            </th>
                            @foreach($timeSlots as $slot)
                                <th class="border border-gray-600 px-1 py-2 text-center text-xs font-bold text-gray-900" style="width: 50px;">
                                    {{ $slot }}
                                    @if($isToday && $slot <= $currentTime && $currentTime < ($timeSlots[$loop->index + 1] ?? '23:59'))
                                        <div class="w-full h-1 bg-red-600 mt-1"></div>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    
                    <!-- 店舗別予約行（予約ライン数に応じて複数行表示） -->
                    <tbody>
                        @foreach($stores as $store)
                            @php
                                $storeReservations = $reservations->where('store_id', $store->id);
                                $businessHours = $this->getStoreBusinessHours($store);
                                $totalLines = ($store->main_lines_count ?? 1) + ($store->sub_lines_count ?? 0);
                                $mainLines = $store->main_lines_count ?? 1;
                                
                                // デバッグ用（一時的）
                                echo "Store: {$store->name}, Main: {$store->main_lines_count}, Sub: {$store->sub_lines_count}, Total: {$totalLines}<br>";
                            @endphp
                            
                            @for($lineIndex = 0; $lineIndex < $totalLines; $lineIndex++)
                                @php
                                    $isMainLine = $lineIndex < $mainLines;
                                    $lineType = $isMainLine ? '本' : '予';
                                @endphp
                                <tr class="{{ $loop->parent->even ? 'bg-gray-50' : 'bg-white' }} {{ $isMainLine ? 'border-l-4 border-blue-500' : 'border-l-4 border-orange-400' }}">
                                    <!-- 店舗名列（最初の行のみ表示、他は結合） -->
                                    @if($lineIndex === 0)
                                        <td class="border-2 border-gray-800 px-4 py-3 bg-blue-50" rowspan="{{ $totalLines }}">
                                            <div class="font-bold text-gray-900 text-sm">{{ $store->name }}</div>
                                            @if($businessHours['is_open'])
                                                <div class="text-xs text-green-700 mt-1">
                                                    営業: {{ $businessHours['open'] }} - {{ $businessHours['close'] }}
                                                </div>
                                            @else
                                                <div class="text-xs text-red-600 mt-1">
                                                    休業日
                                                </div>
                                            @endif
                                            <div class="text-xs text-gray-600 mt-1">
                                                予約: {{ $storeReservations->count() }}件
                                            </div>
                                            <div class="text-xs text-blue-600 mt-1">
                                                本ライン: {{ $mainLines }}
                                                @if($store->sub_lines_count > 0)
                                                    / 予備: {{ $store->sub_lines_count }}
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                
                                <!-- 時間軸セル -->
                                @foreach($timeSlots as $slotIndex => $slot)
                                    @php
                                        // この時間・このラインに予約があるかチェック
                                        $slotReservations = $storeReservations->filter(function($reservation) use ($slot) {
                                            try {
                                                // 時刻の正規化
                                                $startTime = is_string($reservation->start_time) 
                                                    ? (strlen($reservation->start_time) === 5 ? $reservation->start_time : substr($reservation->start_time, 0, 5))
                                                    : $reservation->start_time->format('H:i');
                                                    
                                                $endTime = is_string($reservation->end_time) 
                                                    ? (strlen($reservation->end_time) === 5 ? $reservation->end_time : substr($reservation->end_time, 0, 5))
                                                    : $reservation->end_time->format('H:i');
                                                    
                                                // 予約時間内かチェック（30分単位で考慮）
                                                $nextSlot = date('H:i', strtotime($slot) + 30 * 60);
                                                return ($startTime < $nextSlot && $endTime > $slot);
                                            } catch (\Exception $e) {
                                                return false;
                                            }
                                        });
                                        
                                        // このライン用の予約を取得（予約ライン別配置）
                                        $reservation = $slotReservations->skip($lineIndex)->first();
                                        $isBusinessHour = $businessHours['is_open'] && ($slot >= $businessHours['open'] && $slot < $businessHours['close']);
                                        $isCurrentTimeSlot = ($isToday && $slot <= $currentTime && $currentTime < ($timeSlots[$slotIndex + 1] ?? '23:59'));
                                        
                                        // セル結合の計算
                                        $colspan = 1;
                                        $isStartCell = false;
                                        $shouldHide = false;
                                        
                                        if ($reservation) {
                                            // 予約の開始時刻をチェック
                                            $reservationStartTime = is_string($reservation->start_time) 
                                                ? (strlen($reservation->start_time) === 5 ? $reservation->start_time : substr($reservation->start_time, 0, 5))
                                                : $reservation->start_time->format('H:i');
                                            $reservationEndTime = is_string($reservation->end_time) 
                                                ? (strlen($reservation->end_time) === 5 ? $reservation->end_time : substr($reservation->end_time, 0, 5))
                                                : $reservation->end_time->format('H:i');
                                                
                                            if ($slot === $reservationStartTime) {
                                                // 開始セル - colspan計算
                                                $isStartCell = true;
                                                $startMinutes = strtotime($reservationStartTime);
                                                $endMinutes = strtotime($reservationEndTime);
                                                $durationMinutes = ($endMinutes - $startMinutes) / 60;
                                                $colspan = max(1, intval($durationMinutes / 30)); // 30分単位
                                            } elseif ($slot > $reservationStartTime && $slot < $reservationEndTime) {
                                                // 中間セル - 非表示
                                                $shouldHide = true;
                                            }
                                        }
                                    @endphp
                                    
                                    @if($shouldHide)
                                        <!-- 中間セル - 非表示（colspanで結合済み） -->
                                    @else
                                        <td class="border border-gray-600 text-center relative p-0" 
                                            style="height: 40px;" 
                                            @if($colspan > 1) colspan="{{ $colspan }}" @endif>
                                            @if($slotIndex === 0)
                                                <!-- ライン種別ラベル（最初のセルのみ） -->
                                                <div class="absolute left-0 top-0 px-1 py-0.5 text-xs font-bold {{ $isMainLine ? 'bg-blue-500 text-white' : 'bg-orange-400 text-white' }}" 
                                                     style="z-index: 10;">
                                                    {{ $lineType }}{{ $lineIndex + 1 }}
                                                </div>
                                            @endif
                                            @if($reservation && $isStartCell)
                                                <!-- 予約セル（結合対応） -->
                                                <div class="reservation-cell {{ $reservation->is_new_customer ? 'new-customer' : 'existing-customer' }}"
                                                     onclick="openReservationModalFromData(this)"
                                                     data-reservation-id="{{ $reservation->id }}"
                                                     data-customer-id="{{ $reservation->customer_id }}"
                                                     data-customer-name="{{ $reservation->customer->last_name }}{{ $reservation->customer->first_name }}"
                                                     data-customer-type="{{ $reservation->is_new_customer ? '新規' : '既存' }}"
                                                     data-reservation-number="{{ $reservation->reservation_number }}"
                                                     data-date="{{ $reservation->reservation_date->format('Y/n/j') }}"
                                                     data-time="{{ $reservation->start_time }} - {{ $reservation->end_time }}"
                                                     data-store="{{ $reservation->store->name ?? '' }}"
                                                     data-menu="{{ $reservation->menu->name ?? '-' }}"
                                                     data-amount="{{ number_format($reservation->total_amount) }}"
                                                     data-notes="{{ $reservation->notes ?? '' }}"
                                                     data-phone="{{ $reservation->customer->phone ?? '-' }}"
                                                     title="{{ $reservation->customer->last_name }}{{ $reservation->customer->first_name }}様 ({{ $reservation->is_new_customer ? '新規' : '既存' }}) - クリックで詳細">
                                                    {{ $reservation->is_new_customer ? '★新' : '●既' }}
                                                    @if($colspan > 1)
                                                        <span class="ml-1 text-xs">{{ $reservation->customer->last_name }}{{ $reservation->customer->first_name }}</span>
                                                    @endif
                                                </div>
                                            @elseif($isCurrentTimeSlot)
                                                <!-- 現在時刻 -->
                                                <div class="h-full w-full border-l-2 border-r-2 border-red-600 flex items-center justify-center" style="background-color: #fbbf24 !important;">
                                                    <span class="text-xs text-red-700 font-bold">NOW</span>
                                                </div>
                                            @elseif(!$isBusinessHour)
                                                <!-- 営業時間外 - 濃いグレー -->
                                                <div class="h-full w-full" style="background-color: #9ca3af !important;">
                                                </div>
                                            @else
                                                <!-- 空き時間 - 明るい灰色 -->
                                                <div class="h-full w-full hover:bg-gray-100 transition-colors" style="background-color: #f9fafb !important;">
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                            @endfor
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- 古いLivewireモーダルを削除 - JavaScriptモーダルに置き換え済み -->

            <!-- サマリー統計（エクセル風） -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border-2 border-gray-800 rounded p-4 text-center">
                    <div class="text-2xl font-bold text-blue-700">{{ $reservations->count() }}</div>
                    <div class="text-sm text-gray-700 font-bold">総予約数</div>
                </div>
                <div class="bg-white border-2 border-gray-800 rounded p-4 text-center">
                    <div class="text-2xl font-bold text-green-700">{{ $reservations->where('is_new_customer', true)->count() }}</div>
                    <div class="text-sm text-gray-700 font-bold">新規顧客</div>
                </div>
                <div class="bg-white border-2 border-gray-800 rounded p-4 text-center">
                    <div class="text-2xl font-bold text-blue-700">{{ $reservations->where('is_new_customer', false)->count() }}</div>
                    <div class="text-sm text-gray-700 font-bold">既存顧客</div>
                </div>
                <div class="bg-white border-2 border-gray-800 rounded p-4 text-center">
                    <div class="text-2xl font-bold text-purple-700">¥{{ number_format($reservations->sum('total_amount')) }}</div>
                    <div class="text-sm text-gray-700 font-bold">総売上予定</div>
                </div>
            </div>

            <!-- 凡例（エクセル風） -->
            <div class="bg-white border-2 border-gray-800 rounded p-4">
                <h4 class="font-bold text-gray-900 mb-3 text-lg">📖 凡例</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div class="flex items-center">
                        <div class="w-6 h-6 border border-gray-600 rounded mr-3 reservation-cell new-customer">★新</div>
                        <span class="font-bold text-gray-800">新規顧客（クリック可能）</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 border border-gray-600 rounded mr-3 reservation-cell existing-customer">●既</div>
                        <span class="font-bold text-gray-800">既存顧客（クリック可能）</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 border-2 border-red-600 rounded mr-3 flex items-center justify-center text-red-700 text-xs font-bold" style="background-color: #fbbf24 !important;">NOW</div>
                        <span class="font-bold text-gray-800">現在時刻</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 border border-gray-600 rounded mr-3" style="background-color: #9ca3af !important;"></div>
                        <span class="font-bold text-gray-800">営業時間外</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 border border-gray-600 rounded mr-3" style="background-color: #f9fafb !important;"></div>
                        <span class="font-bold text-gray-800">空き時間</span>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>

    <style>
        /* エクセル風のスタイル */
        table {
            font-family: 'Times New Roman', serif;
            font-size: 12px;
        }
        
        th, td {
            border-color: #374151 !important;
        }
        
        .border-gray-800 {
            border-color: #1f2937 !important;
        }
        
        .border-gray-600 {
            border-color: #4b5563 !important;
        }

        /* シンプル予約セル */
        .reservation-cell {
            height: 100%;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            transition: opacity 0.2s;
        }
        
        .reservation-cell:hover {
            opacity: 0.8;
        }
        
        /* 新規顧客 - 緑色 */
        .new-customer {
            background-color: #22c55e !important;
        }
        
        /* 既存顧客 - 青色 */
        .existing-customer {
            background-color: #3b82f6 !important;
        }

        /* シンプルモーダル */
        .simple-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border: 2px solid #374151;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
    </style>

    <script>
        console.log('✅ ガントチャートJavaScript loaded');
        
        // グローバル関数として確実に定義
        window.openReservationModalFromData = function(element) {
            console.log('openReservationModalFromData called', element);

            try {
                const modal = document.getElementById('reservationModal');
                const content = document.getElementById('modalContent');

                console.log('Modal element:', modal);
                console.log('Content element:', content);

                if (!modal || !content) {
                    console.error('Modal elements not found');
                    return;
                }

                // データを取得
                const reservationId = element.dataset.reservationId;
                const customerId = element.dataset.customerId;
                const customerName = element.dataset.customerName || 'Unknown';
                const customerType = element.dataset.customerType || '不明';
                const reservationNumber = element.dataset.reservationNumber || '-';
                const date = element.dataset.date || '-';
                const time = element.dataset.time || '-';
                const store = element.dataset.store || '-';
                const menu = element.dataset.menu || '-';
                const amount = element.dataset.amount || '0';
                const notes = element.dataset.notes || '';
                const phone = element.dataset.phone || '-';

                // モーダルのHTMLを構築
                content.innerHTML = `
                    <div style="position: relative;">
                        <button onclick="closeReservationModal()" style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 18px;">×</button>

                        <h3 style="font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #1f2937;">📋 予約詳細</h3>

                        <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <span style="display: inline-block; padding: 2px 8px; background: ${customerType === '新規' ? '#22c55e' : '#3b82f6'}; color: white; border-radius: 4px; font-size: 12px; font-weight: bold; margin-right: 10px;">
                                    ${customerType}
                                </span>
                                <strong style="font-size: 18px; color: #1f2937;">${customerName}様</strong>
                            </div>
                            <div style="color: #6b7280; font-size: 14px;">
                                <div>📞 電話: ${phone}</div>
                                <div>🏪 店舗: ${store}</div>
                            </div>
                        </div>

                        <div style="space-y: 12px;">
                            <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px;">
                                <div style="color: #6b7280; font-size: 12px;">予約番号</div>
                                <div style="font-weight: bold; color: #1f2937;">${reservationNumber}</div>
                            </div>

                            <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px;">
                                <div style="color: #6b7280; font-size: 12px;">日時</div>
                                <div style="font-weight: bold; color: #1f2937;">📅 ${date} ⏰ ${time}</div>
                            </div>

                            <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px;">
                                <div style="color: #6b7280; font-size: 12px;">メニュー</div>
                                <div style="font-weight: bold; color: #1f2937;">${menu}</div>
                            </div>

                            <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px;">
                                <div style="color: #6b7280; font-size: 12px;">金額</div>
                                <div style="font-weight: bold; color: #1f2937; font-size: 18px;">¥${amount}</div>
                            </div>

                            ${notes ? `
                            <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 8px;">
                                <div style="color: #6b7280; font-size: 12px;">備考</div>
                                <div style="color: #1f2937; background: #fef3c7; padding: 8px; border-radius: 4px; margin-top: 4px;">${notes}</div>
                            </div>
                            ` : ''}
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button onclick="openCustomerChart('${customerId}', '${customerName}')" style="flex: 1; padding: 10px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                👤 カルテを開く
                            </button>
                            <button onclick="editReservation('${reservationId}')" style="flex: 1; padding: 10px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                ✏️ 予約を編集
                            </button>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <button onclick="changeReservationTime('${reservationId}')" style="flex: 1; padding: 10px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                🕐 時間変更
                            </button>
                            <button onclick="cancelReservation('${reservationId}')" style="flex: 1; padding: 10px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                ❌ キャンセル
                            </button>
                        </div>
                    </div>
                `;
                modal.style.display = 'block';

                console.log('Modal displayed');
            } catch (error) {
                console.error('Error in openReservationModalFromData:', error);
                alert('モーダルを開く際にエラーが発生しました: ' + error.message);
            }
        }
        
        // グローバル関数として定義
        window.closeReservationModal = function() {
            document.getElementById('reservationModal').style.display = 'none';
        }
        
        // カルテを開く
        window.openCustomerChart = function(customerId, customerName) {
            const customerUrl = `/admin/customers/${customerId}`;
            window.open(customerUrl, '_blank');
            window.closeReservationModal();
        }
        
        // 予約編集を開く
        window.editReservation = function(reservationId) {
            const editUrl = `/admin/reservations/${reservationId}/edit`;
            window.open(editUrl, '_blank');
            window.closeReservationModal();
        }

        // 時間変更
        window.changeReservationTime = function(reservationId) {
            if (confirm('予約時間を変更しますか？')) {
                const editUrl = `/admin/reservations/${reservationId}/edit`;
                window.location.href = editUrl;
            }
        }

        // 予約キャンセル
        window.cancelReservation = function(reservationId) {
            if (confirm('この予約をキャンセルしてよろしいですか？\n\nこの操作は取り消せません。')) {
                fetch(`/admin/api/reservations/${reservationId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('キャンセル処理に失敗しました');
                    }
                    return response.json();
                })
                .then(data => {
                    alert('予約をキャンセルしました');
                    window.closeReservationModal();
                    // ページをリロードして最新の状態を表示
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Cancel error:', error);
                    alert('キャンセル処理中にエラーが発生しました。\n\n予約編集画面からキャンセルしてください。');
                    const editUrl = `/admin/reservations/${reservationId}/edit`;
                    window.open(editUrl, '_blank');
                });
            }
        }

        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            const modal = document.getElementById('reservationModal');
            if (event.target === modal) {
                closeReservationModal();
            }
        }
    </script>

    <!-- シンプルモーダル -->
    <div id="reservationModal" class="simple-modal">
        <div class="modal-content" id="modalContent">
        </div>
    </div>
</x-filament-widgets::widget>