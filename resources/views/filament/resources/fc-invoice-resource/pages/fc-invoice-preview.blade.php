<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 月選択 --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <label class="text-sm font-medium text-gray-700">対象月：</label>
                    <input type="month" 
                           wire:model.live="month" 
                           wire:change="updateMonth"
                           class="rounded-md border-gray-300 text-sm"
                           value="{{ $month }}">
                </div>
                <div class="text-sm text-gray-500">
                    ※ 納品完了した発注が請求対象となります
                </div>
            </div>
        </div>

        {{-- 請求書プレビュー --}}
        @forelse($previewData as $data)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                {{-- ヘッダー --}}
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $data['store']->name }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                請求予定額: <span class="font-semibold text-gray-900">¥{{ number_format($data['total']) }}</span>
                                （税込）
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($data['has_existing_invoice'])
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    発行済み
                                </span>
                                @if($data['existing_invoice'])
                                    <a href="{{ route('filament.admin.resources.fc-invoices.edit', $data['existing_invoice']) }}" 
                                       class="text-sm text-primary-600 hover:text-primary-800 font-medium">
                                        請求書番号: {{ $data['existing_invoice']->invoice_number }}
                                    </a>
                                @endif
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    未発行
                                </span>
                                @if(auth()->user()->hasRole('super_admin') || (auth()->user()->store && auth()->user()->store->isHeadquarters()))
                                    <button wire:click="generateInvoice({{ $data['store']->id }})"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        請求書を発行
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 明細テーブル --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    項目
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    数量
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    単価
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    小計
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    消費税
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    合計
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    備考
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($data['items'] as $item)
                                <tr class="{{ $item['is_custom'] ?? false ? 'bg-blue-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $item['description'] }}
                                        @if($item['is_custom'] ?? false)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                固定
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        {{ number_format($item['quantity']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        ¥{{ number_format($item['unit_price']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        ¥{{ number_format($item['subtotal']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        ¥{{ number_format($item['tax_amount']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                        ¥{{ number_format($item['total']) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @if($item['order_number'])
                                            <span class="text-xs">
                                                発注: {{ $item['order_number'] }}<br>
                                                納品: {{ $item['delivered_at'] }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-right text-sm font-medium text-gray-900">
                                    合計
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                    ¥{{ number_format($data['subtotal']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                    ¥{{ number_format($data['tax_amount']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-base font-bold text-gray-900 text-right">
                                    ¥{{ number_format($data['total']) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-4 text-sm font-medium text-gray-900">請求対象がありません</h3>
                <p class="mt-1 text-sm text-gray-500">
                    選択された月に納品完了した発注がありません
                </p>
            </div>
        @endforelse

        {{-- 説明文 --}}
        <div class="bg-blue-50 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">
                        請求書プレビューについて
                    </h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>リアルタイムで当月の請求予定額を確認できます</li>
                            <li>納品完了した発注が自動的に集計されます</li>
                            <li>部分発送の場合は発送済み数量のみが対象となります</li>
                            <li>ロイヤリティ等の固定費用は自動的に追加されます</li>
                            <li>「請求書を発行」ボタンで正式な請求書を作成できます</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>