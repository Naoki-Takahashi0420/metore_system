<div class="p-4 hover:bg-gray-50">
    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ $order->order_number }}</p>
            <p class="text-xs text-gray-500">{{ $order->created_at->format('Y/m/d') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-900">¥{{ number_format($order->total_amount) }}</span>
            @php
                $statusColors = [
                    'draft' => 'bg-gray-100 text-gray-600',
                    'ordered' => 'bg-blue-100 text-blue-700',
                    'shipped' => 'bg-amber-100 text-amber-700',
                    'delivered' => 'bg-green-100 text-green-700',
                    'cancelled' => 'bg-red-100 text-red-700',
                ];
                $statusLabels = [
                    'draft' => '下書き',
                    'ordered' => '発注済み',
                    'shipped' => '発送済み',
                    'delivered' => '納品完了',
                    'cancelled' => 'キャンセル',
                ];
            @endphp
            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $statusLabels[$order->status] ?? $order->status }}
            </span>
        </div>
    </div>
    {{-- 商品名（最初の1つだけ） --}}
    @if($order->items->count() > 0)
        <p class="mt-1 text-xs text-gray-500 truncate">
            {{ $order->items->first()->product_name }}
            @if($order->items->count() > 1)
                他{{ $order->items->count() - 1 }}件
            @endif
        </p>
    @endif
</div>
