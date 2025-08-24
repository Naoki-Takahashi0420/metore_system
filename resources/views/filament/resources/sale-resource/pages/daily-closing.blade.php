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
</x-filament-panels::page>