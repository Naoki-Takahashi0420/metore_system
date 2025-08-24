<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid md:grid-cols-2 gap-6">
            <!-- 売れ筋メニュー -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    今月の売れ筋メニュー TOP10
                </h3>
                
                <div class="space-y-3">
                    @foreach($this->getTopMenus() as $index => $menu)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <span class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full 
                                    {{ $index < 3 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                    {{ $index + 1 }}
                                </span>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $menu->item_name }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $menu->quantity }}個販売
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    ¥{{ number_format($menu->total_amount) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $menu->count }}回
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    @if(count($this->getTopMenus()) === 0)
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            データがありません
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- 時間帯別売上 -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    本日の時間帯別売上
                </h3>
                
                <div class="space-y-3">
                    @php
                        $maxAmount = max(array_column($this->getTimeRangeSales(), 'amount')) ?: 1;
                    @endphp
                    
                    @foreach($this->getTimeRangeSales() as $timeRange)
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $timeRange['label'] }}
                                </span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    ¥{{ number_format($timeRange['amount']) }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                     style="width: {{ $timeRange['amount'] > 0 ? ($timeRange['amount'] / $maxAmount) * 100 : 0 }}%">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>