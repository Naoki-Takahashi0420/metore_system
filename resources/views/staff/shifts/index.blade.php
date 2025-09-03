@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- ヘッダー -->
    <div class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold">シフト確認</h1>
                <div class="flex gap-2">
                    <a href="?view=my&month={{ $date->format('Y-m') }}" 
                       class="px-3 py-1 rounded {{ request('view') !== 'all' ? 'bg-blue-500 text-white' : 'bg-gray-200' }}">
                        自分のシフト
                    </a>
                    <a href="?view=all&month={{ $date->format('Y-m') }}" 
                       class="px-3 py-1 rounded {{ request('view') === 'all' ? 'bg-blue-500 text-white' : 'bg-gray-200' }}">
                        全体シフト
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 月選択 -->
    <div class="bg-white border-b">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <a href="?view={{ request('view') }}&month={{ $date->copy()->subMonth()->format('Y-m') }}" 
                   class="p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                
                <div class="text-lg font-semibold">
                    {{ $date->format('Y年n月') }}
                </div>
                
                <a href="?view={{ request('view') }}&month={{ $date->copy()->addMonth()->format('Y-m') }}" 
                   class="p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
    
    @if(request('view') !== 'all')
        <!-- サマリー（自分のシフト） -->
        <div class="container mx-auto px-4 py-4">
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="bg-white p-4 rounded shadow">
                    <div class="text-sm text-gray-500">シフト日数</div>
                    <div class="text-2xl font-bold">{{ $summary['total_shifts'] }}日</div>
                </div>
                <div class="bg-white p-4 rounded shadow">
                    <div class="text-sm text-gray-500">総勤務時間</div>
                    <div class="text-2xl font-bold">{{ $summary['total_hours'] }}h</div>
                </div>
                <div class="bg-white p-4 rounded shadow">
                    <div class="text-sm text-gray-500">次回出勤</div>
                    <div class="text-sm font-bold">
                        @if($summary['next_shift'])
                            {{ $summary['next_shift']->shift_date->format('n/j') }}
                            {{ \Carbon\Carbon::parse($summary['next_shift']->start_time)->format('H:i') }}
                        @else
                            なし
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- カレンダー表示 -->
        <div class="container mx-auto px-4">
            <div class="bg-white rounded shadow overflow-hidden">
                <div class="grid grid-cols-7 bg-gray-100">
                    @foreach(['日', '月', '火', '水', '木', '金', '土'] as $index => $day)
                        <div class="p-2 text-center text-sm font-semibold {{ $index === 0 ? 'text-red-500' : ($index === 6 ? 'text-blue-500' : '') }}">
                            {{ $day }}
                        </div>
                    @endforeach
                </div>
                
                @foreach($calendarData as $week)
                    <div class="grid grid-cols-7 border-t">
                        @foreach($week as $day)
                            <div class="p-2 min-h-[80px] {{ !$day['is_current_month'] ? 'bg-gray-50 text-gray-400' : '' }} {{ $day['is_today'] ? 'bg-yellow-50' : '' }}">
                                <div class="text-sm {{ $day['date']->dayOfWeek === 0 ? 'text-red-500' : ($day['date']->dayOfWeek === 6 ? 'text-blue-500' : '') }}">
                                    {{ $day['date']->day }}
                                </div>
                                @if($day['has_shift'])
                                    @foreach($day['shifts'] as $shift)
                                        <a href="{{ route('staff.shifts.show', $shift->id) }}" 
                                           class="block mt-1 p-1 bg-blue-100 rounded text-xs">
                                            {{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}-{{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }}
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <!-- 全体シフト表示 -->
        <div class="container mx-auto px-4 py-4">
            @foreach($storeShifts as $storeData)
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-3">{{ $storeData['store']->name }}</h2>
                    
                    <div class="bg-white rounded shadow overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-2 text-left text-sm">日付</th>
                                    <th class="p-2 text-left text-sm">スタッフ</th>
                                    <th class="p-2 text-left text-sm">時間</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($storeData['shifts'] as $dateKey => $dayShifts)
                                    @foreach($dayShifts as $index => $shift)
                                        <tr class="border-t">
                                            @if($index === 0)
                                                <td class="p-2 text-sm" rowspan="{{ count($dayShifts) }}">
                                                    {{ \Carbon\Carbon::parse($dateKey)->format('n/j') }}
                                                    ({{ ['日', '月', '火', '水', '木', '金', '土'][\Carbon\Carbon::parse($dateKey)->dayOfWeek] }})
                                                </td>
                                            @endif
                                            <td class="p-2 text-sm">{{ $shift->user->name }}</td>
                                            <td class="p-2 text-sm">
                                                {{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}-{{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
    @media (max-width: 640px) {
        .container {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        .grid-cols-7 {
            font-size: 0.75rem;
        }
        
        .min-h-\[80px\] {
            min-height: 60px;
        }
    }
</style>
@endsection