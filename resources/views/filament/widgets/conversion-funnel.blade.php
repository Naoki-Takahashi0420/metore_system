<x-filament::widget>
    <x-filament::card>
        <div class="mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                コンバージョンファネル
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                新規顧客の獲得から定着までの流れ
            </p>
        </div>

        <div class="space-y-4">
            @foreach($funnelData as $index => $stage)
                @php
                    $widthPercent = $index === 0 ? 100 : max(20, $stage['rate']);
                    $colors = [
                        'bg-blue-500',
                        'bg-green-500',
                        'bg-yellow-500',
                        'bg-purple-500',
                    ];
                    $bgColor = $colors[$index % count($colors)];
                @endphp

                <div class="relative">
                    <!-- ファネルバー -->
                    <div class="flex items-center">
                        <div class="flex-1">
                            <div class="{{ $bgColor }} text-white rounded-lg shadow-lg transition-all duration-300 hover:shadow-xl"
                                 style="width: {{ $widthPercent }}%;">
                                <div class="px-6 py-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-sm font-medium opacity-90">
                                                {{ $stage['stage'] }}
                                            </p>
                                            <p class="text-2xl font-bold mt-1">
                                                {{ number_format($stage['count']) }}
                                                <span class="text-sm font-normal">
                                                    @if($stage['stage'] === '新規顧客登録' || $stage['stage'] === '2回目予約')
                                                        名
                                                    @else
                                                        件
                                                    @endif
                                                </span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-3xl font-bold">
                                                {{ $stage['rate'] }}%
                                            </p>
                                            @if($index > 0)
                                                <p class="text-xs opacity-90 mt-1">
                                                    転換率
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 右側の余白部分に数値表示 -->
                        @if($widthPercent < 100)
                            <div class="ml-4 text-gray-600 dark:text-gray-400">
                                <span class="text-sm">
                                    離脱 {{ number_format($funnelData[max(0, $index - 1)]['count'] - $stage['count']) }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <!-- 矢印（最後のステージ以外） -->
                    @if(!$loop->last)
                        <div class="flex justify-center my-2" style="margin-left: {{ ($widthPercent / 2) - 5 }}%;">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- サマリー情報 -->
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if(count($funnelData) >= 2)
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400">初回来店率</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $funnelData[1]['rate'] ?? 0 }}%
                        </p>
                    </div>
                @endif

                @if(count($funnelData) >= 3)
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400">リピート率</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $funnelData[2]['rate'] ?? 0 }}%
                        </p>
                    </div>
                @endif

                @if(count($funnelData) >= 4)
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400">サブスク転換率</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $funnelData[3]['rate'] ?? 0 }}%
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- 改善ポイント -->
        @php
            $weakestStage = collect($funnelData)->skip(1)->sortBy('rate')->first();
        @endphp
        @if($weakestStage && $weakestStage['rate'] < 50)
            <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                            改善ポイント
                        </h3>
                        <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                            「{{ $weakestStage['stage'] }}」の転換率が {{ $weakestStage['rate'] }}% と低めです。
                            この段階の改善により、全体の成果を大きく向上させる可能性があります。
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::card>
</x-filament::widget>