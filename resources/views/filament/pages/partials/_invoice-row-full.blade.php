<div class="p-4 sm:p-6">
    {{-- ヘッダー行 --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-0 mb-4">
        <div>
            <span class="text-base sm:text-lg font-semibold text-gray-900">{{ $invoice->invoice_number }}</span>
            <div class="text-xs sm:text-sm text-gray-500 mt-1 sm:mt-0 sm:ml-2 sm:inline">
                {{ $invoice->billing_period_start->format('Y/m/d') }} - {{ $invoice->billing_period_end->format('Y/m/d') }}
            </div>
        </div>
        <div class="sm:text-right">
            <p class="text-lg font-bold text-gray-900">¥{{ number_format($invoice->total_amount) }}</p>
            @if($invoice->due_date)
                <p class="text-xs sm:text-sm {{ $invoice->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                    支払期限: {{ $invoice->due_date->format('Y/m/d') }}
                    @if($invoice->isOverdue())
                        <span class="text-red-600">(期限超過)</span>
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
    <div class="relative mb-4">
        {{-- 背景ライン --}}
        <div class="absolute top-4 left-0 right-0 h-1 bg-gray-200 rounded"></div>
        {{-- 進捗ライン --}}
        @php
            $invoiceProgressPercent = match($invoiceCurrentIndex) {
                0 => 0,
                1 => 50,
                2 => 100,
                default => 0,
            };
        @endphp
        <div class="absolute top-4 left-0 h-1 {{ $isPaid ? 'bg-green-500' : 'bg-amber-500' }} rounded transition-all duration-500" style="width: {{ $invoiceProgressPercent }}%"></div>

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
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all
                            {{ $isCompleted ? 'bg-green-500 border-green-500' : '' }}
                            {{ $isCurrent ? 'bg-green-500 border-green-500 ring-4 ring-green-100' : '' }}
                            {{ $isFuture ? 'bg-white border-gray-300' : '' }}">
                    @else
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all
                            {{ $isCompleted ? 'bg-amber-500 border-amber-500' : '' }}
                            {{ $isCurrent ? 'bg-amber-500 border-amber-500 ring-4 ring-amber-100' : '' }}
                            {{ $isFuture ? 'bg-white border-gray-300' : '' }}">
                    @endif
                        @if($isCompleted || $isCurrent)
                            @if($step['icon'] === 'document-text')
                                <x-heroicon-s-document-text class="w-4 h-4 text-white" />
                            @elseif($step['icon'] === 'envelope')
                                <x-heroicon-s-envelope class="w-4 h-4 text-white" />
                            @else
                                <x-heroicon-s-check-circle class="w-4 h-4 text-white" />
                            @endif
                        @else
                            <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                        @endif
                    </div>
                    {{-- ラベル --}}
                    <span class="mt-2 text-xs font-medium
                        {{ ($isCompleted || $isCurrent) ? ($isPaid ? 'text-green-700' : 'text-amber-700') : 'text-gray-400' }}">
                        {{ $step['label'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- アクションエリア --}}
    @if($invoice->status === 'paid')
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center gap-3">
                <x-heroicon-s-check-circle class="w-6 h-6 text-green-500 flex-shrink-0" />
                <div>
                    <p class="font-medium text-green-800">お支払い完了</p>
                    <p class="text-sm text-green-600">ありがとうございました</p>
                </div>
            </div>
            <a href="{{ route('fc-invoice.pdf', $invoice) }}"
               target="_blank"
               class="w-full sm:w-auto inline-flex items-center justify-center gap-1 px-4 py-3 sm:py-2 bg-white border border-green-300 hover:bg-green-50 text-green-700 rounded-lg text-sm transition">
                <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                PDF表示
            </a>
        </div>
    @else
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-center gap-3">
                <x-heroicon-s-banknotes class="w-6 h-6 text-amber-500 flex-shrink-0" />
                <div>
                    <p class="font-medium text-amber-800">お支払いをお願いします</p>
                    <p class="text-sm text-amber-600">未払い額: ¥{{ number_format($invoice->outstanding_amount) }}</p>
                </div>
            </div>
            <a href="{{ route('fc-invoice.pdf', $invoice) }}"
               target="_blank"
               class="w-full sm:w-auto inline-flex items-center justify-center gap-1 px-4 py-3 sm:py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-bold sm:font-medium transition">
                <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                請求書を確認
            </a>
        </div>
    @endif
</div>
