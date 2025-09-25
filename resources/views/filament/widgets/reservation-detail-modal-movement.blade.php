{{-- 座席移動セクション --}}
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
        @php
            // 予約の店舗を使用（選択中の店舗ではなく）
            $reservationStore = \App\Models\Store::find($selectedReservation->store_id);
            $useStaffMode = $reservationStore->use_staff_assignment ?? false;
        @endphp

        @if($useStaffMode)
            {{-- スタッフシフトモードの移動オプション --}}

            {{-- サブ枠への移動（現在サブ枠にいない場合） --}}
            @if(!$selectedReservation->is_sub && $selectedReservation->line_type !== 'sub')
                @if($this->canMoveToSub($selectedReservation->id))
                    <button
                        type="button"
                        wire:click="moveToSub({{ $selectedReservation->id }})"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        style="background-color: transparent; color: #9333ea; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 2px solid #9333ea; cursor: pointer; transition: all 0.2s;"
                        onmouseover="this.style.backgroundColor='#9333ea'; this.style.color='white';"
                        onmouseout="this.style.backgroundColor='transparent'; this.style.color='#9333ea';"
                    >
                        <span wire:loading.remove wire:target="moveToSub">サブ枠へ</span>
                        <span wire:loading wire:target="moveToSub">処理中...</span>
                    </button>
                @else
                    <button
                        type="button"
                        disabled
                        style="background-color: transparent; color: #d1d5db; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 2px solid #d1d5db; cursor: not-allowed; opacity: 0.5;"
                    >
                        サブ枠へ（利用不可）
                    </button>
                @endif
            @endif

            {{-- 未指定への移動（現在未指定にいない場合） --}}
            @if($selectedReservation->line_type !== 'unassigned')
                <button
                    type="button"
                    wire:click="moveToUnassigned({{ $selectedReservation->id }})"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    style="background-color: transparent; color: #f59e0b; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 2px solid #f59e0b; cursor: pointer; transition: all 0.2s;"
                    onmouseover="this.style.backgroundColor='#f59e0b'; this.style.color='white';"
                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#f59e0b';"
                >
                    <span wire:loading.remove wire:target="moveToUnassigned">未指定へ</span>
                    <span wire:loading wire:target="moveToUnassigned">処理中...</span>
                </button>
            @endif

            {{-- スタッフラインへの移動ボタン --}}
            @php
                // この日のシフトがあるスタッフ一覧を取得
                $shiftsForDay = \App\Models\Shift::where('store_id', $selectedReservation->store_id)
                    ->whereDate('shift_date', $selectedReservation->reservation_date)
                    ->where('status', 'scheduled')
                    ->where('is_available_for_reservation', true)
                    ->with('user')
                    ->get();
                // 店舗の全スタッフも取得（シフトなしも含む）
                $allStaff = \App\Models\User::whereHas('roles', function($q) {
                    $q->whereIn('name', ['staff', 'manager']);
                })->where('store_id', $selectedReservation->store_id)
                  ->where('is_active', true)
                  ->orderBy('name')
                  ->get();
            @endphp

            @foreach($allStaff as $staff)
                @php
                    $hasShift = $shiftsForDay->where('user_id', $staff->id)->first();
                    // 現在このスタッフに割り当てられているか確認
                    $isCurrentStaff = ($selectedReservation->staff_id == $staff->id);
                @endphp
                @if(!$isCurrentStaff) {{-- 現在のスタッフは表示しない --}}
                    <button
                        type="button"
                        @if($hasShift)
                            wire:click="moveToStaff({{ $selectedReservation->id }}, {{ $staff->id }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                        @else
                            disabled
                        @endif
                        style="background-color: transparent; color: {{ $hasShift ? '#10b981' : '#9ca3af' }}; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 2px solid {{ $hasShift ? '#10b981' : '#d1d5db' }}; cursor: {{ $hasShift ? 'pointer' : 'not-allowed' }}; opacity: {{ $hasShift ? '1' : '0.4' }}; transition: all 0.2s;"
                        @if($hasShift)
                            onmouseover="this.style.backgroundColor='#10b981'; this.style.color='white'; this.style.borderColor='#10b981';"
                            onmouseout="this.style.backgroundColor='transparent'; this.style.color='#10b981'; this.style.borderColor='#10b981';"
                        @endif
                        title="{{ $hasShift ? 'クリックして' . $staff->name . 'に割り当て' : $staff->name . 'はこの日シフトがありません' }}"
                    >
                        <span wire:loading.remove wire:target="moveToStaff">
                            {{ $staff->name }}
                            @if(!$hasShift)
                                <small style="opacity: 0.7">(シフトなし)</small>
                            @endif
                        </span>
                        @if($hasShift)
                            <span wire:loading wire:target="moveToStaff">処理中...</span>
                        @endif
                    </button>
                @endif
            @endforeach

        @else
            {{-- 営業時間モードの移動オプション --}}

            {{-- サブ枠への移動 --}}
            @if(!$selectedReservation->is_sub && $this->canMoveToSub($selectedReservation->id))
                <button
                    type="button"
                    wire:click="moveToSub({{ $selectedReservation->id }})"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    style="background-color: transparent; color: #9333ea; padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 2px solid #9333ea; cursor: pointer; transition: all 0.2s;"
                    onmouseover="this.style.backgroundColor='#9333ea'; this.style.color='white';"
                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#9333ea';"
                >
                    <span wire:loading.remove wire:target="moveToSub">サブ枠へ移動</span>
                    <span wire:loading wire:target="moveToSub">処理中...</span>
                </button>
            @endif

            {{-- メイン席への移動 --}}
            @php
                $maxSeats = $reservationStore->main_lines_count ?? 1;
            @endphp
            @for($i = 1; $i <= $maxSeats; $i++)
                @if($selectedReservation->seat_number != $i) {{-- 現在の席は表示しない --}}
                    @if($this->canMoveToMain($selectedReservation->id, $i))
                        <button
                            type="button"
                            wire:click="moveToMain({{ $selectedReservation->id }}, {{ $i }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                            style="background-color: transparent; color: #3b82f6; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 2px solid #3b82f6; cursor: pointer; transition: all 0.2s;"
                            onmouseover="this.style.backgroundColor='#3b82f6'; this.style.color='white';"
                            onmouseout="this.style.backgroundColor='transparent'; this.style.color='#3b82f6';"
                        >
                            <span wire:loading.remove wire:target="moveToMain">席{{ $i }}へ</span>
                            <span wire:loading wire:target="moveToMain">処理中...</span>
                        </button>
                    @else
                        <button
                            type="button"
                            disabled
                            style="background-color: transparent; color: #d1d5db; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 2px solid #d1d5db; cursor: not-allowed; opacity: 0.4;"
                        >
                            席{{ $i }}（利用不可）
                        </button>
                    @endif
                @endif
            @endfor
        @endif
    </div>
    @endif
</div>