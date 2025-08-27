<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <!-- ヘッダー -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">
                    📅 {{ $this->getData()['todayDate'] }} の予約タイムライン
                    @if($this->getData()['isToday'])
                        <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">本日</span>
                    @endif
                </h2>
                
                <div class="flex items-center space-x-4">
                    <!-- 日付ナビゲーション -->
                    <div class="flex items-center space-x-2">
                        <button 
                            wire:click="goToPreviousDay" 
                            @if(!$this->getData()['canNavigateBack']) disabled @endif
                            class="p-2 rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            title="前日">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        
                        <input 
                            type="date" 
                            wire:model.live="selectedDate"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        
                        <button 
                            wire:click="goToNextDay"
                            @if(!$this->getData()['canNavigateForward']) disabled @endif
                            class="p-2 rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            title="翌日">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        
                        @if(!$this->getData()['isToday'])
                            <button 
                                wire:click="goToToday"
                                class="px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-sm transition-colors">
                                今日
                            </button>
                        @endif
                    </div>

                    <!-- 凡例 -->
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <div class="flex items-center">
                            <i class="fas fa-star text-green-500 mr-2"></i>
                            新規客
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-user text-blue-500 mr-2"></i>
                            既存客
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-gray-300 rounded-full mr-2"></div>
                            空き時間
                        </div>
                    </div>
                </div>
            </div>

            @php
                $reservations = $this->getData()['reservations'];
                $totalReservations = $reservations->count();
            @endphp

            <!-- ガントチャート表示 -->
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <div class="min-w-[1200px] px-4 sm:px-0">
                    <!-- ヘッダー：時間軸（ガントチャート風） -->
                    <div class="sticky top-0 bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-300 mb-4 shadow-sm">
                        <div class="grid grid-cols-25 gap-0 p-3">
                            <div class="text-sm font-bold text-gray-700 p-2 bg-white rounded-l border-r border-gray-300 flex items-center">
                                📊 店舗 \ 時間
                            </div>
                            @foreach($this->getData()['timeSlots'] as $index => $slot)
                                <div class="text-xs font-semibold text-center text-gray-600 p-2 border-r border-gray-200 bg-white
                                    {{ $index === 0 ? '' : 'border-l' }}
                                    {{ $index === count($this->getData()['timeSlots']) - 1 ? 'rounded-r' : '' }}">
                                    <div class="mb-1">{{ $slot }}</div>
                                    <div class="h-px bg-gray-300 mx-1"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- 店舗別ガントチャート -->
                    @foreach($this->getData()['stores'] as $storeIndex => $store)
                        <div class="mb-6 border-2 border-gray-200 rounded-xl shadow-lg bg-white overflow-hidden">
                            <!-- 店舗ヘッダー -->
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                                <h3 class="font-bold text-white text-lg flex items-center">
                                    <span class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-3 text-sm">
                                        {{ $storeIndex + 1 }}
                                    </span>
                                    🏢 {{ $store->name }}
                                </h3>
                            </div>
                            
                            <!-- ガントチャートグリッド -->
                            <div class="p-4">
                                <div class="relative">
                                    <!-- 背景グリッド -->
                                    <div class="grid grid-cols-25 gap-0 h-16 border border-gray-200 rounded-lg overflow-hidden">
                                        <div class="bg-gradient-to-b from-gray-100 to-gray-200 border-r border-gray-300 flex items-center justify-center text-xs font-semibold text-gray-600">
                                            予約状況
                                        </div>
                                        
                                        @foreach($this->getData()['timeSlots'] as $slotIndex => $slot)
                                            <div class="bg-gray-50 border-r border-gray-200 relative
                                                {{ $slotIndex % 2 === 0 ? 'bg-opacity-100' : 'bg-opacity-50' }}">
                                                @php
                                                    $currentTime = now()->format('H:i');
                                                    $nextSlot = $this->getData()['timeSlots'][$slotIndex + 1] ?? '23:59';
                                                    $isCurrentTimeSlot = ($this->getData()['isToday'] && $slot <= $currentTime && $currentTime < $nextSlot);
                                                @endphp
                                                @if($isCurrentTimeSlot)
                                                    <div class="absolute inset-0 bg-gradient-to-r from-yellow-200 to-yellow-300 bg-opacity-40 border-2 border-yellow-500 border-dashed animate-pulse"></div>
                                                    <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 bg-yellow-500 text-white text-xs px-2 py-1 rounded-full font-bold shadow-lg">
                                                        NOW
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    
                                    <!-- 予約バー -->
                                    <div class="absolute inset-0 grid grid-cols-25 gap-0 pointer-events-none">
                                        <div class="pointer-events-none"></div> <!-- 店舗名スペース -->
                                        
                                        @php
                                            $storeReservations = $this->getData()['reservations']->where('store_id', $store->id);
                                            $timeSlots = $this->getData()['timeSlots'];
                                        @endphp
                                        
                                        @foreach($storeReservations as $reservation)
                                            @php
                                                $slotInfo = $reservation->slot_info ?? [
                                                    'startSlotIndex' => 0,
                                                    'duration' => 1,
                                                    'startTime' => $reservation->start_time,
                                                    'endTime' => $reservation->end_time
                                                ];
                                                
                                                $startSlotIndex = $slotInfo['startSlotIndex'];
                                                $duration = $slotInfo['duration'];
                                                $leftPosition = (($startSlotIndex + 1) / 25) * 100;
                                                $width = ($duration / 24) * 100;
                                            @endphp
                                            
                                            <div class="absolute pointer-events-auto"
                                                 style="left: {{ $leftPosition }}%; width: {{ $width }}%; top: 4px; height: calc(100% - 8px);">
                                                @if($reservation->is_new_customer)
                                                    <!-- 新規客バー -->
                                                    <div class="gantt-bar-new h-full rounded-lg shadow-md cursor-pointer transform hover:scale-105 transition-all duration-200"
                                                         title="{{ $reservation->customer->last_name }} {{ $reservation->customer->first_name }} 様&#10;{{ $reservation->menu->name ?? '' }}&#10;{{ $slotInfo['startTime'] }} - {{ $slotInfo['endTime'] }}&#10;新規顧客 | 予約番号: {{ $reservation->reservation_number }}">
                                                        <div class="h-full flex items-center px-2 text-white font-medium text-sm">
                                                            <i class="fas fa-star mr-1 text-white"></i>
                                                            <span class="truncate font-bold">{{ $reservation->customer->last_name }}様</span>
                                                            <div class="ml-2 text-xs opacity-90 hidden lg:block truncate">
                                                                {{ $reservation->menu->name ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <!-- 既存客バー -->
                                                    <div class="gantt-bar-existing h-full rounded-lg shadow-md cursor-pointer transform hover:scale-105 transition-all duration-200"
                                                         title="{{ $reservation->customer->last_name }} {{ $reservation->customer->first_name }} 様&#10;{{ $reservation->menu->name ?? '' }}&#10;{{ $slotInfo['startTime'] }} - {{ $slotInfo['endTime'] }}&#10;既存顧客 | 予約番号: {{ $reservation->reservation_number }}">
                                                        <div class="h-full flex items-center px-2 text-white font-medium text-sm">
                                                            <i class="fas fa-user mr-1 text-white"></i>
                                                            <span class="truncate font-bold">{{ $reservation->customer->last_name }}様</span>
                                                            <div class="ml-2 text-xs opacity-90 hidden lg:block truncate">
                                                                {{ $reservation->menu->name ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <!-- 予約なしの場合 -->
                    @if($totalReservations === 0)
                        <div class="text-center py-12 bg-gray-50 rounded-lg">
                            <div class="text-gray-400 text-6xl mb-4">📅</div>
                            <h3 class="text-lg font-medium text-gray-500 mb-2">
                                {{ $this->getData()['isToday'] ? '本日' : $this->getData()['todayDate'] }}の予約はありません
                            </h3>
                            <p class="text-gray-400">
                                {{ $this->getData()['isToday'] ? '新しい予約をお待ちしています' : '空いている日です' }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 選択日の予約リスト -->
            @if($totalReservations > 0)
                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        📋 {{ $this->getData()['isToday'] ? '本日' : $this->getData()['todayDate'] }}の予約詳細
                    </h3>
                    <div class="space-y-3">
                        @foreach($reservations as $reservation)
                            <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            @if($reservation->is_new_customer)
                                                <div class="dot-new w-3 h-3 rounded-full"></div>
                                            @else
                                                <div class="dot-existing w-3 h-3 rounded-full"></div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900">
                                                {{ $reservation->start_time }} - {{ $reservation->end_time }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm text-gray-600">
                                                    {{ $reservation->customer->last_name }} {{ $reservation->customer->first_name }} 様
                                                </span>
                                                @if($reservation->is_new_customer)
                                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">
                                                        <i class="fas fa-star mr-1"></i>新規
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">
                                                        <i class="fas fa-user mr-1"></i>既存
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="hidden sm:block">
                                            <div class="text-sm font-medium text-gray-700">
                                                {{ $reservation->menu->name ?? '-' }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $reservation->store->name }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-semibold text-green-600">
                                            ¥{{ number_format($reservation->total_amount) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $reservation->reservation_number }}
                                        </div>
                                    </div>
                                </div>
                                
                                @if($reservation->notes)
                                    <div class="mt-2 pt-2 border-t border-gray-100">
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">備考：</span>{{ $reservation->notes }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .grid-cols-25 {
            grid-template-columns: 140px repeat(24, minmax(60px, 1fr));
        }
        
        /* ガントチャートアニメーション */
        @keyframes gantt-slide-in {
            from {
                transform: scaleX(0);
                opacity: 0;
            }
            to {
                transform: scaleX(1);
                opacity: 1;
            }
        }
        
        .gantt-bar-new, .gantt-bar-existing {
            animation: gantt-slide-in 0.8s ease-out;
            transform-origin: left;
        }
        
        /* ガントチャート風バーのスタイル */
        .gantt-bar-new {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%) !important;
            border: 2px solid #15803d;
            position: relative;
        }
        .gantt-bar-new::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(to bottom, rgba(255,255,255,0.3), transparent);
            border-radius: 4px 4px 0 0;
        }
        .gantt-bar-new:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
            transform: scale(1.02) !important;
            box-shadow: 0 8px 16px rgba(34, 197, 94, 0.3) !important;
        }
        
        .gantt-bar-existing {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            border: 2px solid #1d4ed8;
            position: relative;
        }
        .gantt-bar-existing::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(to bottom, rgba(255,255,255,0.3), transparent);
            border-radius: 4px 4px 0 0;
        }
        .gantt-bar-existing:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            transform: scale(1.02) !important;
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3) !important;
        }
        
        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .grid-cols-25 {
                grid-template-columns: 100px repeat(24, minmax(40px, 1fr));
            }
            .gantt-bar-new, .gantt-bar-existing {
                font-size: 10px;
            }
        }
        
        /* 現在時刻のインジケーター */
        @keyframes current-time-pulse {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.7; }
        }
        
        .animate-pulse {
            animation: current-time-pulse 2s infinite;
        }
        
        .reservation-pending {
            background-color: #eab308 !important; /* yellow-500 */
        }
        .reservation-pending:hover {
            background-color: #ca8a04 !important; /* yellow-600 */
        }
        
        .dot-pending {
            background-color: #eab308 !important;
        }
        .dot-new {
            background-color: #22c55e !important;
        }
        .dot-existing {
            background-color: #3b82f6 !important;
        }
    </style>
</x-filament-widgets::widget>