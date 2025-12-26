<x-filament-panels::page>
    {{-- ヘルプ情報パネル --}}
    <div x-data="{ open: false }" class="mb-6">
        {{-- ヘルプトグルボタン --}}
        <button @click="open = !open"
                class="w-full bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between hover:from-blue-100 hover:to-indigo-100 transition">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-500 rounded-full">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-white" />
                </div>
                <div class="text-left">
                    <p class="font-bold text-blue-900">FC発注システムの使い方</p>
                    <p class="text-sm text-blue-600">タップして発注フロー・ステータスの説明を見る</p>
                </div>
            </div>
            <x-heroicon-o-chevron-down class="w-5 h-5 text-blue-500 transition-transform" x-bind:class="{ 'rotate-180': open }" />
        </button>

        {{-- ヘルプコンテンツ --}}
        <div x-show="open" x-collapse class="mt-3 bg-white border border-gray-200 rounded-xl overflow-hidden">
            {{-- 発注フロー --}}
            <div class="p-4 sm:p-6 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <x-heroicon-o-arrow-path class="w-5 h-5 text-blue-500" />
                    発注から請求書までの流れ
                </h3>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-0 text-sm">
                    <div class="flex items-center gap-2 bg-blue-100 text-blue-800 px-3 py-2 rounded-lg">
                        <span class="font-bold">1</span>
                        <span>カタログから発注</span>
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400 mx-2 hidden sm:block" />
                    <x-heroicon-o-arrow-down class="w-4 h-4 text-gray-400 mx-2 sm:hidden" />
                    <div class="flex items-center gap-2 bg-amber-100 text-amber-800 px-3 py-2 rounded-lg">
                        <span class="font-bold">2</span>
                        <span>本部が発送</span>
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400 mx-2 hidden sm:block" />
                    <x-heroicon-o-arrow-down class="w-4 h-4 text-gray-400 mx-2 sm:hidden" />
                    <div class="flex items-center gap-2 bg-green-100 text-green-800 px-3 py-2 rounded-lg">
                        <span class="font-bold">3</span>
                        <span>受取確認をタップ</span>
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400 mx-2 hidden sm:block" />
                    <x-heroicon-o-arrow-down class="w-4 h-4 text-gray-400 mx-2 sm:hidden" />
                    <div class="flex items-center gap-2 bg-purple-100 text-purple-800 px-3 py-2 rounded-lg">
                        <span class="font-bold">4</span>
                        <span>請求書が届く</span>
                    </div>
                </div>
            </div>

            {{-- ステータス説明 --}}
            <div class="p-4 sm:p-6 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <x-heroicon-o-tag class="w-5 h-5 text-blue-500" />
                    発注ステータスの意味
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white rounded-full text-xs">発</span>
                        <div>
                            <p class="font-bold text-gray-900">発注済み</p>
                            <p class="text-gray-600">本部で発送準備中です</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-amber-500 text-white rounded-full text-xs">送</span>
                        <div>
                            <p class="font-bold text-gray-900">発送済み</p>
                            <p class="text-gray-600">届いたら<span class="text-green-600 font-bold">「受取確認」</span>をタップ！</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-green-500 text-white rounded-full text-xs">完</span>
                        <div>
                            <p class="font-bold text-gray-900">納品完了</p>
                            <p class="text-gray-600">月末に請求書がまとまります</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-red-500 text-white rounded-full text-xs">×</span>
                        <div>
                            <p class="font-bold text-gray-900">キャンセル</p>
                            <p class="text-gray-600">発注がキャンセルされました</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 次にやるべきこと --}}
            <div class="p-4 sm:p-6 bg-gradient-to-r from-green-50 to-emerald-50">
                <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                    <x-heroicon-o-hand-raised class="w-5 h-5 text-green-500" />
                    次にやるべきこと
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" />
                        <span><span class="font-bold text-blue-600">「カタログを開く」</span>から商品を選んで発注</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" />
                        <span>商品が届いたら<span class="font-bold text-green-600">「受取確認」</span>ボタンをタップ</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" />
                        <span>請求書が届いたらお支払い → 本部が入金確認</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- サマリーカード --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4 mb-6">
        {{-- 進行中の発注 --}}
        <div class="bg-white rounded-xl shadow-sm border p-4 sm:p-6">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="p-2 sm:p-3 bg-blue-100 rounded-lg">
                    <x-heroicon-o-truck class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" />
                </div>
                <div>
                    <p class="text-xs sm:text-sm text-gray-500">進行中の発注</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">{{ $pendingOrders }}件</p>
                </div>
            </div>
        </div>

        {{-- 未払い請求 --}}
        <div class="bg-white rounded-xl shadow-sm border p-4 sm:p-6">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="p-2 sm:p-3 {{ $unpaidTotal > 0 ? 'bg-amber-100' : 'bg-green-100' }} rounded-lg">
                    <x-heroicon-o-banknotes class="w-5 h-5 sm:w-6 sm:h-6 {{ $unpaidTotal > 0 ? 'text-amber-600' : 'text-green-600' }}" />
                </div>
                <div>
                    <p class="text-xs sm:text-sm text-gray-500">未払い請求</p>
                    <p class="text-lg sm:text-2xl font-bold {{ $unpaidTotal > 0 ? 'text-amber-600' : 'text-green-600' }}">
                        ¥{{ number_format($unpaidTotal) }}
                    </p>
                </div>
            </div>
        </div>

        @if(!($isSuperAdmin ?? false))
        {{-- カタログへ（FC店舗ユーザーのみ） --}}
        <a href="{{ route('filament.admin.resources.fc-orders.catalog') }}"
           class="col-span-2 md:col-span-1 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-sm p-4 sm:p-6 text-white hover:from-blue-600 hover:to-blue-700 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm text-blue-100">商品を発注する</p>
                    <p class="text-lg sm:text-xl font-bold">カタログを開く</p>
                </div>
                <x-heroicon-o-arrow-right class="w-6 h-6 sm:w-8 sm:h-8" />
            </div>
        </a>
        @else
        {{-- super_admin用：全店舗数 --}}
        <div class="col-span-2 md:col-span-1 bg-white rounded-xl shadow-sm border p-4 sm:p-6">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="p-2 sm:p-3 bg-purple-100 rounded-lg">
                    <x-heroicon-o-building-storefront class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" />
                </div>
                <div>
                    <p class="text-xs sm:text-sm text-gray-500">FC加盟店数</p>
                    <p class="text-xl sm:text-2xl font-bold text-gray-900">{{ count($storesData ?? []) }}店舗</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if($isSuperAdmin ?? false)
    {{-- super_admin用：一括アクションバー --}}
    <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-heroicon-o-document-plus class="w-5 h-5 text-gray-500" />
                <span class="font-medium text-gray-700">請求書管理</span>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                <button wire:click="generateMonthlyInvoices"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition">
                    <x-heroicon-o-document-plus class="w-4 h-4" />
                    <span wire:loading.remove wire:target="generateMonthlyInvoices">全店舗の月次請求書を生成</span>
                    <span wire:loading wire:target="generateMonthlyInvoices">生成中...</span>
                </button>
                <a href="{{ route('filament.admin.resources.fc-invoices.index') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition">
                    <x-heroicon-o-document-text class="w-4 h-4" />
                    請求書一覧
                </a>
            </div>
        </div>
    </div>
    @endif

    @if($isSuperAdmin ?? false)
        {{-- super_admin: 店舗ごとのデータ表示 --}}
        @forelse($storesData ?? [] as $storeData)
            <div class="mb-6 sm:mb-8 border-2 border-gray-200 rounded-xl overflow-hidden">
                {{-- 店舗ヘッダー --}}
                <div class="bg-gray-100 border-b-2 border-purple-500 px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <x-heroicon-o-building-storefront class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" />
                            <h2 class="text-lg sm:text-xl font-bold text-gray-900">{{ $storeData['store']->name }}</h2>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                            <span class="bg-blue-100 text-blue-700 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                                発注 {{ $storeData['pendingOrders'] }}件
                            </span>
                            <span class="bg-amber-100 text-amber-700 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                                未払 ¥{{ number_format($storeData['unpaidTotal']) }}
                            </span>
                            <button wire:click="generateInvoiceForStore({{ $storeData['store']->id }})"
                                    wire:loading.attr="disabled"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-2 sm:px-3 py-1 rounded-lg text-xs sm:text-sm font-medium transition inline-flex items-center gap-1">
                                <x-heroicon-o-document-plus class="w-3 h-3 sm:w-4 sm:h-4" />
                                請求書生成
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6 bg-white">
                    {{-- 未請求の納品済み発注（請求書生成プレビュー） --}}
                    @if($storeData['unbilledOrders']->count() > 0)
                    <div class="mb-4 sm:mb-6 border-2 border-green-300 rounded-lg bg-green-50">
                        <div class="px-3 sm:px-4 py-2 sm:py-3 border-b border-green-300 bg-green-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <h3 class="font-semibold text-green-800 flex items-center gap-2 text-sm sm:text-base">
                                <x-heroicon-o-clipboard-document-list class="w-4 h-4 sm:w-5 sm:h-5 text-green-600" />
                                未請求の納品済み発注
                            </h3>
                            <span class="bg-green-600 text-white px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-bold self-start sm:self-auto">
                                {{ $storeData['unbilledOrders']->count() }}件
                            </span>
                        </div>
                        <div class="p-3 sm:p-4">
                            <div class="space-y-2 mb-4">
                                @foreach($storeData['unbilledOrders'] as $unbilledOrder)
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-2 px-3 bg-white rounded border border-green-200 gap-2">
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <x-heroicon-s-check-circle class="w-4 h-4 sm:w-5 sm:h-5 text-green-500 flex-shrink-0" />
                                        <div>
                                            <span class="font-medium text-gray-900 text-sm sm:text-base">{{ $unbilledOrder->order_number }}</span>
                                            <span class="text-xs sm:text-sm text-gray-500 ml-1 sm:ml-2">
                                                納品: {{ $unbilledOrder->delivered_at?->format('m/d') ?? '-' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right pl-6 sm:pl-0">
                                        <span class="font-bold text-gray-900 text-sm sm:text-base">¥{{ number_format($unbilledOrder->total_amount) }}</span>
                                        <span class="text-xs text-gray-500 ml-1">{{ $unbilledOrder->items->count() }}商品</span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-green-300">
                                <div class="text-green-800">
                                    <span class="text-xs sm:text-sm">合計:</span>
                                    <span class="text-lg sm:text-xl font-bold ml-1 sm:ml-2">¥{{ number_format($storeData['unbilledTotal']) }}</span>
                                    <span class="text-xs sm:text-sm text-green-600 ml-1">（税込）</span>
                                </div>
                                <button wire:click="generateInvoiceForStore({{ $storeData['store']->id }})"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50"
                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 sm:px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition">
                                    <x-heroicon-o-document-plus class="w-4 h-4 sm:w-5 sm:h-5" />
                                    <span wire:loading.remove wire:target="generateInvoiceForStore({{ $storeData['store']->id }})">
                                        この{{ $storeData['unbilledOrders']->count() }}件で請求書を生成
                                    </span>
                                    <span wire:loading wire:target="generateInvoiceForStore({{ $storeData['store']->id }})">
                                        生成中...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="mb-4 sm:mb-6 border border-gray-200 rounded-lg bg-gray-50 p-3 sm:p-4 text-center text-gray-500">
                        <x-heroicon-o-clipboard-document-list class="w-6 h-6 sm:w-8 sm:h-8 mx-auto mb-2 text-gray-400" />
                        <p class="text-sm">未請求の納品済み発注はありません</p>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        {{-- 発注一覧（コンパクト） --}}
                        <div class="border rounded-lg">
                            <div class="px-3 sm:px-4 py-2 sm:py-3 border-b bg-gray-50 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900 flex items-center gap-2 text-sm sm:text-base">
                                    <x-heroicon-o-shopping-cart class="w-4 h-4 text-blue-500" />
                                    発注状況
                                </h3>
                            </div>
                            <div class="divide-y max-h-80 sm:max-h-96 overflow-y-auto">
                                @forelse($storeData['orders']->take(5) as $order)
                                    @include('filament.pages.partials._order-row-compact', ['order' => $order, 'showActions' => true])
                                @empty
                                    <div class="p-4 text-center text-gray-500 text-sm">発注なし</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- 請求書一覧（コンパクト） --}}
                        <div class="border rounded-lg">
                            <div class="px-3 sm:px-4 py-2 sm:py-3 border-b bg-gray-50 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900 flex items-center gap-2 text-sm sm:text-base">
                                    <x-heroicon-o-document-text class="w-4 h-4 text-amber-500" />
                                    請求書
                                </h3>
                            </div>
                            <div class="divide-y max-h-80 sm:max-h-96 overflow-y-auto">
                                @forelse($storeData['invoices']->take(5) as $invoice)
                                    @include('filament.pages.partials._invoice-row-compact', ['invoice' => $invoice, 'showActions' => true])
                                @empty
                                    <div class="p-4 text-center text-gray-500 text-sm">請求書なし</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border p-8 sm:p-12 text-center">
                <x-heroicon-o-building-storefront class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4 text-gray-300" />
                <p class="text-gray-500">FC加盟店がありません</p>
            </div>
        @endforelse
    @else
        {{-- 通常のFC店舗ユーザー用表示 --}}
        {{-- 発注一覧 --}}
        <div class="bg-white rounded-xl shadow-sm border mb-4 sm:mb-6">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b flex items-center justify-between">
                <h2 class="text-base sm:text-lg font-bold text-gray-900 flex items-center gap-2">
                    <x-heroicon-o-shopping-cart class="w-4 h-4 sm:w-5 sm:h-5 text-blue-500" />
                    発注状況
                </h2>
                <a href="{{ route('filament.admin.resources.fc-orders.index') }}"
                   class="text-xs sm:text-sm text-blue-600 hover:text-blue-800">すべて見る</a>
            </div>

            <div class="divide-y">
                @forelse($orders as $order)
                    @include('filament.pages.partials._order-row-full', ['order' => $order])
                @empty
                    <div class="p-8 sm:p-12 text-center text-gray-500">
                        <x-heroicon-o-shopping-cart class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-4 text-gray-300" />
                        <p>発注履歴がありません</p>
                        <a href="{{ route('filament.admin.resources.fc-orders.catalog') }}"
                           class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition">
                            <x-heroicon-o-shopping-cart class="w-4 h-4" />
                            カタログから発注する
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- 請求書一覧 --}}
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b flex items-center justify-between">
                <h2 class="text-base sm:text-lg font-bold text-gray-900 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-4 h-4 sm:w-5 sm:h-5 text-amber-500" />
                    請求書
                </h2>
                <a href="{{ route('filament.admin.resources.fc-invoices.index') }}"
                   class="text-xs sm:text-sm text-blue-600 hover:text-blue-800">すべて見る</a>
            </div>

            <div class="divide-y">
                @forelse($invoices as $invoice)
                    @include('filament.pages.partials._invoice-row-full', ['invoice' => $invoice])
                @empty
                    <div class="p-8 sm:p-12 text-center text-gray-500">
                        <x-heroicon-o-document-text class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-4 text-gray-300" />
                        <p>請求書がありません</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</x-filament-panels::page>
