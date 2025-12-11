<div>
    {{-- タブナビゲーション --}}
    <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
            <li class="mr-2">
                <button wire:click="setTab('charts')"
                    class="inline-block p-4 border-b-2 rounded-t-lg {{ $activeTab === 'charts' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    ビジュアル分析
                </button>
            </li>
            <li class="mr-2">
                <button wire:click="setTab('tracking')"
                    class="inline-block p-4 border-b-2 rounded-t-lg {{ $activeTab === 'tracking' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    新規結果（個別）
                </button>
            </li>
            <li class="mr-2">
                <button wire:click="setTab('source')"
                    class="inline-block p-4 border-b-2 rounded-t-lg {{ $activeTab === 'source' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    媒体別集計
                </button>
            </li>
            <li class="mr-2">
                <button wire:click="setTab('handler')"
                    class="inline-block p-4 border-b-2 rounded-t-lg {{ $activeTab === 'handler' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    対応者別集計
                </button>
            </li>
            <li class="mr-2">
                <button wire:click="setTab('subscription')"
                    class="inline-block p-4 border-b-2 rounded-t-lg {{ $activeTab === 'subscription' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    サブスク内訳
                </button>
            </li>
            <li class="mr-2">
                <button wire:click="setTab('monthly')"
                    class="inline-block p-4 border-b-2 rounded-t-lg {{ $activeTab === 'monthly' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    月別集計
                </button>
            </li>
        </ul>
    </div>

    {{-- CSVエクスポートボタン --}}
    <div class="mb-4 flex justify-end">
        <button wire:click="exportCsv"
            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            CSVエクスポート
        </button>
    </div>

    {{-- ビジュアル分析タブ --}}
    @if($activeTab === 'charts')
        <div class="space-y-6">
            {{-- ファネルチャート --}}
            <x-filament::card>
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">新規顧客ファネル</h2>
                    <p class="text-sm text-gray-500">期間: {{ $startDate }} 〜 {{ $endDate }}</p>
                </div>
                <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm text-gray-700 dark:text-gray-300">
                    <p class="font-medium mb-1">この図の見方:</p>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>新規予約 → 1回目来店 → 2回目来店 → 3回目来店 → サブスク契約の流れを可視化</li>
                        <li>各段階での離脱率を確認できます</li>
                    </ul>
                </div>
                <div class="flex flex-col items-center space-y-2" id="funnel-container">
                    @foreach($funnelData['labels'] ?? [] as $index => $label)
                        @php
                            $value = $funnelData['values'][$index] ?? 0;
                            $percentage = $funnelData['percentages'][$index] ?? 0;
                            $color = $funnelData['colors'][$index] ?? '#3B82F6';
                            $maxValue = max($funnelData['values'] ?? [1]);
                            $width = $maxValue > 0 ? max(20, ($value / $maxValue) * 100) : 20;
                        @endphp
                        <div class="relative w-full flex items-center justify-center">
                            <div class="relative flex items-center justify-center py-3 rounded-lg text-white font-semibold transition-all duration-300"
                                 style="background-color: {{ $color }}; width: {{ $width }}%; min-width: 150px;">
                                <span class="text-sm">{{ $label }}</span>
                                <span class="ml-2 text-lg font-bold">{{ $value }}人</span>
                                <span class="ml-2 text-xs opacity-80">({{ $percentage }}%)</span>
                            </div>
                        </div>
                        @if($index < count($funnelData['labels']) - 1)
                            @php
                                $nextValue = $funnelData['values'][$index + 1] ?? 0;
                                $dropRate = $value > 0 ? round((1 - $nextValue / $value) * 100, 1) : 0;
                            @endphp
                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                </svg>
                                離脱率: {{ $dropRate }}%
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-filament::card>

            {{-- 結果内訳 円グラフ + 打率ランキング --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- 結果内訳（ドーナツグラフ風） --}}
                <x-filament::card>
                    <div class="mb-4">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $visitNumber }}回目 結果内訳</h2>
                    </div>
                    <div class="flex flex-wrap justify-center gap-4">
                        @php
                            $totalPie = collect($resultPieData)->sum('value');
                        @endphp
                        @foreach($resultPieData as $item)
                            @php
                                $piePercent = $totalPie > 0 ? round($item['value'] / $totalPie * 100, 1) : 0;
                            @endphp
                            <div class="flex flex-col items-center p-3 rounded-lg" style="background-color: {{ $item['color'] }}20;">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-lg"
                                     style="background-color: {{ $item['color'] }};">
                                    {{ $item['value'] }}
                                </div>
                                <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                <span class="text-xs text-gray-500">{{ $piePercent }}%</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::card>

                {{-- 対応者別 打率ランキング --}}
                <x-filament::card>
                    <div class="mb-4">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">対応者 打率ランキング</h2>
                        <p class="text-xs text-gray-500">打率 = (サブスク+回数券+次回予約) / 総数</p>
                    </div>
                    <div class="space-y-3">
                        @foreach(array_slice($handlerRankingData, 0, 8) as $index => $item)
                            @php
                                $barColor = $item['rate'] >= 50 ? '#10B981' : ($item['rate'] >= 30 ? '#F59E0B' : '#6B7280');
                                $medal = match($index) {
                                    0 => '🥇',
                                    1 => '🥈',
                                    2 => '🥉',
                                    default => ''
                                };
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="w-6 text-center">{{ $medal ?: ($index + 1) }}</span>
                                <span class="w-24 text-sm truncate" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-5 relative">
                                    <div class="h-5 rounded-full flex items-center justify-end pr-2"
                                         style="width: {{ $item['rate'] }}%; background-color: {{ $barColor }};">
                                        <span class="text-xs text-white font-semibold">{{ $item['rate'] }}%</span>
                                    </div>
                                </div>
                                <span class="w-12 text-xs text-gray-500 text-right">{{ $item['positive'] }}/{{ $item['total'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::card>
            </div>

            {{-- 媒体別ランキング --}}
            <x-filament::card>
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">媒体別 打率ランキング</h2>
                    <p class="text-xs text-gray-500">どの媒体からの顧客が契約しやすいか</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($sourceRankingData as $index => $item)
                        @php
                            $cardColor = $item['rate'] >= 50 ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                                : ($item['rate'] >= 30 ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800'
                                : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700');
                            $rateColor = $item['rate'] >= 50 ? 'text-green-600' : ($item['rate'] >= 30 ? 'text-yellow-600' : 'text-gray-600');
                        @endphp
                        <div class="p-4 rounded-lg border {{ $cardColor }}">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</span>
                                <span class="text-2xl font-bold {{ $rateColor }}">{{ $item['rate'] }}%</span>
                            </div>
                            <div class="text-sm text-gray-500">
                                成約 {{ $item['positive'] }} / {{ $item['total'] }}件
                            </div>
                            <div class="mt-2 bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $item['rate'] >= 50 ? 'bg-green-500' : ($item['rate'] >= 30 ? 'bg-yellow-500' : 'bg-gray-400') }}"
                                     style="width: {{ $item['rate'] }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::card>

            {{-- 月別トレンド --}}
            <x-filament::card>
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">月別トレンド</h2>
                </div>
                <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm text-gray-700 dark:text-gray-300">
                    <p class="font-medium mb-1">この図の見方:</p>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>青: 新規顧客数 / 緑: サブスク契約数 / オレンジ: 打率(%)</li>
                    </ul>
                </div>
                @if(count($monthlyTrendData['labels'] ?? []) > 0)
                    <div class="relative" style="height: 300px;"
                         x-data="{ chartData: @js($monthlyTrendData) }"
                         x-init="
                            const ctx = $el.querySelector('canvas');
                            if (window.monthlyChart) window.monthlyChart.destroy();
                            window.monthlyChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: chartData.labels || [],
                                    datasets: (chartData.datasets || []).map(ds => ({
                                        ...ds,
                                        tension: 0.3,
                                        fill: true
                                    }))
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    scales: {
                                        y: {
                                            type: 'linear',
                                            display: true,
                                            position: 'left',
                                            title: { display: true, text: '人数' }
                                        },
                                        y1: {
                                            type: 'linear',
                                            display: true,
                                            position: 'right',
                                            min: 0,
                                            max: 100,
                                            title: { display: true, text: '打率(%)' },
                                            grid: { drawOnChartArea: false }
                                        }
                                    },
                                    plugins: { legend: { position: 'top' } }
                                }
                            });
                         ">
                        <canvas></canvas>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">データがありません</div>
                @endif
            </x-filament::card>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

            {{-- ヒートマップ --}}
            <x-filament::card>
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">対応者 × 結果 ヒートマップ</h2>
                    <p class="text-xs text-gray-500">色が濃いほど件数が多い（上位10名）</p>
                </div>
                @if(count($heatmapData['handlers'] ?? []) > 0)
                    <div class="overflow-x-auto">
                        <table class="text-xs border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-2 py-1 text-left"></th>
                                    @foreach($heatmapData['results'] ?? [] as $result)
                                        <th class="px-2 py-1 text-center whitespace-nowrap">{{ $result }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($heatmapData['handlers'] ?? [] as $hIndex => $handler)
                                    <tr>
                                        <td class="px-2 py-1 font-medium whitespace-nowrap">{{ $handler }}</td>
                                        @foreach($heatmapData['results'] ?? [] as $rIndex => $result)
                                            @php
                                                $cellData = collect($heatmapData['data'] ?? [])->first(fn($d) => $d['x'] == $rIndex && $d['y'] == $hIndex);
                                                $cellValue = $cellData['v'] ?? 0;
                                                $maxVal = $heatmapData['maxValue'] ?? 1;
                                                $intensity = $maxVal > 0 ? $cellValue / $maxVal : 0;
                                                $bgColor = $cellValue > 0
                                                    ? sprintf('rgba(59, 130, 246, %.2f)', 0.1 + $intensity * 0.8)
                                                    : 'transparent';
                                                $textColor = $intensity > 0.5 ? 'white' : 'inherit';
                                            @endphp
                                            <td class="px-3 py-2 text-center border border-gray-200 dark:border-gray-700"
                                                style="background-color: {{ $bgColor }}; color: {{ $textColor }};">
                                                {{ $cellValue > 0 ? $cellValue : '-' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">データがありません</div>
                @endif
            </x-filament::card>

            {{-- AI分析セクション --}}
            <x-filament::card>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            AI分析（Claude）
                        </h2>
                        <p class="text-sm text-gray-500">データをもとにAIが傾向分析と改善提案を行います</p>
                    </div>
                    @if($aiAvailable)
                        <button wire:click="runAiAnalysis"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                                class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                            <span wire:loading.remove wire:target="runAiAnalysis">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                分析を実行
                            </span>
                            <span wire:loading wire:target="runAiAnalysis" class="flex items-center">
                                <svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                分析中...
                            </span>
                        </button>
                    @else
                        <div class="text-sm text-gray-500 bg-gray-100 dark:bg-gray-800 px-4 py-2 rounded-lg">
                            <svg class="w-4 h-4 inline mr-1 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            APIキー未設定
                        </div>
                    @endif
                </div>

                {{-- エラー表示 --}}
                @if($aiAnalysisError)
                    <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-center text-red-700 dark:text-red-400">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $aiAnalysisError }}
                        </div>
                    </div>
                @endif

                {{-- 分析結果 --}}
                @if($aiAnalysisResult)
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                            <div class="whitespace-pre-wrap text-gray-800 dark:text-gray-200">{{ $aiAnalysisResult }}</div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 text-right">
                            分析日時: {{ now()->format('Y/m/d H:i') }}
                        </p>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        <p>「分析を実行」ボタンをクリックすると、AIが現在のデータを分析します</p>
                        <p class="text-xs mt-1">分析には30秒〜1分程度かかります</p>
                    </div>
                @endif
            </x-filament::card>
        </div>
    @endif

    {{-- 新規結果（個別）タブ --}}
    @if($activeTab === 'tracking')
        <x-filament::card>
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">新規顧客追跡（1回目→2回目→3回目）</h2>
                <p class="text-sm text-gray-500">期間: {{ $startDate }} 〜 {{ $endDate }}</p>
                <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm text-gray-700 dark:text-gray-300">
                    <p class="font-medium mb-1">この表の見方:</p>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>期間内に初めて予約した顧客を一覧表示</li>
                        <li>各顧客の1回目・2回目・3回目の来店結果を追跡</li>
                        <li>結果: サブスク契約/回数券購入/次回予約あり/予約なし/キャンセル/飛び（ノーショー）</li>
                    </ul>
                </div>
            </div>

            @if(count($trackingData) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse border border-gray-300 dark:border-gray-600">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">顧客名</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">電話番号</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">店舗</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">媒体</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">1回目日付</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">1回目対応</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center whitespace-nowrap bg-blue-50 dark:bg-blue-900/20">1回目結果</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">2回目日付</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">2回目対応</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center whitespace-nowrap bg-green-50 dark:bg-green-900/20">2回目結果</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">3回目日付</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left whitespace-nowrap">3回目対応</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center whitespace-nowrap bg-purple-50 dark:bg-purple-900/20">3回目結果</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900">
                            @foreach($trackingData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['customer_name'] }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['phone'] }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['store'] ?? '-' }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['source'] }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['visit1_date'] }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['visit1_handler'] }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center whitespace-nowrap bg-blue-50 dark:bg-blue-900/20">
                                        @include('livewire.marketing._result-badge', ['result' => $row['visit1_result']])
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['visit2_date'] ?? '-' }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['visit2_handler'] ?? '-' }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center whitespace-nowrap bg-green-50 dark:bg-green-900/20">
                                        @include('livewire.marketing._result-badge', ['result' => $row['visit2_result'] ?? '-'])
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['visit3_date'] ?? '-' }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 whitespace-nowrap">{{ $row['visit3_handler'] ?? '-' }}</td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-center whitespace-nowrap bg-purple-50 dark:bg-purple-900/20">
                                        @include('livewire.marketing._result-badge', ['result' => $row['visit3_result'] ?? '-'])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    全 {{ count($trackingData) }} 件
                </div>
            @else
                <div class="text-center py-8 text-gray-500">データがありません</div>
            @endif
        </x-filament::card>
    @endif

    {{-- 媒体別集計タブ --}}
    @if($activeTab === 'source')
        <x-filament::card>
            <div class="mb-4 flex items-center gap-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">媒体別 × 結果クロス集計</h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">集計対象:</span>
                    @foreach([1, 2, 3] as $num)
                        <button wire:click="setVisitNumber({{ $num }})"
                            class="px-3 py-1 text-sm rounded {{ $visitNumber === $num ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            {{ $num }}回目
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm text-gray-700 dark:text-gray-300">
                <p class="font-medium mb-1">この表の見方:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li>「媒体」= 顧客がどこから来たか（ホットペッパー、紹介、HPなど）</li>
                    <li>各媒体ごとに、N回目の来店結果を集計</li>
                    <li>「打率」= (サブスク + 回数券 + 次回予約) ÷ 総計 × 100%</li>
                    <li>打率が高い媒体 = 効果的な集客チャネル</li>
                </ul>
            </div>

            @if(count($sourceData) > 0)
                @php
                    $resultTypes = ['キャンセル', 'サブスク', '回数券', '次回予約', '飛び', '予約なし'];
                @endphp
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse border border-gray-300 dark:border-gray-600">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left whitespace-nowrap">媒体</th>
                                @foreach($resultTypes as $type)
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap">{{ $type }}</th>
                                @endforeach
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap bg-yellow-50 dark:bg-yellow-900/20">総計</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap bg-green-50 dark:bg-green-900/20">打率</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900">
                            @foreach($sourceData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 {{ $row['source'] === '総計' ? 'font-bold bg-gray-100 dark:bg-gray-800' : '' }}">
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 whitespace-nowrap">{{ $row['source'] }}</td>
                                    @foreach($resultTypes as $type)
                                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                            {{ $row[$type] ?? 0 }}
                                        </td>
                                    @endforeach
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">
                                        {{ $row['total'] }}
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-green-50 dark:bg-green-900/20">
                                        <span class="{{ $row['rate'] >= 0.5 ? 'text-green-600 font-bold' : ($row['rate'] >= 0.3 ? 'text-blue-600' : 'text-gray-600') }}">
                                            {{ number_format($row['rate'] * 100, 0) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">データがありません</div>
            @endif
        </x-filament::card>
    @endif

    {{-- 対応者別集計タブ --}}
    @if($activeTab === 'handler')
        <x-filament::card>
            <div class="mb-4 flex items-center gap-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">対応者別 × 結果クロス集計</h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">集計対象:</span>
                    @foreach([1, 2, 3] as $num)
                        <button wire:click="setVisitNumber({{ $num }})"
                            class="px-3 py-1 text-sm rounded {{ $visitNumber === $num ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            {{ $num }}回目
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm text-gray-700 dark:text-gray-300">
                <p class="font-medium mb-1">この表の見方:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li>「対応者」= カルテに記録された施術担当者（staff_id または handled_by）</li>
                    <li>各対応者ごとに、N回目の来店結果を集計</li>
                    <li>「打率」= (サブスク + 回数券 + 次回予約) ÷ 総計 × 100%</li>
                    <li>打率が高い対応者 = 新規顧客獲得に貢献しているスタッフ</li>
                    <li>「不明」= カルテ未作成またはカルテに対応者未記入の顧客</li>
                </ul>
            </div>

            @if(count($handlerData) > 0)
                @php
                    $resultTypes = ['キャンセル', 'サブスク', '回数券', '次回予約', '飛び', '予約なし'];
                @endphp
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border-collapse border border-gray-300 dark:border-gray-600">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left whitespace-nowrap">対応者</th>
                                @foreach($resultTypes as $type)
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap">{{ $type }}</th>
                                @endforeach
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap bg-yellow-50 dark:bg-yellow-900/20">総計</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap bg-green-50 dark:bg-green-900/20">打率</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900">
                            @foreach($handlerData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 {{ $row['handler'] === '総計' ? 'font-bold bg-gray-100 dark:bg-gray-800' : '' }}">
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 whitespace-nowrap">{{ $row['handler'] }}</td>
                                    @foreach($resultTypes as $type)
                                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                            {{ $row[$type] ?? 0 }}
                                        </td>
                                    @endforeach
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">
                                        {{ $row['total'] }}
                                    </td>
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-green-50 dark:bg-green-900/20">
                                        <span class="{{ $row['rate'] >= 0.5 ? 'text-green-600 font-bold' : ($row['rate'] >= 0.3 ? 'text-blue-600' : 'text-gray-600') }}">
                                            {{ number_format($row['rate'] * 100, 0) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- 次回予約→サブスク転換率 --}}
                <div class="mt-6">
                    <h3 class="text-md font-bold text-gray-900 dark:text-white mb-2">次回予約→サブスク転換率</h3>
                    <div class="overflow-x-auto">
                        <table class="text-xs border-collapse border border-gray-300 dark:border-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">対応者</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">次回予約率</th>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">次回予約→サブスク</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900">
                                @foreach($handlerData as $row)
                                    @if($row['handler'] !== '総計')
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">{{ $row['handler'] }}</td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                                {{ number_format($row['rate'] * 100, 0) }}%
                                            </td>
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                                @php
                                                    $conversionRate = $row['total'] > 0 ? (($row['サブスク'] ?? 0) / $row['total']) : 0;
                                                @endphp
                                                {{ number_format($conversionRate * 100, 0) }}%
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">データがありません</div>
            @endif
        </x-filament::card>
    @endif

    {{-- サブスク内訳タブ --}}
    @if($activeTab === 'subscription')
        <x-filament::card>
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">サブスク契約内訳</h2>
            </div>
            <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm text-gray-700 dark:text-gray-300">
                <p class="font-medium mb-1">この表の見方:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li>期間内にサブスク契約した新規顧客を、契約プラン別に集計</li>
                    <li>「媒体別」= どの集客チャネルからどのプランに契約したか</li>
                    <li>「対応者別」= どのスタッフがどのプランを契約させたか</li>
                    <li>高単価プランへの誘導が上手いスタッフの分析に活用</li>
                </ul>
            </div>

            @if(count($subscriptionData) > 0)
                {{-- 媒体別サブスク --}}
                <div class="mb-6">
                    <h3 class="text-md font-bold text-gray-700 dark:text-gray-300 mb-2">媒体別サブスク契約数</h3>
                    @php
                        $sourceSubscriptions = collect($subscriptionData)->where('group_type', 'source');
                        $planNames = $sourceSubscriptions->flatMap(fn($r) => array_keys($r['plans'] ?? []))->unique()->values();
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="text-xs border-collapse border border-gray-300 dark:border-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">媒体</th>
                                    @foreach($planNames as $plan)
                                        <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap">{{ $plan }}</th>
                                    @endforeach
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">総計</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900">
                                @foreach($sourceSubscriptions as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 {{ $row['name'] === '総計' ? 'font-bold bg-gray-100 dark:bg-gray-800' : '' }}">
                                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">{{ $row['name'] }}</td>
                                        @foreach($planNames as $plan)
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                                {{ $row['plans'][$plan] ?? 0 }}
                                            </td>
                                        @endforeach
                                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">
                                            {{ $row['total'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 対応者別サブスク --}}
                <div>
                    <h3 class="text-md font-bold text-gray-700 dark:text-gray-300 mb-2">対応者別サブスク契約数</h3>
                    @php
                        $handlerSubscriptions = collect($subscriptionData)->where('group_type', 'handler');
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="text-xs border-collapse border border-gray-300 dark:border-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">対応者</th>
                                    @foreach($planNames as $plan)
                                        <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap">{{ $plan }}</th>
                                    @endforeach
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">総計</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900">
                                @foreach($handlerSubscriptions as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 {{ $row['name'] === '総計' ? 'font-bold bg-gray-100 dark:bg-gray-800' : '' }}">
                                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">{{ $row['name'] }}</td>
                                        @foreach($planNames as $plan)
                                            <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                                {{ $row['plans'][$plan] ?? 0 }}
                                            </td>
                                        @endforeach
                                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">
                                            {{ $row['total'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">データがありません</div>
            @endif
        </x-filament::card>
    @endif

    {{-- 月別集計タブ --}}
    @if($activeTab === 'monthly')
        <x-filament::card>
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">月別 × 対応者別 新規対応数</h2>
            </div>
            <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm text-gray-700 dark:text-gray-300">
                <p class="font-medium mb-1">この表の見方:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li>月ごとの新規顧客対応数を、対応者別に表示</li>
                    <li>各スタッフが毎月何人の新規顧客を対応したかを確認</li>
                    <li>新規対応の偏りや、スタッフ配置の最適化に活用</li>
                </ul>
            </div>

            @if(count($monthlyData) > 0)
                @php
                    $months = collect($monthlyData)->pluck('month')->unique()->sort()->values();
                    $handlers = collect($monthlyData)->pluck('handler')->unique()->filter()->values();
                    $monthlyPivot = collect($monthlyData)->groupBy('month');
                @endphp
                <div class="overflow-x-auto">
                    <table class="text-xs border-collapse border border-gray-300 dark:border-gray-600">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-left">月</th>
                                @foreach($handlers as $handler)
                                    <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center whitespace-nowrap">{{ $handler }}</th>
                                @endforeach
                                <th class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">総計</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900">
                            @foreach($months as $month)
                                @php
                                    $monthData = $monthlyPivot->get($month, collect());
                                    $monthTotal = $monthData->sum('count');
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 whitespace-nowrap">{{ $month }}</td>
                                    @foreach($handlers as $handler)
                                        @php
                                            $handlerData = $monthData->firstWhere('handler', $handler);
                                        @endphp
                                        <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                            {{ $handlerData['count'] ?? 0 }}
                                        </td>
                                    @endforeach
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">
                                        {{ $monthTotal }}
                                    </td>
                                </tr>
                            @endforeach
                            {{-- 総計行 --}}
                            <tr class="font-bold bg-gray-100 dark:bg-gray-800">
                                <td class="border border-gray-300 dark:border-gray-600 px-3 py-2">総計</td>
                                @foreach($handlers as $handler)
                                    @php
                                        $handlerTotal = collect($monthlyData)->where('handler', $handler)->sum('count');
                                    @endphp
                                    <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center">
                                        {{ $handlerTotal }}
                                    </td>
                                @endforeach
                                <td class="border border-gray-300 dark:border-gray-600 px-3 py-2 text-center bg-yellow-50 dark:bg-yellow-900/20">
                                    {{ collect($monthlyData)->sum('count') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">データがありません</div>
            @endif
        </x-filament::card>
    @endif
</div>
