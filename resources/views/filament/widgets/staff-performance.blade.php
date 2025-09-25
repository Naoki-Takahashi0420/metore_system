<x-filament::widget>
    <x-filament::card>
        <div class="mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                スタッフパフォーマンス
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            スタッフ名
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            予約件数
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            売上高
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            平均単価
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            新規顧客
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            サブスク転換
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            転換率
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            リピート獲得
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($staffData as $index => $staff)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $staff['name'] }}
                                        </div>
                                        @if($index === 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                TOP
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                {{ number_format($staff['reservation_count']) }}件
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    ¥{{ number_format($staff['revenue']) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                ¥{{ number_format($staff['avg_ticket']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                {{ number_format($staff['new_customers']) }}名
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                {{ number_format($staff['subscription_conversions']) }}件
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($staff['conversion_rate'] >= 50) bg-green-100 text-green-800
                                    @elseif($staff['conversion_rate'] >= 30) bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800
                                    @endif">
                                    {{ $staff['conversion_rate'] }}%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                {{ number_format($staff['repeat_reservations']) }}件
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                データがありません
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($staffData) > 0)
                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                合計
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format(collect($staffData)->sum('reservation_count')) }}件
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                ¥{{ number_format(collect($staffData)->sum('revenue')) }}
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                -
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format(collect($staffData)->sum('new_customers')) }}名
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format(collect($staffData)->sum('subscription_conversions')) }}件
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                @php
                                    $totalNew = collect($staffData)->sum('new_customers');
                                    $totalConv = collect($staffData)->sum('subscription_conversions');
                                    $avgRate = $totalNew > 0 ? round(($totalConv / $totalNew) * 100, 1) : 0;
                                @endphp
                                {{ $avgRate }}%
                            </td>
                            <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format(collect($staffData)->sum('repeat_reservations')) }}件
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::card>
</x-filament::widget>