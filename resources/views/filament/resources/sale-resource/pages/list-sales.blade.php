<x-filament-panels::page>
    {{-- ローディングオーバーレイ --}}
    <div id="customLoading" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-xl">
            <div class="flex items-center gap-3">
                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-lg font-semibold text-gray-700 dark:text-gray-300">読み込み中...</span>
            </div>
        </div>
    </div>

    {{-- フィルタセクション --}}
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            {{-- 店舗セレクト --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">店舗</label>
                <select wire:model.live="storeId" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    @if(auth()->user()->hasRole('super_admin'))
                        <option value="">全店舗</option>
                    @endif
                    @foreach($this->stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 開始日 --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-1">
                    <x-heroicon-o-calendar class="w-4 h-4" />
                    開始日
                </label>
                <input type="date" wire:model.live="dateFrom" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            {{-- 終了日 --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-1">
                    <x-heroicon-o-calendar class="w-4 h-4" />
                    終了日
                </label>
                <input type="date" wire:model.live="dateTo" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </div>

        {{-- クイックボタン --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">クイック選択</label>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                <button wire:click="setToday" style="background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); color: #ffffff !important;" class="px-4 py-2.5 text-sm font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 flex items-center gap-2 border-2 border-blue-900">
                    <x-heroicon-o-calendar-days class="w-5 h-5" style="color: #ffffff !important;" />
                    <span style="color: #ffffff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">今日</span>
                </button>
                <button wire:click="setThisMonth" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: #ffffff !important;" class="px-4 py-2.5 text-sm font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 flex items-center gap-2 border-2 border-green-900">
                    <x-heroicon-o-chart-bar class="w-5 h-5" style="color: #ffffff !important;" />
                    <span style="color: #ffffff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">今月</span>
                </button>
                <button wire:click="setLastMonth" style="background: #7c3aed; color: #ffffff !important;" class="px-4 py-2 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-1 border-2 border-purple-800 hover:bg-purple-700">
                    <span style="color: #ffffff !important;">先月</span>
                </button>
                <button wire:click="setLast30Days" style="background: #ea580c; color: #ffffff !important;" class="px-4 py-2 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-1 border-2 border-orange-800 hover:bg-orange-700">
                    <span style="color: #ffffff !important;">30日</span>
                </button>
            </div>
        </div>

        {{-- 現在のフィルタ表示 --}}
        <div class="text-sm text-gray-600 dark:text-gray-400">
            期間: <span class="font-semibold">{{ $this->dateFrom }}</span> 〜 <span class="font-semibold">{{ $this->dateTo }}</span>
            （{{ \Carbon\Carbon::parse($this->dateFrom)->diffInDays(\Carbon\Carbon::parse($this->dateTo)) + 1 }}日間）
        </div>
    </div>

    {{-- 統計情報セクション --}}
    <div class="mb-6 space-y-4">
        {{-- 支払方法別売上 --}}
        <div>
            <h3 class="text-lg font-semibold mb-3">支払方法別売上（期間）</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                @foreach($this->stats['payment_methods'] ?? [] as $method)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-{{ $method['color'] }}-500">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $method['label'] }}</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            ¥{{ number_format($method['amount']) }}
                        </div>
                    </div>
                @endforeach
                <div class="bg-green-50 dark:bg-green-900 rounded-lg shadow p-4 border-2 border-green-500">
                    <div class="text-sm text-gray-600 dark:text-gray-400">合計金額</div>
                    <div class="text-2xl font-bold text-green-700 dark:text-green-300">
                        ¥{{ number_format($this->stats['total_amount'] ?? 0) }}
                    </div>
                </div>
            </div>
        </div>

        {{-- 利用形態別件数 --}}
        <div>
            <h3 class="text-lg font-semibold mb-3">利用形態別件数（期間）</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($this->stats['sources'] ?? [] as $source)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ $source['label'] }}</div>
                            <span class="px-2 py-1 text-xs rounded-full bg-{{ $source['color'] }}-100 text-{{ $source['color'] }}-700">
                                {{ $source['count'] }}件
                            </span>
                        </div>

                        {{-- 売上金額の表示 --}}
                        @if($source['label'] === 'スポット')
                            <div class="text-sm text-gray-600 dark:text-gray-400">施術売上</div>
                            <div class="text-lg font-semibold {{ $source['amount'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}">
                                ¥{{ number_format($source['amount']) }}
                            </div>
                        @elseif($source['label'] === 'サブスク')
                            <div class="text-sm text-gray-600 dark:text-gray-400">決済売上</div>
                            <div class="text-lg font-semibold {{ $source['amount'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}">
                                ¥{{ number_format($source['amount']) }}
                            </div>
                        @elseif($source['label'] === '回数券')
                            <div class="text-sm text-gray-600 dark:text-gray-400">販売売上</div>
                            <div class="text-lg font-semibold {{ $source['amount'] > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}">
                                ¥{{ number_format($source['amount']) }}
                            </div>
                        @endif

                        {{-- サブスク：今月入金見込み --}}
                        @if(isset($source['expected_revenue']))
                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                <div class="text-xs text-gray-500 dark:text-gray-400">今月入金見込み</div>
                                <div class="text-xl font-bold text-green-600 dark:text-green-400">¥{{ number_format($source['expected_revenue']) }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">契約人数: {{ $source['contract_count'] }}人</div>
                            </div>
                        @endif
                    </div>
                @endforeach
                <div class="bg-blue-50 dark:bg-blue-900 rounded-lg shadow p-4 border-2 border-blue-500">
                    <div class="text-sm text-gray-600 dark:text-gray-400">合計件数</div>
                    <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">
                        {{ number_format($this->stats['total_count'] ?? 0) }}件
                    </div>
                </div>
            </div>
        </div>

        {{-- 売上推移グラフ --}}
        @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        @endonce

        <div class="mb-6 flex justify-center">
            <div class="w-full max-w-4xl">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <x-heroicon-o-chart-bar-square class="w-5 h-5 text-indigo-600" />
                        売上推移（日別）
                    </h3>
                    <div class="relative" style="height: 300px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        @script
        <script>
            let salesChartInstance = null;
            const loadingEl = document.getElementById('customLoading');

            function showLoading() {
                if (loadingEl) {
                    loadingEl.style.display = 'flex';
                }
            }

            function hideLoading() {
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
            }

            function initSalesChart(labels, data) {
                console.log('🔍 initSalesChart called');
                console.log('📊 Labels:', labels);
                console.log('💰 Data:', data);

                const canvas = document.getElementById('salesChart');
                if (!canvas) {
                    console.error('❌ Canvas not found');
                    hideLoading();
                    return;
                }

                // Destroy existing chart
                if (salesChartInstance) {
                    console.log('🗑️ Destroying existing chart');
                    salesChartInstance.destroy();
                }

                console.log('🎯 Creating new chart...');
                try {
                    salesChartInstance = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '売上金額',
                                data: data,
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.3,
                                fill: true,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: 'rgb(59, 130, 246)',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return '売上: ¥' + context.parsed.y.toLocaleString('ja-JP');
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '¥' + value.toLocaleString('ja-JP');
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                    console.log('✅ Chart created successfully!');
                    hideLoading();
                } catch (error) {
                    console.error('❌ Error creating chart:', error);
                    hideLoading();
                }
            }

            // 初回読み込み
            const initialLabels = {!! json_encode($this->stats['chart_labels'] ?? []) !!};
            const initialData = {!! json_encode($this->stats['chart_data'] ?? []) !!};

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    initSalesChart(initialLabels, initialData);
                });
            } else {
                initSalesChart(initialLabels, initialData);
            }

            // Livewireイベントで更新
            Livewire.on('chart-update', (event) => {
                console.log('🔄 Chart update event received:', event);
                showLoading();
                // 少し遅延させてスムーズに
                setTimeout(() => {
                    initSalesChart(event.labels, event.data);
                }, 100);
            });

            // Livewireのリクエスト開始/終了を監視
            document.addEventListener('livewire:initialized', () => {
                Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                    // リクエスト開始
                    showLoading();

                    // レスポンス処理
                    succeed(({ snapshot, effect }) => {
                        hideLoading();
                    });

                    fail(() => {
                        hideLoading();
                    });
                });
            });
        </script>
        @endscript

        {{-- スタッフ別売上：3パターン --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- 1. 施術スタッフ別 --}}
            @if(!empty($this->stats['top_staff_by_sales']))
                <div>
                    <h3 class="text-lg font-semibold mb-3 flex items-center gap-2">
                        <x-heroicon-o-user-circle class="w-5 h-5 text-blue-600" />
                        施術スタッフ別売上（期間）
                    </h3>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">順位</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">スタッフ</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">件数</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">売上</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($this->stats['top_staff_by_sales'] as $staff)
                                    <tr>
                                        <td class="px-4 py-2 text-center">
                                            @if($staff['rank'] <= 3)
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 text-yellow-600">
                                                    <x-heroicon-s-trophy class="w-4 h-4" />
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-600">{{ $staff['rank'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm font-medium">{{ $staff['name'] }}</td>
                                        <td class="px-4 py-2 text-sm text-right">{{ $staff['count'] }}件</td>
                                        <td class="px-4 py-2 text-sm font-bold text-right">¥{{ number_format($staff['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 2. 指名スタッフ別 --}}
            @if(!empty($this->stats['top_staff_by_reservation']))
                <div>
                    <h3 class="text-lg font-semibold mb-3 flex items-center gap-2">
                        <x-heroicon-o-hand-raised class="w-5 h-5 text-green-600" />
                        指名スタッフ別売上（期間）
                    </h3>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">順位</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">スタッフ</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">件数</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">売上</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($this->stats['top_staff_by_reservation'] as $staff)
                                    <tr>
                                        <td class="px-4 py-2 text-center">
                                            @if($staff['rank'] <= 3)
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 text-yellow-600">
                                                    <x-heroicon-s-trophy class="w-4 h-4" />
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-600">{{ $staff['rank'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm font-medium">{{ $staff['name'] }}</td>
                                        <td class="px-4 py-2 text-sm text-right">{{ $staff['count'] }}件</td>
                                        <td class="px-4 py-2 text-sm font-bold text-right">¥{{ number_format($staff['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 3. 販売スタッフ別（回数券） --}}
            @if(!empty($this->stats['top_staff_by_ticket_sales']))
                <div>
                    <h3 class="text-lg font-semibold mb-3 flex items-center gap-2">
                        <x-heroicon-o-ticket class="w-5 h-5 text-purple-600" />
                        販売スタッフ別売上（期間）
                    </h3>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">順位</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">スタッフ</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">販売数</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">売上</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($this->stats['top_staff_by_ticket_sales'] as $staff)
                                    <tr>
                                        <td class="px-4 py-2 text-center">
                                            @if($staff['rank'] <= 3)
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 text-yellow-600">
                                                    <x-heroicon-s-trophy class="w-4 h-4" />
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-600">{{ $staff['rank'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm font-medium">{{ $staff['name'] }}</td>
                                        <td class="px-4 py-2 text-sm text-right">{{ $staff['count'] }}枚</td>
                                        <td class="px-4 py-2 text-sm font-bold text-right">¥{{ number_format($staff['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- 元のテーブル --}}
    {{ $this->table }}

    {{-- データ説明セクション --}}
    <div class="mt-8 p-6 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500" />
            データ集計について
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs text-gray-600 dark:text-gray-400">
            {{-- 施術スタッフ --}}
            <div class="bg-white dark:bg-gray-900 p-4 rounded-lg">
                <div class="font-semibold text-blue-600 dark:text-blue-400 mb-2 flex items-center gap-1">
                    <x-heroicon-o-user-circle class="w-4 h-4" />
                    施術スタッフ別売上
                </div>
                <p class="leading-relaxed">
                    <span class="font-medium">集計元：</span>売上テーブル（sales.staff_id）<br>
                    <span class="font-medium">対象：</span>実際に施術を担当したスタッフ<br>
                    <span class="font-medium">金額：</span>施術料金 + 物販の合計
                </p>
            </div>

            {{-- 指名スタッフ --}}
            <div class="bg-white dark:bg-gray-900 p-4 rounded-lg">
                <div class="font-semibold text-green-600 dark:text-green-400 mb-2 flex items-center gap-1">
                    <x-heroicon-o-hand-raised class="w-4 h-4" />
                    指名スタッフ別売上
                </div>
                <p class="leading-relaxed">
                    <span class="font-medium">集計元：</span>予約テーブル（reservations.staff_id）<br>
                    <span class="font-medium">対象：</span>予約時に顧客が指名したスタッフ<br>
                    <span class="font-medium">金額：</span>その予約から発生した売上金額
                </p>
            </div>

            {{-- 販売スタッフ --}}
            <div class="bg-white dark:bg-gray-900 p-4 rounded-lg">
                <div class="font-semibold text-purple-600 dark:text-purple-400 mb-2 flex items-center gap-1">
                    <x-heroicon-o-ticket class="w-4 h-4" />
                    販売スタッフ別売上
                </div>
                <p class="leading-relaxed">
                    <span class="font-medium">集計元：</span>回数券テーブル（customer_tickets.sold_by）<br>
                    <span class="font-medium">対象：</span>回数券を販売したスタッフ<br>
                    <span class="font-medium">金額：</span>回数券の購入金額
                </p>
            </div>
        </div>

        {{-- サブスク情報 --}}
        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
            <p class="text-xs text-blue-700 dark:text-blue-300">
                <span class="font-semibold">サブスク契約について：</span>
                「今月入金見込み」は、現在アクティブな全契約の月額料金（monthly_price）合計です。店舗フィルタが選択されている場合は、その店舗の契約のみを集計します。
            </p>
        </div>
    </div>
</x-filament-panels::page>
