<x-filament-panels::page>
    <div class="space-y-6">
        <!-- 精算情報入力フォーム -->
        <form wire:submit.prevent="performClosing">
            {{ $this->form }}
        </form>

        <!-- 売上サマリー -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">売上サマリー</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600">
                        ¥{{ number_format($this->salesData['total_sales'] ?? 0) }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">総売上</div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600">
                        {{ $this->salesData['transaction_count'] ?? 0 }}件
                    </div>
                    <div class="text-sm text-gray-600 mt-1">取引件数</div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600">
                        {{ $this->salesData['customer_count'] ?? 0 }}名
                    </div>
                    <div class="text-sm text-gray-600 mt-1">来店客数</div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600">
                        ¥{{ number_format(($this->salesData['total_sales'] ?? 0) / max(($this->salesData['customer_count'] ?? 1), 1)) }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">客単価</div>
                </div>
            </div>

            <!-- 支払方法別売上 -->
            <div class="border-t pt-4">
                <h3 class="text-md font-medium text-gray-900 mb-3">支払方法別売上</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">現金</span>
                        <span class="font-semibold">¥{{ number_format($this->salesData['cash_sales'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">カード</span>
                        <span class="font-semibold">¥{{ number_format($this->salesData['card_sales'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">電子マネー</span>
                        <span class="font-semibold">¥{{ number_format($this->salesData['digital_sales'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- 現金計算 -->
            <div class="border-t pt-4 mt-4">
                <h3 class="text-md font-medium text-gray-900 mb-3">現金計算</h3>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">釣銭準備金</span>
                        <span>¥{{ number_format($this->openingCash ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">現金売上</span>
                        <span>¥{{ number_format($this->salesData['cash_sales'] ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center font-semibold border-t pt-2">
                        <span>予定現金残高</span>
                        <span class="text-lg">¥{{ number_format($this->salesData['expected_cash'] ?? 0) }}</span>
                    </div>
                    @if($this->actualCash)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">実際の現金残高</span>
                            <span>¥{{ number_format($this->actualCash) }}</span>
                        </div>
                        <div class="flex justify-between items-center font-semibold">
                            <span>差異</span>
                            @php
                                $difference = $this->actualCash - ($this->salesData['expected_cash'] ?? 0);
                            @endphp
                            <span class="text-lg {{ $difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $difference >= 0 ? '+' : '' }}¥{{ number_format($difference) }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- 本日の未計上予約 -->
        @if(count($this->unposted) > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">本日の未計上予約</h2>
                    <button
                        wire:click="postAll"
                        class="px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        一括計上
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">時間</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">顧客</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">メニュー</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">種別</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">支払方法</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">金額</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->unposted as $res)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-3 text-sm text-gray-900">{{ $res['time'] }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-900">{{ $res['customer_name'] }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-900">{{ $res['menu_name'] }}</td>
                                    <td class="px-3 py-3">
                                        @if($res['source'] === 'subscription')
                                            <span class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">サブスク</span>
                                        @elseif($res['source'] === 'ticket')
                                            <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded">回数券</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded">スポット</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @if($res['source'] === 'spot')
                                            <select
                                                wire:model="rowState.{{ $res['id'] }}.payment_method"
                                                class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                            >
                                                <option value="cash">現金</option>
                                                <option value="credit_card">クレジットカード</option>
                                                <option value="debit_card">デビットカード</option>
                                                <option value="paypay">PayPay</option>
                                                <option value="line_pay">LINE Pay</option>
                                                <option value="other">その他</option>
                                            </select>
                                        @else
                                            <span class="text-xs text-gray-500">その他</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @if($res['source'] === 'spot')
                                            <input
                                                type="number"
                                                wire:model="rowState.{{ $res['id'] }}.amount"
                                                class="block w-24 text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                min="0"
                                                step="1"
                                            />
                                        @else
                                            <span class="text-xs text-gray-500">¥0</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <button
                                            wire:click="openEditor({{ $res['id'] }})"
                                            class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:border-gray-400 transition-colors"
                                        >
                                            編集
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-sm text-gray-500">
                    <p class="flex items-center">
                        <span class="inline-block w-3 h-3 bg-blue-100 rounded mr-2"></span>
                        <span class="font-semibold text-blue-700 mr-1">青（サブスク）</span>・
                        <span class="inline-block w-3 h-3 bg-green-100 rounded mx-2"></span>
                        <span class="font-semibold text-green-700 mr-1">緑（回数券）</span>
                        は0円で計上されます。
                        <span class="inline-block w-3 h-3 bg-gray-100 rounded mx-2"></span>
                        <span class="font-semibold text-gray-700 mr-1">灰（スポット）</span>
                        は支払方法と金額を入力してください。
                    </p>
                </div>
            </div>
        @endif

        <!-- スタッフ別売上 -->
        @if(isset($this->salesData['sales_by_staff']) && count($this->salesData['sales_by_staff']) > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-md font-medium text-gray-900 mb-3">スタッフ別売上</h3>
                <div class="space-y-2">
                    @foreach($this->salesData['sales_by_staff'] as $staffSales)
                        <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                            <span class="text-sm">{{ $staffSales['name'] }}</span>
                            <div class="text-right">
                                <span class="font-semibold">¥{{ number_format($staffSales['amount']) }}</span>
                                <span class="text-xs text-gray-500 ml-2">({{ $staffSales['count'] }}件)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- 売れ筋メニュー -->
        @if(isset($this->salesData['top_menus']) && count($this->salesData['top_menus']) > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-md font-medium text-gray-900 mb-3">売れ筋メニュー TOP10</h3>
                <div class="space-y-2">
                    @foreach($this->salesData['top_menus'] as $index => $menu)
                        <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                            <div class="flex items-center">
                                <span class="text-sm font-semibold text-gray-500 mr-3">{{ $index + 1 }}.</span>
                                <span class="text-sm">{{ $menu->item_name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-semibold">¥{{ number_format($menu->total) }}</span>
                                <span class="text-xs text-gray-500 ml-2">({{ $menu->count }}個)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- アクションボタン -->
        <div class="flex justify-end space-x-3">
            <x-filament::button
                wire:click="performClosing"
                color="success"
                size="lg"
                :disabled="!$this->actualCash"
            >
                日次精算を実行
            </x-filament::button>
        </div>
    </div>

    <!-- 編集ドロワー -->
    @if($this->editorOpen)
        <!-- オーバーレイ（ぼかし効果付き） -->
        <div class="fixed inset-0 bg-black bg-opacity-40 backdrop-blur-md z-40"
             wire:click="closeEditor">
        </div>

        <!-- ドロワーパネル -->
        <div class="fixed inset-y-0 right-0 w-full max-w-2xl bg-white shadow-2xl overflow-y-auto z-50"
             style="animation: slideIn 0.3s ease-out;">

            <style>
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                    }
                    to {
                        transform: translateX(0);
                    }
                }
            </style>
                    <!-- ヘッダー -->
                    <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 z-10">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">売上編集</h2>
                            <button wire:click="closeEditor" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- コンテンツ -->
                    <div class="px-6 py-6 space-y-6">
                        <!-- 予約情報 -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">予約情報</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">予約番号:</span>
                                    <span class="font-medium">{{ $this->editorData['reservation']['reservation_number'] ?? '' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">顧客:</span>
                                    <span class="font-medium">{{ $this->editorData['reservation']['customer_name'] ?? '' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">時刻:</span>
                                    <span class="font-medium">{{ $this->editorData['reservation']['time'] ?? '' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">種別:</span>
                                    <span>
                                        @if($this->editorData['payment_source'] === 'subscription')
                                            <span class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">サブスク</span>
                                        @elseif($this->editorData['payment_source'] === 'ticket')
                                            <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded">回数券</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded">スポット</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- サービス明細 -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 mb-3">サービス明細</h3>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="grid grid-cols-12 gap-3">
                                    <div class="col-span-6">
                                        <label class="block text-xs text-gray-500 mb-1">サービス名</label>
                                        <input type="text"
                                               wire:model="editorData.service_item.name"
                                               class="block w-full text-sm border-gray-300 rounded-md"
                                               disabled>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-xs text-gray-500 mb-1">単価</label>
                                        <input type="number"
                                               wire:model="editorData.service_item.price"
                                               wire:change="updateCalculation"
                                               class="block w-full text-sm border-gray-300 rounded-md"
                                               min="0"
                                               @if($this->editorData['payment_source'] !== 'spot') disabled @endif>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-xs text-gray-500 mb-1">数量</label>
                                        <input type="number"
                                               wire:model="editorData.service_item.quantity"
                                               wire:change="updateCalculation"
                                               class="block w-full text-sm border-gray-300 rounded-md"
                                               min="1"
                                               disabled>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 物販明細 -->
                        @if($this->editorData['payment_source'] === 'spot')
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-medium text-gray-900">物販明細</h3>
                                    <button
                                        wire:click="addProductItem"
                                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:border-gray-400 transition-colors"
                                    >
                                        + 追加
                                    </button>
                                </div>

                                @forelse($this->editorData['product_items'] ?? [] as $index => $item)
                                    <div class="border border-gray-200 rounded-lg p-4 mb-3">
                                        <div class="grid grid-cols-12 gap-3">
                                            <div class="col-span-5">
                                                <label class="block text-xs text-gray-500 mb-1">商品名</label>
                                                <input type="text"
                                                       wire:model="editorData.product_items.{{ $index }}.name"
                                                       class="block w-full text-sm border-gray-300 rounded-md"
                                                       placeholder="商品名を入力">
                                            </div>
                                            <div class="col-span-3">
                                                <label class="block text-xs text-gray-500 mb-1">単価</label>
                                                <input type="number"
                                                       wire:model="editorData.product_items.{{ $index }}.price"
                                                       wire:change="updateCalculation"
                                                       class="block w-full text-sm border-gray-300 rounded-md"
                                                       min="0">
                                            </div>
                                            <div class="col-span-2">
                                                <label class="block text-xs text-gray-500 mb-1">数量</label>
                                                <input type="number"
                                                       wire:model="editorData.product_items.{{ $index }}.quantity"
                                                       wire:change="updateCalculation"
                                                       class="block w-full text-sm border-gray-300 rounded-md"
                                                       min="1">
                                            </div>
                                            <div class="col-span-2 flex items-end">
                                                <button wire:click="removeProductItem({{ $index }})"
                                                        class="text-red-600 hover:text-red-800 text-sm">
                                                    削除
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 italic">物販はありません</p>
                                @endforelse
                            </div>
                        @endif

                        <!-- 支払方法 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">支払方法</label>
                            <select wire:model="editorData.payment_method"
                                    class="block w-full text-sm border-gray-300 rounded-md"
                                    @if($this->editorData['payment_source'] !== 'spot') disabled @endif>
                                @foreach($this->editorData['payment_methods_list'] ?? ['現金', 'その他'] as $method)
                                    <option value="{{ $method }}">{{ $method }}</option>
                                @endforeach
                            </select>
                            @if($this->editorData['payment_source'] !== 'spot')
                                <p class="mt-1 text-xs text-gray-500">※ サブスク/回数券は支払方法固定です</p>
                            @endif
                        </div>

                        <!-- 合計 -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">小計</span>
                                    <span class="font-medium">¥{{ number_format((int)($this->editorData['subtotal'] ?? 0)) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">税額</span>
                                    <span class="font-medium">¥0</span>
                                </div>
                                <div class="flex justify-between text-base font-semibold border-t pt-2">
                                    <span>合計</span>
                                    <span class="text-primary-600">¥{{ number_format((int)($this->editorData['total'] ?? 0)) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- フッター（固定） -->
                    <div class="sticky bottom-0 bg-white border-t border-gray-200 px-6 py-4">
                        <div class="flex justify-end space-x-3">
                            <button
                                wire:click="closeEditor"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:border-gray-400 transition-colors"
                            >
                                キャンセル
                            </button>
                            <button
                                wire:click="saveSaleWithItems"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 transition-colors shadow-sm"
                            >
                                決定
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>