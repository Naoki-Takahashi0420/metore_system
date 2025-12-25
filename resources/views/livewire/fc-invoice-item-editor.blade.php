<div class="bg-white rounded-lg border">
    <!-- ヘッダー情報 -->
    <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center">
        <div class="flex items-center gap-6 text-sm">
            <span><strong>小計:</strong> ¥{{ number_format($invoice->subtotal) }}</span>
            <span><strong>税:</strong> ¥{{ number_format($invoice->tax_amount) }}</span>
            <span class="text-lg font-bold text-blue-600"><strong>合計:</strong> ¥{{ number_format($invoice->total_amount) }}</span>
        </div>
        @unless($readonly)
        <div class="flex gap-2">
            <button wire:click="addRow" class="bg-gray-500 text-white px-3 py-1 rounded text-sm hover:bg-gray-600">
                + 空行追加
            </button>
        </div>
        @endunless
    </div>

    <!-- 説明 -->
    @unless($readonly)
    <div class="px-4 py-2 bg-blue-50 border-b text-xs text-blue-700">
        入力内容は自動保存されます。ブラウザを閉じても編集内容は保持されます。
    </div>
    @endunless

    <!-- テーブル -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-gray-600">
                <tr>
                    <th class="px-3 py-2 text-left w-24">種別</th>
                    <th class="px-3 py-2 text-left">項目名</th>
                    <th class="px-3 py-2 text-right w-20">数量</th>
                    <th class="px-3 py-2 text-right w-24">単価</th>
                    <th class="px-3 py-2 text-right w-24">金額</th>
                    @unless($readonly)
                    <th class="px-3 py-2 w-12"></th>
                    @endunless
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($items as $index => $item)
                <tr class="hover:bg-gray-50">
                    <!-- 種別 -->
                    <td class="px-3 py-2">
                        @if($readonly)
                            <span class="text-xs px-2 py-1 rounded bg-gray-100">
                                {{ \App\Models\FcInvoiceItem::getTypes()[$item['type']] ?? $item['type'] }}
                            </span>
                        @else
                            <select wire:model.lazy="items.{{ $index }}.type" wire:change="setItemType({{ $index }}, $event.target.value)" class="w-full border rounded px-2 py-1 text-xs">
                                @foreach(\App\Models\FcInvoiceItem::getTypes() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </td>

                    <!-- 項目名 -->
                    <td class="px-3 py-2">
                        @if($readonly)
                            {{ $item['description'] }}
                        @else
                            @if($item['type'] === 'product')
                                <select wire:model.lazy="items.{{ $index }}.fc_product_id" wire:change="selectProduct({{ $index }}, $event.target.value)" class="w-full border rounded px-2 py-1">
                                    <option value="">商品を選択...</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }} (¥{{ number_format($product->price) }})</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" wire:model.lazy="items.{{ $index }}.description" wire:change="updateItem({{ $index }}, 'description')" class="w-full border rounded px-2 py-1" placeholder="項目名">
                            @endif
                        @endif
                    </td>

                    <!-- 数量 -->
                    <td class="px-3 py-2 text-right">
                        @if($readonly)
                            {{ $item['quantity'] }}
                        @else
                            <input type="number" wire:model.lazy="items.{{ $index }}.quantity" wire:change="updateItem({{ $index }}, 'quantity')" class="w-16 border rounded px-2 py-1 text-right" min="0" step="1">
                        @endif
                    </td>

                    <!-- 単価 -->
                    <td class="px-3 py-2 text-right">
                        @if($readonly)
                            ¥{{ number_format($item['unit_price']) }}
                        @else
                            <input type="number" wire:model.lazy="items.{{ $index }}.unit_price" wire:change="updateItem({{ $index }}, 'unit_price')" class="w-20 border rounded px-2 py-1 text-right" min="0">
                        @endif
                    </td>

                    <!-- 金額 -->
                    <td class="px-3 py-2 text-right font-medium">
                        ¥{{ number_format($item['total_amount']) }}
                    </td>

                    <!-- 削除 -->
                    @unless($readonly)
                    <td class="px-3 py-2 text-center">
                        @if($item['id'])
                            <button wire:click="removeRow({{ $index }})" onclick="return confirm('削除しますか？')" class="text-red-500 hover:text-red-700">×</button>
                        @endif
                    </td>
                    @endunless
                </tr>
                @endforeach

                @if(count($items) === 0)
                <tr>
                    <td colspan="{{ $readonly ? 5 : 6 }}" class="px-3 py-8 text-center text-gray-400">
                        明細がありません
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- テンプレートボタン -->
    @unless($readonly)
    @if($templates->count() > 0)
    <div class="px-4 py-3 border-t bg-gray-50">
        <div class="text-xs text-gray-500 mb-2">テンプレートから追加:</div>
        <div class="flex flex-wrap gap-2">
            @foreach($templates as $template)
                <button
                    wire:click="addFromTemplate({{ $template->id }})"
                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 rounded hover:bg-blue-50 hover:border-blue-300 text-sm transition"
                >
                    <span>{{ $template->name }}</span>
                    <span class="text-gray-400">¥{{ number_format($template->unit_price) }}</span>
                </button>
            @endforeach
        </div>
    </div>
    @endif
    @endunless
</div>
