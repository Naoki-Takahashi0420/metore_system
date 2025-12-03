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
                        <p class="text-sm font-medium text-gray-900">{{ $product->min_order_quantity }}{{ $product->unit }}</p>
                    </div>
                </div>

                {{-- 税込み価格 --}}
                <div class="p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">税込価格</span>
                        <span class="text-xl font-bold text-blue-700">
                            ¥{{ number_format($product->unit_price * (1 + $product->tax_rate / 100)) }}
                        </span>
                    </div>
                </div>

                {{-- カートに追加ボタン --}}
                <div class="mt-4 flex items-center gap-2">
                    <input
                        type="number"
                        min="{{ $product->min_order_quantity }}"
                        value="{{ $product->min_order_quantity }}"
                        id="quantity-{{ $product->id }}"
                        class="w-20 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                    <span class="text-sm text-gray-600">{{ $product->unit }}</span>
                    <button
                        wire:click="addToCart({{ $product->id }}, document.getElementById('quantity-{{ $product->id }}').value)"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition flex items-center justify-center gap-2"
                        {{ $product->stock_quantity <= 0 ? 'disabled' : '' }}
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        @if($product->stock_quantity > 0)
                            <span>カートに追加</span>
                        @else
                            <span>在庫なし</span>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>