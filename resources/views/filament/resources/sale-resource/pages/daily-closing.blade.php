<x-filament-panels::page>
    <div class="space-y-6">
        <!-- 店舗・日付選択 -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-4">
                    <label class="text-sm font-medium text-gray-700">店舗：</label>
                    <select
                        wire:model.live="selectedStoreId"
                        class="block w-64 text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        @if(auth()->user()->hasRole('super_admin'))
                            <option value="">全店舗</option>
                        @endif
                        @foreach($this->getAccessibleStores() as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-4">
                    <label class="text-sm font-medium text-gray-700">日付：</label>
                    <div class="flex items-center gap-2">
                        <button
                            wire:click="previousDay"
                            type="button"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span class="ml-1">前の日</span>
                        </button>
                        <input
                            type="date"
                            wire:model.live="closingDate"
                            class="block w-48 text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        />
                        <button
                            wire:click="nextDay"
                            type="button"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            <span class="mr-1">次の日</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 売上サマリー -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">売上サマリー</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600">
                        ¥{{ number_format((int)($this->salesData['total_sales'] ?? 0)) }}
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
                        ¥{{ number_format((int)(($this->salesData['total_sales'] ?? 0) / max(($this->salesData['customer_count'] ?? 1), 1))) }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">客単価</div>
                </div>
            </div>

            <!-- 利用内訳（件数） -->
            <div class="border-t pt-4 mt-4">
                <h3 class="text-md font-medium text-gray-900 mb-3">利用内訳</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                        <span class="text-sm text-gray-600">サブスク利用</span>
                        <span class="font-semibold">
                            {{ $this->salesData['subscription_count'] ?? 0 }}件
                            @if(($this->salesData['subscription_with_products_count'] ?? 0) > 0)
                                <span class="text-xs text-gray-500 ml-1">
                                    (うち物販: {{ $this->salesData['subscription_with_products_count'] }}件、¥{{ number_format((int)($this->salesData['subscription_with_products_amount'] ?? 0)) }})
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="text-sm text-gray-600">回数券利用</span>
                        <span class="font-semibold">
                            {{ $this->salesData['ticket_count'] ?? 0 }}件
                            @if(($this->salesData['ticket_with_products_count'] ?? 0) > 0)
                                <span class="text-xs text-gray-500 ml-1">
                                    (うち物販: {{ $this->salesData['ticket_with_products_count'] }}件、¥{{ number_format((int)($this->salesData['ticket_with_products_amount'] ?? 0)) }})
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">スポット</span>
                        <span class="font-semibold">{{ $this->salesData['spot_count'] ?? 0 }}件</span>
                    </div>
                </div>
            </div>

            <!-- 支払方法別売上 -->
            @if(!empty($this->salesData['sales_by_payment_method']) && count($this->salesData['sales_by_payment_method']) > 0)
                <div class="border-t pt-4">
                    <h3 class="text-md font-medium text-gray-900 mb-3">支払方法別売上</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($this->salesData['sales_by_payment_method'] as $method)
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                <span class="text-sm text-gray-600">{{ $method['name'] }}</span>
                                <span class="font-semibold">¥{{ number_format((int)($method['amount'] ?? 0)) }} ({{ $method['count'] }}件)</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                                <tr class="hover:bg-gray-50 {{ $res['is_posted'] ? 'bg-green-50' : '' }}">
                                    <td class="px-3 py-3 text-sm text-gray-900">
                                        {{ $res['time'] }}
                                        @if($res['is_posted'])
                                            <span class="ml-2 px-2 py-0.5 text-xs bg-green-600 text-white rounded font-medium">計上済み</span>
                                        @endif
                                    </td>
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
                                        <select
                                            wire:model="rowState.{{ $res['id'] }}.payment_method"
                                            class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                            {{ $res['is_posted'] ? 'disabled' : '' }}
                                        >
                                            @foreach($res['payment_methods'] ?? ['現金'] as $method)
                                                <option value="{{ $method }}">{{ $method }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        @if($res['source'] === 'spot')
                                            <input
                                                type="number"
                                                wire:model="rowState.{{ $res['id'] }}.amount"
                                                class="block w-24 text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                min="0"
                                                step="1"
                                                {{ $res['is_posted'] ? 'disabled' : '' }}
                                            />
                                        @else
                                            <span class="text-xs text-gray-500">¥0</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex gap-2">
                                            @if($res['is_posted'])
                                                <!-- 計上済み：取消ボタンのみ -->
                                                <button
                                                    wire:click="cancelSale({{ $res['id'] }})"
                                                    wire:confirm="本当にこの売上を取り消しますか？\n\n顧客名: {{ $res['customer_name'] }}\nメニュー: {{ $res['menu_name'] }}\n金額: ¥{{ number_format((int)($res['amount'] ?? 0)) }}"
                                                    style="display: inline-block !important; visibility: visible !important; opacity: 1 !important; background-color: #fef3c7 !important; color: #92400e !important; padding: 6px 12px !important; border-radius: 6px !important; font-size: 12px !important; font-weight: 500 !important; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important; border: 1px solid #fbbf24 !important;"
                                                    onmouseover="this.style.backgroundColor='#fde68a'"
                                                    onmouseout="this.style.backgroundColor='#fef3c7'"
                                                >
                                                    取消
                                                </button>
                                            @else
                                                <!-- 未計上：計上ボタンと編集ボタン -->
                                                <button
                                                    wire:click="postSingleSale({{ $res['id'] }})"
                                                    style="display: inline-block !important; visibility: visible !important; opacity: 1 !important; background-color: #dbeafe !important; color: #1e40af !important; padding: 6px 12px !important; border-radius: 6px !important; font-size: 12px !important; font-weight: 500 !important; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important; border: 1px solid #93c5fd !important;"
                                                    onmouseover="this.style.backgroundColor='#bfdbfe'"
                                                    onmouseout="this.style.backgroundColor='#dbeafe'"
                                                >
                                                    計上
                                                </button>
                                                <button
                                                    wire:click="openEditor({{ $res['id'] }})"
                                                    style="background-color: #f9fafb; color: #374151; padding: 6px 12px; border: 2px solid #d1d5db; border-radius: 6px; font-size: 12px; font-weight: 500; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);"
                                                    onmouseover="this.style.backgroundColor='#f3f4f6'"
                                                    onmouseout="this.style.backgroundColor='#f9fafb'"
                                                >
                                                    編集
                                                </button>
                                            @endif
                                        </div>
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
                                <span class="font-semibold">¥{{ number_format((int)($staffSales['amount'] ?? 0)) }}</span>
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
                                <span class="font-semibold">¥{{ number_format((int)($menu->total ?? 0)) }}</span>
                                <span class="text-xs text-gray-500 ml-2">({{ $menu->count }}個)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- 編集ドロワー -->
    @if($this->editorOpen)
        <div class="fixed inset-0 z-50 flex justify-end">
            <!-- ヘルプボタンを隠す -->
            <style>
                div[style*="position:fixed"][style*="bottom:24px"][style*="right:24px"] {
                    display: none !important;
                }
            </style>

            <!-- オーバーレイ（ぼかし効果付き） -->
            <div class="absolute inset-0 bg-gray-900/50"
                 style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);"
                 wire:click="closeEditor">
            </div>

            <!-- ドロワーパネル -->
            <div class="relative w-full max-w-2xl bg-white shadow-2xl overflow-y-auto"
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

                        <!-- オプション明細 -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-medium text-gray-900">オプション</h3>
                                @if(!empty($this->editorData['option_menus']))
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500">
                                            (選択肢: {{ count($this->editorData['option_menus']) }}件)
                                        </span>
                                        <button
                                            wire:click="addOptionItem"
                                            class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:border-gray-400 transition-colors"
                                        >
                                            + 追加
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if(empty($this->editorData['option_menus']))
                                <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                    <p class="text-sm text-gray-600">
                                        オプション/アップセル用メニューが登録されていません。
                                    </p>
                                </div>
                            @endif

                            @forelse($this->editorData['option_items'] ?? [] as $index => $item)
                                <div class="border border-gray-200 rounded-lg p-4 mb-3 bg-blue-50">
                                    <div class="grid grid-cols-12 gap-3">
                                        <div class="col-span-5">
                                            <label class="block text-xs text-gray-500 mb-1">オプション選択</label>
                                            <select
                                                wire:model.live="editorData.option_items.{{ $index }}.option_id"
                                                wire:change="selectOptionMenu({{ $index }}, $event.target.value)"
                                                class="block w-full text-sm border-gray-300 rounded-md"
                                                @if(empty($this->editorData['option_menus'])) disabled @endif>
                                                <option value="">-- オプションを選択 --</option>
                                                @foreach($this->editorData['option_menus'] ?? [] as $optionMenu)
                                                    <option value="{{ $optionMenu['type'] }}:{{ $optionMenu['id'] }}"
                                                            @if(isset($item['option_type']) && isset($item['option_id']) && $item['option_type'] == $optionMenu['type'] && $item['option_id'] == $optionMenu['id']) selected @endif>
                                                        {{ $optionMenu['name'] }} (¥{{ number_format((int)($optionMenu['price'] ?? 0)) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-xs text-gray-500 mb-1">単価</label>
                                            <input type="number"
                                                   wire:model="editorData.option_items.{{ $index }}.price"
                                                   wire:change="updateCalculation"
                                                   class="block w-full text-sm border-gray-300 rounded-md bg-gray-100"
                                                   min="0"
                                                   readonly>
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs text-gray-500 mb-1">数量</label>
                                            <input type="number"
                                                   wire:model="editorData.option_items.{{ $index }}.quantity"
                                                   wire:change="updateCalculation"
                                                   class="block w-full text-sm border-gray-300 rounded-md"
                                                   min="1">
                                        </div>
                                        <div class="col-span-2 flex items-end">
                                            <button wire:click="removeOptionItem({{ $index }})"
                                                    class="text-red-600 hover:text-red-800 text-sm">
                                                削除
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 italic">オプションはありません</p>
                            @endforelse
                        </div>

                        <!-- 物販明細 -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-medium text-gray-900">物販（メニュー外商品）</h3>
                                <button
                                    wire:click="addProductItem"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:border-gray-400 transition-colors"
                                >
                                    + 追加
                                </button>
                            </div>

                            @forelse($this->editorData['product_items'] ?? [] as $index => $item)
                                <div class="border border-gray-200 rounded-lg p-4 mb-3 bg-green-50">
                                    <div class="grid grid-cols-12 gap-3">
                                        <div class="col-span-5">
                                            <label class="block text-xs text-gray-500 mb-1">商品名</label>
                                            <input type="text"
                                                   wire:model="editorData.product_items.{{ $index }}.name"
                                                   class="block w-full text-sm border-gray-300 rounded-md"
                                                   placeholder="例：サプリメント、グッズ">
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

                        <!-- 支払方法 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">支払方法</label>
                            <select wire:model="editorData.payment_method"
                                    class="block w-full text-sm border-gray-300 rounded-md">
                                @foreach($this->editorData['payment_methods_list'] ?? ['現金', 'その他'] as $method)
                                    <option value="{{ $method }}">{{ $method }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                @if($this->editorData['payment_source'] === 'subscription')
                                    ※ サブスクの場合、通常は決済方法（スクエア等）を選択
                                @elseif($this->editorData['payment_source'] === 'ticket')
                                    ※ 回数券の場合、オプション/物販があれば決済方法を選択
                                @else
                                    ※ 合計金額に応じた支払方法を選択してください
                                @endif
                            </p>
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
                                style="display: inline-block !important; visibility: visible !important; opacity: 1 !important; background-color: white !important; color: #374151 !important; padding: 0.5rem 1rem !important; border: 1px solid #d1d5db !important; border-radius: 0.375rem !important; font-size: 0.875rem !important; font-weight: 500 !important;"
                                onmouseover="this.style.backgroundColor='#f9fafb'"
                                onmouseout="this.style.backgroundColor='white'"
                            >
                                キャンセル
                            </button>
                            <button
                                wire:click="saveSaleWithItems"
                                style="display: inline-block !important; visibility: visible !important; opacity: 1 !important; background-color: #4f46e5 !important; color: white !important; padding: 0.5rem 1rem !important; border: 1px solid transparent !important; border-radius: 0.375rem !important; font-size: 0.875rem !important; font-weight: 500 !important; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;"
                                onmouseover="this.style.backgroundColor='#4338ca'"
                                onmouseout="this.style.backgroundColor='#4f46e5'"
                            >
                                決定
                            </button>
                        </div>
                    </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>