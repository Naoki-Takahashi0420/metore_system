<x-filament-panels::page>
    <div class="space-y-6">
        <!-- ステップ1: 月選択と日付選択 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold flex items-center">
                    <span class="bg-blue-100 text-blue-700 rounded-full w-8 h-8 flex items-center justify-center mr-3 text-sm font-bold">1</span>
                    対象月と日付を選択
                </h2>
                @if(count($selectedDates) > 0)
                    <div class="bg-blue-500 text-white px-4 py-2 rounded-lg font-bold text-lg animate-pulse">
                        {{ count($selectedDates) }}日選択中
                    </div>
                @endif
            </div>
            
            <!-- 月選択 -->
            <div class="flex items-center justify-center mb-6 space-x-4">
                <button 
                    wire:click="changeMonth('prev')"
                    class="p-2 hover:bg-gray-100 rounded-lg transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                
                <div class="text-xl font-bold min-w-[150px] text-center">
                    {{ $selectedYear }}年{{ $selectedMonth }}月
                </div>
                
                <button 
                    wire:click="changeMonth('next')"
                    class="p-2 hover:bg-gray-100 rounded-lg transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
            
            <!-- クイック選択ボタン -->
            <div class="flex flex-wrap gap-2 mb-4">
                <button 
                    wire:click="selectWeekdays"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition"
                >
                    平日を選択
                </button>
                <button 
                    wire:click="selectWeekends"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition"
                >
                    週末を選択
                </button>
                <button 
                    wire:click="selectAllDays"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition"
                >
                    全て選択
                </button>
                <button 
                    wire:click="clearSelection"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition"
                >
                    選択解除
                </button>
                <button 
                    wire:click="copyFromLastWeek"
                    class="px-4 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg text-sm font-medium transition"
                >
                    先週からコピー
                </button>
            </div>
            
            <!-- 日付グリッド -->
            <div class="grid grid-cols-7 gap-2">
                @php
                    $start = Carbon\Carbon::create($selectedYear, $selectedMonth, 1);
                    $end = $start->copy()->endOfMonth();
                    $startOfWeek = $start->copy()->startOfWeek();
                    $endOfWeek = $end->copy()->endOfWeek();
                    $days = ['日', '月', '火', '水', '木', '金', '土'];
                @endphp
                
                <!-- 曜日ヘッダー -->
                @foreach($days as $index => $day)
                    <div class="text-center text-sm font-semibold p-2 
                                {{ $index === 0 ? 'text-red-500' : '' }}
                                {{ $index === 6 ? 'text-blue-500' : '' }}">
                        {{ $day }}
                    </div>
                @endforeach
                
                <!-- 日付 -->
                @php $currentDate = $startOfWeek->copy(); @endphp
                @while($currentDate <= $endOfWeek)
                    @php
                        $dateStr = $currentDate->format('Y-m-d');
                        $isCurrentMonth = $currentDate->month === $selectedMonth;
                        $isSelected = in_array($dateStr, $selectedDates);
                        $isPast = $currentDate->isPast();
                    @endphp
                    
                    <div 
                        @if($isCurrentMonth && !$isPast) wire:click="toggleDate('{{ $dateStr }}')" @endif
                        class="relative {{ !$isCurrentMonth || $isPast ? 'opacity-30 cursor-not-allowed' : 'cursor-pointer' }}">
                        <div class="p-3 text-center rounded-lg transition-all duration-200 border-2 transform
                                    {{ $isSelected ? 'bg-blue-600 text-white border-blue-700 font-bold scale-95 shadow-lg' : 'border-gray-200' }}
                                    {{ !$isPast && $isCurrentMonth && !$isSelected ? 'hover:bg-gray-100 hover:border-gray-300 hover:scale-105' : '' }}
                                    {{ $isPast ? 'line-through text-gray-400 bg-gray-50' : '' }}
                                    {{ $currentDate->isToday() && !$isSelected ? 'ring-2 ring-yellow-400 bg-yellow-50' : '' }}
                                    {{ $currentDate->isToday() && $isSelected ? 'ring-2 ring-yellow-300' : '' }}
                                    {{ $currentDate->dayOfWeek === 0 && !$isSelected ? 'text-red-500' : '' }}
                                    {{ $currentDate->dayOfWeek === 6 && !$isSelected ? 'text-blue-500' : '' }}">
                            <div class="text-lg font-semibold">{{ $currentDate->day }}</div>
                            @if($isSelected)
                                <div class="text-sm mt-1 font-bold">✓</div>
                            @endif
                        </div>
                    </div>
                    @php $currentDate->addDay(); @endphp
                @endwhile
            </div>
            
            <!-- 選択状況の表示 -->
            <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-blue-900 font-medium">
                            選択中: <span class="text-2xl font-bold">{{ count($selectedDates) }}</span>日
                        </span>
                    </div>
                    @if(count($selectedDates) > 0)
                        <div class="text-sm text-blue-700">
                            @php
                                $dates = collect($selectedDates)->sort()->map(fn($d) => \Carbon\Carbon::parse($d));
                                $firstDate = $dates->first();
                                $lastDate = $dates->last();
                            @endphp
                            {{ $firstDate->format('m/d') }} 〜 {{ $lastDate->format('m/d') }}
                        </div>
                    @endif
                </div>
                
                @if(count($selectedDates) > 0)
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach(collect($selectedDates)->sort()->take(10) as $date)
                            <span class="px-2 py-1 bg-white rounded text-xs text-blue-700">
                                {{ \Carbon\Carbon::parse($date)->format('m/d') }}({{ ['日','月','火','水','木','金','土'][\Carbon\Carbon::parse($date)->dayOfWeek] }})
                            </span>
                        @endforeach
                        @if(count($selectedDates) > 10)
                            <span class="px-2 py-1 text-xs text-blue-600">
                                他{{ count($selectedDates) - 10 }}日...
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        
        <!-- ステップ2: スタッフとシフト設定 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center">
                <span class="bg-blue-100 text-blue-700 rounded-full w-8 h-8 flex items-center justify-center mr-3 text-sm font-bold">2</span>
                スタッフとシフトを設定
            </h2>
            
            <div class="space-y-3">
                @foreach($staffShifts as $userId => $shift)
                    <div class="border rounded-lg p-4 {{ $shift['enabled'] ? 'bg-blue-50 border-blue-300' : 'bg-gray-50' }}">
                        <div class="flex items-center space-x-4">
                            <!-- チェックボックス -->
                            <input 
                                type="checkbox" 
                                wire:model.live="staffShifts.{{ $userId }}.enabled"
                                class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500"
                                {{ $shift['enabled'] ? 'checked' : '' }}
                            >
                            
                            <!-- スタッフ名 -->
                            <div class="flex-1">
                                <div class="font-medium">{{ $shift['name'] }}</div>
                                @if($shift['store_id'])
                                    <div class="text-xs text-gray-500">
                                        {{ \App\Models\Store::find($shift['store_id'])?->name }}
                                    </div>
                                @endif
                            </div>
                            
                            <!-- パターン選択 -->
                            <select 
                                wire:model.live="staffShifts.{{ $userId }}.pattern"
                                wire:change="applyPattern({{ $userId }}, $event.target.value)"
                                class="px-3 py-1.5 border rounded-lg text-sm {{ !$shift['enabled'] ? 'opacity-50' : '' }}"
                                {{ !$shift['enabled'] ? 'disabled' : '' }}
                            >
                                <option value="">カスタム</option>
                                <option value="full" {{ $shift['pattern'] === 'full' ? 'selected' : '' }}>終日 (10:00-20:00)</option>
                                <option value="morning" {{ $shift['pattern'] === 'morning' ? 'selected' : '' }}>朝番 (10:00-15:00)</option>
                                <option value="evening" {{ $shift['pattern'] === 'evening' ? 'selected' : '' }}>遅番 (15:00-20:00)</option>
                            </select>
                            
                            <!-- 時間入力 -->
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="time" 
                                    wire:model.live="staffShifts.{{ $userId }}.start_time"
                                    class="px-3 py-1.5 border rounded-lg text-sm {{ !$shift['enabled'] ? 'opacity-50' : '' }}"
                                    {{ !$shift['enabled'] ? 'disabled' : '' }}
                                    value="{{ $shift['start_time'] }}"
                                >
                                <span class="text-gray-500">〜</span>
                                <input 
                                    type="time" 
                                    wire:model.live="staffShifts.{{ $userId }}.end_time"
                                    class="px-3 py-1.5 border rounded-lg text-sm {{ !$shift['enabled'] ? 'opacity-50' : '' }}"
                                    {{ !$shift['enabled'] ? 'disabled' : '' }}
                                    value="{{ $shift['end_time'] }}"
                                >
                            </div>
                        </div>
                        
                        <!-- 休憩時間（展開可能） -->
                        @if($shift['enabled'])
                            <div class="mt-3 pl-9" x-data="{ showBreak: false }">
                                <button 
                                    @click="showBreak = !showBreak"
                                    type="button"
                                    class="text-sm text-blue-600 hover:text-blue-800"
                                >
                                    休憩時間設定 <span x-text="showBreak ? '▼' : '▶'"></span>
                                </button>
                                
                                <div x-show="showBreak" x-transition class="mt-2 flex items-center space-x-2">
                                    <input 
                                        type="time" 
                                        wire:model.live="staffShifts.{{ $userId }}.break_start"
                                        class="px-3 py-1.5 border rounded-lg text-sm"
                                        placeholder="休憩開始"
                                        value="{{ $shift['break_start'] }}"
                                    >
                                    <span class="text-gray-500">〜</span>
                                    <input 
                                        type="time" 
                                        wire:model.live="staffShifts.{{ $userId }}.break_end"
                                        class="px-3 py-1.5 border rounded-lg text-sm"
                                        placeholder="休憩終了"
                                        value="{{ $shift['break_end'] }}"
                                    >
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        
        <!-- サマリーと登録ボタン -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold mb-4">登録内容の確認</h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-sm text-gray-600">選択日数</div>
                    <div class="text-2xl font-bold">{{ count($selectedDates) }}日</div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-sm text-gray-600">対象スタッフ</div>
                    <div class="text-2xl font-bold">
                        {{ collect($staffShifts)->filter(fn($s) => $s['enabled'])->count() }}名
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-sm text-gray-600">作成シフト数</div>
                    <div class="text-2xl font-bold">
                        {{ count($selectedDates) * collect($staffShifts)->filter(fn($s) => $s['enabled'])->count() }}件
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-sm text-gray-600">対象月</div>
                    <div class="text-lg font-bold">{{ $selectedYear }}年{{ $selectedMonth }}月</div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <a 
                    href="{{ \App\Filament\Resources\ShiftResource::getUrl('index') }}"
                    class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition"
                >
                    キャンセル
                </a>
                <button 
                    wire:click="save"
                    wire:loading.attr="disabled"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50"
                >
                    <span wire:loading.remove>一括登録</span>
                    <span wire:loading>登録中...</span>
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>