<div>
    <x-filament::card>
        <div class="mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        完全なコンバージョンファネル分析
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        新規顧客→初回予約→カルテ→契約の全ステップを可視化
                    </p>
                </div>

                <!-- ビュー切り替えボタン -->
                <div class="flex gap-2">
                    <button
                        wire:click="switchView('store')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors
                            {{ $view === 'store'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        店舗別
                    </button>
                    <button
                        wire:click="switchView('staff')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors
                            {{ $view === 'staff'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        スタッフ別
                    </button>
                </div>
            </div>
        </div>

        @if(empty($funnelData))
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">データがありません</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">選択した期間にデータが見つかりませんでした</p>
            </div>
        @else
            <!-- サマリー表示 -->
            @if(isset($funnelData['summary']))
                @php $summary = $funnelData['summary']; @endphp
                <div class="mb-6 bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-800 dark:to-gray-900 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 text-center">全体サマリー</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <p class="text-xs text-gray-500 dark:text-gray-400">新規顧客</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($summary['new_customers']) }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <p class="text-xs text-gray-500 dark:text-gray-400">初回予約</p>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($summary['with_first_reservation']) }}</p>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ $summary['first_reservation_rate'] }}%</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <p class="text-xs text-gray-500 dark:text-gray-400">カルテ作成</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ number_format($summary['with_medical_record']) }}</p>
                            <p class="text-xs text-green-600 dark:text-green-400 mt-1">{{ $summary['medical_record_rate'] }}%</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <p class="text-xs text-gray-500 dark:text-gray-400">次回予約</p>
                            <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">{{ number_format($summary['with_next_reservation']) }}</p>
                            <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">{{ $summary['next_reservation_rate'] }}%</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <p class="text-xs text-gray-500 dark:text-gray-400">サブスク</p>
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ number_format($summary['with_subscription']) }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <p class="text-xs text-gray-500 dark:text-gray-400">回数券</p>
                            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($summary['with_ticket']) }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                            <p class="text-xs text-gray-500 dark:text-gray-400">総契約数</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ number_format($summary['with_any_contract']) }}</p>
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $summary['contract_conversion_rate'] }}%</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- テーブル表示 -->
            @if($view === 'store' && isset($funnelData['stores']))
                <div class="overflow-x-auto">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">店舗別コンバージョンデータ</h3>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">店舗名</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">新規顧客</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">初回予約</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">カルテ作成</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">次回予約</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">サブスク</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">回数券</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">総契約数</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">契約転換率</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">全体転換率</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($funnelData['stores'] as $store)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $store['store_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($store['new_customers']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span class="text-gray-900 dark:text-white font-medium">{{ number_format($store['with_first_reservation']) }}</span>
                                        <span class="block text-xs text-blue-600 dark:text-blue-400">{{ $store['first_reservation_rate'] }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span class="text-gray-900 dark:text-white font-medium">{{ number_format($store['with_medical_record']) }}</span>
                                        <span class="block text-xs text-green-600 dark:text-green-400">{{ $store['medical_record_rate'] }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span class="text-gray-900 dark:text-white font-medium">{{ number_format($store['with_next_reservation']) }}</span>
                                        <span class="block text-xs text-yellow-600 dark:text-yellow-400">{{ $store['next_reservation_rate'] }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($store['with_subscription']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($store['with_ticket']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($store['with_any_contract']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                            @if($store['contract_conversion_rate'] >= 20) bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                            @elseif($store['contract_conversion_rate'] >= 10) bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                            @elseif($store['contract_conversion_rate'] >= 5) bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ $store['contract_conversion_rate'] }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                            @if($store['overall_conversion_rate'] >= 10) bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300
                                            @elseif($store['overall_conversion_rate'] >= 5) bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ $store['overall_conversion_rate'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($view === 'staff' && isset($funnelData['staff']))
                <div class="overflow-x-auto">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">スタッフ別コンバージョンデータ</h3>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">スタッフ名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">店舗</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">新規顧客</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">初回予約</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">カルテ作成</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">次回予約</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">サブスク</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">回数券</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">総契約数</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">契約転換率</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">全体転換率</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($funnelData['staff'] as $staff)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                                                <span class="text-indigo-600 dark:text-indigo-400 font-semibold">
                                                    {{ mb_substr($staff['staff_name'], 0, 1) }}
                                                </span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $staff['staff_name'] }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $staff['store_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($staff['new_customers']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span class="text-gray-900 dark:text-white font-medium">{{ number_format($staff['with_first_reservation']) }}</span>
                                        <span class="block text-xs text-blue-600 dark:text-blue-400">{{ $staff['first_reservation_rate'] }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span class="text-gray-900 dark:text-white font-medium">{{ number_format($staff['with_medical_record']) }}</span>
                                        <span class="block text-xs text-green-600 dark:text-green-400">{{ $staff['medical_record_rate'] }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span class="text-gray-900 dark:text-white font-medium">{{ number_format($staff['with_next_reservation']) }}</span>
                                        <span class="block text-xs text-yellow-600 dark:text-yellow-400">{{ $staff['next_reservation_rate'] }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($staff['with_subscription']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($staff['with_ticket']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($staff['with_any_contract']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                            @if($staff['contract_conversion_rate'] >= 20) bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                            @elseif($staff['contract_conversion_rate'] >= 10) bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                            @elseif($staff['contract_conversion_rate'] >= 5) bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ $staff['contract_conversion_rate'] }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                            @if($staff['overall_conversion_rate'] >= 10) bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300
                                            @elseif($staff['overall_conversion_rate'] >= 5) bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ $staff['overall_conversion_rate'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- 注釈 -->
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">指標の説明</h4>
                <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <li><strong>契約転換率:</strong> カルテ作成した顧客のうち、契約（サブスクまたは回数券）を獲得した割合</li>
                    <li><strong>全体転換率:</strong> 新規顧客のうち、最終的に契約を獲得した割合（新規→契約の直接転換率）</li>
                    <li><strong>初回予約率:</strong> 新規顧客のうち、初回予約を取得した割合</li>
                    <li><strong>カルテ作成率:</strong> 初回予約した顧客のうち、カルテが作成された割合</li>
                    <li><strong>次回予約率:</strong> カルテ作成後、次回予約を獲得した割合</li>
                </ul>
            </div>
        @endif
    </x-filament::card>
</div>
