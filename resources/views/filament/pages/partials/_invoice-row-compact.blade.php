<div class="p-4 hover:bg-gray-50">
    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ $invoice->invoice_number }}</p>
            <p class="text-xs text-gray-500">
                {{ $invoice->billing_period_start->format('m/d') }} - {{ $invoice->billing_period_end->format('m/d') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-900">¥{{ number_format($invoice->total_amount) }}</span>
            @php
                $statusColors = [
                    'draft' => 'bg-gray-100 text-gray-600',
                    'issued' => 'bg-amber-100 text-amber-700',
                    'sent' => 'bg-blue-100 text-blue-700',
                    'paid' => 'bg-green-100 text-green-700',
                ];
                $statusLabels = [
                    'draft' => '作成中',
                    'issued' => '発行済み',
                    'sent' => '送付済み',
                    'paid' => '入金完了',
                ];
            @endphp
            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$invoice->status] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $statusLabels[$invoice->status] ?? $invoice->status }}
            </span>
        </div>
    </div>
    {{-- 支払期限（未払いの場合のみ） --}}
    @if($invoice->status !== 'paid' && $invoice->due_date)
        <p class="mt-1 text-xs {{ $invoice->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
            期限: {{ $invoice->due_date->format('Y/m/d') }}
            @if($invoice->isOverdue())
                (超過)
            @endif
        </p>
    @endif
</div>
