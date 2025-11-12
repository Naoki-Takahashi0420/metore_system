<div>
    <x-filament::card>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    新規予約→契約転換分析
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    カルテ対応者別の転換ファネル分析
                </p>
            </div>

            @if(!empty($availableHandlers))
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">カルテ対応者</label>
                    <select wire:model.live="handler_id" class="w-48 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">全対応者</option>
                        @foreach($availableHandlers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        @if(empty($conversionData))
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">データがありません</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">選択した期間にカルテデータが見つかりませんでした</p>
            </div>
        @else
            <!-- サマリー -->
            <div class="mb-6">
                @php
                    $totalNewReservations = collect($conversionData)->sum('new_reservation_count');
                    $totalRecords = collect($conversionData)->sum('medical_record_count');
                    $totalNextReservations = collect($conversionData)->sum('next_reservation_count');
                    $totalContracts = collect($conversionData)->sum('total_contract_count');

                    $medicalRecordRate = $totalNewReservations > 0 ? round(($totalRecords / $totalNewReservations) * 100, 1) : 0;
                    $nextReservationRate = $totalRecords > 0 ? round(($totalNextReservations / $totalRecords) * 100, 1) : 0;
                    $contractRate = $totalRecords > 0 ? round(($totalContracts / $totalRecords) * 100, 1) : 0;
                @endphp

                <!-- ファネルチャート -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 text-center">転換ファネル</h3>
                    <div class="max-w-3xl mx-auto space-y-2">
                        <!-- 1. 新規予約 -->
                        <div class="relative">
                            <div class="bg-indigo-500 rounded-lg p-4 shadow-lg" style="width: 100%;">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold" style="color: black !important;">1. 新規予約</span>
                                    <span class="text-xl font-bold" style="color: black !important;">{{ number_format($totalNewReservations) }}件</span>
                                </div>
                                <div class="text-sm mt-1" style="color: black !important;">初回接点</div>
                            </div>
                        </div>

                        <!-- 矢印 -->
                        <div class="flex justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v10.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>

                        <!-- 2. カルテ作成 -->
                        <div class="relative flex justify-center">
                            @php $medicalRecordWidth = $totalNewReservations > 0 ? ($totalRecords / $totalNewReservations) * 100 : 0; @endphp
                            <div class="bg-blue-500 rounded-lg p-4 shadow-lg" style="width: {{ max($medicalRecordWidth, 20) }}%;">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold" style="color: black !important;">2. カルテ作成</span>
                                    <span class="text-xl font-bold" style="color: black !important;">{{ number_format($totalRecords) }}件</span>
                                </div>
                                <div class="text-sm mt-1" style="color: black !important;">転換率: {{ $medicalRecordRate }}%</div>
                            </div>
                        </div>

                        <!-- 矢印 -->
                        <div class="flex justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v10.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>

                        <!-- 3. 次回予約 -->
                        <div class="relative flex justify-center">
                            @php $nextReservationWidth = $totalRecords > 0 ? ($totalNextReservations / $totalRecords) * 100 : 0; @endphp
                            <div class="bg-green-500 rounded-lg p-4 shadow-lg" style="width: {{ max($nextReservationWidth, 15) }}%;">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold" style="color: black !important;">3. 次回予約</span>
                                    <span class="text-xl font-bold" style="color: black !important;">{{ number_format($totalNextReservations) }}件</span>
                                </div>
                                <div class="text-sm mt-1" style="color: black !important;">転換率: {{ $nextReservationRate }}%</div>
                            </div>
                        </div>

                        <!-- 矢印 -->
                        <div class="flex justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v10.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>

                        <!-- 4. 契約獲得 -->
                        <div class="relative flex justify-center">
                            @php $contractWidth = $totalRecords > 0 ? ($totalContracts / $totalRecords) * 100 : 0; @endphp
                            <div class="bg-purple-500 rounded-lg p-4 shadow-lg" style="width: {{ max($contractWidth, 10) }}%;">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold" style="color: black !important;">4. 契約獲得</span>
                                    <span class="text-xl font-bold" style="color: black !important;">{{ number_format($totalContracts) }}件</span>
                                </div>
                                <div class="text-sm mt-1" style="color: black !important;">転換率: {{ $contractRate }}%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 数値サマリー -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4">
                        <p class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">新規予約</p>
                        <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100 mt-1">{{ number_format($totalNewReservations) }}</p>
                        <p class="text-xs text-indigo-700 dark:text-indigo-300 mt-1">初回接点</p>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">カルテ作成率</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">{{ $medicalRecordRate }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ number_format($totalRecords) }}件</p>
                    </div>

                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                        <p class="text-sm text-green-600 dark:text-green-400 font-medium">次回予約獲得率</p>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">{{ $nextReservationRate }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ number_format($totalNextReservations) }}件</p>
                    </div>

                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                        <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">契約転換率</p>
                        <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-1">{{ $contractRate }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ number_format($totalContracts) }}件</p>
                    </div>
                </div>
            </div>

            <!-- テーブル -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                対応者
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                新規予約
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                カルテ数
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                次回予約
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                サブスク
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                回数券
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                総契約数
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                契約転換率
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                顧客一覧
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($conversionData as $handler)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                                            <span class="text-indigo-600 dark:text-indigo-400 font-semibold">
                                                {{ mb_substr($handler['handler_name'], 0, 1) }}
                                            </span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $handler['handler_name'] }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($handler['new_reservation_count']) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($handler['medical_record_count']) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($handler['next_reservation_count']) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($handler['subscription_count']) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($handler['ticket_count']) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($handler['total_contract_count']) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($handler['contract_rate'] >= 50) bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300
                                        @elseif($handler['contract_rate'] >= 30) bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                        @endif">
                                        {{ $handler['contract_rate'] }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        wire:click="toggleHandler({{ $handler['handler_id'] }})"
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 text-sm font-medium">
                                        @if($this->isExpanded($handler['handler_id']))
                                            ▲ 閉じる
                                        @else
                                            ▼ 詳細 ({{ count($handler['customers']) }}名)
                                        @endif
                                    </button>
                                </td>
                            </tr>

                            @if($this->isExpanded($handler['handler_id']))
                                <tr>
                                    <td colspan="9" class="px-4 py-3 bg-gray-50 dark:bg-gray-800">
                                        <div class="overflow-x-auto">
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                {{ $handler['handler_name'] }}の顧客一覧（{{ count($handler['customers']) }}名）
                                            </p>
                                            <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-600">
                                                <thead>
                                                    <tr class="bg-gray-100 dark:bg-gray-700">
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">顧客名</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">カルテ日</th>
                                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">次回予約</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">予約日</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">契約種別</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">契約日</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                                    @foreach($handler['customers'] as $customer)
                                                        <tr class="hover:bg-gray-100 dark:hover:bg-gray-700">
                                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                                {{ $customer['customer_name'] }}
                                                            </td>
                                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                                                {{ $customer['medical_record_date'] }}
                                                            </td>
                                                            <td class="px-3 py-2 text-center text-sm">
                                                                @if($customer['has_next_reservation'])
                                                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 inline-block" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @else
                                                                    <span class="text-gray-400">-</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                                                {{ $customer['next_reservation_date'] ?? '-' }}
                                                            </td>
                                                            <td class="px-3 py-2 text-sm">
                                                                @if($customer['contract_type'])
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                                        @if($customer['contract_type'] === 'サブスク')
                                                                            bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300
                                                                        @else
                                                                            bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                                                        @endif">
                                                                        {{ $customer['contract_type'] }}
                                                                    </span>
                                                                @else
                                                                    <span class="text-gray-400">-</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                                                {{ $customer['contract_date'] ?? '-' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::card>
</div>
