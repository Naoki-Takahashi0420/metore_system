<div>
    <x-filament::card>
        <div class="mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                顧客分析
            </h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 顧客セグメント -->
            <div wire:ignore style="height: 300px;">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">顧客セグメント分布</h3>
                <div style="height: 200px;">
                    <canvas id="customerSegmentChart"></canvas>
                </div>

                <div class="mt-4 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">新規顧客</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ number_format($customerData['segments']['new']) }}名
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">アクティブ（30日以内）</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ number_format($customerData['segments']['active']) }}名
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">休眠（30-60日）</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ number_format($customerData['segments']['dormant']) }}名
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            離脱リスク（60日以上）
                        </span>
                        <span class="text-sm font-semibold text-red-600 dark:text-red-400">
                            {{ number_format($customerData['segments']['lost']) }}名
                        </span>
                    </div>
                </div>
            </div>

            <!-- 新規顧客推移 -->
            <div wire:ignore style="height: 300px;">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">新規顧客獲得推移</h3>
                <div style="height: 200px;">
                    <canvas id="newCustomerTrendChart"></canvas>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                        <p class="text-sm text-blue-600 dark:text-blue-400">既存顧客来店</p>
                        <p class="text-xl font-bold text-blue-900 dark:text-blue-100">
                            {{ number_format($customerData['existing_customer_visits']) }}回
                        </p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                        <p class="text-sm text-red-600 dark:text-red-400">離脱リスク</p>
                        <p class="text-xl font-bold text-red-900 dark:text-red-100">
                            {{ number_format($customerData['churn_risk_customers']) }}名
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- キャンセル・ノーショー率 -->
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">予約キャンセル分析</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center">
                    <div class="flex-1">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">キャンセル率</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $customerData['cancel_rate'] }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                            <div class="bg-yellow-600 h-2.5 rounded-full" style="width: {{ min($customerData['cancel_rate'], 100) }}%"></div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="flex-1">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">ノーショー率</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $customerData['no_show_rate'] }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                            <div class="bg-red-600 h-2.5 rounded-full" style="width: {{ min($customerData['no_show_rate'], 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::card>
</div>

@push('scripts')
    <script>
        console.log('🔵 customer-analysis-stats.blade.php スクリプト読み込み開始');

        // Livewire更新に対応したチャート初期化
        function initCustomerCharts() {
            console.log('🔵 initCustomerCharts() 呼び出し');

            // Chart.jsまたはグローバル設定が読み込まれていない場合は待機
            if (typeof Chart === 'undefined' || !window.chartGlobalDefaultsSet) {
                console.log('⏳ Chart.jsまたはグローバル設定未完了（customer）、100ms後に再試行');
                setTimeout(initCustomerCharts, 100);
                return;
            }

            console.log('🟢 Chart.jsグローバル設定確認（customer）:', {
                animation: Chart.defaults.animation,
                animations: Chart.defaults.animations,
                transitions: Chart.defaults.transitions
            });

            const segmentCanvas = document.getElementById('customerSegmentChart');
            const trendCanvas = document.getElementById('newCustomerTrendChart');

            console.log('📊 キャンバス要素（customer）:', { segmentCanvas, trendCanvas });

            // キャンバスが存在しない場合は何もしない
            if (!segmentCanvas || !trendCanvas) {
                console.log('❌ キャンバスが存在しない（customer）');
                return;
            }

            // 既存のチャートインスタンスを破棄
            if (window.segmentChartInstance) {
                console.log('🗑️ 既存のsegmentChartInstanceを破棄');
                window.segmentChartInstance.destroy();
            }
            if (window.trendChartInstance) {
                console.log('🗑️ 既存のtrendChartInstanceを破棄');
                window.trendChartInstance.destroy();
            }

            // 顧客セグメントチャート
            const segmentCtx = segmentCanvas.getContext('2d');
            console.log('🎨 顧客セグメントチャート作成開始');
            window.segmentChartInstance = new Chart(segmentCtx, {
                type: 'doughnut',
                data: {
                    labels: ['新規', 'アクティブ', '休眠', '離脱リスク'],
                    datasets: [{
                        data: [
                            {{ $customerData['segments']['new'] }},
                            {{ $customerData['segments']['active'] }},
                            {{ $customerData['segments']['dormant'] }},
                            {{ $customerData['segments']['lost'] }}
                        ],
                        backgroundColor: [
                            'rgb(59, 130, 246)',
                            'rgb(34, 197, 94)',
                            'rgb(251, 191, 36)',
                            'rgb(239, 68, 68)'
                        ]
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
                            position: 'bottom',
                        }
                    }
                }
            });
            console.log('✅ 顧客セグメントチャート作成完了:', window.segmentChartInstance);

            // 新規顧客推移チャート
            const trendCtx = trendCanvas.getContext('2d');
            const trendData = @json($customerData['new_customers_trend']);
            const dates = Object.keys(trendData);
            const values = Object.values(trendData);

            console.log('🎨 新規顧客推移チャート作成開始');
            window.trendChartInstance = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: dates.map(date => {
                        const d = new Date(date);
                        return (d.getMonth() + 1) + '/' + d.getDate();
                    }),
                    datasets: [{
                        label: '新規顧客数',
                        data: values,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
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
                                precision: 0  // 整数のみ表示（stepSizeは自動計算）
                            }
                        }
                    }
                }
            });
            console.log('✅ 新規顧客推移チャート作成完了:', window.trendChartInstance);
        }

        // 初回実行
        console.log('📍 初回実行の準備（customer）');
        if (document.readyState === 'loading') {
            console.log('⏳ DOMContentLoadedを待機中（customer）');
            document.addEventListener('DOMContentLoaded', initCustomerCharts);
        } else {
            console.log('▶️ 即座に実行（customer）');
            initCustomerCharts();
        }

        // Livewire更新時に再初期化
        console.log('🔄 Livewireイベントリスナー登録（customer）');
        document.addEventListener('livewire:navigated', initCustomerCharts);
    </script>
@endpush