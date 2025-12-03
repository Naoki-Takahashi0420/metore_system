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
                                    @if($category->products_count)
                                        <span class="text-xs text-gray-500">({{ $category->products_count }})</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- 商品グリッド --}}
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

                {{-- カテゴリごとのグループ表示 --}}
                @if(isset($groupedProducts) && $groupedProducts)
                    @foreach($groupedProducts as $group)
                        <div class="mb-8">
                            {{-- カテゴリヘッダー --}}
                            <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-500 px-6 py-3 mb-4 rounded-r-lg">
                                <h2 class="text-xl font-bold text-blue-900">{{ $group['category']->name }}</h2>
                                @if($group['category']->description)
                                    <p class="text-sm text-blue-700 mt-1">{{ $group['category']->description }}</p>
                                @endif
                            </div>
                            
                            {{-- カテゴリ内の商品 --}}
                            <div class="grid md:grid-cols-2 gap-4">
                                @foreach($group['products'] as $product)
                                    @include('livewire.partials.fc-product-card', ['product' => $product])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                
                {{-- 通常表示（検索時またはカテゴリ選択時） --}}
                @else
                    <div class="grid md:grid-cols-2 gap-4">
                        @forelse($products as $product)
                            @include('livewire.partials.fc-product-card', ['product' => $product])
                        @empty
                            <div class="col-span-2 text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <p class="mt-4 text-lg font-medium text-gray-900">商品が見つかりませんでした</p>
                                <p class="mt-2 text-sm text-gray-500">検索条件を変更してください</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- ページネーション --}}
                    @if($products->hasPages())
                        <div class="mt-8">
                            {{ $products->links() }}
                        </div>
                    @endif
                @endif
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
                            @forelse($cart as $item)
                                <div class="mb-4 pb-4 border-b">
                                    <div class="flex items-start gap-4">
                                        {{-- 商品画像 --}}
                                        <div class="w-20 h-20 bg-gray-100 rounded-lg flex-shrink-0">
                                            @if($item['image_path'])
                                                <img src="{{ Storage::url($item['image_path']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover rounded-lg">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-900">{{ $item['name'] }}</h4>
                                            <p class="text-xs text-gray-500 mt-1">{{ $item['sku'] }}</p>
                                            <p class="text-sm text-gray-600 mt-2">
                                                ¥{{ number_format($item['unit_price']) }} × {{ $item['quantity'] }}{{ $item['unit'] }}
                                            </p>
                                            <p class="text-sm font-medium text-gray-900 mt-1">
                                                小計: ¥{{ number_format($item['unit_price'] * $item['quantity']) }}
                                            </p>
                                        </div>

                                        <button wire:click="removeFromCart({{ $item['product_id'] }})" class="text-red-500 hover:text-red-700">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                    <p class="mt-4 text-gray-500">カートが空です</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- フッター --}}
                        @if(count($cart) > 0)
                            <div class="px-6 py-4 bg-gray-50 border-t">
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">小計</span>
                                        <span class="font-medium">¥{{ number_format($cartSubtotal) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">消費税</span>
                                        <span class="font-medium">¥{{ number_format($cartTaxTotal) }}</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                        <span>合計</span>
                                        <span>¥{{ number_format($cartTotal) }}</span>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-2">
                                    <button wire:click="submitOrder" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition">
                                        発注する
                                    </button>
                                    <button wire:click="clearCart" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition">
                                        カートをクリア
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>