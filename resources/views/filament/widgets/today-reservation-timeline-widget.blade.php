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
                    
                    <!-- 店舗別予約行 -->
                    <tbody>
                        @foreach($stores as $store)
                            @php
                                $storeReservations = $reservations->where('store_id', $store->id);
                                $businessHours = $this->getStoreBusinessHours($store);
                            @endphp
                            <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                                <!-- 店舗名列 -->
                                <td class="border-2 border-gray-800 px-4 py-3 bg-blue-50">
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
                                </td>
                                
                                <!-- 時間軸セル -->
                                @foreach($timeSlots as $slotIndex => $slot)
                                    @php
                                        // この時間に予約があるかチェック（より厳密に）
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
                                        
                                        $reservation = $slotReservations->first();
                                        $isBusinessHour = $businessHours['is_open'] && ($slot >= $businessHours['open'] && $slot < $businessHours['close']);
                                        $isCurrentTimeSlot = ($isToday && $slot <= $currentTime && $currentTime < ($timeSlots[$slotIndex + 1] ?? '23:59'));
                                    @endphp
                                    
                                    <td class="border border-gray-600 text-center relative p-0" style="height: 40px;">
                                        @if($reservation)
                                            <!-- 予約あり -->
                                            @if($reservation->is_new_customer)
                                                <!-- 新規顧客 - 鮮やかな緑色 -->
                                                <div class="h-full w-full cursor-pointer hover:opacity-80 flex items-center justify-center text-white text-xs font-bold transition-all"
                                                     style="background-color: #22c55e !important;"
                                                     wire:click="openReservationModal({{ $reservation->id }})"
                                                     title="{{ $reservation->customer->last_name }}{{ $reservation->customer->first_name }}様 (新規) - クリックで詳細">
                                                    ★新
                                                </div>
                                            @else
                                                <!-- 既存顧客 - 鮮やかな青色 -->
                                                <div class="h-full w-full cursor-pointer hover:opacity-80 flex items-center justify-center text-white text-xs font-bold transition-all"
                                                     style="background-color: #3b82f6 !important;"
                                                     wire:click="openReservationModal({{ $reservation->id }})"
                                                     title="{{ $reservation->customer->last_name }}{{ $reservation->customer->first_name }}様 (既存) - クリックで詳細">
                                                    ●既
                                                </div>
                                            @endif
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
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- 予約詳細モーダル -->
            @if($showReservationModal && $this->getSelectedReservation())
                @php
                    $selectedReservation = $this->getSelectedReservation();
                @endphp
                <div class="fixed inset-0 z-50 overflow-y-auto" wire:key="reservation-modal-{{ $selectedReservation->id }}">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                        <div class="fixed inset-0 transition-opacity" wire:click="closeReservationModal">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>

                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border-2 border-gray-800">
                            <div class="bg-white px-6 pt-6 pb-4">
                                <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4 border-b-2 border-gray-200 pb-2">
                                    📋 予約詳細
                                </h3>
                                
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div class="col-span-2">
                                        <label class="font-bold text-gray-700">顧客名</label>
                                        <div class="text-gray-900 bg-gray-50 p-2 rounded border">
                                            {{ $selectedReservation->customer->last_name ?? '' }} {{ $selectedReservation->customer->first_name ?? '' }} 様
                                            @if($selectedReservation->is_new_customer)
                                                <span class="ml-2 bg-green-200 text-green-800 px-2 py-1 rounded text-xs font-bold">新規</span>
                                            @else
                                                <span class="ml-2 bg-blue-200 text-blue-800 px-2 py-1 rounded text-xs">既存</span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="font-bold text-gray-700">電話番号</label>
                                        <div class="text-gray-900 bg-gray-50 p-2 rounded border">{{ $selectedReservation->customer->phone ?? '-' }}</div>
                                    </div>
                                    
                                    <div>
                                        <label class="font-bold text-gray-700">予約番号</label>
                                        <div class="text-gray-900 bg-gray-50 p-2 rounded border font-mono text-xs">{{ $selectedReservation->reservation_number }}</div>
                                    </div>
                                    
                                    <div>
                                        <label class="font-bold text-gray-700">日時</label>
                                        <div class="text-gray-900 bg-gray-50 p-2 rounded border">
                                            {{ $selectedReservation->reservation_date->format('Y/n/j') }}<br>
                                            {{ $selectedReservation->start_time }} - {{ $selectedReservation->end_time }}
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="font-bold text-gray-700">店舗</label>
                                        <div class="text-gray-900 bg-gray-50 p-2 rounded border">{{ $selectedReservation->store->name ?? '-' }}</div>
                                    </div>
                                    
                                    <div class="col-span-2">
                                        <label class="font-bold text-gray-700">メニュー</label>
                                        <div class="text-gray-900 bg-gray-50 p-2 rounded border">{{ $selectedReservation->menu->name ?? '-' }}</div>
                                    </div>
                                    
                                    <div>
                                        <label class="font-bold text-gray-700">金額</label>
                                        <div class="text-lg font-bold text-green-600 bg-gray-50 p-2 rounded border">¥{{ number_format($selectedReservation->total_amount) }}</div>
                                    </div>
                                    
                                    @if($selectedReservation->notes)
                                        <div class="col-span-2">
                                            <label class="font-bold text-gray-700">備考</label>
                                            <div class="text-gray-900 bg-yellow-50 p-2 rounded border">{{ $selectedReservation->notes }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="bg-gray-100 px-6 py-3 border-t-2 border-gray-200">
                                <button wire:click="closeReservationModal" type="button" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded border-2 border-blue-800 transition-colors">
                                    閉じる
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

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
                        <div class="w-6 h-6 border border-gray-600 rounded mr-3 flex items-center justify-center text-white font-bold text-xs" style="background-color: #22c55e !important;">★新</div>
                        <span class="font-bold text-gray-800">新規顧客（クリック可能）</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-6 h-6 border border-gray-600 rounded mr-3 flex items-center justify-center text-white font-bold text-xs" style="background-color: #3b82f6 !important;">●既</div>
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
    </style>
</x-filament-widgets::widget>