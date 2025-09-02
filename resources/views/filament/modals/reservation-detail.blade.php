<div>
    @if($reservation)
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-sm text-gray-500">予約番号:</span>
                    <p class="font-medium">{{ $reservation->reservation_number }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">ステータス:</span>
                    <p class="font-medium">{{ $reservation->status }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">顧客名:</span>
                    <p class="font-medium">
                        {{ $reservation->customer->last_name ?? '' }} {{ $reservation->customer->first_name ?? '' }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">メニュー:</span>
                    <p class="font-medium">{{ $reservation->menu->name ?? 'なし' }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">日時:</span>
                    <p class="font-medium">
                        {{ \Carbon\Carbon::parse($reservation->reservation_date)->format('Y/m/d') }}
                        {{ \Carbon\Carbon::parse($reservation->start_time)->format('H:i') }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">配置:</span>
                    <p class="font-medium">
                        @if($reservation->is_sub)
                            サブ枠
                        @else
                            席{{ $reservation->seat_number }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="border-t pt-4 flex gap-2">
                @if($reservation->is_sub)
                    <span class="text-sm text-gray-500 mr-2">通常席に移動:</span>
                    @for($i = 1; $i <= 3; $i++)
                        <button 
                            wire:click="moveToMain({{ $reservation->id }}, {{ $i }})"
                            class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
                        >
                            席{{ $i }}へ
                        </button>
                    @endfor
                @else
                    <button 
                        wire:click="moveToSub({{ $reservation->id }})"
                        class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600"
                    >
                        サブ枠へ移動
                    </button>
                @endif
                
                <button 
                    wire:click="$dispatch('close-modal', { id: 'reservation-detail' })"
                    class="ml-auto px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
                >
                    閉じる
                </button>
            </div>
        </div>
    @else
        <p class="text-gray-500">予約情報が見つかりません</p>
    @endif
</div>