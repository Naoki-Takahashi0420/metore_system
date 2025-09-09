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
                border-top: 1px solid #e0e0e0;
                border-bottom: 1px solid #e0e0e0;
                border-left: 1px solid #e0e0e0;
                padding: 0;
                height: 60px;
                position: relative;
            }
            
            .timeline-table th:last-child,
            .timeline-table td:last-child {
                border-right: 1px solid #e0e0e0;
            }
            
            .timeline-table th {
                background: #f8f8f8;
                font-weight: normal;
                font-size: 14px;
                text-align: center;
                min-width: 20px;
            }
            
            .timeline-table th[colspan] {
                min-width: 80px;
                border-right: 1px solid #e0e0e0;
            }
            
            .timeline-table td {
                width: 20px;
                min-width: 20px;
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
            
            .booking-block.span-1 { width: calc(20px - 4px); }
            .booking-block.span-2 { width: calc(40px - 4px); }
            .booking-block.span-3 { width: calc(60px - 4px); }
            .booking-block.span-4 { width: calc(80px - 4px); }
            .booking-block.span-5 { width: calc(100px - 4px); }
            .booking-block.span-6 { width: calc(120px - 4px); }
            .booking-block.span-7 { width: calc(140px - 4px); }
            .booking-block.span-8 { width: calc(160px - 4px); }
            
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
            {{-- 店舗選択（柔軟な表示方式） --}}
            @php
                $storeCount = $stores->count();
                $currentStore = $stores->firstWhere('id', $selectedStore);
                $useStaffAssignment = $currentStore->use_staff_assignment ?? false;
                $shiftBasedCapacity = $currentStore->shift_based_capacity ?? 1;
            @endphp
            
            @if($storeCount <= 3)
                {{-- 3店舗以下：ボタン形式 --}}
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    @foreach($stores as $store)
                        <button
                            wire:click="$set('selectedStore', {{ $store->id }})"
                            class="px-3 py-1 text-sm rounded-lg transition-colors {{ $selectedStore == $store->id ? 'bg-primary-600 text-white' : 'bg-gray-100 hover:bg-gray-200' }}"
                        >
                            {{ $store->name }}
                        </button>
                    @endforeach
                </div>
            @elseif($storeCount <= 8)
                {{-- 4-8店舗：ドロップダウン --}}
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    <x-filament::dropdown placement="bottom-start">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-3 py-1 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700">
                                <x-heroicon-o-building-storefront class="w-4 h-4" />
                                <span>{{ $currentStore ? $currentStore->name : '店舗を選択' }}</span>
                                <x-heroicon-m-chevron-down class="w-3 h-3" />
                            </button>
                        </x-slot>
                        
                        <div class="py-1">
                            @foreach($stores as $store)
                                @if($store->id != $selectedStore)
                                <button 
                                    wire:click="$set('selectedStore', {{ $store->id }})"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    @if($store->is_active)
                                        <x-heroicon-m-check-circle class="w-4 h-4 text-green-500" />
                                    @else
                                        <x-heroicon-m-x-circle class="w-4 h-4 text-gray-400" />
                                    @endif
                                    {{ $store->name }}
                                </button>
                                @endif
                            @endforeach
                        </div>
                    </x-filament::dropdown>
                </div>
            @else
                {{-- 9店舗以上：検索可能な選択 --}}
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    <select wire:model.live="selectedStore" class="border rounded px-3 py-1 text-sm">
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            
            {{-- 予約管理モード表示 --}}
            <div class="flex items-center gap-2 px-3 py-1 rounded-lg text-sm {{ $useStaffAssignment ? 'bg-blue-50 text-blue-700' : 'bg-gray-50 text-gray-700' }}">
                @if($useStaffAssignment)
                    <x-heroicon-m-user-group class="w-4 h-4" />
                    <span>シフトベース</span>
                    <span class="font-medium">（最大{{ $shiftBasedCapacity }}席）</span>
                @else
                    <x-heroicon-m-clock class="w-4 h-4" />
                    <span>営業時間ベース</span>
                    <span class="font-medium">（{{ $currentStore->main_lines_count ?? 3 }}席）</span>
                @endif
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
                
                <!-- 新規予約ボタン -->
                <button 
                    wire:click="openNewReservationModal"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                    <x-heroicon-o-plus-circle class="w-5 h-5" />
                    <span>新規予約</span>
                </button>
                
                <!-- デバッグ用 -->
                <button 
                    wire:click="$set('showNewReservationModal', true)"
                    type="button"
                    class="px-3 py-1 bg-blue-500 text-white text-xs rounded">
                    テスト
                </button>
            </div>
        </div>
        
        <!-- タイムライン -->
        <div class="overflow-x-auto">
            @if(!empty($timelineData))
                <table class="timeline-table">
                    <thead>
                        <tr>
                            <th style="vertical-align: middle;">席数</th>
                            @php
                                $hourGroups = [];
                                foreach($timelineData['slots'] as $index => $slot) {
                                    $hour = substr($slot, 0, 2);
                                    if (!isset($hourGroups[$hour])) {
                                        $hourGroups[$hour] = 0;
                                    }
                                    $hourGroups[$hour]++;
                                }
                            @endphp
                            @foreach($hourGroups as $hour => $count)
                                <th colspan="{{ $count }}" style="font-weight: bold;">{{ $hour }}:00</th>
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
                                    @php
                                        $hasReservation = false;
                                        foreach($seat['reservations'] as $reservation) {
                                            if($reservation['start_slot'] <= $index && $index < $reservation['start_slot'] + $reservation['span']) {
                                                $hasReservation = true;
                                                break;
                                            }
                                        }
                                        $isBlocked = in_array($index, $timelineData['blockedSlots']);
                                        
                                        // 過去の時間帯かチェック（現在時刻から30分前まで許可）
                                        $slotDateTime = \Carbon\Carbon::parse($selectedDate . ' ' . $slot);
                                        $minimumTime = \Carbon\Carbon::now()->subMinutes(30);
                                        $isPast = $slotDateTime->lt($minimumTime);
                                        
                                        $isClickable = !$hasReservation && !$isBlocked && !$isPast;
                                    @endphp
                                    <td class="time-cell {{ $isBlocked ? 'blocked-cell' : '' }} {{ $isClickable ? 'empty-slot' : '' }}"
                                        @if($isClickable)
                                            wire:click="openNewReservationFromSlot('{{ $key }}', '{{ $slot }}')"
                                            style="cursor: pointer; position: relative;"
                                            onmouseover="this.style.backgroundColor='#e3f2fd'" 
                                            onmouseout="this.style.backgroundColor=''"
                                            title="クリックして予約を作成"
                                        @endif>
                                        @if($isBlocked)
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
                                                        @if($reservation['staff_name'])
                                                            <div style="font-size: 10px; color: #666; margin-top: 2px;">
                                                                👤 {{ $reservation['staff_name'] }}
                                                            </div>
                                                        @endif
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
                            <p class="text-xs text-gray-500 mb-1">担当スタッフ</p>
                            <p class="text-sm font-medium">
                                @if($selectedReservation->staff)
                                    <span class="inline-block px-2 py-1 bg-green-100 text-green-700 rounded text-xs">
                                        👤 {{ $selectedReservation->staff->name }}
                                    </span>
                                @else
                                    <span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">未割当</span>
                                @endif
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
                        @php
                            $reservationDateTime = \Carbon\Carbon::parse($selectedReservation->reservation_date->format('Y-m-d') . ' ' . $selectedReservation->start_time);
                            $isPastReservation = $reservationDateTime->isPast();
                        @endphp
                        @if($isPastReservation)
                            <p class="text-sm text-gray-500 mb-3">⚠️ 過去の予約のため座席移動はできません</p>
                        @else
                            <p class="text-sm font-medium mb-3">座席を移動</p>
                        @endif
                        @if(!$isPastReservation)
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
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
    
    <!-- 新規予約作成モーダル -->
    @if($showNewReservationModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeNewReservationModal">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto" wire:click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">新規予約作成</h2>
                    <button wire:click="closeNewReservationModal" class="text-gray-500 hover:text-gray-700">
                        <x-heroicon-s-x-mark class="w-6 h-6" />
                    </button>
                </div>
                
                <!-- Step 1: 顧客選択 -->
                @if($reservationStep === 1)
                    <div class="space-y-4">
                        <!-- 選択された時間と席の情報 -->
                        @if(!empty($newReservation['start_time']))
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="text-sm font-medium text-blue-900">
                                    予約時間: {{ $newReservation['date'] }} {{ $newReservation['start_time'] }}
                                    @if($newReservation['line_type'] === 'main')
                                        （席{{ $newReservation['line_number'] }}）
                                    @else
                                        （サブライン）
                                    @endif
                                </div>
                            </div>
                        @endif
                        
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <button 
                                wire:click="$set('customerSelectionMode', 'existing')"
                                class="px-4 py-2 {{ $customerSelectionMode === 'existing' ? 'bg-primary-600 text-white' : 'bg-gray-100' }} rounded-lg transition">
                                既存顧客
                            </button>
                            <button 
                                wire:click="$set('customerSelectionMode', 'new')"
                                class="px-4 py-2 {{ $customerSelectionMode === 'new' ? 'bg-primary-600 text-white' : 'bg-gray-100' }} rounded-lg transition">
                                新規顧客
                            </button>
                        </div>
                        
                        @if($customerSelectionMode === 'existing')
                            <div>
                                <label class="block text-sm font-medium mb-2">電話番号・名前で検索</label>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="phoneSearch"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="電話番号または名前を入力"
                                    autofocus>
                            </div>
                        @else
                            <button 
                                wire:click="$set('reservationStep', 2); $set('newCustomer.phone', phoneSearch)"
                                class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                                新規顧客情報を入力
                            </button>
                        @endif
                        
                        @if(strlen($phoneSearch) >= 2)
                            @if(count($searchResults) > 0)
                                <div class="border rounded-lg divide-y">
                                    <div class="bg-gray-50 px-4 py-2 font-medium text-sm">
                                        検索結果 ({{ count($searchResults) }}件)
                                    </div>
                                    @foreach($searchResults as $customer)
                                        <div 
                                            wire:click="selectCustomer({{ $customer->id }})"
                                            class="px-4 py-3 hover:bg-blue-50 cursor-pointer transition">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <div class="font-medium">
                                                        {{ $customer->last_name }} {{ $customer->first_name }}
                                                        <span class="text-sm text-gray-500">({{ $customer->last_name_kana }} {{ $customer->first_name_kana }})</span>
                                                    </div>
                                                    <div class="text-sm text-gray-600">
                                                        📞 {{ $customer->phone }}
                                                        @if($customer->email)
                                                            | ✉️ {{ $customer->email }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-right text-sm">
                                                    <div class="text-gray-500">来店回数: {{ $customer->reservations_count ?? 0 }}回</div>
                                                    @if($customer->last_visit_date)
                                                        <div class="text-gray-500">最終: {{ \Carbon\Carbon::parse($customer->last_visit_date)->format('n/j') }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600" />
                                        <span class="font-medium">該当する顧客が見つかりません</span>
                                    </div>
                                    <button 
                                        wire:click="startNewCustomerRegistration"
                                        class="w-full mt-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                                        新規顧客として登録
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
                
                <!-- Step 2: 新規顧客登録 -->
                @if($reservationStep === 2)
                    <div class="space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                            <div class="font-medium text-blue-900">新規顧客登録</div>
                            <div class="text-sm text-blue-700">電話番号: {{ $phoneSearch }}</div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">姓 <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    wire:model="newCustomer.last_name"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="山田">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">名 <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    wire:model="newCustomer.first_name"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="太郎">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium mb-1">電話番号 <span class="text-red-500">*</span></label>
                                <input 
                                    type="tel" 
                                    wire:model="newCustomer.phone"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="090-1234-5678">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium mb-1">メールアドレス</label>
                                <input 
                                    type="email" 
                                    wire:model="newCustomer.email"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                    placeholder="yamada@example.com">
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <button 
                                wire:click="$set('reservationStep', 1)"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                                戻る
                            </button>
                            <button 
                                wire:click="createNewCustomer"
                                class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                                登録して予約作成へ
                            </button>
                        </div>
                    </div>
                @endif
                
                <!-- Step 3: 予約詳細入力 -->
                @if($reservationStep === 3)
                    <div class="space-y-4">
                        @if($selectedCustomer)
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="font-medium text-green-900">
                                    {{ $selectedCustomer->last_name }} {{ $selectedCustomer->first_name }} 様
                                </div>
                                <div class="text-sm text-green-700">
                                    📞 {{ $selectedCustomer->phone }}
                                    @if($selectedCustomer->email)
                                        | ✉️ {{ $selectedCustomer->email }}
                                    @endif
                                </div>
                            </div>
                        @endif
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">予約日時</label>
                            <div class="grid grid-cols-3 gap-2">
                                <input 
                                    type="date" 
                                    wire:model="newReservation.date"
                                    value="{{ $selectedDate }}"
                                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <input 
                                    type="time" 
                                    wire:model="newReservation.start_time"
                                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <select 
                                    wire:model="newReservation.duration"
                                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option value="60">60分</option>
                                    <option value="90">90分</option>
                                    <option value="120">120分</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">メニュー</label>
                            <select 
                                wire:model="newReservation.menu_id"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="">選択してください</option>
                                @foreach(\App\Models\Menu::where('is_available', true)->get() as $menu)
                                    <option value="{{ $menu->id }}">
                                        {{ $menu->name }} ({{ $menu->duration_minutes }}分) - ¥{{ number_format($menu->price) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">ライン（席）</label>
                            <select 
                                wire:model="newReservation.line_type"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="main">メインライン</option>
                                <option value="sub">サブライン</option>
                            </select>
                        </div>
                        
                        @if($newReservation['line_type'] === 'main')
                            <div>
                                <label class="block text-sm font-medium mb-1">席番号</label>
                                <select 
                                    wire:model="newReservation.line_number"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    @for($i = 1; $i <= 3; $i++)
                                        <option value="{{ $i }}">席{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        @endif
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">備考</label>
                            <textarea 
                                wire:model="newReservation.notes"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                rows="3"
                                placeholder="電話予約、特記事項など"></textarea>
                        </div>
                        
                        <div class="flex gap-2">
                            <button 
                                wire:click="$set('reservationStep', 1)"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                                戻る
                            </button>
                            <button 
                                wire:click="createReservation"
                                class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                                予約を作成
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-filament-widgets::widget>