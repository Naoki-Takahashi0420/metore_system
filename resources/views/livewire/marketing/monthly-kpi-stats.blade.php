<div>
    <x-filament::card>
        <div class="mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                月次KPI - {{ $kpiData['period_label'] ?? '' }}
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 売上高 -->
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">売上高</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">
                            ¥{{ number_format($kpiData['total_revenue'] ?? 0) }}
                        </p>
                        @if(($kpiData['revenue_growth'] ?? 0) !== 0)
                            <p class="text-sm mt-1">
                                <span class="@if($kpiData['revenue_growth'] > 0) text-green-600 @else text-red-600 @endif">
                                    @if($kpiData['revenue_growth'] > 0)
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                        </svg>
                                    @endif
                                    {{ abs($kpiData['revenue_growth']) }}%
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">前期比</span>
                            </p>
                        @endif
                    </div>
                    <div class="text-blue-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 予約数 -->
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-600 dark:text-green-400 font-medium">予約件数</p>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">
                            {{ number_format($kpiData['total_reservations'] ?? 0) }}件
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            平均単価 ¥{{ number_format($kpiData['avg_ticket'] ?? 0) }}
                        </p>
                    </div>
                    <div class="text-green-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 新規顧客 -->
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">新規顧客</p>
                        <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-1">
                            {{ number_format($kpiData['new_customers'] ?? 0) }}名
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            リピート率 {{ $kpiData['repeat_rate'] ?? 0 }}%
                        </p>
                    </div>
                    <div class="text-purple-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- サブスク -->
            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-orange-600 dark:text-orange-400 font-medium">サブスク契約</p>
                        <p class="text-2xl font-bold text-orange-900 dark:text-orange-100 mt-1">
                            {{ number_format($kpiData['active_subscriptions'] ?? 0) }}件
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            アクティブ契約
                        </p>
                    </div>
                    <div class="text-orange-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::card>
</div>