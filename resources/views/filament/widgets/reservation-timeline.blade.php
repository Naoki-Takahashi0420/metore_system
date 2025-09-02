<x-filament-widgets::widget>
    <x-filament::card>
        <style>
            .timeline-table {
                border-collapse: collapse;
                width: 100%;
                min-width: 1200px;
            }
            
            .timeline-table th,
            .timeline-table td {
                border: 1px solid #e0e0e0;
                padding: 0;
                height: 60px;
                position: relative;
            }
            
            .timeline-table th {
                background: #f8f8f8;
                font-weight: normal;
                font-size: 14px;
                text-align: center;
                width: 80px;
            }
            
            .timeline-table td {
                width: 80px;
                cursor: pointer;
            }
            
            .timeline-table td:hover {
                background: #f5f5f5;
            }
            
            .seat-label {
                background: #f8f8f8;
                text-align: center;
                font-size: 14px;
                padding: 0 10px;
            }
            
            .sub-time-label {
                background: #e8f4f8;
                font-weight: bold;
            }
            
            .booking-block {
                position: absolute;
                top: 2px;
                bottom: 2px;
                left: 2px;
                padding: 4px;
                border-radius: 4px;
                font-size: 12px;
                cursor: pointer;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: center;
                z-index: 10;
                transition: all 0.2s;
            }
            
            .booking-block:hover {
                transform: scale(1.02);
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }
            
            .booking-block.span-1 { width: calc(100% - 4px); }
            .booking-block.span-2 { width: calc(200% - 4px); }
            .booking-block.span-3 { width: calc(300% - 4px); }
            .booking-block.span-4 { width: calc(400% - 4px); }
            
            .booking-name {
                font-weight: bold;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .booking-menu {
                font-size: 11px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .course-care {
                background: #e3f2fd;
                border-left: 3px solid #2196f3;
            }
            
            .course-hydrogen {
                background: #f3e5f5;
                border-left: 3px solid #9c27b0;
            }
            
            .course-training {
                background: #fff3e0;
                border-left: 3px solid #ff9800;
            }
            
            .break-block {
                background: #757575 !important;
                color: white;
                text-align: center;
                line-height: 56px;
                font-weight: bold;
            }
            
            .blocked-cell {
                background: #f5f5f5 !important;
                cursor: not-allowed !important;
            }
            
            .conflicting-reservation {
                border: 2px solid red !important;
                background: #ffe5e5 !important;
            }
            
            .time-cell {
                position: relative;
            }
            
            .time-cell::after {
                content: '';
                position: absolute;
                left: 50%;
                top: 0;
                bottom: 0;
                width: 1px;
                background: #f0f0f0;
            }
        </style>
        
        <!-- 操作説明 -->
        <div class="bg-blue-50 border border-blue-200 rounded p-2 mb-4 text-sm">
            💡 <strong>席の移動方法:</strong> 予約ブロックをクリックすると詳細画面が開き、通常席⇔サブ枠の移動ができます
        </div>
        
        <!-- 競合警告 -->
        @if(!empty($timelineData['conflictingReservations']))
            <div class="bg-red-50 border border-red-300 rounded p-3 mb-4">
                <div class="flex items-start">
                    <div class="text-red-600 mr-2">⚠️</div>
                    <div>
                        <p class="font-bold text-red-700 mb-2">予約ブロック時間帯に予約が入っています！</p>
                        <ul class="text-sm text-red-600 space-y-1">
                            @foreach($timelineData['conflictingReservations'] as $conflict)
                                <li>• {{ $conflict['customer_name'] }} - {{ $conflict['time'] }}</li>
                            @endforeach
                        </ul>
                        <p class="text-xs text-red-500 mt-2">これらの予約を別の時間に移動してください。</p>
                    </div>
                </div>
            </div>
        @endif
        
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
                <div class="flex items-center gap-2">
                    <button wire:click="changeDate('prev')" class="px-3 py-1 border rounded hover:bg-gray-100">
                        ◀
                    </button>
                    <div class="font-bold px-4">
                        {{ \Carbon\Carbon::parse($selectedDate)->format('Y年n月j日') }}
                        ({{ ['日', '月', '火', '水', '木', '金', '土'][\Carbon\Carbon::parse($selectedDate)->dayOfWeek] }})
                    </div>
                    <button wire:click="changeDate('next')" class="px-3 py-1 border rounded hover:bg-gray-100">
                        ▶
                    </button>
                </div>
            </div>
        </div>
        
        <!-- タイムライン -->
        <div class="overflow-x-auto">
            @if(!empty($timelineData))
                <table class="timeline-table">
                    <thead>
                        <tr>
                            <th>席数</th>
                            @foreach($timelineData['slots'] as $slot)
                                <th>{{ $slot }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timelineData['timeline'] as $key => $seat)
                            <tr>
                                <td class="seat-label {{ $seat['type'] === 'sub' ? 'sub-time-label' : '' }}">
                                    {{ $seat['label'] }}
                                </td>
                                @foreach($timelineData['slots'] as $index => $slot)
                                    <td class="time-cell {{ in_array($index, $timelineData['blockedSlots']) ? 'blocked-cell' : '' }}">
                                        @if(in_array($index, $timelineData['blockedSlots']))
                                            <div style="background: #9e9e9e; color: white; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                                                BRK
                                            </div>
                                        @else
                                            @foreach($seat['reservations'] as $reservation)
                                                @if($reservation['start_slot'] == $index)
                                                    <div class="booking-block 
                                                        course-{{ $reservation['course_type'] }}
                                                        span-{{ ceil($reservation['span']) }}
                                                        {{ $reservation['is_conflicting'] ?? false ? 'conflicting-reservation' : '' }}"
                                                        wire:click="openReservationDetail({{ $reservation['id'] }})">
                                                        <div class="booking-name">
                                                            @if($reservation['is_new_customer'] ?? false)
                                                                <span style="background: #ff6b6b; color: white; padding: 1px 4px; border-radius: 3px; font-size: 10px; margin-right: 3px;">NEW</span>
                                                            @endif
                                                            {{ $reservation['customer_name'] }}
                                                        </div>
                                                        <div class="booking-menu">{{ $reservation['menu_name'] }}</div>
                                                        @if($reservation['is_conflicting'] ?? false)
                                                            <div style="color: red; font-size: 10px; font-weight: bold;">⚠️ 競合</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="text-center py-8 text-gray-500">
                    データがありません
                </div>
            @endif
        </div>
        
        <!-- 凡例 -->
        <div class="flex gap-6 mt-4 text-sm">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded course-care border"></div>
                <span>ケアコース</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded course-hydrogen border"></div>
                <span>水素コース</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded course-training border"></div>
                <span>トレーニングコース</span>
            </div>
        </div>
    </x-filament::card>
    
    <!-- 予約詳細パネル -->
    @if($selectedReservation)
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-on:click="show = false; $wire.closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        >
            <div 
                x-on:click.stop
                class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg"
            >
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">予約詳細</h3>
                    <button 
                        x-on:click="show = false; $wire.closeModal()"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        ✕
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-gray-50 p-3 rounded">
                        <p class="text-xs text-gray-500 mb-1">予約番号</p>
                        <p class="font-mono text-sm">{{ $selectedReservation->reservation_number }}</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">顧客名</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->is_new_customer ?? false)
                                    <span style="background: #ff6b6b; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 4px;">NEW</span>
                                @endif
                                {{ $selectedReservation->customer->last_name ?? '' }} {{ $selectedReservation->customer->first_name ?? '' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">顧客ステータス</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->is_new_customer ?? false)
                                    <span class="inline-block px-2 py-1 bg-red-100 text-red-700 rounded text-xs">新規顧客</span>
                                @else
                                    <span class="inline-block px-2 py-1 bg-green-100 text-green-700 rounded text-xs">
                                        既存顧客（{{ $selectedReservation->customer_visit_count ?? 0 }}回目）
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">メニュー</p>
                            <p class="text-sm font-medium">{{ $selectedReservation->menu->name ?? 'なし' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">日時</p>
                            <p class="text-sm font-medium">
                                {{ \Carbon\Carbon::parse($selectedReservation->reservation_date)->format('m/d') }}
                                {{ \Carbon\Carbon::parse($selectedReservation->start_time)->format('H:i') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">現在の配置</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->is_sub)
                                    <span class="inline-block px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">サブ枠</span>
                                @else
                                    <span class="inline-block px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">席{{ $selectedReservation->seat_number }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <p class="text-sm font-medium mb-3">座席を移動</p>
                        <div class="flex gap-2 flex-wrap">
                            @if($selectedReservation->is_sub)
                                @for($i = 1; $i <= 3; $i++)
                                    @if($this->canMoveToMain($selectedReservation->id, $i))
                                        <button 
                                            type="button"
                                            wire:click="moveToMain({{ $selectedReservation->id }}, {{ $i }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50"
                                            style="background-color: #3b82f6 !important; color: white !important; padding: 8px 12px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer;"
                                            onmouseover="this.style.backgroundColor='#2563eb'"
                                            onmouseout="this.style.backgroundColor='#3b82f6'"
                                        >
                                            <span wire:loading.remove wire:target="moveToMain">席{{ $i }}へ</span>
                                            <span wire:loading wire:target="moveToMain">処理中...</span>
                                        </button>
                                    @else
                                        <button 
                                            type="button"
                                            disabled
                                            style="background-color: #d1d5db !important; color: #6b7280 !important; padding: 8px 12px; border-radius: 6px; font-size: 14px; border: none; cursor: not-allowed;"
                                        >
                                            席{{ $i }}（利用不可）
                                        </button>
                                    @endif
                                @endfor
                            @else
                                @if($this->canMoveToSub($selectedReservation->id))
                                    <button 
                                        type="button"
                                        wire:click="moveToSub({{ $selectedReservation->id }})"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50"
                                        style="background-color: #9333ea !important; color: white !important; padding: 8px 16px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer;"
                                        onmouseover="this.style.backgroundColor='#7c3aed'"
                                        onmouseout="this.style.backgroundColor='#9333ea'"
                                    >
                                        <span wire:loading.remove wire:target="moveToSub">サブ枠へ移動</span>
                                        <span wire:loading wire:target="moveToSub">処理中...</span>
                                    </button>
                                @else
                                    <div class="text-sm text-gray-500">
                                        サブ枠は既に予約が入っているため移動できません
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>