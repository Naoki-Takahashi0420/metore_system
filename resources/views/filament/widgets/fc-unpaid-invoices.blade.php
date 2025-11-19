<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            未払いの請求書
        </x-slot>

        <x-slot name="description">
            支払期限が近い、または期限切れの請求書
        </x-slot>

        @if($this->getInvoices()->count() > 0)
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                請求書番号
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                請求先FC店舗
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                ステータス
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                請求金額
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                未払い金額
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                支払期限
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                操作
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-white/5">
                        @foreach($this->getInvoices() as $invoice)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $invoice->invoice_number }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ $invoice->fcStore->name }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                        {{ $invoice->status === 'issued' ? '発行済み' : '送付済み' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                    ¥{{ number_format($invoice->total_amount) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-bold text-danger-600 dark:text-danger-400">
                                    ¥{{ number_format($invoice->outstanding_amount) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm
                                    {{ $invoice->isOverdue() ? 'text-danger-600 dark:text-danger-400 font-bold' : ($invoice->due_date->diffInDays(now()) <= 7 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-600 dark:text-gray-400') }}">
                                    {{ $invoice->due_date->format('Y/m/d') }}
                                    @if($invoice->isOverdue())
                                        <span class="ml-1">⚠️</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <a href="{{ route('filament.admin.resources.fc-invoices.view', $invoice) }}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                                        詳細
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                未払いの請求書はありません
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
