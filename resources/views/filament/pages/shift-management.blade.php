<x-filament-panels::page>
    <style>
        .shift-cell {
            min-height: 120px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .shift-cell:hover {
            background: #f9fafb !important;
        }
        .shift-badge {
            display: inline-block;
            padding: 2px 8px;
            margin: 2px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            background: #dbeafe;
            color: #1e40af;
            white-space: nowrap;
        }
        .shift-badge.full {
            background: #dcfce7;
            color: #166534;
        }
        .shift-badge.morning {
            background: #fef3c7;
            color: #92400e;
        }
        .shift-badge.evening {
            background: #e9d5ff;
            color: #6b21a8;
        }
        .quick-add-form {
            background: #f3f4f6;
            padding: 12px;
            border-radius: 6px;
            margin-top: 8px;
        }
        .day-header {
            font-weight: 600;
            padding: 8px;
            text-align: center;
            background: #f9fafb;
        }
        .day-header.sunday { color: #dc2626; }
        .day-header.saturday { color: #2563eb; }
    </style>

    <div class="space-y-6">
        <!-- コントロールパネル -->
        <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <!-- 店舗選択 -->
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium">店舗:</label>
                    <select wire:model.live="selectedStore" wire:change="changeStore" 
                            class="border-gray-300 rounded-md text-sm px-3 py-1.5">
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- 月選択 -->
                <div class="flex items-center gap-3">
                    <button wire:click="previousMonth" 
                            class="p-2 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="font-semibold text-lg min-w-[120px] text-center">
                        {{ $currentYear }}年{{ $currentMonth }}月
                    </div>
                    <button wire:click="nextMonth" 
                            class="p-2 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
                
                <!-- アクションボタン -->
                <div class="flex gap-2">
                    <button onclick="Livewire.dispatch('open-bulk-edit-modal')"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                        一括編集
                    </button>
                    <button onclick="Livewire.dispatch('open-pattern-modal')"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                        パターン管理
                    </button>
                </div>
            </div>
        </div>

        <!-- 凡例 -->
        <div class="bg-white rounded-lg shadow-sm p-3">
            <div class="flex items-center gap-6 text-sm">
                <span class="font-medium">凡例:</span>
                <div class="flex items-center gap-2">
                    <span class="shift-badge full">終日</span>
                    <span class="text-gray-600">10:00-20:00</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="shift-badge morning">朝番</span>
                    <span class="text-gray-600">10:00-15:00</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="shift-badge evening">遅番</span>
                    <span class="text-gray-600">15:00-20:00</span>
                </div>
            </div>
        </div>

        <!-- カレンダー -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="grid grid-cols-7">
                <!-- 曜日ヘッダー -->
                <div class="day-header sunday">日</div>
                <div class="day-header">月</div>
                <div class="day-header">火</div>
                <div class="day-header">水</div>
                <div class="day-header">木</div>
                <div class="day-header">金</div>
                <div class="day-header saturday">土</div>
                
                <!-- カレンダー本体 -->
                @foreach($calendarData as $week)
                    @foreach($week as $day)
                        <div class="shift-cell border-t border-r p-2 
                                    {{ !$day['isCurrentMonth'] ? 'bg-gray-50 opacity-50' : '' }}
                                    {{ $day['isToday'] ? 'bg-yellow-50' : '' }}"
                             x-data="{ showQuickAdd: false }">
                            
                            <!-- 日付 -->
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-semibold text-sm 
                                           {{ $day['dayOfWeek'] === 0 ? 'text-red-600' : '' }}
                                           {{ $day['dayOfWeek'] === 6 ? 'text-blue-600' : '' }}">
                                    {{ $day['day'] }}
                                </span>
                                @if($day['isCurrentMonth'] && !$day['isPast'])
                                    <button @click="showQuickAdd = !showQuickAdd"
                                            class="text-blue-600 hover:text-blue-800 text-xs">
                                        + 追加
                                    </button>
                                @endif
                            </div>
                            
                            <!-- シフト表示 -->
                            <div class="space-y-1">
                                @foreach($day['shifts'] as $shift)
                                    <div class="flex items-center justify-between group">
                                        <span class="shift-badge 
                                                   {{ strlen($shift['start']) > 0 && $shift['start'] == '10:00' && $shift['end'] == '20:00' ? 'full' : '' }}
                                                   {{ strlen($shift['start']) > 0 && $shift['start'] == '10:00' && $shift['end'] == '15:00' ? 'morning' : '' }}
                                                   {{ strlen($shift['start']) > 0 && $shift['start'] == '15:00' && $shift['end'] == '20:00' ? 'evening' : '' }}">
                                            {{ $shift['user_name'] }} {{ $shift['start'] }}-{{ $shift['end'] }}
                                        </span>
                                        <button wire:click="deleteShift({{ $shift['id'] }})"
                                                class="opacity-0 group-hover:opacity-100 text-red-500 hover:text-red-700 text-xs ml-1">
                                            ×
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            
                            <!-- クイック追加フォーム -->
                            <div x-show="showQuickAdd" 
                                 x-transition
                                 class="quick-add-form"
                                 @click.stop>
                                <div class="space-y-2">
                                    <select id="staff-{{ $day['dateKey'] }}" class="w-full text-xs border-gray-300 rounded">
                                        <option value="">スタッフ選択</option>
                                        @foreach($staffList as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex gap-1">
                                        <input type="time" id="start-{{ $day['dateKey'] }}" value="10:00" class="flex-1 text-xs border-gray-300 rounded">
                                        <span class="text-xs self-center">-</span>
                                        <input type="time" id="end-{{ $day['dateKey'] }}" value="20:00" class="flex-1 text-xs border-gray-300 rounded">
                                    </div>
                                    <div class="flex gap-1">
                                        <button onclick="quickAddShift('{{ $day['dateKey'] }}')"
                                                class="flex-1 px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                                            追加
                                        </button>
                                        <button @click="showQuickAdd = false"
                                                class="px-2 py-1 bg-gray-300 text-gray-700 rounded text-xs hover:bg-gray-400">
                                            ×
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        <!-- スタッフ別サマリー -->
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h3 class="font-semibold text-lg mb-3">スタッフ別シフト数</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($staffList as $staff)
                    @php
                        $shiftCount = 0;
                        $totalHours = 0;
                        foreach($calendarData as $week) {
                            foreach($week as $day) {
                                foreach($day['shifts'] as $shift) {
                                    if($shift['user_id'] == $staff->id) {
                                        $shiftCount++;
                                        $start = Carbon::parse($shift['start']);
                                        $end = Carbon::parse($shift['end']);
                                        $totalHours += $start->diffInHours($end);
                                    }
                                }
                            }
                        }
                    @endphp
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="font-medium">{{ $staff->name }}</div>
                        <div class="text-sm text-gray-600">{{ $shiftCount }}日 / {{ $totalHours }}時間</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- モーダル -->
    @livewire('shift-bulk-edit-modal')
    @livewire('shift-pattern-modal')

    <script>
        function quickAddShift(dateKey) {
            const staffSelect = document.getElementById('staff-' + dateKey);
            const startInput = document.getElementById('start-' + dateKey);
            const endInput = document.getElementById('end-' + dateKey);
            
            if (staffSelect.value && startInput.value && endInput.value) {
                @this.quickAddShift(dateKey, staffSelect.value, startInput.value, endInput.value);
            }
        }
    </script>
</x-filament-panels::page>