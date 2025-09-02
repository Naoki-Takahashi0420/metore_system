@extends('layouts.app')

@section('title', '視力推移')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- ヘッダー --}}
    <div class="bg-white border-b sticky top-0 z-40">
        <div class="max-w-lg mx-auto px-4 py-3">
            <div class="flex items-center">
                <button onclick="history.back()" class="p-2 -ml-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 class="text-lg font-bold text-gray-900 ml-2">視力推移</h1>
            </div>
        </div>
    </div>

    {{-- サマリーカード --}}
    <div class="max-w-lg mx-auto px-4 py-4">
        <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl p-6 text-white shadow-lg">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-sm opacity-90 mb-1">現在の視力</p>
                    <div class="flex items-baseline">
                        <span class="text-4xl font-bold">1.2</span>
                        <span class="text-lg ml-2 opacity-75">/ 1.0</span>
                    </div>
                </div>
                <div class="bg-white/20 rounded-lg px-3 py-1.5">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M7 14l5-5 5 5z"/>
                        </svg>
                        <span class="text-sm font-medium">+0.3</span>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-4 pt-4 border-t border-white/20">
                <div>
                    <p class="text-xs opacity-75">初回</p>
                    <p class="text-lg font-semibold">0.9</p>
                </div>
                <div>
                    <p class="text-xs opacity-75">最高</p>
                    <p class="text-lg font-semibold">1.5</p>
                </div>
                <div>
                    <p class="text-xs opacity-75">改善率</p>
                    <p class="text-lg font-semibold">33%</p>
                </div>
            </div>
        </div>
    </div>

    {{-- 期間選択タブ --}}
    <div class="max-w-lg mx-auto px-4">
        <div class="bg-white rounded-xl shadow-sm p-1">
            <div class="grid grid-cols-4 gap-1">
                <button onclick="changePeriod('1m')" class="period-btn px-3 py-2 rounded-lg text-sm font-medium bg-blue-500 text-white">
                    1ヶ月
                </button>
                <button onclick="changePeriod('3m')" class="period-btn px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100">
                    3ヶ月
                </button>
                <button onclick="changePeriod('6m')" class="period-btn px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100">
                    6ヶ月
                </button>
                <button onclick="changePeriod('1y')" class="period-btn px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100">
                    1年
                </button>
            </div>
        </div>
    </div>

    {{-- グラフエリア --}}
    <div class="max-w-lg mx-auto px-4 py-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-gray-900">視力の変化</h3>
                <div class="flex items-center space-x-4 text-xs">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-1"></div>
                        <span class="text-gray-600">右目</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-1"></div>
                        <span class="text-gray-600">左目</span>
                    </div>
                </div>
            </div>
            <div class="relative" style="height: 250px;">
                <canvas id="visionChart"></canvas>
            </div>
        </div>
    </div>

    {{-- 詳細データ --}}
    <div class="max-w-lg mx-auto px-4 pb-4">
        <div class="bg-white rounded-xl shadow-sm">
            <div class="px-4 py-3 border-b">
                <h3 class="font-semibold text-gray-900">測定履歴</h3>
            </div>
            <div id="measurement-history" class="divide-y">
                {{-- 測定データがここに表示される --}}
            </div>
        </div>
    </div>

    {{-- トレーニング効果 --}}
    <div class="max-w-lg mx-auto px-4 pb-20">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-900 mb-4">トレーニング効果</h3>
            
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm text-gray-700">遠視改善</span>
                        <span class="text-sm font-medium">85%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: 85%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm text-gray-700">近視改善</span>
                        <span class="text-sm font-medium">70%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: 70%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm text-gray-700">眼精疲労軽減</span>
                        <span class="text-sm font-medium">90%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-500 h-2 rounded-full" style="width: 90%"></div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <p class="text-sm text-blue-800">
                    <span class="font-medium">アドバイス：</span>
                    順調に改善しています。このペースでトレーニングを継続することをおすすめします。
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let visionChart = null;
let currentPeriod = '1m';

// ページ読み込み時
document.addEventListener('DOMContentLoaded', function() {
    loadVisionData();
    initChart();
    loadMeasurementHistory();
});

// グラフ初期化
function initChart() {
    const ctx = document.getElementById('visionChart').getContext('2d');
    
    // グラデーション作成
    const gradientRight = ctx.createLinearGradient(0, 0, 0, 250);
    gradientRight.addColorStop(0, 'rgba(59, 130, 246, 0.1)');
    gradientRight.addColorStop(1, 'rgba(59, 130, 246, 0)');
    
    const gradientLeft = ctx.createLinearGradient(0, 0, 0, 250);
    gradientLeft.addColorStop(0, 'rgba(147, 51, 234, 0.1)');
    gradientLeft.addColorStop(1, 'rgba(147, 51, 234, 0)');
    
    visionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: '右目',
                data: [],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: gradientRight,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }, {
                label: '左目',
                data: [],
                borderColor: 'rgb(147, 51, 234)',
                backgroundColor: gradientLeft,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: 'rgb(147, 51, 234)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: {
                        size: 12
                    },
                    bodyFont: {
                        size: 14
                    },
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(1);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    max: 2.0,
                    ticks: {
                        stepSize: 0.2,
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return value.toFixed(1);
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });
}

// 視力データ読み込み
async function loadVisionData() {
    // サンプルデータ（実際はAPIから取得）
    const sampleData = generateSampleData(currentPeriod);
    updateChart(sampleData);
}

// サンプルデータ生成
function generateSampleData(period) {
    const periods = {
        '1m': 30,
        '3m': 90,
        '6m': 180,
        '1y': 365
    };
    
    const days = periods[period];
    const labels = [];
    const rightEye = [];
    const leftEye = [];
    
    const now = new Date();
    
    for (let i = 0; i < 10; i++) {
        const date = new Date(now - (days / 10 * i) * 24 * 60 * 60 * 1000);
        labels.unshift(formatChartDate(date));
        
        // 徐々に改善するデータを生成
        const base = 0.9 + (i * 0.03);
        rightEye.unshift(base + Math.random() * 0.1);
        leftEye.unshift(base + Math.random() * 0.1 - 0.05);
    }
    
    return { labels, rightEye, leftEye };
}

// グラフ更新
function updateChart(data) {
    if (visionChart) {
        visionChart.data.labels = data.labels;
        visionChart.data.datasets[0].data = data.rightEye;
        visionChart.data.datasets[1].data = data.leftEye;
        visionChart.update();
    }
}

// 期間変更
function changePeriod(period) {
    currentPeriod = period;
    
    // ボタンのスタイル更新
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('bg-blue-500', 'text-white');
        btn.classList.add('text-gray-700', 'hover:bg-gray-100');
    });
    event.target.classList.remove('text-gray-700', 'hover:bg-gray-100');
    event.target.classList.add('bg-blue-500', 'text-white');
    
    loadVisionData();
}

// 測定履歴読み込み
function loadMeasurementHistory() {
    const history = [
        { date: '2025-08-30', right: 1.2, left: 1.0, note: '順調に改善' },
        { date: '2025-08-23', right: 1.1, left: 0.9, note: '前回より向上' },
        { date: '2025-08-16', right: 1.0, left: 0.9, note: '安定している' },
        { date: '2025-08-09', right: 0.9, left: 0.8, note: '初回測定' }
    ];
    
    const container = document.getElementById('measurement-history');
    container.innerHTML = history.map(item => `
        <div class="px-4 py-3 hover:bg-gray-50">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm text-gray-500">${formatDate(item.date)}</p>
                    <div class="flex space-x-4 mt-1">
                        <span class="text-sm">右: <span class="font-medium">${item.right}</span></span>
                        <span class="text-sm">左: <span class="font-medium">${item.left}</span></span>
                    </div>
                    ${item.note ? `<p class="text-xs text-gray-500 mt-1">${item.note}</p>` : ''}
                </div>
                <div class="flex items-center text-green-600">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7 14l5-5 5 5z"/>
                    </svg>
                </div>
            </div>
        </div>
    `).join('');
}

// 日付フォーマット
function formatChartDate(date) {
    return `${date.getMonth() + 1}/${date.getDate()}`;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    const day = date.getDate();
    const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    const weekday = weekdays[date.getDay()];
    
    return `${year}年${month}月${day}日(${weekday})`;
}
</script>
@endsection