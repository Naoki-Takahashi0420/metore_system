<div class="p-4 hover:bg-gray-50">
    <div class="flex items-center justify-between mb-2">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ $invoice->invoice_number }}</p>
            <p class="text-xs text-gray-500">
                {{ $invoice->billing_period_start->format('m/d') }} - {{ $invoice->billing_period_end->format('m/d') }} | ¥{{ number_format($invoice->total_amount) }}
            </p>
        </div>
    </div>

    {{-- ミニプログレスバー --}}
    @php
        $steps = ['issued', 'sent', 'paid'];
        $currentIndex = array_search($invoice->status, $steps);
        if ($currentIndex === false) $currentIndex = 0;
        $isPaid = $invoice->status === 'paid';
    @endphp

    <div class="flex items-center gap-2">
        @foreach($steps as $index => $step)
            @php
                $isCompleted = $index <= $currentIndex;
                $color = $isPaid ? 'bg-green-500' : ($isCompleted ? 'bg-amber-500' : 'bg-gray-200');
                $lineColor = $isPaid ? 'bg-green-500' : ($index < $currentIndex ? 'bg-amber-500' : 'bg-gray-200');
            @endphp
            <div class="flex items-center gap-1">
                <div class="w-5 h-5 rounded-full flex items-center justify-center {{ $color }}">
                    @if($isCompleted)
                        <x-heroicon-s-check class="w-3 h-3 text-white" />
                    @endif
                </div>
                @if($index < 2)
                    <div class="w-6 h-0.5 {{ $lineColor }}"></div>
                @endif
            </div>
        @endforeach
        <span class="ml-2 text-xs font-medium {{ $isPaid ? 'text-green-600' : 'text-amber-600' }}">
            {{ ['issued' => '請求済み', 'sent' => '送付済み', 'paid' => '入金完了'][$invoice->status] ?? $invoice->status }}
        </span>
    </div>

    {{-- 期限超過警告 --}}
    @if(!$isPaid && $invoice->due_date && $invoice->isOverdue())
        <div class="mt-1 text-xs text-red-600 font-medium flex items-center gap-1">
            <x-heroicon-s-exclamation-triangle class="w-3 h-3" />
            期限超過
        </div>
    @endif
</div>
