<div>
    <x-filament::card>
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                スタッフパフォーマンス詳細
            </h2>
        </div>

        @if(count($staffData) > 0)
            <!-- KPIチャート -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- 売上比較チャート -->
                <div wire:ignore style="height: 300px;">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">売上ランキング</h3>
                    <div style="height: 260px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- パフォーマンス指標チャート -->
                <div wire:ignore style="height: 300px;">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">主要パフォーマンス指標</h3>
                    <div style="height: 260px;">
                        <canvas id="performanceRadarChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 詳細データテーブル -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                スタッフ
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                売上高
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                新規獲得
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                サブスク転換率
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                リピート獲得率
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                顧客継続率
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                満足度スコア
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($staffData as $index => $staff)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <!-- スタッフ名 -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $staff['name'] }}
                                            </div>
                                            @if($index === 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    </svg>
                                                    1位
                                                </span>
                                            @elseif($index === 1)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    2位
                                                </span>
                                            @elseif($index === 2)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                    3位
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- 売上高 -->
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        ¥{{ number_format($staff['revenue']) }}
                                    </div>
                                </td>

                                <!-- 新規獲得 -->
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ number_format($staff['new_customers']) }}名
                                    </div>
                                </td>

                                <!-- サブスク転換率 -->
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($staff['conversion_rate'] >= 50) bg-green-100 text-green-800
                                        @elseif($staff['conversion_rate'] >= 30) bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ $staff['conversion_rate'] }}%
                                    </span>
                                </td>

                                <!-- リピート獲得率 -->
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($staff['repeat_acquisition_rate'] >= 70) bg-green-100 text-green-800
                                        @elseif($staff['repeat_acquisition_rate'] >= 50) bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ $staff['repeat_acquisition_rate'] }}%
                                    </span>
                                </td>

                                <!-- 顧客継続率 -->
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($staff['customer_retention_rate'] >= 80) bg-green-100 text-green-800
                                        @elseif($staff['customer_retention_rate'] >= 60) bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ $staff['customer_retention_rate'] }}%
                                    </span>
                                </td>

                                <!-- 満足度スコア -->
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white mr-2">
                                            {{ $staff['satisfaction_score'] }}%
                                        </div>
                                        <div class="w-16 bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $staff['satisfaction_score'] }}%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- サマリー統計 -->
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ number_format(collect($staffData)->sum('revenue')) }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">総売上（円）</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ number_format(collect($staffData)->sum('new_customers')) }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">新規顧客（名）</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        @php
                            $avgConversion = collect($staffData)->avg('conversion_rate');
                        @endphp
                        {{ round($avgConversion, 1) }}%
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">平均転換率</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                        @php
                            $avgSatisfaction = collect($staffData)->avg('satisfaction_score');
                        @endphp
                        {{ round($avgSatisfaction, 1) }}%
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">平均満足度</div>
                </div>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                データがありません
            </div>
        @endif
    </x-filament::card>
</div>

@push('scripts')
<script>
    console.log('🔵 staff-performance-stats.blade.php スクリプト読み込み開始');

    // Livewire更新に対応したチャート初期化
    function initStaffCharts() {
        console.log('🔵 initStaffCharts() 呼び出し');

        // Chart.jsまたはグローバル設定が読み込まれていない場合は待機
        if (typeof Chart === 'undefined' || !window.chartGlobalDefaultsSet) {
            console.log('⏳ Chart.jsまたはグローバル設定未完了、100ms後に再試行');
            setTimeout(initStaffCharts, 100);
            return;
        }

        console.log('🟢 Chart.jsグローバル設定確認:', {
            animation: Chart.defaults.animation,
            animations: Chart.defaults.animations,
            transitions: Chart.defaults.transitions
        });

        const staffData = @json($staffData);
        const revenueCanvas = document.getElementById('revenueChart');
        const radarCanvas = document.getElementById('performanceRadarChart');

        console.log('📊 キャンバス要素:', { revenueCanvas, radarCanvas, staffDataLength: staffData.length });

        // キャンバスが存在しない場合は何もしない
        if (!revenueCanvas || !radarCanvas || staffData.length === 0) {
            console.log('❌ キャンバスまたはデータが存在しない');
            return;
        }

        // 既存のチャートインスタンスを破棄
        if (window.revenueChartInstance) {
            console.log('🗑️ 既存のrevenueChartInstanceを破棄');
            window.revenueChartInstance.destroy();
        }
        if (window.radarChartInstance) {
            console.log('🗑️ 既存のradarChartInstanceを破棄');
            window.radarChartInstance.destroy();
        }

        // 売上チャート
        const revenueCtx = revenueCanvas.getContext('2d');
        console.log('🎨 売上チャート作成開始');
        window.revenueChartInstance = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: staffData.map(staff => staff.name),
                    datasets: [{
                        label: '売上（円）',
                        data: staffData.map(staff => staff.revenue),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    animation: false,
                    animations: false,
                    transitions: false,
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '¥' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        console.log('✅ 売上チャート作成完了:', window.revenueChartInstance);

        // パフォーマンスレーダーチャート（上位3名のみ）
        console.log('🎨 レーダーチャート作成開始');
        const topStaff = staffData.slice(0, 3);
        const radarCtx = radarCanvas.getContext('2d');
        window.radarChartInstance = new Chart(radarCtx, {
                type: 'radar',
                data: {
                    labels: ['転換率', 'リピート獲得率', '継続率', '満足度', '新規獲得'],
                    datasets: topStaff.map((staff, index) => ({
                        label: staff.name,
                        data: [
                            staff.conversion_rate,
                            staff.repeat_acquisition_rate,
                            staff.customer_retention_rate,
                            staff.satisfaction_score,
                            Math.min(staff.new_customers * 10, 100) // 新規顧客数を0-100スケールに調整
                        ],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.2)',
                            'rgba(34, 197, 94, 0.2)',
                            'rgba(251, 191, 36, 0.2)'
                        ][index],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(34, 197, 94, 1)',
                            'rgba(251, 191, 36, 1)'
                        ][index],
                        pointBackgroundColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(34, 197, 94, 1)',
                            'rgba(251, 191, 36, 1)'
                        ][index],
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(34, 197, 94, 1)',
                            'rgba(251, 191, 36, 1)'
                        ][index]
                    }))
                },
                options: {
                    animation: false,
                    animations: false,
                    transitions: false,
                    responsive: true,
                    maintainAspectRatio: true,
                    elements: {
                        line: {
                            borderWidth: 3
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        r: {
                            angleLines: {
                                display: false
                            },
                            suggestedMin: 0,
                            suggestedMax: 100
                        }
                    }
                }
            });
        console.log('✅ レーダーチャート作成完了:', window.radarChartInstance);
    }

    // 初回実行
    console.log('📍 初回実行の準備（staff）');
    if (document.readyState === 'loading') {
        console.log('⏳ DOMContentLoadedを待機中（staff）');
        document.addEventListener('DOMContentLoaded', initStaffCharts);
    } else {
        console.log('▶️ 即座に実行（staff）');
        initStaffCharts();
    }

    // Livewire更新時に再初期化
    console.log('🔄 Livewireイベントリスナー登録（staff）');
    document.addEventListener('livewire:navigated', initStaffCharts);
</script>
@endpush
