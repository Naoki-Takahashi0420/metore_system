<div class="min-h-screen bg-gray-50">
    {{-- ヘッダー --}}
    <div class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-bold text-gray-900">FC商品カタログ</h1>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">本部から発注</span>
                </div>
                <button wire:click="toggleCart" class="relative px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>カート</span>
                    @if($cartItemCount > 0)
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">
                            {{ $cartItemCount }}
                        </span>
                    @endif
                </button>
            </div>
        </div>
    </div>

    {{-- メインコンテンツ --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-4 lg:gap-8">
            {{-- サイドバー --}}
            <div class="hidden lg:block">
                {{-- 検索 --}}
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">商品検索</h3>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="商品名・SKUで検索..."
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                </div>

                {{-- カテゴリ --}}
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">カテゴリ</h3>
                    <ul class="space-y-1">
                        <li>
                            <button
                                wire:click="$set('selectedCategory', null)"
                                class="w-full text-left px-3 py-2 rounded-lg text-sm transition {{ is_null($selectedCategory) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}"
                            >
                                すべて
                            </button>
                        </li>
                        @foreach($categories as $category)
                            <li>
                                <button
                                    wire:click="$set('selectedCategory', {{ $category->id }})"
                                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition {{ $selectedCategory == $category->id ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}"
                                >
                                    {{ $category->name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- 商品一覧 --}}
            <div class="lg:col-span-3">
                @if(session()->has('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session()->has('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="grid md:grid-cols-2 gap-4">
                    @forelse($products as $product)
                        <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition overflow-hidden">
                            {{-- 商品画像 --}}
                            <div class="w-full bg-gray-100" style="aspect-ratio: 4/3;">
                                @if($product->image_path)
                                    <img src="{{ Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <svg class="h-20 w-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            {{-- 商品情報 --}}
                            <div class="p-6">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 font-medium mb-1">{{ $product->sku }}</p>
                                        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $product->name }}</h3>

                                        @if($product->description)
                                            <p class="text-sm text-gray-600 mb-4">{{ $product->description }}</p>
                                        @endif

                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <p class="text-xs text-gray-500">税抜価格</p>
                                                <p class="text-sm font-medium text-gray-900">¥{{ number_format($product->unit_price) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">税率</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $product->tax_rate }}%</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">在庫</p>
                                                <p class="text-sm font-medium {{ $product->stock_quantity > 10 ? 'text-green-600' : ($product->stock_quantity > 0 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $product->stock_quantity }}{{ $product->unit }}
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500">最小発注数</p>
                                                <p class="text-sm font-medium text-gray-900">{{ $product->min_order_quantity }}{{ $product->unit }}〜</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ml-6 text-right">
                                        <p class="text-sm text-gray-500 mb-1">税込価格</p>
                                        <p class="text-3xl font-bold text-blue-600">¥{{ number_format($product->tax_included_price) }}</p>
                                    </div>
                                </div>

                                {{-- カートに追加 --}}
                                <div class="flex items-center gap-3 mt-4 pt-4 border-t">
                                    <div class="flex items-center gap-2" x-data="{ qty: {{ $product->min_order_quantity }} }">
                                        <label class="text-sm font-medium text-gray-700">数量:</label>
                                        <input
                                            type="number"
                                            min="{{ $product->min_order_quantity }}"
                                            max="{{ $product->stock_quantity }}"
                                            x-model="qty"
                                            class="w-24 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                        <span class="text-sm text-gray-500">{{ $product->unit }}</span>
                                        <button
                                            @click="$wire.addToCart({{ $product->id }}, qty)"
                                            class="ml-4 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition flex items-center gap-2"
                                            {{ $product->stock_quantity <= 0 ? 'disabled' : '' }}
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            {{ $product->stock_quantity > 0 ? 'カートに追加' : '在庫なし' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-16">
                            <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="mt-4 text-lg font-medium text-gray-900">商品が見つかりませんでした</p>
                            <p class="mt-2 text-sm text-gray-500">検索条件を変更してください</p>
                        </div>
                    @endforelse
                </div>

                {{-- ページネーション --}}
                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- カートサイドバー --}}
    @if($showCart)
        <div class="fixed inset-0 z-50 overflow-hidden">
            <div class="absolute inset-0 bg-black bg-opacity-50" wire:click="toggleCart"></div>
            <div class="fixed inset-y-0 right-0 max-w-full flex">
                <div class="w-screen max-w-lg">
                    <div class="h-full flex flex-col bg-white shadow-2xl">
                        {{-- ヘッダー --}}
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-xl font-bold">カート</h2>
                                    <p class="text-sm text-blue-100 mt-1">{{ count($cart) }}商品</p>
                                </div>
                                <button wire:click="toggleCart" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2 transition">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- カート内容 --}}
                        <div class="flex-1 overflow-y-auto px-6 py-4">
                            @if(empty($cart))
                                <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                                    <svg class="h-24 w-24 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="text-lg font-medium">カートは空です</p>
                                    <p class="text-sm mt-1">商品をカートに追加してください</p>
                                </div>
                            @else
                                <div class="space-y-3">
                                    @foreach($cart as $productId => $item)
                                        <div class="flex gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                            <div class="w-16 h-16 flex-shrink-0 bg-white rounded-lg overflow-hidden border">
                                                @if($item['image_path'])
                                                    <img src="{{ Storage::url($item['image_path']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-medium text-gray-900 truncate">{{ $item['name'] }}</h3>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $item['sku'] }}</p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <input
                                                        type="number"
                                                        wire:model.blur="cart.{{ $productId }}.quantity"
                                                        min="1"
                                                        class="w-16 px-2 py-1 text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                                    >
                                                    <span class="text-sm text-gray-600">× ¥{{ number_format($item['unit_price'] * (1 + $item['tax_rate'] / 100)) }}</span>
                                                </div>
                                                <div class="mt-1 text-base font-bold text-blue-600">
                                                    ¥{{ number_format($item['unit_price'] * $item['quantity'] * (1 + $item['tax_rate'] / 100)) }}
                                                </div>
                                            </div>
                                            <button wire:click="removeFromCart({{ $productId }})" class="self-start text-gray-400 hover:text-red-600 transition p-1">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- フッター --}}
                        @if(!empty($cart))
                            <div class="border-t bg-gray-50 px-6 py-4 space-y-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">小計（税抜）</span>
                                        <span class="font-medium text-gray-900">¥{{ number_format($cartSubtotal) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">消費税</span>
                                        <span class="font-medium text-gray-900">¥{{ number_format($cartTaxTotal) }}</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2">
                                        <span class="text-gray-900">合計</span>
                                        <span class="text-blue-600">¥{{ number_format($cartTotal) }}</span>
                                    </div>
                                </div>

                                <button
                                    @click="$wire.submitOrder()"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold text-lg transition shadow-lg"
                                >
                                    発注する
                                </button>

                                <button
                                    wire:click="clearCart"
                                    class="w-full bg-white hover:bg-gray-50 text-gray-700 px-6 py-2 rounded-lg text-sm font-medium border transition"
                                >
                                    カートをクリア
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
