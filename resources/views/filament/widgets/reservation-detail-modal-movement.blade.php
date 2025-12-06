{{-- 座席変更セクション --}}
<div class="border-t pt-4">
    @php
        $startTime = \Carbon\Carbon::parse($selectedReservation->start_time);
        $reservationDateTime = \Carbon\Carbon::parse($selectedReservation->reservation_date->format('Y-m-d') . ' ' . $startTime->format('H:i:s'));
        $isPastReservation = $reservationDateTime->isPast();
    @endphp

    @if($isPastReservation)
        <h4 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">座席変更</h4>
        <p class="text-sm text-gray-500 mb-3">⚠️ 過去の予約のため座席変更はできません</p>
    @else
    <div class="flex gap-2 flex-wrap">
        @php
            // 予約の店舗を使用（選択中の店舗ではなく）
            $reservationStore = \App\Models\Store::find($selectedReservation->store_id);
            $useStaffMode = $reservationStore->use_staff_assignment ?? false;
        @endphp

        @if($useStaffMode)
            {{-- スタッフシフトモードの移動オプション --}}
            <h4 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">スタッフ・席を選択</h4>

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

                // 現在の割り当て状態を判定
                $currentAssignment = 'unassigned';
                if ($selectedReservation->is_sub || $selectedReservation->line_type === 'sub') {
                    $currentAssignment = 'sub';
                } elseif ($selectedReservation->staff_id) {
                    $currentAssignment = 'staff_' . $selectedReservation->staff_id;
                } elseif ($selectedReservation->line_type === 'unassigned') {
                    $currentAssignment = 'unassigned';
                }
            @endphp

            <p class="text-sm text-gray-600 mb-4">
                現在の割り当て:
                <span class="font-semibold text-gray-900">
                    @if($currentAssignment === 'sub')
                        サブ枠
                    @elseif($currentAssignment === 'unassigned')
                        未指定
                    @elseif(str_starts_with($currentAssignment, 'staff_'))
                        {{ $allStaff->where('id', $selectedReservation->staff_id)->first()->name ?? '不明' }}
                    @else
                        未設定
                    @endif
                </span>
            </p>

            <div class="grid grid-cols-3 gap-3">
                {{-- 未指定 --}}
                @php
                    $isCurrentUnassigned = ($currentAssignment === 'unassigned');
                @endphp

                @if($isCurrentUnassigned)
                    <div class="border-2 border-blue-600 bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-base font-bold text-blue-600 mb-1">未指定</div>
                        <div class="text-xs text-blue-600 font-medium">現在</div>
                    </div>
                @else
                    <button
                        type="button"
                        wire:click="moveToUnassigned({{ $selectedReservation->id }})"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="border-2 border-gray-300 bg-white rounded-lg p-4 text-center hover:border-amber-400 hover:bg-amber-50 transition-colors"
                    >
                        <div class="text-base font-bold text-gray-700 mb-1">未指定</div>
                        <div class="text-xs text-green-600 font-medium">
                            <span wire:loading.remove wire:target="moveToUnassigned">移動可</span>
                            <span wire:loading wire:target="moveToUnassigned">処理中...</span>
                        </div>
                    </button>
                @endif

                {{-- サブ枠 --}}
                @php
                    $isCurrentSub = ($currentAssignment === 'sub');
                    $canMoveToSubFrame = !$isCurrentSub && $this->canMoveToSub($selectedReservation->id);
                @endphp

                @if($isCurrentSub)
                    <div class="border-2 border-blue-600 bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-base font-bold text-blue-600 mb-1">サブ枠</div>
                        <div class="text-xs text-blue-600 font-medium">現在</div>
                    </div>
                @elseif($canMoveToSubFrame)
                    <button
                        type="button"
                        wire:click="moveToSub({{ $selectedReservation->id }})"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="border-2 border-gray-300 bg-white rounded-lg p-4 text-center hover:border-purple-400 hover:bg-purple-50 transition-colors"
                    >
                        <div class="text-base font-bold text-gray-700 mb-1">サブ枠</div>
                        <div class="text-xs text-green-600 font-medium">
                            <span wire:loading.remove wire:target="moveToSub">空き</span>
                            <span wire:loading wire:target="moveToSub">処理中...</span>
                        </div>
                    </button>
                @else
                    <div class="border-2 border-gray-300 bg-gray-100 rounded-lg p-4 text-center cursor-not-allowed opacity-60">
                        <div class="text-base font-bold text-gray-500 mb-1">サブ枠</div>
                        <div class="text-xs text-red-600 font-medium">使用中</div>
                    </div>
                @endif

                {{-- スタッフ一覧 --}}
                @foreach($allStaff as $staff)
                    @php
                        $hasShift = $shiftsForDay->where('user_id', $staff->id)->first();
                        $isCurrentStaff = ($currentAssignment === 'staff_' . $staff->id);
                    @endphp

                    @if($isCurrentStaff)
                        {{-- 現在このスタッフに割り当てられている --}}
                        <div class="border-2 border-blue-600 bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-base font-bold text-blue-600 mb-1">{{ $staff->name }}</div>
                            <div class="text-xs text-blue-600 font-medium">現在</div>
                        </div>
                    @elseif($hasShift)
                        {{-- シフトがあり、移動可能 --}}
                        <button
                            type="button"
                            wire:click="moveToStaff({{ $selectedReservation->id }}, {{ $staff->id }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                            class="border-2 border-gray-300 bg-white rounded-lg p-4 text-center hover:border-green-400 hover:bg-green-50 transition-colors"
                            title="クリックして{{ $staff->name }}に割り当て"
                        >
                            <div class="text-base font-bold text-gray-700 mb-1">{{ $staff->name }}</div>
                            <div class="text-xs text-green-600 font-medium">
                                <span wire:loading.remove wire:target="moveToStaff">空き</span>
                                <span wire:loading wire:target="moveToStaff">処理中...</span>
                            </div>
                        </button>
                    @else
                        {{-- シフトなし、移動不可 --}}
                        <div class="border-2 border-gray-300 bg-gray-100 rounded-lg p-4 text-center cursor-not-allowed opacity-60"
                             title="{{ $staff->name }}はこの日シフトがありません">
                            <div class="text-base font-bold text-gray-500 mb-1">{{ $staff->name }}</div>
                            <div class="text-xs text-gray-500 font-medium">シフトなし</div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between text-sm">
                <div class="flex gap-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded bg-green-600"></div>
                        <span class="text-gray-600">移動可</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded bg-red-600"></div>
                        <span class="text-gray-600">使用中</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded bg-gray-500"></div>
                        <span class="text-gray-600">シフトなし</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded bg-blue-600"></div>
                        <span class="text-gray-600">現在の割り当て</span>
                    </div>
                </div>
            </div>

        @else
            {{-- 営業時間モードの移動オプション --}}
            @php
                $currentSeatType = 'main';
                if ($selectedReservation->is_sub || $selectedReservation->line_type === 'sub') {
                    $currentSeatType = 'sub';
                }
                $maxSeats = $reservationStore->main_lines_count ?? 6;
            @endphp

            <div class="grid grid-cols-4 gap-3">
                {{-- サブ席 --}}
                @php
                    $isCurrentSeatSub = ($currentSeatType === 'sub');
                    $canMoveToSubSeat = !$isCurrentSeatSub && $this->canMoveToSub($selectedReservation->id);
                @endphp

                @if($isCurrentSeatSub)
                    {{-- 現在サブ席にいる --}}
                    <div class="border-2 border-blue-600 bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-lg font-bold text-blue-600 mb-1">サブ席</div>
                        <div class="text-xs text-blue-600 font-medium">現在</div>
                    </div>
                @elseif($canMoveToSubSeat)
                    {{-- サブ席へ移動可能 --}}
                    <button
                        type="button"
                        wire:click="moveToSub({{ $selectedReservation->id }})"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="border-2 border-gray-300 bg-white rounded-lg p-4 text-center hover:border-purple-400 hover:bg-purple-50 transition-colors"
                    >
                        <div class="text-lg font-bold text-gray-700 mb-1">サブ席</div>
                        <div class="text-xs text-green-600 font-medium">
                            <span wire:loading.remove wire:target="moveToSub">空き</span>
                            <span wire:loading wire:target="moveToSub">処理中...</span>
                        </div>
                    </button>
                @else
                    {{-- サブ席移動不可 --}}
                    <div class="border-2 border-gray-300 bg-gray-100 rounded-lg p-4 text-center cursor-not-allowed opacity-60">
                        <div class="text-lg font-bold text-gray-500 mb-1">サブ席</div>
                        <div class="text-xs text-red-600 font-medium">使用中</div>
                    </div>
                @endif

                {{-- メイン席（席1〜6） --}}
                @for($i = 1; $i <= $maxSeats; $i++)
                    @php
                        $isCurrentSeat = ($currentSeatType === 'main') && ($selectedReservation->seat_number == $i);
                        $canMove = !$isCurrentSeat && $this->canMoveToMain($selectedReservation->id, $i);
                    @endphp

                    @if($isCurrentSeat)
                        {{-- 現在の座席 --}}
                        <div class="border-2 border-blue-600 bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-lg font-bold text-blue-600 mb-1">席{{ $i }}</div>
                            <div class="text-xs text-blue-600 font-medium">現在</div>
                        </div>
                    @elseif($canMove)
                        {{-- 空き座席 --}}
                        <button
                            type="button"
                            wire:click="moveToMain({{ $selectedReservation->id }}, {{ $i }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                            class="border-2 border-gray-300 bg-white rounded-lg p-4 text-center hover:border-blue-400 hover:bg-blue-50 transition-colors"
                        >
                            <div class="text-lg font-bold text-gray-700 mb-1">席{{ $i }}</div>
                            <div class="text-xs text-green-600 font-medium">
                                <span wire:loading.remove wire:target="moveToMain">空き</span>
                                <span wire:loading wire:target="moveToMain">処理中...</span>
                            </div>
                        </button>
                    @else
                        {{-- 使用中座席 --}}
                        <div class="border-2 border-gray-300 bg-gray-100 rounded-lg p-4 text-center cursor-not-allowed opacity-60">
                            <div class="text-lg font-bold text-gray-500 mb-1">席{{ $i }}</div>
                            <div class="text-xs text-red-600 font-medium">使用中</div>
                        </div>
                    @endif
                @endfor
            </div>

            <div class="mt-4 flex items-center justify-between text-sm">
                <div class="flex gap-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded bg-green-600"></div>
                        <span class="text-gray-600">空き</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded bg-red-600"></div>
                        <span class="text-gray-600">使用中</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded bg-blue-600"></div>
                        <span class="text-gray-600">現在の席</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endif
</div>