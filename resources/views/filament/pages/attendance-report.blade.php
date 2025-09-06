<x-filament-panels::page>
    <div class="space-y-6">
        {{-- フィルター --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if(count($stores) > 1)
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">店舗</label>
                    <select wire:model.live="selectedStore" wire:change="changeStore" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">年</label>
                    <select wire:model.live="selectedYear" wire:change="changeMonth"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @for($year = 2024; $year <= now()->year + 1; $year++)
                            <option value="{{ $year }}">{{ $year }}年</option>
                        @endfor
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">月</label>
                    <select wire:model.live="selectedMonth" wire:change="changeMonth"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @for($month = 1; $month <= 12; $month++)
                            <option value="{{ $month }}">{{ $month }}月</option>
                        @endfor
                    </select>
                </div>
            </div>
            
            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="exportCsv" 
                    class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm">
                    CSV出力
                </button>
                <a href="{{ route('attendance-report.pdf', ['store' => $selectedStore, 'year' => $selectedYear, 'month' => $selectedMonth]) }}" 
                    target="_blank"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm inline-block">
                    印刷する
                </a>
            </div>
        </div>
        
        @if(!empty($reportData))
        {{-- レポート概要 --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">レポート概要</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">対象期間</span>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $reportData['period'] }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">店舗名</span>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $reportData['store']->name }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">総スタッフ数</span>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ count($staffSummary) }}名</p>
                </div>
                <div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">生成日時</span>
                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ $reportData['generated_at'] }}</p>
                </div>
            </div>
        </div>
        
        {{-- スタッフ別集計 --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">スタッフ別集計</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">氏名</th>
                            <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">勤務日数</th>
                            <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">総勤務時間</th>
                            <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">休憩時間</th>
                            <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">実働時間</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">シフトパターン</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                        @forelse($staffSummary as $staff)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $staff['name'] }}</td>
                            <td class="px-4 py-2 text-center text-sm text-gray-900 dark:text-gray-100">{{ $staff['days'] }}日</td>
                            <td class="px-4 py-2 text-center text-sm text-gray-900 dark:text-gray-100">{{ $staff['total_hours'] }}時間</td>
                            <td class="px-4 py-2 text-center text-sm text-gray-900 dark:text-gray-100">{{ $staff['break_hours'] }}時間</td>
                            <td class="px-4 py-2 text-center text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $staff['actual_hours'] }}時間</td>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @foreach($staff['patterns'] as $pattern => $count)
                                    <span class="inline-block px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-600 mr-1">
                                        {{ $pattern }}: {{ $count }}回
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                データがありません
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(count($staffSummary) > 0)
                    <tfoot class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <td class="px-4 py-2 font-semibold text-sm text-gray-700 dark:text-gray-300">合計</td>
                            <td class="px-4 py-2 text-center font-semibold text-sm text-gray-700 dark:text-gray-300">
                                {{ collect($staffSummary)->sum('days') }}日
                            </td>
                            <td class="px-4 py-2 text-center font-semibold text-sm text-gray-700 dark:text-gray-300">
                                {{ collect($staffSummary)->sum('total_hours') }}時間
                            </td>
                            <td class="px-4 py-2 text-center font-semibold text-sm text-gray-700 dark:text-gray-300">
                                {{ collect($staffSummary)->sum('break_hours') }}時間
                            </td>
                            <td class="px-4 py-2 text-center font-semibold text-sm text-gray-700 dark:text-gray-300">
                                {{ collect($staffSummary)->sum('actual_hours') }}時間
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
        
        {{-- シフトパターン分析 --}}
        @if(!empty($patternAnalysis))
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">シフトパターン分析</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($patternAnalysis as $pattern => $count)
                <div class="bg-gray-50 dark:bg-gray-700 rounded p-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $pattern }}</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $count }}回</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        
        {{-- 日別詳細（折りたたみ可能） --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <details>
                <summary class="cursor-pointer text-lg font-semibold text-gray-900 dark:text-gray-100">
                    日別詳細
                </summary>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">日付</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">スタッフ</th>
                                <th class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-300">合計時間</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                            @foreach($dailySummary as $day)
                            <tr>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                    {{ $day['date'] }} {{ $day['day'] }}
                                </td>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                    @if(count($day['staff']) > 0)
                                        @foreach($day['staff'] as $staff)
                                            <div>{{ $staff['name'] }} ({{ $staff['time'] }})</div>
                                        @endforeach
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $day['total_hours'] }}時間
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
        @endif
    </div>
</x-filament-panels::page>