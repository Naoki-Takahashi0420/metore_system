<x-filament-widgets::widget>
    {{-- タイムライン表示のみ --}}
        <x-filament::section>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
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
                    
                    <a href="{{ $this->getShiftManagementUrl() }}" 
                       class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                        詳細管理画面へ →
                    </a>
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
                            @if(count($todayTimeline) > 0)
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
                                        この日のシフトは登録されていません
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
        </x-filament::section>
</x-filament-widgets::widget>