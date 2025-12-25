<div class="p-4 hover:bg-gray-50">
    <div class="flex items-center justify-between mb-2">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ $order->order_number }}</p>
            <p class="text-xs text-gray-500">{{ $order->created_at->format('Y/m/d') }} | ¥{{ number_format($order->total_amount) }}</p>
        </div>
    </div>

    {{-- ミニプログレスバー --}}
    @php
        $steps = ['ordered', 'shipped', 'delivered'];
        $currentIndex = array_search($order->status, $steps);
        if ($order->status === 'draft') $currentIndex = -1;
        if ($order->status === 'cancelled') $currentIndex = -2;
    @endphp

    @if($order->status === 'cancelled')
        <div class="flex items-center gap-1 text-xs text-red-600">
            <x-heroicon-s-x-circle class="w-4 h-4" />
            <span>キャンセル</span>
        </div>
    @elseif($order->status === 'draft')
        <div class="flex items-center gap-1 text-xs text-gray-500">
            <x-heroicon-s-pencil-square class="w-4 h-4" />
            <span>下書き</span>
        </div>
    @else
        <div class="flex items-center gap-2">
            @foreach($steps as $index => $step)
                @php
                    $isCompleted = $index <= $currentIndex;
                    $labels = ['発注済', '発送済', '納品完了'];
                @endphp
                <div class="flex items-center gap-1">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center {{ $isCompleted ? 'bg-green-500' : 'bg-gray-200' }}">
                        @if($isCompleted)
                            <x-heroicon-s-check class="w-3 h-3 text-white" />
                        @endif
                    </div>
                    @if($index < 2)
                        <div class="w-6 h-0.5 {{ $index < $currentIndex ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endforeach
            <span class="ml-2 text-xs font-medium {{ $order->status === 'delivered' ? 'text-green-600' : 'text-blue-600' }}">
                {{ ['ordered' => '発注済み', 'shipped' => '発送中', 'delivered' => '納品完了'][$order->status] ?? $order->status }}
            </span>
        </div>
    @endif
</div>
