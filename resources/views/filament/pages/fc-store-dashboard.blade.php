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
                    <p class="text-sm text-gray-500">進行中の発注{{ $isSuperAdmin ?? false ? '(全店舗)' : '' }}</p>
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
                    <p class="text-sm text-gray-500">未払い請求額{{ $isSuperAdmin ?? false ? '(全店舗)' : '' }}</p>
                    <p class="text-2xl font-bold {{ $unpaidTotal > 0 ? 'text-amber-600' : 'text-green-600' }}">
                        ¥{{ number_format($unpaidTotal) }}
                    </p>
                </div>
            </div>
        </div>

        @if(!($isSuperAdmin ?? false))
        {{-- カタログへ（FC店舗ユーザーのみ） --}}
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
        @else
        {{-- super_admin用：全店舗数 --}}
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <x-heroicon-o-building-storefront class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <p class="text-sm text-gray-500">FC加盟店数</p>
                    <p class="text-2xl font-bold text-gray-900">{{ count($storesData ?? []) }}店舗</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if($isSuperAdmin ?? false)
        {{-- super_admin: 店舗ごとのデータ表示 --}}
        @forelse($storesData ?? [] as $storeData)
            <div class="mb-8 border-2 border-gray-200 rounded-xl overflow-hidden">
                {{-- 店舗ヘッダー --}}
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-building-storefront class="w-6 h-6" />
                            <h2 class="text-xl font-bold">{{ $storeData['store']->name }}</h2>
                        </div>
                        <div class="flex gap-4 text-sm">
                            <span class="bg-white/20 px-3 py-1 rounded-full">
                                発注 {{ $storeData['pendingOrders'] }}件
                            </span>
                            <span class="bg-white/20 px-3 py-1 rounded-full">
                                未払 ¥{{ number_format($storeData['unpaidTotal']) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- 発注一覧（コンパクト） --}}
                        <div class="border rounded-lg">
                            <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                    <x-heroicon-o-shopping-cart class="w-4 h-4 text-blue-500" />
                                    発注状況
                                </h3>
                            </div>
                            <div class="divide-y max-h-64 overflow-y-auto">
                                @forelse($storeData['orders']->take(5) as $order)
                                    @include('filament.pages.partials._order-row-compact', ['order' => $order])
                                @empty
                                    <div class="p-4 text-center text-gray-500 text-sm">発注なし</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- 請求書一覧（コンパクト） --}}
                        <div class="border rounded-lg">
                            <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                                    <x-heroicon-o-document-text class="w-4 h-4 text-amber-500" />
                                    請求書
                                </h3>
                            </div>
                            <div class="divide-y max-h-64 overflow-y-auto">
                                @forelse($storeData['invoices']->take(5) as $invoice)
                                    @include('filament.pages.partials._invoice-row-compact', ['invoice' => $invoice])
                                @empty
                                    <div class="p-4 text-center text-gray-500 text-sm">請求書なし</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border p-12 text-center">
                <x-heroicon-o-building-storefront class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                <p class="text-gray-500">FC加盟店がありません</p>
            </div>
        @endforelse
    @else
        {{-- 通常のFC店舗ユーザー用表示 --}}
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
                    @include('filament.pages.partials._order-row-full', ['order' => $order])
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
                    @include('filament.pages.partials._invoice-row-full', ['invoice' => $invoice])
                @empty
                    <div class="p-12 text-center text-gray-500">
                        <x-heroicon-o-document-text class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                        <p>請求書がありません</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</x-filament-panels::page>
