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
               class="text-sm text-blue-600 hover:text-blue-800">すべて見る</a>
        </div>

        <div class="divide-y">
            @forelse($orders as $order)
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
            @empty
                <div class="p-12 text-center text-gray-500">
                    <x-heroicon-o-shopping-cart class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                    <p>発注履歴がありません</p>
                    <a href="{{ route('filament.admin.resources.fc-orders.catalog') }}"
                       class="mt-4 inline-block text-blue-600 hover:text-blue-800">
                        カタログから発注する
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
               class="text-sm text-blue-600 hover:text-blue-800">すべて見る</a>
        </div>

        <div class="divide-y">
            @forelse($invoices as $invoice)
                <div class="p-6">
                    {{-- ヘッダー行 --}}
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
                        <div class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <x-heroicon-s-check-circle class="w-6 h-6 text-green-500" />
                                <div>
                                    <p class="font-medium text-green-800">お支払い完了</p>
                                    <p class="text-sm text-green-600">ありがとうございました</p>
                                </div>
                            </div>
                            <a href="{{ route('fc-invoice.pdf', $invoice) }}"
                               target="_blank"
                               class="inline-flex items-center gap-1 px-4 py-2 bg-white border border-green-300 hover:bg-green-50 text-green-700 rounded-lg text-sm transition">
                                <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                PDF表示
                            </a>
                        </div>
                    @else
                        <div class="flex items-center justify-between p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <x-heroicon-s-banknotes class="w-6 h-6 text-amber-500" />
                                <div>
                                    <p class="font-medium text-amber-800">お支払いをお願いします</p>
                                    <p class="text-sm text-amber-600">未払い額: ¥{{ number_format($invoice->outstanding_amount) }}</p>
                                </div>
                            </div>
                            <a href="{{ route('fc-invoice.pdf', $invoice) }}"
                               target="_blank"
                               class="inline-flex items-center gap-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition">
                                <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                請求書を確認
                            </a>
                        </div>
                    @endif
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
