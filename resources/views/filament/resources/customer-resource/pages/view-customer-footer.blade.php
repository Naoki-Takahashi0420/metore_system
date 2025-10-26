<div class="p-6">
    <!-- 視力推移グラフ -->
    <div id="admin-vision-chart-container" class="hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">視力推移グラフ</h3>

            <!-- タブナビゲーション -->
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-4" aria-label="グラフ切り替え">
                    <button id="admin-tab-naked" onclick="switchAdminVisionChart('naked')" class="admin-vision-tab px-4 py-2 text-sm font-medium border-b-2 border-primary-500 text-primary-600 dark:text-primary-400">
                        裸眼視力
                    </button>
                    <button id="admin-tab-corrected" onclick="switchAdminVisionChart('corrected')" class="admin-vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                        矯正視力
                    </button>
                    <button id="admin-tab-presbyopia" onclick="switchAdminVisionChart('presbyopia')" class="admin-vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                        老眼測定
                    </button>
                </nav>
            </div>

            <!-- グラフコンテンツ -->
            <div id="admin-naked-vision-chart-wrapper" class="admin-chart-content">
                <div class="relative" style="height: 300px;">
                    <canvas id="adminNakedVisionChart"></canvas>
                </div>
            </div>
            <div id="admin-corrected-vision-chart-wrapper" class="admin-chart-content hidden">
                <div class="relative" style="height: 300px;">
                    <canvas id="adminCorrectedVisionChart"></canvas>
                </div>
            </div>
            <div id="admin-presbyopia-vision-chart-wrapper" class="admin-chart-content hidden">
                <div class="relative" style="height: 300px;">
                    <canvas id="adminPresbyopiaVisionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
console.log('[DEBUG] View Customer Footer loaded');

// 視力グラフ切り替え関数（管理画面用）
function switchAdminVisionChart(type) {
    console.log('[DEBUG] Switching to:', type);
    // 全てのタブとコンテンツを非アクティブに
    document.querySelectorAll('.admin-vision-tab').forEach(tab => {
        tab.classList.remove('border-primary-500', 'text-primary-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });

    document.querySelectorAll('.admin-chart-content').forEach(content => {
        content.classList.add('hidden');
    });

    // 選択されたタブとコンテンツをアクティブに
    const activeTab = document.getElementById(`admin-tab-${type}`);
    const activeContent = document.getElementById(`admin-${type}-vision-chart-wrapper`);

    if (activeTab) {
        activeTab.classList.remove('border-transparent', 'text-gray-500');
        activeTab.classList.add('border-primary-500', 'text-primary-600');
    }

    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

// 管理画面用の視力グラフ描画
document.addEventListener('DOMContentLoaded', function() {
    console.log('[DEBUG] View Customer Footer DOMContentLoaded');

    // PHPからデータを直接渡す
    const medicalRecordsData = @json($record->medicalRecords()
        ->with(['presbyopiaMeasurements', 'reservation.menu', 'staff'])
        ->orderBy('record_date', 'desc')
        ->get()
        ->map(function ($record) {
            $visionRecords = is_string($record->vision_records)
                ? json_decode($record->vision_records, true)
                : $record->vision_records;

            $presbyopiaData = [
                'before' => null,
                'after' => null,
            ];

            foreach ($record->presbyopiaMeasurements as $measurement) {
                if ($measurement->status === '施術前') {
                    $presbyopiaData['before'] = $measurement;
                } elseif ($measurement->status === '施術後') {
                    $presbyopiaData['after'] = $measurement;
                }
            }

            return [
                'id' => $record->id,
                'record_date' => $record->record_date,
                'treatment_date' => $record->treatment_date,
                'session_number' => $record->session_number,
                'vision_records' => $visionRecords,
                'presbyopia_measurements' => $presbyopiaData,
                'menu_name' => $record->reservation?->menu?->name,
                'staff_name' => $record->staff?->name,
            ];
        }));

    console.log('[DEBUG] Medical records loaded:', medicalRecordsData.length);

    if (medicalRecordsData.length > 0) {
        renderAdminVisionCharts(medicalRecordsData);
    } else {
        console.log('[DEBUG] No medical records found');
    }
});

function renderAdminVisionCharts(records) {
    console.log('[DEBUG] renderAdminVisionCharts called with', records.length, 'records');

    // 全カルテから視力記録を収集
    const allVisionRecords = [];

    records.forEach(record => {
        // vision_recordsがある場合
        if (record.vision_records && record.vision_records.length > 0) {
            record.vision_records.forEach(vision => {
                allVisionRecords.push({
                    ...vision,
                    date: record.record_date || record.treatment_date || record.created_at,
                    treatment_date: record.record_date || record.treatment_date || record.created_at
                });
            });
        }
        // 個別カラムに視力データがある場合（従来形式）
        else if (record.before_naked_left || record.after_naked_left ||
                 record.before_naked_right || record.after_naked_right ||
                 record.before_corrected_left || record.after_corrected_left ||
                 record.before_corrected_right || record.after_corrected_right) {
            allVisionRecords.push({
                date: record.record_date || record.treatment_date || record.created_at,
                before_naked_left: record.before_naked_left,
                after_naked_left: record.after_naked_left,
                before_naked_right: record.before_naked_right,
                after_naked_right: record.after_naked_right,
                before_corrected_left: record.before_corrected_left,
                after_corrected_left: record.after_corrected_left,
                before_corrected_right: record.before_corrected_right,
                after_corrected_right: record.after_corrected_right,
                treatment_date: record.record_date || record.treatment_date || record.created_at
            });
        }
    });

    console.log('[DEBUG] Vision records collected:', allVisionRecords.length);

    if (allVisionRecords.length === 0) {
        console.log('[DEBUG] No vision records found');
        return; // データがない場合は何もしない
    }

    // 日付でソート
    allVisionRecords.sort((a, b) => {
        const dateA = new Date(a.date || a.treatment_date);
        const dateB = new Date(b.date || b.treatment_date);
        return dateA - dateB;
    });

    // データ整形
    const dates = [];
    const leftNakedBefore = [];
    const leftNakedAfter = [];
    const rightNakedBefore = [];
    const rightNakedAfter = [];
    const leftCorrectedBefore = [];
    const leftCorrectedAfter = [];
    const rightCorrectedBefore = [];
    const rightCorrectedAfter = [];

    let hasNakedData = false;
    let hasCorrectedData = false;

    allVisionRecords.forEach((vision, index) => {
        const date = vision.date ? new Date(vision.date) : new Date(vision.treatment_date);
        dates.push(date.toLocaleDateString('ja-JP', { month: 'numeric', day: 'numeric' }));

        // 施術前後の視力を収集
        const leftNakedB = vision.before_naked_left ? parseFloat(vision.before_naked_left) : null;
        const leftNakedA = vision.after_naked_left ? parseFloat(vision.after_naked_left) : null;
        const rightNakedB = vision.before_naked_right ? parseFloat(vision.before_naked_right) : null;
        const rightNakedA = vision.after_naked_right ? parseFloat(vision.after_naked_right) : null;

        const leftCorrectedB = vision.before_corrected_left ? parseFloat(vision.before_corrected_left) : null;
        const leftCorrectedA = vision.after_corrected_left ? parseFloat(vision.after_corrected_left) : null;
        const rightCorrectedB = vision.before_corrected_right ? parseFloat(vision.before_corrected_right) : null;
        const rightCorrectedA = vision.after_corrected_right ? parseFloat(vision.after_corrected_right) : null;

        leftNakedBefore.push(leftNakedB);
        leftNakedAfter.push(leftNakedA);
        rightNakedBefore.push(rightNakedB);
        rightNakedAfter.push(rightNakedA);
        leftCorrectedBefore.push(leftCorrectedB);
        leftCorrectedAfter.push(leftCorrectedA);
        rightCorrectedBefore.push(rightCorrectedB);
        rightCorrectedAfter.push(rightCorrectedA);

        if (leftNakedB !== null || leftNakedA !== null || rightNakedB !== null || rightNakedA !== null) hasNakedData = true;
        if (leftCorrectedB !== null || leftCorrectedA !== null || rightCorrectedB !== null || rightCorrectedA !== null) hasCorrectedData = true;
    });

    console.log('[DEBUG] Has naked data:', hasNakedData, 'Has corrected data:', hasCorrectedData);

    const chartContainer = document.getElementById('admin-vision-chart-container');
    if (!chartContainer) {
        console.error('[ERROR] Chart container not found');
        return;
    }

    const chartConfig = {
        type: 'line',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { size: 12 },
                        padding: 10,
                        boxWidth: 40,
                    }
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
                        stepSize: 0.1,
                        font: { size: 11 }
                    },
                    title: {
                        display: true,
                        text: '視力',
                        font: { size: 12 }
                    }
                },
                x: {
                    ticks: { font: { size: 11 } },
                    title: {
                        display: true,
                        text: '測定日',
                        font: { size: 12 }
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
    if (hasNakedData) {
        const nakedCanvas = document.getElementById('adminNakedVisionChart');
        if (nakedCanvas) {
            new Chart(nakedCanvas, {
                ...chartConfig,
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: '左眼 施術前',
                            data: leftNakedBefore,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgb(255, 99, 132)',
                            borderDash: [5, 5],
                            pointStyle: 'rect',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '左眼 施術後',
                            data: leftNakedAfter,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgb(255, 99, 132)',
                            pointStyle: 'circle',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '右眼 施術前',
                            data: rightNakedBefore,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgb(54, 162, 235)',
                            borderDash: [5, 5],
                            pointStyle: 'rect',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '右眼 施術後',
                            data: rightNakedAfter,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgb(54, 162, 235)',
                            pointStyle: 'circle',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        }
                    ]
                }
            });
        }
    }

    // 矯正視力グラフ
    if (hasCorrectedData) {
        const correctedCanvas = document.getElementById('adminCorrectedVisionChart');
        if (correctedCanvas) {
            new Chart(correctedCanvas, {
                ...chartConfig,
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: '左眼 施術前',
                            data: leftCorrectedBefore,
                            borderColor: 'rgb(255, 159, 64)',
                            backgroundColor: 'rgb(255, 159, 64)',
                            borderDash: [5, 5],
                            pointStyle: 'rect',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '左眼 施術後',
                            data: leftCorrectedAfter,
                            borderColor: 'rgb(255, 159, 64)',
                            backgroundColor: 'rgb(255, 159, 64)',
                            pointStyle: 'circle',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '右眼 施術前',
                            data: rightCorrectedBefore,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgb(75, 192, 192)',
                            borderDash: [5, 5],
                            pointStyle: 'rect',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '右眼 施術後',
                            data: rightCorrectedAfter,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgb(75, 192, 192)',
                            pointStyle: 'circle',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        }
                    ]
                }
            });
        }
    }

    // 老眼グラフ
    const leftPresbyopiaBefore = [];
    const leftPresbyopiaAfter = [];
    const rightPresbyopiaBefore = [];
    const rightPresbyopiaAfter = [];
    let hasPresbyopiaData = false;

    records.forEach(record => {
        if (record.presbyopia_measurements?.before || record.presbyopia_measurements?.after) {
            hasPresbyopiaData = true;

            const leftPresbyB = record.presbyopia_measurements.before?.a_95_left ? parseFloat(record.presbyopia_measurements.before.a_95_left) : null;
            const rightPresbyB = record.presbyopia_measurements.before?.a_95_right ? parseFloat(record.presbyopia_measurements.before.a_95_right) : null;
            const leftPresbyA = record.presbyopia_measurements.after?.a_95_left ? parseFloat(record.presbyopia_measurements.after.a_95_left) : null;
            const rightPresbyA = record.presbyopia_measurements.after?.a_95_right ? parseFloat(record.presbyopia_measurements.after.a_95_right) : null;

            leftPresbyopiaBefore.push(leftPresbyB);
            leftPresbyopiaAfter.push(leftPresbyA);
            rightPresbyopiaBefore.push(rightPresbyB);
            rightPresbyopiaAfter.push(rightPresbyA);
        } else {
            leftPresbyopiaBefore.push(null);
            leftPresbyopiaAfter.push(null);
            rightPresbyopiaBefore.push(null);
            rightPresbyopiaAfter.push(null);
        }
    });

    console.log('[DEBUG] Has presbyopia data:', hasPresbyopiaData);

    if (hasPresbyopiaData) {
        const presbyopiaCanvas = document.getElementById('adminPresbyopiaVisionChart');
        if (presbyopiaCanvas) {
            new Chart(presbyopiaCanvas, {
                ...chartConfig,
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: '左眼 施術前',
                            data: leftPresbyopiaBefore,
                            borderColor: 'rgb(139, 92, 246)',
                            backgroundColor: 'rgb(139, 92, 246)',
                            borderDash: [5, 5],
                            pointStyle: 'rect',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '左眼 施術後',
                            data: leftPresbyopiaAfter,
                            borderColor: 'rgb(139, 92, 246)',
                            backgroundColor: 'rgb(139, 92, 246)',
                            pointStyle: 'circle',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '右眼 施術前',
                            data: rightPresbyopiaBefore,
                            borderColor: 'rgb(234, 88, 12)',
                            backgroundColor: 'rgb(234, 88, 12)',
                            borderDash: [5, 5],
                            pointStyle: 'rect',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        },
                        {
                            label: '右眼 施術後',
                            data: rightPresbyopiaAfter,
                            borderColor: 'rgb(234, 88, 12)',
                            backgroundColor: 'rgb(234, 88, 12)',
                            pointStyle: 'circle',
                            pointRadius: 6,
                            tension: 0.4,
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    ...chartConfig.options,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: { size: 11 },
                                callback: function(value) {
                                    return value + 'cm';
                                }
                            },
                            title: {
                                display: true,
                                text: '近見距離（cm）',
                                font: { size: 12 }
                            }
                        },
                        x: {
                            ticks: { font: { size: 11 } },
                            title: {
                                display: true,
                                text: '測定日',
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
        }
    }

    // グラフコンテナを表示
    if (hasNakedData || hasCorrectedData || hasPresbyopiaData) {
        console.log('[DEBUG] Showing chart container');
        chartContainer.classList.remove('hidden');

        // デフォルトで最初に表示するタブを決定
        let defaultTab = null;
        if (hasNakedData) {
            defaultTab = 'naked';
        } else if (hasCorrectedData) {
            defaultTab = 'corrected';
        } else if (hasPresbyopiaData) {
            defaultTab = 'presbyopia';
        }

        // データがないタブを非表示にする
        if (!hasNakedData) {
            const nakedTab = document.getElementById('admin-tab-naked');
            if (nakedTab) nakedTab.style.display = 'none';
        }
        if (!hasCorrectedData) {
            const correctedTab = document.getElementById('admin-tab-corrected');
            if (correctedTab) correctedTab.style.display = 'none';
        }
        if (!hasPresbyopiaData) {
            const presbyopiaTab = document.getElementById('admin-tab-presbyopia');
            if (presbyopiaTab) presbyopiaTab.style.display = 'none';
        }

        // デフォルトタブを表示
        if (defaultTab) {
            switchAdminVisionChart(defaultTab);
        }
    }
}
</script>
