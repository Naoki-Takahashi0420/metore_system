<x-filament-panels::page>
    <div class="space-y-4">
        <!-- ナビゲーションヘッダー -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between mb-4">
                <button 
                    wire:click="previousWeek"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                
                <h2 class="text-xl font-bold text-gray-900">
                    {{ $weekStart }} - {{ $weekEnd }}
                </h2>
                
                <button 
                    wire:click="nextWeek"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
            
            @if($isSuperAdmin)
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700">店舗選択:</label>
                    <select 
                        wire:model.live="selectedStoreId"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">全店舗</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <!-- カレンダーグリッド -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-gray-200 bg-gray-50 p-2 text-sm font-medium text-gray-700 sticky left-0 z-10 w-20">
                                時間
                            </th>
                            @foreach($weekDays as $day)
                                <th class="border border-gray-200 bg-gray-50 p-2 text-sm font-medium {{ $day['isToday'] ? 'bg-blue-50' : '' }} min-w-[120px]">
                                    <div>{{ $day['date']->format('m/d') }}</div>
                                    <div class="text-xs {{ $day['isToday'] ? 'text-blue-600 font-bold' : 'text-gray-500' }}">
                                        ({{ $day['dayJa'] }})
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeSlots as $timeIndex => $time)
                            <tr class="{{ substr($time, -2) == '00' ? 'border-t-2 border-gray-400' : '' }}">
                                <td class="border border-gray-200 bg-gray-50 p-1 text-xs font-medium text-gray-700 sticky left-0 z-10">
                                    {{ $time }}
                                </td>
                                @foreach($weekDays as $day)
                                    @php
                                        $cellShifts = collect();
                                        foreach($day['shifts'] as $shift) {
                                            $shiftStart = \Carbon\Carbon::parse($shift->start_time);
                                            $shiftEnd = \Carbon\Carbon::parse($shift->end_time);
                                            $cellTime = \Carbon\Carbon::parse($day['date']->format('Y-m-d') . ' ' . $time);
                                            $cellTimeEnd = $cellTime->copy()->addMinutes(30);
                                            
                                            // このセルの時間帯にシフトが重なっているか確認
                                            if ($cellTime >= $shiftStart && $cellTime < $shiftEnd) {
                                                $cellShifts->push($shift);
                                            }
                                        }
                                    @endphp
                                    
                                    <td class="border border-gray-200 p-0 relative h-10 {{ $day['isToday'] ? 'bg-blue-50/10' : '' }}">
                                        @if($cellShifts->count() > 0)
                                            <div class="absolute inset-0 flex flex-row gap-0.5 p-0">
                                                @foreach($cellShifts as $shiftIndex => $shift)
                                                    @php
                                                        $shiftStart = \Carbon\Carbon::parse($shift->start_time);
                                                        $shiftEnd = \Carbon\Carbon::parse($shift->end_time);
                                                        $cellTime = \Carbon\Carbon::parse($day['date']->format('Y-m-d') . ' ' . $time);
                                                        
                                                        // シフトの開始・終了セルか判定
                                                        $isStartCell = $cellTime->format('H:i') == $shiftStart->format('H:i');
                                                        $isEndCell = $cellTime->copy()->addMinutes(30)->format('H:i') == $shiftEnd->format('H:i');
                                                        
                                                        // 何分間のシフトか計算
                                                        $duration = $shiftStart->diffInMinutes($shiftEnd);
                                                        $cellsSpan = ceil($duration / 30);
                                                        
                                                        // 背景色を決定（よりビビッドな色に変更）
                                                        $bgColor = 'bg-blue-500';
                                                        $textColor = 'text-white';
                                                        
                                                        if ($shift->actual_start_time) {
                                                            if ($shift->status === 'completed') {
                                                                $bgColor = 'bg-green-500';
                                                            } elseif ($shift->status === 'working') {
                                                                $bgColor = 'bg-amber-500';
                                                            }
                                                        } else {
                                                            $bgColor = 'bg-gray-400';
                                                            $textColor = 'text-white';
                                                        }
                                                        
                                                        // 複数のシフトがある場合は幅を分割
                                                        $width = $cellShifts->count() > 1 ? 'w-1/2' : 'w-full';
                                                    @endphp
                                                    
                                                    <div class="{{ $width }} h-full relative">
                                                        @if($isStartCell)
                                                            <!-- 縦長のシフトバー -->
                                                            @php
                                                                // 何セル分の高さか計算
                                                                $cellHeight = ceil($duration / 30);
                                                                $heightClass = 'h-[' . ($cellHeight * 40) . 'px]';
                                                            @endphp
                                                            <div class="absolute top-0 left-0 {{ $bgColor }} {{ $textColor }} rounded-lg shadow-md border border-white/50 overflow-hidden z-20" 
                                                                 style="height: {{ $cellHeight * 40 }}px; width: calc(100% - 2px);">
                                                                <div class="p-1">
                                                                    <div class="font-bold text-[11px] leading-tight">
                                                                        {{ $shift->user->name }}
                                                                    </div>
                                                                    <div class="text-[9px] opacity-90 leading-tight">
                                                                        {{ $shiftStart->format('H:i') }}-{{ $shiftEnd->format('H:i') }}
                                                                    </div>
                                                                    @if($shift->actual_start_time)
                                                                        <div class="text-[9px] opacity-80 leading-tight mt-0.5">
                                                                            実:{{ \Carbon\Carbon::parse($shift->actual_start_time)->format('H:i') }}
                                                                        </div>
                                                                    @endif
                                                                    @if($shift->store)
                                                                        <div class="text-[8px] opacity-70 leading-tight mt-0.5">
                                                                            {{ $shift->store->name }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 凡例 -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-medium text-gray-700 mb-2">凡例</h3>
            <div class="flex flex-wrap gap-4 text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 bg-gray-400 rounded"></span>
                    <span>予定</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 bg-amber-500 rounded"></span>
                    <span>勤務中</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 bg-green-500 rounded"></span>
                    <span>勤務完了</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 bg-blue-500 rounded"></span>
                    <span>未打刻</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>