<x-filament-widgets::widget>
    <x-filament::card>
        <style>
            .shift-calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 1px;
                background: #e0e0e0;
                border: 1px solid #e0e0e0;
            }
            
            .calendar-day-header {
                background: #f8f8f8;
                padding: 10px;
                text-align: center;
                font-weight: bold;
                font-size: 14px;
            }
            
            .calendar-day-header.sunday { color: #f44336; }
            .calendar-day-header.saturday { color: #2196f3; }
            
            .calendar-day {
                background: white;
                min-height: 100px;
                padding: 8px;
                cursor: pointer;
                position: relative;
                transition: background 0.2s;
            }
            
            .calendar-day:hover { background: #f5f5f5; }
            .calendar-day.past { background: #fafafa; color: #999; }
            .calendar-day.today { background: #fff3e0; }
            
            .day-number {
                font-weight: bold;
                margin-bottom: 4px;
                font-size: 14px;
            }
            
            .shift-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 3px;
                margin-top: 4px;
            }
            
            .shift-badge {
                padding: 2px 6px;
                border-radius: 12px;
                font-size: 11px;
                white-space: nowrap;
            }
            
            .shift-badge.morning { background: #ffecb3; }
            .shift-badge.afternoon { background: #c5e1a5; }
            .shift-badge.evening { background: #b3e5fc; }
            .shift-badge.full { background: #d1c4e9; }
            
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
            }
            
            .summary-card {
                padding: 15px;
                background: #f8f8f8;
                border-radius: 8px;
            }
            
            .summary-label {
                font-size: 12px;
                color: #666;
                margin-bottom: 8px;
            }
            
            .summary-value {
                font-size: 24px;
                font-weight: bold;
                color: #333;
            }
            
            @media (max-width: 768px) {
                .shift-calendar-grid {
                    font-size: 12px;
                }
                
                .calendar-day {
                    min-height: 80px;
                    padding: 4px;
                }
                
                .shift-badge {
                    font-size: 10px;
                    padding: 1px 4px;
                }
            }
        </style>
        
        <!-- ヘッダー -->
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-4">
                <label class="text-sm font-medium">店舗：</label>
                <select wire:model.live="selectedStore" class="border rounded px-3 py-1 text-sm">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="flex items-center gap-4">
                <button wire:click="previousMonth" class="px-3 py-1 border rounded hover:bg-gray-100">
                    ◀
                </button>
                <div class="font-bold px-4">
                    {{ $currentYear }}年{{ $currentMonth }}月
                </div>
                <button wire:click="nextMonth" class="px-3 py-1 border rounded hover:bg-gray-100">
                    ▶
                </button>
            </div>
            
            <div class="flex gap-2">
                <button 
                    onclick="openBulkEditModal()" 
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                    一括編集
                </button>
                <button 
                    onclick="openPatternModal()" 
                    class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                >
                    パターン管理
                </button>
            </div>
        </div>
        
        <!-- カレンダー -->
        <div class="shift-calendar-grid">
            <!-- 曜日ヘッダー -->
            <div class="calendar-day-header sunday">日</div>
            <div class="calendar-day-header">月</div>
            <div class="calendar-day-header">火</div>
            <div class="calendar-day-header">水</div>
            <div class="calendar-day-header">木</div>
            <div class="calendar-day-header">金</div>
            <div class="calendar-day-header saturday">土</div>
            
            <!-- カレンダー日付 -->
            @php
                $firstDay = \Carbon\Carbon::create($currentYear, $currentMonth, 1);
                $startPadding = $firstDay->dayOfWeek;
                $daysInMonth = $firstDay->daysInMonth;
            @endphp
            
            <!-- 前月の空白 -->
            @for($i = 0; $i < $startPadding; $i++)
                <div class="calendar-day past"></div>
            @endfor
            
            <!-- 当月の日付 -->
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $dateKey = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                    $dayData = $calendarData[$dateKey] ?? null;
                @endphp
                
                <div 
                    class="calendar-day {{ $dayData && $dayData['is_today'] ? 'today' : '' }} {{ $dayData && $dayData['is_past'] ? 'past' : '' }}"
                    wire:click="openShiftEdit('{{ $dateKey }}')"
                >
                    <div class="day-number">{{ $day }}</div>
                    @if($dayData && count($dayData['shifts']) > 0)
                        <div class="shift-badges">
                            @foreach($dayData['shifts'] as $shift)
                                <span class="shift-badge {{ $shift['type'] }}">
                                    {{ $shift['staff_name'] }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endfor
        </div>
        
        <!-- サマリー -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">今月の総勤務時間</div>
                <div class="summary-value">{{ $monthlySummary['total_hours'] ?? 0 }}時間</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">平均スタッフ数/日</div>
                <div class="summary-value">{{ $monthlySummary['avg_staff_per_day'] ?? 0 }}人</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">シフト入力率</div>
                <div class="summary-value">{{ $monthlySummary['input_rate'] ?? 0 }}%</div>
            </div>
        </div>
    </x-filament::card>
    
    <!-- シフト編集モーダル -->
    @livewire('shift-edit-modal')
    
    <!-- 一括編集モーダル -->
    @livewire('shift-bulk-edit-modal')
    
    <!-- パターン管理モーダル -->
    @livewire('shift-pattern-modal')
</x-filament-widgets::widget>