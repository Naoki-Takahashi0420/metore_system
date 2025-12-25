<x-filament-panels::page>
    {{-- サマリーカード --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- 進行中の発注 --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <x-heroicon-o-truck class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <p class="text-sm text-gray-500">進行中の発注</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $pendingOrders }}件</p>
                </div>
            </div>
        </div>

        {{-- 未払い請求 --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 {{ $unpaidTotal > 0 ? 'bg-amber-100' : 'bg-green-100' }} rounded-lg">
                    <x-heroicon-o-banknotes class="w-6 h-6 {{ $unpaidTotal > 0 ? 'text-amber-600' : 'text-green-600' }}" />
                </div>
                <div>
                    <p class="text-sm text-gray-500">未払い請求額</p>
                    <p class="text-2xl font-bold {{ $unpaidTotal > 0 ? 'text-amber-600' : 'text-green-600' }}">
                        ¥{{ number_format($unpaidTotal) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- カタログへ --}}
        <a href="{{ route('filament.admin.resources.fc-orders.catalog') }}"
           class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white hover:from-blue-600 hover:to-blue-700 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-100">商品を発注する</p>
                    <p class="text-xl font-bold">カタログを開く</p>
                </div>
                <x-heroicon-o-arrow-right class="w-8 h-8" />
            </div>
        </a>
    </div>

    {{-- 発注一覧 --}}
    <div class="bg-white rounded-xl shadow-sm border mb-6">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <x-heroicon-o-shopping-cart class="w-5 h-5 text-blue-500" />
                発注状況
            </h2>
            <a href="{{ route('filament.admin.resources.fc-orders.index') }}"
               class="text-sm text-blue-600 hover:text-blue-800">すべて見る →</a>
        </div>

        <div class="divide-y">
            @forelse($orders as $order)
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <span class="text-lg font-semibold text-gray-900">{{ $order->order_number }}</span>
                            <span class="ml-2 text-sm text-gray-500">
                                {{ $order->created_at->format('Y/m/d') }}
                            </span>
                        </div>
                        <span class="text-lg font-bold text-gray-900">¥{{ number_format($order->total_amount) }}</span>
                    </div>

                    {{-- ステータス進捗バー --}}
                    <div class="flex items-center gap-2 mb-4">
                        @php
                            $statuses = ['ordered' => '発注済み', 'shipped' => '発送中', 'delivered' => '納品完了'];
                            $currentIndex = array_search($order->status, array_keys($statuses));
                            if ($order->status === 'draft') $currentIndex = -1;
                            if ($order->status === 'cancelled') $currentIndex = -2;
                        @endphp

                        @if($order->status === 'cancelled')
                            <div class="flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm">
                                <x-heroicon-o-x-circle class="w-4 h-4" />
                                キャンセル
                            </div>
                        @elseif($order->status === 'draft')
                            <div class="flex items-center gap-2 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                                下書き
                            </div>
                        @else
                            @foreach($statuses as $status => $label)
                                @php
                                    $stepIndex = array_search($status, array_keys($statuses));
                                    $isActive = $stepIndex <= $currentIndex;
                                    $isCurrent = $stepIndex === $currentIndex;
                                @endphp

                                @if($stepIndex > 0)
                                    <div class="flex-1 h-1 {{ $isActive ? 'bg-green-500' : 'bg-gray-200' }} rounded"></div>
                                @endif

                                <div class="flex items-center gap-1 px-3 py-1 rounded-full text-sm
                                    {{ $isCurrent ? 'bg-green-100 text-green-700 font-medium' : ($isActive ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400') }}">
                                    @if($isActive)
                                        <x-heroicon-o-check-circle class="w-4 h-4" />
                                    @else
                                        <x-heroicon-o-clock class="w-4 h-4" />
                                    @endif
                                    {{ $label }}
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- 商品リスト --}}
                    <div class="text-sm text-gray-600">
                        @foreach($order->items->take(3) as $item)
                            <span class="inline-block bg-gray-100 rounded px-2 py-1 mr-2 mb-1">
                                {{ $item->product_name }} ×{{ $item->quantity }}
                            </span>
                        @endforeach
                        @if($order->items->count() > 3)
                            <span class="text-gray-400">他{{ $order->items->count() - 3 }}件</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-gray-500">
                    <x-heroicon-o-shopping-cart class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                    <p>発注履歴がありません</p>
                    <a href="{{ route('filament.admin.resources.fc-orders.catalog') }}"
                       class="mt-4 inline-block text-blue-600 hover:text-blue-800">
                        カタログから発注する →
                    </a>
                </div>
            @endforelse
        </div>
    </div>

    {{-- 請求書一覧 --}}
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <x-heroicon-o-document-text class="w-5 h-5 text-amber-500" />
                請求書
            </h2>
            <a href="{{ route('filament.admin.resources.fc-invoices.index') }}"
               class="text-sm text-blue-600 hover:text-blue-800">すべて見る →</a>
        </div>

        <div class="divide-y">
            @forelse($invoices as $invoice)
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <span class="text-lg font-semibold text-gray-900">{{ $invoice->invoice_number }}</span>
                            <span class="ml-2 text-sm text-gray-500">
                                {{ $invoice->billing_period_start->format('Y/m/d') }} - {{ $invoice->billing_period_end->format('Y/m/d') }}
                            </span>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900">¥{{ number_format($invoice->total_amount) }}</p>
                            @if($invoice->due_date)
                                <p class="text-sm {{ $invoice->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                    支払期限: {{ $invoice->due_date->format('Y/m/d') }}
                                    @if($invoice->isOverdue())
                                        <span class="text-red-600">(期限超過)</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- ステータス進捗バー --}}
                    <div class="flex items-center gap-2 mb-4">
                        @php
                            $invoiceStatuses = ['issued' => '請求書発行', 'paid' => '入金完了'];
                            $currentInvIndex = array_search($invoice->status, array_keys($invoiceStatuses));
                            if ($invoice->status === 'draft') $currentInvIndex = -1;
                            if ($invoice->status === 'sent') $currentInvIndex = 0; // sent is same as issued for display
                            if ($invoice->status === 'cancelled') $currentInvIndex = -2;
                        @endphp

                        @if($invoice->status === 'cancelled')
                            <div class="flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm">
                                <x-heroicon-o-x-circle class="w-4 h-4" />
                                キャンセル
                            </div>
                        @elseif($invoice->status === 'draft')
                            <div class="flex items-center gap-2 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm">
                                <x-heroicon-o-pencil class="w-4 h-4" />
                                作成中
                            </div>
                            <div class="flex-1 h-1 bg-gray-200 rounded"></div>
                            <div class="flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-400">
                                <x-heroicon-o-clock class="w-4 h-4" />
                                請求書発行
                            </div>
                            <div class="flex-1 h-1 bg-gray-200 rounded"></div>
                            <div class="flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-400">
                                <x-heroicon-o-clock class="w-4 h-4" />
                                入金完了
                            </div>
                        @else
                            @foreach($invoiceStatuses as $status => $label)
                                @php
                                    $stepIndex = array_search($status, array_keys($invoiceStatuses));
                                    $isActive = $stepIndex <= $currentInvIndex;
                                    $isCurrent = $stepIndex === $currentInvIndex;
                                @endphp

                                @if($stepIndex > 0)
                                    <div class="flex-1 h-1 {{ $isActive ? 'bg-green-500' : 'bg-gray-200' }} rounded"></div>
                                @endif

                                <div class="flex items-center gap-1 px-3 py-1 rounded-full text-sm
                                    {{ $isCurrent ? 'bg-amber-100 text-amber-700 font-medium' : ($isActive ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400') }}">
                                    @if($isActive && !$isCurrent)
                                        <x-heroicon-o-check-circle class="w-4 h-4" />
                                    @elseif($isCurrent)
                                        @if($status === 'issued')
                                            <x-heroicon-o-banknotes class="w-4 h-4" />
                                        @else
                                            <x-heroicon-o-check-circle class="w-4 h-4" />
                                        @endif
                                    @else
                                        <x-heroicon-o-clock class="w-4 h-4" />
                                    @endif
                                    {{ $label }}
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- アクションボタン --}}
                    <div class="flex items-center gap-3">
                        @if(in_array($invoice->status, ['issued', 'sent', 'paid']))
                            <a href="{{ route('fc-invoice.pdf', $invoice) }}"
                               target="_blank"
                               class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm transition">
                                <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                PDF表示
                            </a>
                        @endif

                        @if($invoice->status === 'issued' || $invoice->status === 'sent')
                            <span class="text-sm text-amber-600">
                                お支払いをお願いします（未払い: ¥{{ number_format($invoice->outstanding_amount) }}）
                            </span>
                        @elseif($invoice->status === 'paid')
                            <span class="text-sm text-green-600 flex items-center gap-1">
                                <x-heroicon-o-check-circle class="w-4 h-4" />
                                お支払い完了
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-gray-500">
                    <x-heroicon-o-document-text class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                    <p>請求書がありません</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
