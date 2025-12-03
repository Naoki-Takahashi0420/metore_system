<div class="space-y-6">
    <!-- ヘッダー -->
    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-blue-900">請求明細編集</h3>
                <p class="text-sm text-blue-700 mt-1">
                    明細を入力すると自動的に保存されます。商品、ロイヤリティ、システム使用料など様々な項目を追加できます。
                </p>
            </div>
            @unless($readonly)
            <button 
                wire:click="addRow" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
            >
                + 行追加
            </button>
            @endunless
        </div>
    </div>

    <!-- スプレッドシート風テーブル -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px]">
                <!-- ヘッダー -->
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                            タイプ
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-64">
                            項目・商品名
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                            数量
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                            単価
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                            値引き
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                            小計
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                            税率
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                            税額
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                            合計
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                            備考
                        </th>
                        @unless($readonly)
                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                            削除
                        </th>
                        @endunless
                    </tr>
                </thead>

                <!-- 明細行 -->
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($items as $index => $item)
                    <tr class="hover:bg-gray-50 @if(!$item['id'] && empty($item['description'])) opacity-60 @endif">
                        <!-- タイプ -->
                        <td class="px-3 py-2">
                            @if($readonly)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    {{ $item['type'] === 'product' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $item['type'] === 'royalty' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $item['type'] === 'system_fee' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $item['type'] === 'custom' ? 'bg-gray-100 text-gray-800' : '' }}
                                ">
                                    {{ \App\Models\FcInvoiceItem::getTypes()[$item['type']] ?? $item['type'] }}
                                </span>
                            @else
                                <select 
                                    wire:model.lazy="items.{{ $index }}.type" 
                                    wire:change="setItemType({{ $index }}, $event.target.value)"
                                    class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-xs bg-transparent"
                                >
                                    @foreach(\App\Models\FcInvoiceItem::getTypes() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>

                        <!-- 項目・商品名 -->
                        <td class="px-3 py-2">
                            @if($readonly)
                                <div>
                                    {{ $item['description'] }}
                                    @if($item['fc_product_id'])
                                        <span class="text-xs text-gray-500 block">商品ID: {{ $item['fc_product_id'] }}</span>
                                    @endif
                                </div>
                            @else
                                @if($item['type'] === 'product')
                                    <div class="space-y-1">
                                        <select 
                                            wire:model.lazy="items.{{ $index }}.fc_product_id"
                                            wire:change="selectProduct({{ $index }}, $event.target.value)"
                                            class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent"
                                        >
                                            <option value="">商品を選択...</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">
                                                    {{ $product->name }} (¥{{ number_format($product->price) }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($item['fc_product_id'])
                                            <input 
                                                type="text"
                                                wire:model.lazy="items.{{ $index }}.description"
                                                wire:change="updateItem({{ $index }}, 'description')"
                                                class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent"
                                                placeholder="商品名"
                                            />
                                        @endif
                                    </div>
                                @else
                                    <input 
                                        type="text"
                                        wire:model.lazy="items.{{ $index }}.description"
                                        wire:change="updateItem({{ $index }}, 'description')"
                                        class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent"
                                        placeholder="項目名を入力..."
                                    />
                                @endif
                            @endif
                        </td>

                        <!-- 数量 -->
                        <td class="px-3 py-2 text-right">
                            @if($readonly)
                                {{ $item['quantity'] }}
                            @else
                                <input 
                                    type="number"
                                    wire:model.lazy="items.{{ $index }}.quantity"
                                    wire:change="updateItem({{ $index }}, 'quantity')"
                                    class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent text-right"
                                    min="0"
                                    step="0.01"
                                />
                            @endif
                        </td>

                        <!-- 単価 -->
                        <td class="px-3 py-2 text-right">
                            @if($readonly)
                                ¥{{ number_format($item['unit_price']) }}
                            @else
                                <input 
                                    type="number"
                                    wire:model.lazy="items.{{ $index }}.unit_price"
                                    wire:change="updateItem({{ $index }}, 'unit_price')"
                                    class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent text-right"
                                    min="0"
                                    step="1"
                                />
                            @endif
                        </td>

                        <!-- 値引き -->
                        <td class="px-3 py-2 text-right">
                            @if($readonly)
                                @if($item['discount_amount'] > 0)
                                    -¥{{ number_format($item['discount_amount']) }}
                                @else
                                    -
                                @endif
                            @else
                                <input 
                                    type="number"
                                    wire:model.lazy="items.{{ $index }}.discount_amount"
                                    wire:change="updateItem({{ $index }}, 'discount_amount')"
                                    class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent text-right"
                                    min="0"
                                    step="1"
                                />
                            @endif
                        </td>

                        <!-- 小計 -->
                        <td class="px-3 py-2 text-right font-medium">
                            ¥{{ number_format($item['subtotal']) }}
                        </td>

                        <!-- 税率 -->
                        <td class="px-3 py-2 text-right">
                            @if($readonly)
                                {{ $item['tax_rate'] }}%
                            @else
                                <div class="flex items-center">
                                    <input 
                                        type="number"
                                        wire:model.lazy="items.{{ $index }}.tax_rate"
                                        wire:change="updateItem({{ $index }}, 'tax_rate')"
                                        class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent text-right"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                    />
                                    <span class="text-xs text-gray-500 ml-1">%</span>
                                </div>
                            @endif
                        </td>

                        <!-- 税額 -->
                        <td class="px-3 py-2 text-right">
                            ¥{{ number_format($item['tax_amount']) }}
                        </td>

                        <!-- 合計 -->
                        <td class="px-3 py-2 text-right font-bold bg-blue-50">
                            ¥{{ number_format($item['total_amount']) }}
                        </td>

                        <!-- 備考 -->
                        <td class="px-3 py-2">
                            @if($readonly)
                                {{ $item['notes'] }}
                            @else
                                <input 
                                    type="text"
                                    wire:model.lazy="items.{{ $index }}.notes"
                                    wire:change="updateItem({{ $index }}, 'notes')"
                                    class="w-full border-0 focus:ring-2 focus:ring-blue-500 rounded text-sm bg-transparent"
                                    placeholder="備考..."
                                />
                            @endif
                        </td>

                        <!-- 削除ボタン -->
                        @unless($readonly)
                        <td class="px-3 py-2 text-center">
                            @if($item['id'])
                                <button 
                                    wire:click="removeRow({{ $index }})"
                                    class="text-red-600 hover:text-red-800 transition-colors"
                                    onclick="return confirm('この行を削除しますか？')"
                                >
                                    🗑️
                                </button>
                            @endif
                        </td>
                        @endunless
                    </tr>
                    @endforeach
                </tbody>

                <!-- 合計行 -->
                <tfoot class="bg-gray-100 border-t-2 border-gray-200">
                    <tr>
                        <td colspan="{{ $readonly ? '5' : '6' }}" class="px-3 py-3 text-right font-bold">
                            合計
                        </td>
                        <td class="px-3 py-3 text-right font-bold">
                            ¥{{ number_format($invoice->subtotal) }}
                        </td>
                        <td class="px-3 py-3"></td>
                        <td class="px-3 py-3 text-right font-bold">
                            ¥{{ number_format($invoice->tax_amount) }}
                        </td>
                        <td class="px-3 py-3 text-right font-bold text-lg bg-blue-100">
                            ¥{{ number_format($invoice->total_amount) }}
                        </td>
                        <td colspan="{{ $readonly ? '1' : '2' }}" class="px-3 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- 操作説明 -->
    @unless($readonly)
    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
        <h4 class="font-medium text-yellow-900 mb-2">📝 操作方法</h4>
        <ul class="text-sm text-yellow-800 space-y-1">
            <li>• タイプを選択：商品、ロイヤリティ、システム使用料、その他</li>
            <li>• 商品タイプを選択すると商品一覧から選択可能</li>
            <li>• 数量、単価、値引き額を入力すると自動計算されます</li>
            <li>• 明細を入力すると自動保存され、請求書合計も更新されます</li>
            <li>• 🗑️ ボタンで行を削除できます</li>
        </ul>
    </div>
    @endunless
</div>

<style>
/* セルの境界線をより明確に */
table input, table select {
    border: 1px solid transparent;
}

table input:focus, table select:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

table td {
    border-right: 1px solid #E5E7EB;
}

table td:last-child {
    border-right: none;
}

/* 数値入力フィールドのスピンボタンを非表示 */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}
</style>