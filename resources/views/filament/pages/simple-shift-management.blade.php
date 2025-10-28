<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 店舗選択（タイムラインの上に移動） --}}
        @if(count($stores) > 1)
        <div class="flex justify-start">
            <select wire:model.live="selectedStore" wire:change="changeStore" 
                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                @foreach($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        
        {{-- タイムライン表示 --}}
        @if(count($todayShifts) > 0 || true)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-2">
                    <button wire:click="previousTimelineDay" 
                        class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        @php
                            $displayDate = $timelineDate ? \Carbon\Carbon::parse($timelineDate) : now();
                            $isToday = $displayDate->isToday();
                        @endphp
                        {{ $isToday ? '本日' : $displayDate->format('n月j日') }}のシフト状況
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            （{{ $displayDate->format('Y年n月j日') }} {{ $displayDate->isoFormat('(ddd)') }}）
                        </span>
                    </h3>
                    
                    <button wire:click="nextTimelineDay" 
                        class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    
                    @if(!$isToday)
                    <button wire:click="goToToday" 
                        class="ml-2 px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600">
                        今日
                    </button>
                    @endif
                </div>
            </div>
            
            {{-- タイムラインテーブル --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700">
                            <th class="border border-gray-200 dark:border-gray-600 px-2 py-1 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 w-32">スタッフ</th>
                            @php
                                $hourGroups = [];
                                foreach($timeSlots as $time) {
                                    $hour = substr($time, 0, 2);
                                    if (!isset($hourGroups[$hour])) {
                                        $hourGroups[$hour] = 0;
                                    }
                                    $hourGroups[$hour]++;
                                }
                            @endphp
                            @foreach($hourGroups as $hour => $count)
                                <th colspan="{{ $count }}" class="border border-gray-200 dark:border-gray-600 px-0 py-1 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $hour }}:00
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        {{-- スタッフのタイムライン --}}
                        @if(isset($todayTimeline) && count($todayTimeline) > 0)
                            @foreach($todayTimeline as $staff)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="border border-gray-200 dark:border-gray-600 px-2 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $staff['name'] }}
                                    </td>
                                    @foreach($timeSlots as $time)
                                        @php
                                            $status = $staff['slots'][$time] ?? '';
                                            $bgClass = match($status) {
                                                'working' => 'bg-blue-200 dark:bg-blue-700',
                                                'break' => 'bg-yellow-200 dark:bg-yellow-700',
                                                default => 'bg-gray-50 dark:bg-gray-800'
                                            };
                                        @endphp
                                        <td class="border border-gray-200 dark:border-gray-600 p-0">
                                            <div class="h-8 {{ $bgClass }}"></div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="{{ count($timeSlots) + 1 }}" class="border border-gray-200 dark:border-gray-600 px-4 py-4 text-center text-gray-500">
                                    スタッフが登録されていません
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            
            {{-- 凡例 --}}
            <div class="flex gap-4 mt-3 text-xs">
                <div class="flex items-center gap-1">
                    <div class="w-4 h-4 bg-blue-200 dark:bg-blue-700 border border-gray-300"></div>
                    <span class="text-gray-600 dark:text-gray-400">勤務中</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-4 h-4 bg-yellow-200 dark:bg-yellow-700 border border-gray-300"></div>
                    <span class="text-gray-600 dark:text-gray-400">休憩中</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-4 h-4 bg-gray-50 dark:bg-gray-800 border border-gray-300"></div>
                    <span class="text-gray-600 dark:text-gray-400">不在</span>
                </div>
            </div>
        </div>
        @else
        <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
            <p class="text-yellow-800 dark:text-yellow-200">⚠️ 本日の勤務登録がありません</p>
        </div>
        @endif
        
        {{-- 複数選択モード時の操作パネル --}}
        @if($isSelectMode)
        <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
            @if(count($selectedDates) > 0)
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-blue-900 dark:text-blue-100 font-medium">
                        {{ count($selectedDates) }}日選択中
                    </span>
                    <span class="text-sm text-blue-700 dark:text-blue-300 ml-2">
                        クリックで選択/選択解除
                    </span>
                </div>
                <div class="flex gap-2">
                    <button wire:click="clearSelection" 
                        class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded">
                        選択をクリア
                    </button>
                    <button wire:click="openBulkModal" 
                        class="px-4 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600">
                        一括シフト登録
                    </button>
                    <button wire:click="exitSelectMode" 
                        class="px-3 py-1 text-sm bg-gray-500 text-white rounded hover:bg-gray-600">
                        選択モード終了
                    </button>
                </div>
            </div>
            @else
            <div class="text-center">
                <span class="text-blue-900 dark:text-blue-100">
                    カレンダーの日付をクリックして選択してください
                </span>
                <button wire:click="exitSelectMode" 
                    class="ml-4 px-3 py-1 text-sm bg-gray-500 text-white rounded hover:bg-gray-600">
                    キャンセル
                </button>
            </div>
            @endif
        </div>
        @endif
        
        {{-- ヘッダー: 月移動と複数選択モード --}}
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                {{-- 複数選択モードトグル --}}
                <button 
                    wire:click="toggleSelectMode"
                    class="px-4 py-2 rounded flex items-center gap-2 transition-colors
                           {{ $isSelectMode ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <span class="text-sm font-medium">
                        {{ $isSelectMode ? '選択モード ON' : '複数選択' }}
                    </span>
                </button>
                
                <div class="flex items-center gap-2">
                    <button wire:click="previousMonth" class="p-2 hover:bg-gray-100 rounded">
                        <x-heroicon-o-chevron-left class="w-5 h-5" />
                    </button>
                    <span class="font-semibold text-lg">
                        {{ $currentYear }}年 {{ $currentMonth }}月
                    </span>
                    <button wire:click="nextMonth" class="p-2 hover:bg-gray-100 rounded">
                        <x-heroicon-o-chevron-right class="w-5 h-5" />
                    </button>
                </div>
            </div>
            
            {{-- パターン適用ボタン --}}
            <div class="text-sm text-gray-500">
                クリック: シフト追加 | 右クリック: 削除
            </div>
        </div>

        {{-- スタッフカラー凡例 --}}
        @if(count($staffList) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-4">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">スタッフ一覧</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($staffList as $staff)
                    <div class="flex items-center gap-1 px-2 py-1 rounded-md"
                         style="background-color: {{ $staff->theme_color }}15;">
                        <div class="w-3 h-3 rounded-full"
                             style="background-color: {{ $staff->theme_color }};"></div>
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ $staff->name }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- シンプルなカレンダー表示 --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="grid grid-cols-7 gap-0">
                {{-- 曜日ヘッダー --}}
                @foreach(['月', '火', '水', '木', '金', '土', '日'] as $dayName)
                    <div class="p-2 text-center font-semibold border-b text-gray-700 dark:text-gray-300 {{ in_array($dayName, ['土', '日']) ? 'bg-gray-50 dark:bg-gray-700' : 'bg-gray-100 dark:bg-gray-750' }}">
                        {{ $dayName }}
                    </div>
                @endforeach
                
                {{-- カレンダー本体 --}}
                @php
                    $firstDay = \Carbon\Carbon::create($currentYear, $currentMonth, 1);
                    $startPadding = ($firstDay->dayOfWeek == 0 ? 6 : $firstDay->dayOfWeek - 1);
                @endphp
                
                {{-- 月初の空白 --}}
                @for($i = 0; $i < $startPadding; $i++)
                    <div class="border border-gray-200 dark:border-gray-600 p-2 min-h-[100px] bg-gray-50 dark:bg-gray-700"></div>
                @endfor
                
                {{-- 各日のシフト --}}
                @foreach($calendarData as $dateKey => $dayData)
                    <div 
                        class="border border-gray-200 dark:border-gray-600 p-2 min-h-[100px] cursor-pointer relative transition-all
                               {{ in_array($dateKey, $selectedDates) ? 'ring-2 ring-blue-500 bg-blue-100 dark:bg-blue-900' : '' }}
                               {{ $dayData['isToday'] && !in_array($dateKey, $selectedDates) ? 'bg-yellow-50 dark:bg-yellow-900' : '' }}
                               {{ !in_array($dateKey, $selectedDates) && in_array($dayData['dayOfWeek'], [0, 6]) ? 'bg-gray-50 dark:bg-gray-700' : (!in_array($dateKey, $selectedDates) ? 'bg-white dark:bg-gray-800' : '') }}
                               {{ $isSelectMode ? 'hover:ring-2 hover:ring-blue-300' : 'hover:bg-blue-50 dark:hover:bg-blue-900' }}"
                        wire:click="handleDateClick('{{ $dateKey }}')"
                        x-on:contextmenu.prevent="$wire.call('deleteShift', $event.target.dataset.shiftId)"
                    >
                        @if($isSelectMode && in_array($dateKey, $selectedDates))
                        <div class="absolute top-1 right-1">
                            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        @endif
                        <div class="font-semibold text-sm mb-1 text-gray-800 dark:text-gray-200">
                            {{ $dayData['date']->day }}
                        </div>
                        
                        {{-- シフト一覧（シンプル表示） --}}
                        <div class="space-y-1">
                            @foreach($dayData['shifts'] as $shift)
                                <div class="text-xs p-1 rounded truncate cursor-pointer transition-all hover:opacity-80 hover:shadow-sm"
                                     style="background-color: {{ $shift['user_color'] }}20; border-left: 3px solid {{ $shift['user_color'] }};"
                                     data-shift-id="{{ $shift['id'] }}"
                                     wire:click.stop="openEditModal({{ $shift['id'] }})">
                                    <span class="font-medium" style="color: {{ $shift['user_color'] }};">{{ $shift['user_name'] }}</span>
                                    <span class="text-gray-600 dark:text-gray-400 text-xs">{{ $shift['time'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        
        {{-- 凡例 --}}
        <div class="flex gap-6 text-sm text-gray-600">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-yellow-50 border"></div>
                <span>今日</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-gray-50 border"></div>
                <span>週末</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-blue-100 border"></div>
                <span>シフト登録済み</span>
            </div>
        </div>
    </div>

    {{-- 一括登録モーダル --}}
    @if($showBulkModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg w-[500px] max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">
                一括シフト登録（{{ count($selectedDates) }}日分）
            </h3>
            
            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">選択した日付：</p>
                <div class="flex flex-wrap gap-1 mt-2">
                    @foreach($selectedDates as $date)
                        <span class="px-2 py-1 text-xs bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded">
                            {{ \Carbon\Carbon::parse($date)->format('n/j') }}
                        </span>
                    @endforeach
                </div>
            </div>
            
            <div class="space-y-4">
                {{-- スタッフ選択 --}}
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">スタッフ</label>
                    <select wire:model="bulkStaff" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">選択してください</option>
                        @foreach($staffList as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                {{-- パターン選択 --}}
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">シフトパターン</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($patterns as $pattern)
                            <button 
                                wire:click="$set('bulkPattern', {{ $pattern['id'] }})"
                                class="p-3 text-sm border rounded 
                                       @if($bulkPattern == $pattern['id']) 
                                           bg-blue-500 text-white 
                                       @else 
                                           hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 
                                       @endif">
                                <span class="block font-medium {{ $bulkPattern == $pattern['id'] ? 'text-white' : 'text-gray-800 dark:text-gray-200' }}">{{ $pattern['name'] }}</span>
                                <span class="block text-xs {{ $bulkPattern == $pattern['id'] ? 'text-blue-100' : 'text-gray-600 dark:text-gray-400' }}">{{ $pattern['start'] }}-{{ $pattern['end'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                
                {{-- 休憩時間設定 --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">休憩時間</label>
                        <button type="button" wire:click="addBulkBreak" 
                            class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                            + 休憩を追加
                        </button>
                    </div>
                    
                    <div class="space-y-2 max-h-32 overflow-y-auto">
                        @foreach($bulkBreaks as $index => $break)
                        <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded">
                            <input type="time" wire:model="bulkBreaks.{{ $index }}.start" 
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">〜</span>
                            <input type="time" wire:model="bulkBreaks.{{ $index }}.end" 
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 text-sm">
                            <button type="button" wire:click="removeBulkBreak({{ $index }})" 
                                class="text-red-600 hover:text-red-700 dark:text-red-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        @endforeach
                        
                        @if(empty($bulkBreaks))
                        <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-2">
                            休憩なし
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <button wire:click="closeBulkModal" 
                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">
                    キャンセル
                </button>
                <button wire:click="bulkAddShifts" 
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    一括登録
                </button>
            </div>
        </div>
    </div>
    @endif
    
    {{-- シフト編集モーダル --}}
    @if($showEditModal && $editingShift)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); background: rgba(0, 0, 0, 0.5);"
         wire:click="closeEditModal">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-[600px] max-h-[85vh] overflow-y-auto"
             wire:click.stop>

            @if($isEditMode)
                {{-- 編集モード --}}
                <div class="p-6">
                    @php
                        $date = \Carbon\Carbon::parse($editingShift->shift_date);
                        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];
                    @endphp
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">
                        シフト編集 - {{ $date->format('Y年n月j日') }}（{{ $dayOfWeek }}）
                    </h3>

                    <div class="space-y-4">
                        {{-- スタッフ選択 --}}
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">スタッフ</label>
                            <select wire:model="editStaffId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach($staffList as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 勤務時間 --}}
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">勤務時間</label>
                            <div class="flex items-center gap-3">
                                <input type="time" wire:model="editStartTime"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <span class="text-gray-500 dark:text-gray-400">〜</span>
                                <input type="time" wire:model="editEndTime"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            </div>
                        </div>

                        {{-- 休憩時間 --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">休憩時間</label>
                                <button type="button" wire:click="addEditBreak"
                                    class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                    + 休憩を追加
                                </button>
                            </div>

                            @if(!empty($editingBreaks))
                                <div class="space-y-2">
                                    @foreach($editingBreaks as $index => $break)
                                    <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                        <input type="time" wire:model="editingBreaks.{{ $index }}.start"
                                            class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">〜</span>
                                        <input type="time" wire:model="editingBreaks.{{ $index }}.end"
                                            class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 text-sm">
                                        <button type="button" wire:click="removeEditBreak({{ $index }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-2 bg-gray-50 dark:bg-gray-700 rounded">
                                    休憩なし
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- 編集モードのボタン --}}
                    <div class="flex justify-end gap-2 mt-6">
                        <button wire:click="$set('isEditMode', false)"
                            class="px-4 py-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded">
                            キャンセル
                        </button>
                        <button wire:click="updateShift"
                            class="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600">
                            保存する
                        </button>
                    </div>
                </div>
            @else
                {{-- 表示モード --}}
                <div class="p-6">
                    @php
                        $date = \Carbon\Carbon::parse($editingShift->shift_date);
                        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];
                    @endphp
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">
                        シフト詳細 - {{ $date->format('Y年n月j日') }}（{{ $dayOfWeek }}）
                    </h3>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">スタッフ</span>
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $editingShift->user->name }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">勤務時間</span>
                                <p class="font-medium text-gray-800 dark:text-gray-200">
                                    {{ \Carbon\Carbon::parse($editingShift->start_time)->format('H:i') }} 〜
                                    {{ \Carbon\Carbon::parse($editingShift->end_time)->format('H:i') }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <span class="text-sm text-gray-500 dark:text-gray-400">休憩時間</span>
                            @if(!empty($editingBreaks))
                                <div class="space-y-1 mt-1">
                                    @foreach($editingBreaks as $break)
                                    <p class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ $break['start'] }} 〜 {{ $break['end'] }}
                                    </p>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-500 dark:text-gray-400 mt-1">休憩なし</p>
                            @endif
                        </div>
                    </div>

                    {{-- 表示モードのボタン --}}
                    <div class="border-t pt-4 flex justify-between gap-2 mt-6">
                        <button wire:click="confirmDeleteShift"
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            削除
                        </button>
                        <div class="flex gap-2">
                            <button wire:click="closeEditModal"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                                閉じる
                            </button>
                            <button wire:click="enableEditMode"
                                class="px-4 py-2 !bg-gray-900 !text-white rounded hover:!bg-black dark:!bg-gray-600 dark:hover:!bg-gray-500">
                                編集
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif
    
    {{-- クイック追加モーダル --}}
    @if($showQuickAdd)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg w-96">
            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">シフト追加 - {{ $quickAddDate }}</h3>
            
            <div class="space-y-4">
                {{-- スタッフ選択 --}}
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">スタッフ</label>
                    <select wire:model="quickAddStaff" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">選択してください</option>
                        @foreach($staffList as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                {{-- パターン選択 --}}
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">シフトパターン</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($patterns as $pattern)
                            <button 
                                wire:click="$set('quickAddPattern', {{ $pattern['id'] }})"
                                class="p-2 text-sm border rounded 
                                       @if($quickAddPattern == $pattern['id']) 
                                           bg-blue-500 text-white 
                                       @else 
                                           hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 
                                       @endif">
                                <span class="block {{ $quickAddPattern == $pattern['id'] ? 'text-white' : 'text-gray-800 dark:text-gray-200' }}">{{ $pattern['name'] }}</span>
                                <span class="block text-xs {{ $quickAddPattern == $pattern['id'] ? 'text-blue-100' : 'text-gray-600 dark:text-gray-400' }}">{{ $pattern['start'] }}-{{ $pattern['end'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                
                {{-- 休憩時間設定 --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">休憩時間</label>
                        <button type="button" wire:click="addBreak" 
                            class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                            + 休憩を追加
                        </button>
                    </div>
                    
                    <div class="space-y-2 max-h-32 overflow-y-auto">
                        @foreach($quickAddBreaks as $index => $break)
                        <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded">
                            <input type="time" wire:model="quickAddBreaks.{{ $index }}.start" 
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">〜</span>
                            <input type="time" wire:model="quickAddBreaks.{{ $index }}.end" 
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 text-sm">
                            <button type="button" wire:click="removeBreak({{ $index }})" 
                                class="text-red-600 hover:text-red-700 dark:text-red-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        @endforeach
                        
                        @if(empty($quickAddBreaks))
                        <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-2">
                            休憩なし
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <button wire:click="$set('showQuickAdd', false)" 
                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">
                    キャンセル
                </button>
                <button wire:click="quickAddShift" 
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    登録
                </button>
            </div>
        </div>
    </div>
    @endif
</x-filament-panels::page>