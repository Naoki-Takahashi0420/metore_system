<x-filament-widgets::widget>
    @if(count($storesWithUnbilledOrders) > 0)
        {{-- 未請求発注がある場合 --}}
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-300 rounded-xl overflow-hidden">
            {{-- ヘッダー --}}
            <div class="px-4 sm:px-6 py-4 bg-gradient-to-r from-green-100 to-emerald-100 border-b-2 border-green-300">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-500 rounded-lg">
                            <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-green-900">未請求の納品済み発注</h2>
                            <p class="text-sm text-green-700">請求書を生成すると、下記の発注がまとめて1枚の請求書になります</p>
                        </div>
                    </div>
                    <span class="self-start sm:self-auto bg-green-600 text-white px-4 py-1.5 rounded-full text-sm font-bold shadow-sm">
                        合計 {{ $totalUnbilledCount }}件
                    </span>
                </div>
            </div>

            {{-- 店舗ごとのセクション --}}
            <div class="p-4 sm:p-6 space-y-4">
                @foreach($storesWithUnbilledOrders as $storeData)
                <div class="bg-white rounded-xl border border-green-200 shadow-sm overflow-hidden">
                    {{-- 店舗ヘッダー --}}
                    <div class="px-4 py-3 bg-gray-50 border-b border-green-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-building-storefront class="w-5 h-5 text-purple-600" />
                            <span class="font-bold text-gray-900">{{ $storeData['store_name'] }}</span>
                            <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded-full text-xs font-bold">
                                {{ $storeData['count'] }}件
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-gray-900 font-bold">
                                ¥{{ number_format($storeData['total']) }}
                            </span>
                        </div>
                    </div>

                    {{-- 発注リスト --}}
                    <div class="p-3 sm:p-4">
                        <div class="space-y-2 mb-4">
                            @foreach($storeData['orders'] as $order)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between py-2 px-3 bg-green-50 rounded-lg border border-green-100 gap-2">
                                <div class="flex items-center gap-3">
                                    <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                                    <div>
                                        <span class="font-medium text-gray-900">{{ $order['order_number'] }}</span>
                                        <span class="text-sm text-gray-500 ml-2">
                                            納品: {{ $order['delivered_at'] ?? '-' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 pl-8 sm:pl-0">
                                    <span class="text-xs text-gray-500">{{ $order['items_count'] }}商品</span>
                                    <span class="font-bold text-gray-900">¥{{ number_format($order['total_amount']) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- 請求書生成ボタン --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-green-200">
                            <div class="text-green-800">
                                <span class="text-sm">この店舗の合計:</span>
                                <span class="text-xl font-bold ml-2">¥{{ number_format($storeData['total']) }}</span>
                                <span class="text-sm text-green-600 ml-1">（税込）</span>
                            </div>
                            <button wire:click="generateInvoiceForStore({{ $storeData['store_id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition shadow-sm">
                                <x-heroicon-o-document-plus class="w-5 h-5" />
                                <span wire:loading.remove wire:target="generateInvoiceForStore({{ $storeData['store_id'] }})">
                                    この{{ $storeData['count'] }}件で請求書を生成
                                </span>
                                <span wire:loading wire:target="generateInvoiceForStore({{ $storeData['store_id'] }})">
                                    生成中...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    @else
        {{-- 未請求発注がない場合 --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
            <div class="p-3 bg-gray-100 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                <x-heroicon-o-clipboard-document-list class="w-8 h-8 text-gray-400" />
            </div>
            <p class="text-gray-600 font-medium">未請求の納品済み発注はありません</p>
            <p class="text-sm text-gray-500 mt-1">納品完了した発注がここに表示されます</p>
        </div>
    @endif
</x-filament-widgets::widget>
