<div class="p-4 hover:bg-gray-50">
    {{-- ヘッダー行 --}}
    <div class="flex items-start justify-between mb-4">
        <div>
            <span class="text-base font-semibold text-gray-900">{{ $invoice->invoice_number }}</span>
            <span class="ml-2 text-sm text-gray-500">
                {{ $invoice->billing_period_start->format('m/d') }} - {{ $invoice->billing_period_end->format('m/d') }}
            </span>
        </div>
        <div class="text-right">
            <p class="text-base font-bold text-gray-900">¥{{ number_format($invoice->total_amount) }}</p>
            @if($invoice->due_date)
                <p class="text-xs {{ $invoice->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                    期限: {{ $invoice->due_date->format('m/d') }}
                    @if($invoice->isOverdue())
                        (超過)
                    @endif
                </p>
            @endif
        </div>
    </div>

    {{-- Amazon風ステータス表示 --}}
    @php
        $invoiceSteps = [
            'issued' => ['label' => '請求書発行', 'icon' => 'document-text'],
            'sent' => ['label' => '送付済み', 'icon' => 'envelope'],
            'paid' => ['label' => 'お支払い完了', 'icon' => 'check-circle'],
        ];
        $invoiceStepKeys = array_keys($invoiceSteps);
        $invoiceCurrentIndex = array_search($invoice->status, $invoiceStepKeys);
        if ($invoiceCurrentIndex === false) $invoiceCurrentIndex = 0;
        $isPaid = $invoice->status === 'paid';
    @endphp

    {{-- プログレスバー --}}
    <div class="relative">
        {{-- 背景ライン --}}
        <div class="absolute top-3 left-0 right-0 h-0.5 bg-gray-200 rounded"></div>
        {{-- 進捗ライン --}}
        @php
            $invoiceProgressPercent = match($invoiceCurrentIndex) {
                0 => 0,
                1 => 50,
                2 => 100,
                default => 0,
            };
        @endphp
        <div class="absolute top-3 left-0 h-0.5 {{ $isPaid ? 'bg-green-500' : 'bg-amber-500' }} rounded transition-all duration-500" style="width: {{ $invoiceProgressPercent }}%"></div>

        {{-- ステップ --}}
        <div class="relative flex justify-between">
            @foreach($invoiceSteps as $stepKey => $step)
                @php
                    $stepIndex = array_search($stepKey, $invoiceStepKeys);
                    $isCompleted = $stepIndex < $invoiceCurrentIndex;
                    $isCurrent = $stepIndex === $invoiceCurrentIndex;
                    $isFuture = $stepIndex > $invoiceCurrentIndex;
                @endphp
                <div class="flex flex-col items-center">
                    {{-- ステップ円 --}}
                    @if($isPaid)
                        <div class="w-6 h-6 rounded-full flex items-center justify-center border-2 transition-all
                            {{ $isCompleted ? 'bg-green-500 border-green-500' : '' }}
                            {{ $isCurrent ? 'bg-green-500 border-green-500 ring-2 ring-green-100' : '' }}
                            {{ $isFuture ? 'bg-white border-gray-300' : '' }}">
                    @else
                        <div class="w-6 h-6 rounded-full flex items-center justify-center border-2 transition-all
                            {{ $isCompleted ? 'bg-amber-500 border-amber-500' : '' }}
                            {{ $isCurrent ? 'bg-amber-500 border-amber-500 ring-2 ring-amber-100' : '' }}
                            {{ $isFuture ? 'bg-white border-gray-300' : '' }}">
                    @endif
                        @if($isCompleted || $isCurrent)
                            @if($step['icon'] === 'document-text')
                                <x-heroicon-s-document-text class="w-3 h-3 text-white" />
                            @elseif($step['icon'] === 'envelope')
                                <x-heroicon-s-envelope class="w-3 h-3 text-white" />
                            @else
                                <x-heroicon-s-check-circle class="w-3 h-3 text-white" />
                            @endif
                        @else
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                        @endif
                    </div>
                    {{-- ラベル --}}
                    <span class="mt-1 text-xs font-medium
                        {{ ($isCompleted || $isCurrent) ? ($isPaid ? 'text-green-700' : 'text-amber-700') : 'text-gray-400' }}">
                        {{ $step['label'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- アクションエリア --}}
    <div class="mt-3">
        @if($invoice->status === 'paid')
            <div class="flex items-center justify-between p-2 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center gap-2">
                    <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                    <span class="text-xs font-medium text-green-800">お支払い完了</span>
                </div>
                <a href="{{ route('fc-invoice.pdf', $invoice) }}"
                   target="_blank"
                   class="text-xs text-green-600 hover:text-green-800">
                    PDF
                </a>
            </div>
        @else
            <div class="flex items-center justify-between p-2 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="flex items-center gap-2">
                    <x-heroicon-s-banknotes class="w-4 h-4 text-amber-500" />
                    <span class="text-xs font-medium text-amber-800">未払 ¥{{ number_format($invoice->outstanding_amount) }}</span>
                </div>
                <a href="{{ route('fc-invoice.pdf', $invoice) }}"
                   target="_blank"
                   class="text-xs bg-amber-500 text-white px-2 py-1 rounded hover:bg-amber-600">
                    確認
                </a>
            </div>
        @endif
    </div>
</div>
