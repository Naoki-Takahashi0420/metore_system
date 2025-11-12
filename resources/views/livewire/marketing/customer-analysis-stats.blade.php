<div>
    <x-filament::card>
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                顧客分析
            </h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 顧客セグメント -->
            <div wire:ignore>
                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-2">顧客セグメント分布</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    最終来店日から顧客を4つに分類。休眠・離脱リスク顧客への再アプローチで売上向上が見込めます
                </p>
                <div style="height: 250px;" class="mb-4">
                    <canvas id="customerSegmentChart"></canvas>
                </div>

                <div class="mt-4 space-y-3 bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <span class="text-base font-medium text-gray-700 dark:text-gray-300">新規顧客</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">初回来店</p>
                        </div>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($customerData['segments']['new']) }}名
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <span class="text-base font-medium text-gray-700 dark:text-gray-300">アクティブ</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">30日以内に来店</p>
                        </div>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($customerData['segments']['active']) }}名
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <span class="text-base font-medium text-gray-700 dark:text-gray-300">休眠</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">30-60日来店なし（要フォロー）</p>
                        </div>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($customerData['segments']['dormant']) }}名
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <div>
                            <span class="text-base font-medium text-gray-700 dark:text-gray-300">離脱リスク</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">60日以上来店なし（至急アプローチ）</p>
                        </div>
                        <span class="text-xl font-bold text-red-600 dark:text-red-400">
                            {{ number_format($customerData['segments']['lost']) }}名
                        </span>
                    </div>
                </div>
            </div>

            <!-- 新規顧客推移 -->
            <div wire:ignore>
                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-4">新規顧客獲得推移</h3>
                <div style="height: 250px;" class="mb-4">
                    <canvas id="newCustomerTrendChart"></canvas>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border-2 border-blue-200 dark:border-blue-800">
                        <p class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-1">既存顧客来店</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                            {{ number_format($customerData['existing_customer_visits']) }}回
                        </p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border-2 border-red-200 dark:border-red-800">
                        <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-1">離脱リスク</p>
                        <p class="text-2xl font-bold text-red-900 dark:text-red-100">
                            {{ number_format($customerData['churn_risk_customers']) }}名
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 流入経路別コンバージョン分析 -->
        @if(!empty($customerData['acquisition_sources']) && count($customerData['acquisition_sources']) > 0)
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">流入経路別コンバージョン分析</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">流入経路</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">カルテ数</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">サブスク</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">回数券</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">総契約数</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">転換率</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($customerData['acquisition_sources'] as $source)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $source['source'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($source['record_count']) }}件
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($source['subscription_count']) }}件
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($source['ticket_count']) }}件
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($source['total_contracts']) }}件
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                            @if($source['conversion_rate'] >= 50) bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                            @elseif($source['conversion_rate'] >= 30) bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                            @elseif($source['conversion_rate'] >= 10) bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ $source['conversion_rate'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    ※カルテ作成時に記録された流入経路をもとに、期間内の契約転換率を算出しています
                </p>
            </div>
        @endif

        <!-- キャンセル・ノーショー率 -->
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-2">予約キャンセル分析</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                キャンセル率が高い場合はリマインド強化、ノーショー（無断キャンセル）は要注意顧客として対応を検討
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- キャンセル率 -->
                <div>
                    <div class="flex items-center mb-4">
                        <div class="flex-1">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <span class="text-base font-medium text-gray-700 dark:text-gray-300">キャンセル率</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">事前連絡あり</p>
                                </div>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ $customerData['cancel_rate'] }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                                <div class="bg-yellow-600 h-3 rounded-full" style="width: {{ min($customerData['cancel_rate'], 100) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- キャンセル顧客一覧 -->
                    @if(isset($customerData['cancelled_customers']) && count($customerData['cancelled_customers']) > 0)
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3 border border-yellow-200 dark:border-yellow-800">
                            <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-300 mb-2">キャンセル顧客（最新10件）</h4>
                            <div class="space-y-1 max-h-48 overflow-y-auto">
                                @foreach($customerData['cancelled_customers'] as $customer)
                                    <div class="flex justify-between items-center text-xs py-1 border-b border-yellow-100 dark:border-yellow-900 last:border-0">
                                        <a href="{{ route('filament.admin.resources.customers.view', ['record' => $customer['customer_id'] ?? '#']) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                           target="_blank">
                                            {{ $customer['customer_name'] }}
                                        </a>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($customer['reservation_date'])->format('m/d') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- ノーショー率 -->
                <div>
                    <div class="flex items-center mb-4">
                        <div class="flex-1">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <span class="text-base font-medium text-gray-700 dark:text-gray-300">ノーショー率</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">無断キャンセル</p>
                                </div>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ $customerData['no_show_rate'] }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                                <div class="bg-red-600 h-3 rounded-full" style="width: {{ min($customerData['no_show_rate'], 100) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ノーショー顧客一覧 -->
                    @if(isset($customerData['no_show_customers']) && count($customerData['no_show_customers']) > 0)
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border border-red-200 dark:border-red-800">
                            <h4 class="text-sm font-semibold text-red-800 dark:text-red-300 mb-2">ノーショー顧客（最新10件）</h4>
                            <div class="space-y-1 max-h-48 overflow-y-auto">
                                @foreach($customerData['no_show_customers'] as $customer)
                                    <div class="flex justify-between items-center text-xs py-1 border-b border-red-100 dark:border-red-900 last:border-0">
                                        <a href="{{ route('filament.admin.resources.customers.view', ['record' => $customer['customer_id'] ?? '#']) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                           target="_blank">
                                            {{ $customer['customer_name'] }}
                                        </a>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($customer['reservation_date'])->format('m/d') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-200 dark:border-green-800">
                            <p class="text-sm text-green-800 dark:text-green-300 text-center">
                                ✓ ノーショーなし
                            </p>
                        </div>
                    @endif
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
                            labels: {
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                padding: 15,
                                color: 'rgb(55, 65, 81)'
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 14
                            },
                            padding: 12
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
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
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
                        },
                        tooltip: {
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 14
                            },
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 12
                                }
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