<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            予約ガントチャート
        </x-slot>
        
        {{-- コントロールパネル --}}
        <div class="flex flex-wrap gap-4 mb-6">
            <div class="flex items-center gap-2">
                <button wire:click="previousDay" class="p-2 hover:bg-gray-100 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                
                <input type="date" 
                       wire:model.live="selectedDate"
                       class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                
                <button wire:click="nextDay" class="p-2 hover:bg-gray-100 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                
                <button wire:click="today" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                    今日
                </button>
            </div>
            
            <div>
                <select wire:model.live="selectedStore"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="ml-auto text-sm text-gray-600">
                予約数: {{ $reservations->count() }}件
            </div>
        </div>
        
        {{-- ガントチャート --}}
        <div class="overflow-x-auto">
            <div class="min-w-[1200px]">
                {{-- タイムヘッダー --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <div class="w-32 p-2 font-semibold border-r border-gray-200 dark:border-gray-700">
                        スタッフ
                    </div>
                    <div class="flex-1 relative">
                        <div class="flex">
                            @foreach($timeSlots as $time)
                                @if(str_ends_with($time, ':00'))
                                    <div class="w-60 text-center text-sm font-medium p-1 border-r border-gray-200 dark:border-gray-700">
                                        {{ $time }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                
                {{-- スタッフ行 --}}
                @forelse($staffList as $staff)
                    <div class="flex border-b border-gray-200 dark:border-gray-700 min-h-[60px]">
                        <div class="w-32 p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                            <div class="font-medium text-sm">
                                {{ $staff->name ?? '未割当' }}
                            </div>
                        </div>
                        <div class="flex-1 relative bg-white dark:bg-gray-900">
                            {{-- 時間グリッド --}}
                            <div class="absolute inset-0 flex pointer-events-none">
                                @foreach($timeSlots as $time)
                                    <div class="w-[60px] border-r border-gray-100 dark:border-gray-800 
                                                {{ str_ends_with($time, ':00') ? 'border-gray-200 dark:border-gray-700' : '' }}">
                                    </div>
                                @endforeach
                            </div>
                            
                            {{-- 予約ブロック --}}
                            <div class="relative h-full" style="min-height: 60px;">
                                @foreach($this->getReservationsByStaff($staff->id ?? 0) as $reservation)
                                    @php
                                        $position = $this->getReservationPosition($reservation);
                                        $statusColors = [
                                            'confirmed' => 'bg-green-500',
                                            'pending' => 'bg-yellow-500',
                                            'completed' => 'bg-blue-500',
                                            'no_show' => 'bg-red-500',
                                        ];
                                        $bgColor = $statusColors[$reservation->status] ?? 'bg-gray-500';
                                    @endphp
                                    
                                    <div class="absolute top-2 {{ $bgColor }} text-white rounded px-2 py-1 text-xs shadow-lg hover:shadow-xl transition-shadow cursor-pointer"
                                         style="left: {{ $position['left'] }}px; width: {{ $position['width'] }}px; z-index: 10;"
                                         title="{{ $reservation->customer->name }} - {{ $reservation->menu->name }}"
                                         wire:click="$dispatch('openModal', { component: 'reservation-detail-modal', arguments: { reservationId: {{ $reservation->id }} } })">
                                        <div class="font-semibold truncate">
                                            {{ Carbon\Carbon::parse($reservation->reservation_time)->format('H:i') }}
                                        </div>
                                        <div class="truncate">
                                            {{ $reservation->customer->name }}
                                        </div>
                                        <div class="truncate text-xs opacity-90">
                                            {{ $reservation->menu->name }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500">
                        この日の予約はありません
                    </div>
                @endforelse
            </div>
        </div>
        
        {{-- 凡例 --}}
        <div class="mt-4 flex gap-4 text-sm">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-green-500 rounded"></div>
                <span>確定</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                <span>保留</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-blue-500 rounded"></div>
                <span>完了</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-500 rounded"></div>
                <span>無断キャンセル</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>