@if($medicalRecords && $medicalRecords->count() > 0)
    @php
        // データをPHPで準備
        $chartData = $medicalRecords->map(function ($record) {
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
            ];
        });
    @endphp

    <div id="modal-vision-chart-container" class="mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900">視力推移グラフ</h3>

            <!-- タブナビゲーション -->
            <div class="mb-6 border-b border-gray-200">
                <nav class="flex space-x-4" aria-label="グラフ切り替え">
                    <button id="modal-tab-naked" class="modal-vision-tab px-4 py-2 text-sm font-medium border-b-2 border-primary-500 text-primary-600">
                        裸眼視力
                    </button>
                    <button id="modal-tab-corrected" class="modal-vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        矯正視力
                    </button>
                    <button id="modal-tab-presbyopia" class="modal-vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        老眼測定
                    </button>
                </nav>
            </div>

            <!-- グラフコンテンツ -->
            <div id="modal-naked-vision-chart-wrapper" class="modal-chart-content">
                <div class="relative" style="height: 300px;">
                    <canvas id="modalNakedVisionChart"></canvas>
                </div>
            </div>
            <div id="modal-corrected-vision-chart-wrapper" class="modal-chart-content hidden">
                <div class="relative" style="height: 300px;">
                    <canvas id="modalCorrectedVisionChart"></canvas>
                </div>
            </div>
            <div id="modal-presbyopia-vision-chart-wrapper" class="modal-chart-content hidden">
                <div class="relative" style="height: 300px;">
                    <canvas id="modalPresbyopiaVisionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Chart.jsの読み込みを確認してから実行
    function initModalCharts() {
        console.log('[DEBUG] Medical Records Chart Partial DOMContentLoaded');

        // データをJavaScriptに渡す
        const modalMedicalRecordsData = @json($chartData);
        
        console.log('[DEBUG] Modal medical records loaded:', modalMedicalRecordsData.length);

        // 視力グラフ切り替え関数（モーダル用）
        function switchModalVisionChart(type) {
            console.log('[DEBUG] Switching modal chart to:', type);

            // 全てのタブとコンテンツを非アクティブに
            document.querySelectorAll('.modal-vision-tab').forEach(tab => {
                tab.classList.remove('border-primary-500', 'text-primary-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });

            document.querySelectorAll('.modal-chart-content').forEach(content => {
                content.classList.add('hidden');
            });

            // 選択されたタブとコンテンツをアクティブに
            const activeTab = document.getElementById(`modal-tab-${type}`);
            const activeContent = document.getElementById(`modal-${type}-vision-chart-wrapper`);

            if (activeTab) {
                activeTab.classList.remove('border-transparent', 'text-gray-500');
                activeTab.classList.add('border-primary-500', 'text-primary-600');
            }

            if (activeContent) {
                activeContent.classList.remove('hidden');
            }
        }

        // グローバルスコープに配置
        window.switchModalVisionChart = switchModalVisionChart;

        // タブにクリックイベントを追加
        document.getElementById('modal-tab-naked')?.addEventListener('click', () => switchModalVisionChart('naked'));
        document.getElementById('modal-tab-corrected')?.addEventListener('click', () => switchModalVisionChart('corrected'));
        document.getElementById('modal-tab-presbyopia')?.addEventListener('click', () => switchModalVisionChart('presbyopia'));

        // Chart.jsが読み込まれるまで待つ
        function waitForChartJs(callback) {
            if (typeof Chart !== 'undefined') {
                callback();
            } else {
                console.log('[DEBUG] Waiting for Chart.js to load...');
                setTimeout(() => waitForChartJs(callback), 100);
            }
        }

        // グラフを描画
        waitForChartJs(function() {
            console.log('[DEBUG] Chart.js loaded, rendering charts...');
            renderModalVisionCharts(modalMedicalRecordsData);
        });

        function renderModalVisionCharts(records) {
            console.log('[DEBUG] renderModalVisionCharts called with', records.length, 'records');

            // 全カルテから視力記録を収集
            const allVisionRecords = [];

            records.forEach(record => {
                // vision_recordsがある場合
                if (record.vision_records && record.vision_records.length > 0) {
                    record.vision_records.forEach(vision => {
                        allVisionRecords.push({
                            ...vision,
                            date: record.record_date || record.treatment_date,
                            treatment_date: record.record_date || record.treatment_date
                        });
                    });
                }
            });

            console.log('[DEBUG] Vision records collected:', allVisionRecords.length);

            if (allVisionRecords.length === 0) {
                console.log('[DEBUG] No vision records found');
                return;
            }

            // 日付でソート（古い順）
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

            allVisionRecords.forEach((vision) => {
                const date = vision.date ? new Date(vision.date) : new Date(vision.treatment_date);
                dates.push(date.toLocaleDateString('ja-JP', { month: 'numeric', day: 'numeric' }));

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

                if (leftNakedB || leftNakedA || rightNakedB || rightNakedA) hasNakedData = true;
                if (leftCorrectedB || leftCorrectedA || rightCorrectedB || rightCorrectedA) hasCorrectedData = true;
            });

            // Chart.jsのデフォルト設定
            Chart.defaults.font.family = "'Hiragino Sans', 'Meiryo', sans-serif";

            // 裸眼視力グラフ
            const nakedCtx = document.getElementById('modalNakedVisionChart')?.getContext('2d');
            if (nakedCtx) {
                console.log('[DEBUG] Drawing naked vision chart...');
                new Chart(nakedCtx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                label: '左眼（施術前）',
                                data: leftNakedBefore,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: '左眼（施術後）',
                                data: leftNakedAfter,
                                borderColor: 'rgba(54, 162, 235, 1)',
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: '右眼（施術前）',
                                data: rightNakedBefore,
                                borderColor: 'rgba(255, 159, 64, 1)',
                                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                                borderDash: [5, 5],
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: '右眼（施術後）',
                                data: rightNakedAfter,
                                borderColor: 'rgba(153, 102, 255, 1)',
                                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                                borderDash: [5, 5],
                                tension: 0.1,
                                spanGaps: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: '裸眼視力の推移'
                            },
                            legend: {
                                position: 'bottom'
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
                            }
                        }
                    }
                });
            }

            // 矯正視力グラフ
            const correctedCtx = document.getElementById('modalCorrectedVisionChart')?.getContext('2d');
            if (correctedCtx) {
                console.log('[DEBUG] Drawing corrected vision chart...');
                new Chart(correctedCtx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                label: '左眼（施術前）',
                                data: leftCorrectedBefore,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: '左眼（施術後）',
                                data: leftCorrectedAfter,
                                borderColor: 'rgba(54, 162, 235, 1)',
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: '右眼（施術前）',
                                data: rightCorrectedBefore,
                                borderColor: 'rgba(255, 159, 64, 1)',
                                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                                borderDash: [5, 5],
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: '右眼（施術後）',
                                data: rightCorrectedAfter,
                                borderColor: 'rgba(153, 102, 255, 1)',
                                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                                borderDash: [5, 5],
                                tension: 0.1,
                                spanGaps: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: '矯正視力の推移'
                            },
                            legend: {
                                position: 'bottom'
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
                            }
                        }
                    }
                });
            }

            // 老眼測定グラフ
            const presbyopiaData = [];
            records.forEach((record) => {
                if (record.presbyopia_measurements) {
                    const pm = record.presbyopia_measurements;
                    if (pm.before || pm.after) {
                        presbyopiaData.push({
                            date: record.record_date || record.treatment_date,
                            before_left: pm.before?.left_eye || null,
                            before_right: pm.before?.right_eye || null,
                            after_left: pm.after?.left_eye || null,
                            after_right: pm.after?.right_eye || null
                        });
                    }
                }
            });

            console.log('[DEBUG] Presbyopia data collected:', presbyopiaData.length);

            let hasPresbyopiaData = presbyopiaData.length > 0;

            if (hasPresbyopiaData) {
                // 老眼データを日付でソート
                presbyopiaData.sort((a, b) => new Date(a.date) - new Date(b.date));

                const presbyopiaDates = presbyopiaData.map(d => 
                    new Date(d.date).toLocaleDateString('ja-JP', { month: 'numeric', day: 'numeric' })
                );
                const beforeLeft = presbyopiaData.map(d => d.before_left ? parseFloat(d.before_left) : null);
                const afterLeft = presbyopiaData.map(d => d.after_left ? parseFloat(d.after_left) : null);
                const beforeRight = presbyopiaData.map(d => d.before_right ? parseFloat(d.before_right) : null);
                const afterRight = presbyopiaData.map(d => d.after_right ? parseFloat(d.after_right) : null);

                const presbyopiaCtx = document.getElementById('modalPresbyopiaVisionChart')?.getContext('2d');
                if (presbyopiaCtx) {
                    console.log('[DEBUG] Drawing presbyopia chart...');
                    new Chart(presbyopiaCtx, {
                        type: 'line',
                        data: {
                            labels: presbyopiaDates,
                            datasets: [
                                {
                                    label: '左眼（施術前）',
                                    data: beforeLeft,
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                    tension: 0.1,
                                    spanGaps: true
                                },
                                {
                                    label: '左眼（施術後）',
                                    data: afterLeft,
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                    tension: 0.1,
                                    spanGaps: true
                                },
                                {
                                    label: '右眼（施術前）',
                                    data: beforeRight,
                                    borderColor: 'rgba(255, 159, 64, 1)',
                                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                                    borderDash: [5, 5],
                                    tension: 0.1,
                                    spanGaps: true
                                },
                                {
                                    label: '右眼（施術後）',
                                    data: afterRight,
                                    borderColor: 'rgba(153, 102, 255, 1)',
                                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                                    borderDash: [5, 5],
                                    tension: 0.1,
                                    spanGaps: true
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: '老眼測定値の推移'
                                },
                                legend: {
                                    position: 'bottom'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        stepSize: 10,
                                        callback: function(value) {
                                            return value + 'cm';
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: '測定距離'
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // デフォルトで最初に表示するタブを決定
            let defaultTab = null;
            if (hasNakedData) {
                defaultTab = 'naked';
            } else if (hasCorrectedData) {
                defaultTab = 'corrected';
            } else if (hasPresbyopiaData) {
                defaultTab = 'presbyopia';
            }

            if (defaultTab) {
                switchModalVisionChart(defaultTab);
            }

            console.log('[DEBUG] All charts rendered successfully');
        }
    }

    // DOMContentLoadedまたは既に読み込み済みの場合に実行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Chart.jsが読み込まれているか確認
            if (typeof Chart !== 'undefined') {
                initModalCharts();
            } else {
                // Chart.jsの読み込みを待つ
                window.addEventListener('chartjs:loaded', initModalCharts);
                
                // 既にロード中でない場合は手動でロード
                if (!window.chartJsLoading && !window.chartJsLoaded) {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                    script.onload = function() {
                        console.log('[DEBUG] Chart.js loaded from modal');
                        initModalCharts();
                    };
                    document.head.appendChild(script);
                }
            }
        });
    } else {
        // 既にDOMが読み込まれている場合
        if (typeof Chart !== 'undefined') {
            initModalCharts();
        } else {
            // Chart.jsの読み込みを待つ
            window.addEventListener('chartjs:loaded', initModalCharts);
            
            // 既にロード中でない場合は手動でロード
            if (!window.chartJsLoading && !window.chartJsLoaded) {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                script.onload = function() {
                    console.log('[DEBUG] Chart.js loaded from modal (after DOM)');
                    initModalCharts();
                };
                document.head.appendChild(script);
            }
        }
    }
    </script>
@endif