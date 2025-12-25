<div class="p-4 hover:bg-gray-50">
    {{-- ヘッダー行 --}}
    <div class="flex items-start justify-between mb-4">
        <div>
            <span class="text-base font-semibold text-gray-900">{{ $order->order_number }}</span>
            <span class="ml-2 text-sm text-gray-500">{{ $order->created_at->format('Y/m/d') }}</span>
        </div>
        <span class="text-base font-bold text-gray-900">¥{{ number_format($order->total_amount) }}</span>
    </div>

    {{-- Amazon風ステータストラッカー --}}
    @php
        $steps = [
            'ordered' => ['label' => '発注済み', 'icon' => 'paper-airplane'],
            'shipped' => ['label' => '発送済み', 'icon' => 'truck'],
            'delivered' => ['label' => '納品完了', 'icon' => 'check-circle'],
        ];
        $stepKeys = array_keys($steps);
        $currentIndex = array_search($order->status, $stepKeys);
        if ($order->status === 'draft') $currentIndex = -1;
        if ($order->status === 'cancelled') $currentIndex = -2;
    @endphp

    @if($order->status === 'cancelled')
        <div class="flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-sm">
            <x-heroicon-s-x-circle class="w-4 h-4 text-red-500" />
            <span class="font-medium text-red-700">キャンセル</span>
        </div>
    @elseif($order->status === 'draft')
        <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm">
            <x-heroicon-s-pencil-square class="w-4 h-4 text-gray-500" />
            <span class="font-medium text-gray-700">下書き</span>
        </div>
    @else
        {{-- プログレスバー --}}
        <div class="relative">
            {{-- 背景ライン --}}
            <div class="absolute top-3 left-0 right-0 h-0.5 bg-gray-200 rounded"></div>
            {{-- 進捗ライン --}}
            @php
                $progressPercent = match($currentIndex) {
                    0 => 0,
                    1 => 50,
                    2 => 100,
                    default => 0,
                };
            @endphp
            <div class="absolute top-3 left-0 h-0.5 bg-green-500 rounded transition-all duration-500" style="width: {{ $progressPercent }}%"></div>

            {{-- ステップ --}}
            <div class="relative flex justify-between">
                @foreach($steps as $stepKey => $step)
                    @php
                        $stepIndex = array_search($stepKey, $stepKeys);
                        $isCompleted = $stepIndex < $currentIndex;
                        $isCurrent = $stepIndex === $currentIndex;
                        $isFuture = $stepIndex > $currentIndex;
                    @endphp
                    <div class="flex flex-col items-center">
                        {{-- ステップ円 --}}
                        <div class="w-6 h-6 rounded-full flex items-center justify-center border-2 transition-all
                            {{ $isCompleted ? 'bg-green-500 border-green-500' : '' }}
                            {{ $isCurrent ? 'bg-green-500 border-green-500 ring-2 ring-green-100' : '' }}
                            {{ $isFuture ? 'bg-white border-gray-300' : '' }}">
                            @if($isCompleted)
                                <x-heroicon-s-check class="w-3 h-3 text-white" />
                            @elseif($isCurrent)
                                @if($step['icon'] === 'paper-airplane')
                                    <x-heroicon-s-paper-airplane class="w-3 h-3 text-white" />
                                @elseif($step['icon'] === 'truck')
                                    <x-heroicon-s-truck class="w-3 h-3 text-white" />
                                @else
                                    <x-heroicon-s-check-circle class="w-3 h-3 text-white" />
                                @endif
                            @else
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                            @endif
                        </div>
                        {{-- ラベル --}}
                        <span class="mt-1 text-xs font-medium
                            {{ $isCompleted || $isCurrent ? 'text-green-700' : 'text-gray-400' }}">
                            {{ $step['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ステータスメッセージ --}}
        <div class="mt-3">
            @if($order->status === 'ordered')
                <div class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded">
                    本部で発送準備中
                </div>
            @elseif($order->status === 'shipped')
                <div class="text-xs text-amber-600 bg-amber-50 px-2 py-1 rounded">
                    商品を発送しました
                </div>
            @elseif($order->status === 'delivered')
                <div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                    納品完了
                </div>
            @endif
        </div>
    @endif

    {{-- 商品リスト --}}
    @if($order->items && $order->items->count() > 0)
        <div class="mt-2 text-xs text-gray-600">
            @foreach($order->items->take(2) as $item)
                <span class="inline-block bg-gray-100 rounded px-1.5 py-0.5 mr-1 mb-1">
                    {{ $item->product_name }} x{{ number_format($item->quantity) }}
                </span>
            @endforeach
            @if($order->items->count() > 2)
                <span class="text-gray-400">他{{ $order->items->count() - 2 }}件</span>
            @endif
        </div>
    @endif
</div>
