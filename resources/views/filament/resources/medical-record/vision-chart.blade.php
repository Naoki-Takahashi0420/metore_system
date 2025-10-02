@php
    // $record は viewData から渡される
    $visionRecords = $record->vision_records ?? [];

    // 一意なIDを生成
    $chartId = 'chart_' . $record->id . '_' . uniqid();

    // データを整形
    $dates = [];
    $leftNakedBefore = [];
    $leftNakedAfter = [];
    $rightNakedBefore = [];
    $rightNakedAfter = [];
    $leftCorrectedBefore = [];
    $leftCorrectedAfter = [];
    $rightCorrectedBefore = [];
    $rightCorrectedAfter = [];

    foreach ($visionRecords as $index => $vision) {
        $dates[] = isset($vision['date']) ? \Carbon\Carbon::parse($vision['date'])->format('m/d') : ($index + 1) . '回目';

        $leftNakedBefore[] = isset($vision['before_naked_left']) ? (float)$vision['before_naked_left'] : null;
        $leftNakedAfter[] = isset($vision['after_naked_left']) ? (float)$vision['after_naked_left'] : null;
        $rightNakedBefore[] = isset($vision['before_naked_right']) ? (float)$vision['before_naked_right'] : null;
        $rightNakedAfter[] = isset($vision['after_naked_right']) ? (float)$vision['after_naked_right'] : null;

        $leftCorrectedBefore[] = isset($vision['before_corrected_left']) ? (float)$vision['before_corrected_left'] : null;
        $leftCorrectedAfter[] = isset($vision['after_corrected_left']) ? (float)$vision['after_corrected_left'] : null;
        $rightCorrectedBefore[] = isset($vision['before_corrected_right']) ? (float)$vision['before_corrected_right'] : null;
        $rightCorrectedAfter[] = isset($vision['after_corrected_right']) ? (float)$vision['after_corrected_right'] : null;
    }

    $hasNakedData = !empty(array_filter(array_merge($leftNakedBefore, $leftNakedAfter, $rightNakedBefore, $rightNakedAfter)));
    $hasCorrectedData = !empty(array_filter(array_merge($leftCorrectedBefore, $leftCorrectedAfter, $rightCorrectedBefore, $rightCorrectedAfter)));
@endphp

<div class="space-y-6">
    @if ($hasNakedData)
        <div class="bg-white p-6 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold mb-4 text-gray-900">裸眼視力の推移</h3>
            <canvas id="nakedVisionChart{{ $chartId }}" width="400" height="200"></canvas>
        </div>
    @endif

    @if ($hasCorrectedData)
        <div class="bg-white p-6 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold mb-4 text-gray-900">矯正視力の推移</h3>
            <canvas id="correctedVisionChart{{ $chartId }}" width="400" height="200"></canvas>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    'use strict';

    // データを準備
    const chartData{{ $chartId }} = {
        dates: @json($dates),
        leftNakedBefore: @json($leftNakedBefore),
        leftNakedAfter: @json($leftNakedAfter),
        rightNakedBefore: @json($rightNakedBefore),
        rightNakedAfter: @json($rightNakedAfter),
        leftCorrectedBefore: @json($leftCorrectedBefore),
        leftCorrectedAfter: @json($leftCorrectedAfter),
        rightCorrectedBefore: @json($rightCorrectedBefore),
        rightCorrectedAfter: @json($rightCorrectedAfter),
        hasNakedData: {{ $hasNakedData ? 'true' : 'false' }},
        hasCorrectedData: {{ $hasCorrectedData ? 'true' : 'false' }}
    };

    // Chart.jsが読み込まれるまで待機
    function initCharts{{ $chartId }}() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts{{ $chartId }}, 100);
            return;
        }

        const chartConfig = {
            type: 'line',
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 2.0,
                        ticks: {
                            stepSize: 0.1
                        },
                        title: {
                            display: true,
                            text: '視力'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '測定日'
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        };

        // 裸眼視力グラフ
        if (chartData{{ $chartId }}.hasNakedData) {
            const nakedCanvas = document.getElementById('nakedVisionChart{{ $chartId }}');
            if (nakedCanvas) {
                new Chart(nakedCanvas, {
                    type: 'line',
                    data: {
                        labels: chartData{{ $chartId }}.dates,
                        datasets: [
                            {
                                label: '左眼（施術前）',
                                data: chartData{{ $chartId }}.leftNakedBefore,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.4
                            },
                            {
                                label: '左眼（施術後）',
                                data: chartData{{ $chartId }}.leftNakedAfter,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: '右眼（施術前）',
                                data: chartData{{ $chartId }}.rightNakedBefore,
                                borderColor: 'rgb(54, 162, 235)',
                                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.4
                            },
                            {
                                label: '右眼（施術後）',
                                data: chartData{{ $chartId }}.rightNakedAfter,
                                borderColor: 'rgb(54, 162, 235)',
                                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                tension: 0.4
                            }
                        ]
                    },
                    options: chartConfig.options
                });
            }
        }

        // 矯正視力グラフ
        if (chartData{{ $chartId }}.hasCorrectedData) {
            const correctedCanvas = document.getElementById('correctedVisionChart{{ $chartId }}');
            if (correctedCanvas) {
                new Chart(correctedCanvas, {
                    type: 'line',
                    data: {
                        labels: chartData{{ $chartId }}.dates,
                        datasets: [
                            {
                                label: '左眼（施術前）',
                                data: chartData{{ $chartId }}.leftCorrectedBefore,
                                borderColor: 'rgb(255, 159, 64)',
                                backgroundColor: 'rgba(255, 159, 64, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.4
                            },
                            {
                                label: '左眼（施術後）',
                                data: chartData{{ $chartId }}.leftCorrectedAfter,
                                borderColor: 'rgb(255, 159, 64)',
                                backgroundColor: 'rgba(255, 159, 64, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: '右眼（施術前）',
                                data: chartData{{ $chartId }}.rightCorrectedBefore,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.4
                            },
                            {
                                label: '右眼（施術後）',
                                data: chartData{{ $chartId }}.rightCorrectedAfter,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                tension: 0.4
                            }
                        ]
                    },
                    options: chartConfig.options
                });
            }
        }
    }

    // DOMContentLoadedとLivewireの両方に対応
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts{{ $chartId }});
    } else {
        initCharts{{ $chartId }}();
    }

    // Livewireの再レンダリング後も実行
    if (typeof Livewire !== 'undefined') {
        Livewire.hook('message.processed', (message, component) => {
            initCharts{{ $chartId }}();
        });
    }
})();
</script>
