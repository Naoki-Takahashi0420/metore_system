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

            <!-- タイムライン表示 -->
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <div class="min-w-[800px] px-4 sm:px-0">
                    <!-- ヘッダー：時間軸 -->
                    <div class="sticky top-0 bg-white border-b-2 border-gray-200 mb-4">
                        <div class="grid grid-cols-25 gap-1 p-2">
                            <div class="text-xs font-semibold text-gray-500 p-1 sm:p-2">時間</div>
                            @foreach($this->getData()['timeSlots'] as $slot)
                                <div class="text-xs text-center text-gray-500 p-0.5 sm:p-1 border-r border-gray-100">
                                    {{ $slot }}
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- 店舗別タイムライン -->
                    @foreach($this->getData()['stores'] as $store)
                        <div class="mb-4 border rounded-lg p-4 bg-gray-50">
                            <h3 class="font-semibold text-gray-800 mb-3">🏢 {{ $store->name }}</h3>
                            
                            <div class="grid grid-cols-25 gap-1">
                                <div class="text-sm font-medium text-gray-700 p-2 bg-white rounded">
                                    予約状況
                                </div>
                                
                                @foreach($this->getData()['timeSlots'] as $slot)
                                    @php
                                        $reservation = $this->getReservationAtTime($slot, $store->id);
                                    @endphp
                                    
                                    <div class="relative">
                                        @if($reservation)
                                            @if($reservation->is_new_customer)
                                                <!-- 新規客 - 緑色 -->
                                                <div class="reservation-new text-white text-center p-1 sm:p-2 rounded shadow-sm cursor-pointer transition-colors text-xs sm:text-sm" 
                                                     title="{{ $reservation->customer->last_name }} {{ $reservation->customer->first_name }} 様&#10;{{ $reservation->menu->name ?? '' }}&#10;{{ $slot }} - {{ $reservation->end_time }}&#10;新規顧客">
                                                    <i class="fas fa-star text-lg mb-1"></i>
                                                    <div class="text-xs font-medium truncate">
                                                        {{ $reservation->customer->last_name }}
                                                    </div>
                                                </div>
                                            @else
                                                <!-- 既存客 - 青色 -->
                                                <div class="reservation-existing text-white text-center p-1 sm:p-2 rounded shadow-sm cursor-pointer transition-colors text-xs sm:text-sm" 
                                                     title="{{ $reservation->customer->last_name }} {{ $reservation->customer->first_name }} 様&#10;{{ $reservation->menu->name ?? '' }}&#10;{{ $slot }} - {{ $reservation->end_time }}&#10;既存顧客">
                                                    <i class="fas fa-user text-lg mb-1"></i>
                                                    <div class="text-xs font-medium truncate">
                                                        {{ $reservation->customer->last_name }}
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="bg-gray-200 h-12 rounded border-2 border-dashed border-gray-300 opacity-50">
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
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
            grid-template-columns: 120px repeat(24, minmax(60px, 1fr));
        }
        
        /* 色の強制適用 */
        .reservation-pending {
            background-color: #eab308 !important; /* yellow-500 */
        }
        .reservation-pending:hover {
            background-color: #ca8a04 !important; /* yellow-600 */
        }
        .reservation-new {
            background-color: #22c55e !important; /* green-500 */
        }
        .reservation-new:hover {
            background-color: #16a34a !important; /* green-600 */
        }
        .reservation-existing {
            background-color: #3b82f6 !important; /* blue-500 */
        }
        .reservation-existing:hover {
            background-color: #2563eb !important; /* blue-600 */
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