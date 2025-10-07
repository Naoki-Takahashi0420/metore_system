<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <!-- ヘッダーコントロール -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <h2 class="text-xl font-semibold text-gray-900">
                        📊 予約タイムライン
                    </h2>
                </div>
                
                <div class="flex flex-wrap items-center gap-4">
                    <!-- 店舗選択 -->
                    @if(count($this->stores) > 1)
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-gray-700">店舗:</label>
                            <select 
                                wire:model.live="selectedStoreId"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($this->stores as $store)
                                    <option value="{{ $store['id'] }}">{{ $store['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    
                    <!-- 日付ナビゲーション -->
                    <div class="flex items-center space-x-2">
                        <button 
                            wire:click="changeDate('prev')"
                            class="p-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
                            title="前日">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        
                        <input 
                            type="date" 
                            wire:model.live="currentDate"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        
                        <button 
                            wire:click="changeDate('next')"
                            class="p-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
                            title="翌日">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        
                        <button
                            wire:click="goToToday"
                            class="px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-sm transition-colors">
                            今日
                        </button>

                        <button
                            wire:click="refreshData"
                            class="p-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
                            title="最新の予約を取得">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- タイムラインチャート -->
            <div class="overflow-x-auto">
                <div class="min-w-[1200px] bg-white border border-gray-200 rounded-lg shadow-sm">
                    <!-- 時間軸ヘッダー -->
                    <div class="sticky top-0 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 p-4">
                        <div class="flex items-center">
                            <div class="w-32 text-sm font-semibold text-gray-700">時間</div>
                            <div class="flex-1 flex">
                                @foreach($this->timeSlots as $slot)
                                    <div class="flex-1 text-center text-xs text-gray-600 border-r border-gray-200 last:border-r-0 py-2">
                                        {{ $slot }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- 予約バー表示エリア -->
                    <div class="p-4">
                        <div class="relative h-16 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                            <!-- グリッド背景 -->
                            <div class="absolute inset-0 flex">
                                @foreach($this->timeSlots as $index => $slot)
                                    <div class="flex-1 border-r border-gray-300 {{ $index % 2 === 0 ? 'bg-gray-100' : 'bg-gray-50' }}">
                                        @php
                                            $currentTime = now()->format('H:i');
                                            $isCurrentSlot = ($currentTime >= $slot && $currentTime < ($this->timeSlots[$index + 1] ?? '23:59'));
                                        @endphp
                                        @if($isCurrentSlot && \Carbon\Carbon::parse($this->currentDate)->isToday())
                                            <div class="absolute inset-0 bg-yellow-200 bg-opacity-50 border-2 border-yellow-400 border-dashed animate-pulse">
                                                <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 bg-yellow-500 text-white text-xs px-2 py-1 rounded-full font-bold">
                                                    NOW
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <!-- 予約バー -->
                            @foreach($this->reservations as $reservation)
                                <div 
                                    class="absolute top-2 bottom-2 rounded-lg shadow-md cursor-pointer transform hover:scale-105 transition-all duration-200"
                                    style="left: {{ $reservation['start_position'] }}%; width: {{ $reservation['width'] }}%; background: {{ $reservation['color'] }};"
                                    title="
                                        {{ $reservation['customer_name'] }} 様
                                        {{ $reservation['menu_name'] }}
                                        {{ $reservation['start_time'] }} - {{ $reservation['end_time'] }}
                                        ¥{{ number_format($reservation['total_amount']) }}
                                        {{ $reservation['status_text'] }}
                                        {{ $reservation['reservation_number'] }}
                                    ">
                                    <div class="h-full flex items-center px-2 text-white text-sm font-medium">
                                        <span class="mr-1">{{ $reservation['status_icon'] }}</span>
                                        <span class="truncate">{{ $reservation['customer_name'] }}</span>
                                        @if($reservation['is_new'])
                                            <span class="ml-1 w-2 h-2 bg-yellow-400 rounded-full animate-ping"></span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- 凡例 -->
                        <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-gray-600">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-blue-500 rounded mr-2"></div>
                                既存予約
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-blue-800 rounded mr-2"></div>
                                新規予約（24時間以内）
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                                来店済み
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                                キャンセル
                            </div>
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-yellow-400 rounded-full animate-ping mr-2"></div>
                                新規予約インジケーター
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 予約詳細リスト -->
            @if(count($this->reservations) > 0)
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            📋 {{ \Carbon\Carbon::parse($this->currentDate)->format('Y年n月j日') }}の予約詳細
                        </h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($this->reservations as $reservation)
                            <div class="p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div 
                                            class="w-4 h-4 rounded-full"
                                            style="background-color: {{ $reservation['color'] }}">
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900">
                                                {{ $reservation['start_time'] }} - {{ $reservation['end_time'] }}
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                {{ $reservation['customer_name'] }} 様
                                                @if($reservation['is_new'])
                                                    <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">NEW</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="hidden md:block">
                                            <div class="text-sm font-medium text-gray-700">
                                                {{ $reservation['menu_name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $reservation['store_name'] }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-green-600">
                                            ¥{{ number_format($reservation['total_amount']) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $reservation['reservation_number'] }}
                                        </div>
                                    </div>
                                </div>
                                
                                @if($reservation['notes'])
                                    <div class="mt-2 pt-2 border-t border-gray-100">
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">備考：</span>{{ $reservation['notes'] }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                    <div class="text-gray-400 text-6xl mb-4">📅</div>
                    <h3 class="text-lg font-medium text-gray-500 mb-2">
                        {{ \Carbon\Carbon::parse($this->currentDate)->format('Y年n月j日') }}の予約はありません
                    </h3>
                    <p class="text-gray-400">
                        {{ \Carbon\Carbon::parse($this->currentDate)->isToday() ? '新しい予約をお待ちしています' : '空いている日です' }}
                    </p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>