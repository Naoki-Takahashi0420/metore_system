<div class="p-6">
    {{-- ヘッダー行 --}}
    <div class="flex items-start justify-between mb-5">
        <div>
            <span class="text-lg font-semibold text-gray-900">{{ $order->order_number }}</span>
            <span class="ml-2 text-sm text-gray-500">
                {{ $order->created_at->format('Y/m/d') }}
            </span>
        </div>
        <span class="text-lg font-bold text-gray-900">¥{{ number_format($order->total_amount) }}</span>
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
        <div class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 rounded-lg mb-4">
            <x-heroicon-s-x-circle class="w-5 h-5 text-red-500" />
            <span class="font-medium text-red-700">この発注はキャンセルされました</span>
        </div>
    @elseif($order->status === 'draft')
        <div class="flex items-center gap-2 px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg mb-4">
            <x-heroicon-s-pencil-square class="w-5 h-5 text-gray-500" />
            <span class="font-medium text-gray-700">下書き - まだ発注されていません</span>
        </div>
    @else
        {{-- Amazon風プログレスバー --}}
        <div class="relative mb-4">
            {{-- 背景ライン --}}
            <div class="absolute top-4 left-0 right-0 h-1 bg-gray-200 rounded"></div>
            {{-- 進捗ライン --}}
            @php
                $progressPercent = match($currentIndex) {
                    0 => 0,
                    1 => 50,
                    2 => 100,
                    default => 0,
                };
            @endphp
            <div class="absolute top-4 left-0 h-1 bg-green-500 rounded transition-all duration-500" style="width: {{ $progressPercent }}%"></div>

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
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all
                            {{ $isCompleted ? 'bg-green-500 border-green-500' : '' }}
                            {{ $isCurrent ? 'bg-green-500 border-green-500 ring-4 ring-green-100' : '' }}
                            {{ $isFuture ? 'bg-white border-gray-300' : '' }}">
                            @if($isCompleted)
                                <x-heroicon-s-check class="w-4 h-4 text-white" />
                            @elseif($isCurrent)
                                @if($step['icon'] === 'paper-airplane')
                                    <x-heroicon-s-paper-airplane class="w-4 h-4 text-white" />
                                @elseif($step['icon'] === 'truck')
                                    <x-heroicon-s-truck class="w-4 h-4 text-white" />
                                @else
                                    <x-heroicon-s-check-circle class="w-4 h-4 text-white" />
                                @endif
                            @else
                                <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                            @endif
                        </div>
                        {{-- ラベル --}}
                        <span class="mt-2 text-xs font-medium
                            {{ $isCompleted || $isCurrent ? 'text-green-700' : 'text-gray-400' }}">
                            {{ $step['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 現在のステータスメッセージ --}}
        @if($order->status === 'ordered')
            <div class="text-sm text-blue-600 bg-blue-50 px-3 py-2 rounded-lg">
                本部で発送準備中です。発送後にお知らせします。
            </div>
        @elseif($order->status === 'shipped')
            <div class="text-sm text-amber-600 bg-amber-50 px-3 py-2 rounded-lg">
                商品を発送しました。到着までしばらくお待ちください。
            </div>
        @elseif($order->status === 'delivered')
            <div class="text-sm text-green-600 bg-green-50 px-3 py-2 rounded-lg">
                納品完了しました。
            </div>
        @endif
    @endif

    {{-- 商品リスト --}}
    <div class="mt-4 pt-4 border-t text-sm text-gray-600">
        @foreach($order->items->take(3) as $item)
            <span class="inline-block bg-gray-100 rounded px-2 py-1 mr-2 mb-1">
                {{ $item->product_name }} x{{ number_format($item->quantity) }}
            </span>
        @endforeach
        @if($order->items->count() > 3)
            <span class="text-gray-400">他{{ $order->items->count() - 3 }}件</span>
        @endif
    </div>
</div>
