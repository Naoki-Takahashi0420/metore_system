@extends('layouts.app')

@section('title', 'カルテ')

@section('content')
<div class="bg-white min-h-screen pb-20 md:pb-0">
    <!-- Fixed Header Bar -->
    <div class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 shadow-sm z-40">
        <div class="max-w-4xl mx-auto px-4">
            <div class="py-4 md:py-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">カルテ</h1>
                        <p class="text-sm text-gray-500 mt-1" id="customer-info">
                            読み込み中...
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="history.back()" class="text-gray-600 hover:text-gray-900 p-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <a href="/customer/dashboard" class="text-gray-600 hover:text-gray-900 p-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                        </a>
                        <a href="/customer/reservations" class="hidden md:block text-gray-600 hover:text-gray-900 px-3 py-2 text-sm">
                            予約履歴
                        </a>
                        <a href="#" onclick="goToReservation(); return false;" class="bg-gray-900 text-white px-3 md:px-4 py-2 rounded-lg text-sm hover:bg-gray-800 transition-colors">
                            新規予約
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content with top padding to account for fixed header -->
    <div class="max-w-4xl mx-auto px-4 pt-24 md:pt-28">

        <!-- 視力推移グラフ -->
        <div id="vision-chart-container" class="hidden py-6">
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900">視力推移グラフ</h2>

                <!-- タブナビゲーション -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="flex space-x-4" aria-label="グラフ切り替え">
                        <button id="tab-naked" onclick="switchVisionChart('naked')" class="vision-tab px-4 py-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                            裸眼視力
                        </button>
                        <button id="tab-corrected" onclick="switchVisionChart('corrected')" class="vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            矯正視力
                        </button>
                        <button id="tab-presbyopia" onclick="switchVisionChart('presbyopia')" class="vision-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            老眼測定
                        </button>
                    </nav>
                </div>

                <!-- グラフコンテンツ -->
                <div id="naked-vision-chart-wrapper" class="chart-content">
                    <div class="relative" style="height: 300px;">
                        <canvas id="nakedVisionChart"></canvas>
                    </div>
                </div>
                <div id="corrected-vision-chart-wrapper" class="chart-content hidden">
                    <div class="relative" style="height: 300px;">
                        <canvas id="correctedVisionChart"></canvas>
                    </div>
                </div>
                <div id="presbyopia-vision-chart-wrapper" class="chart-content hidden">
                    <div class="relative" style="height: 300px;">
                        <canvas id="presbyopiaVisionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 顧客画像ギャラリー（カルテ画像+顧客画像を統合） -->
        <div id="all-images-container" class="hidden py-6">
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900">あなたの画像</h2>
                <p class="text-sm text-gray-500 mb-4">カルテや顧客管理から登録された画像を確認できます</p>
                <div id="all-images-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <!-- 画像がここに表示される -->
                </div>
            </div>
        </div>

        <!-- Medical Records List -->
        <div id="medical-records-container" class="py-4">
            <div class="text-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 mx-auto"></div>
                <p class="text-gray-500 mt-3 text-sm">カルテ情報を読み込み中...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden py-16 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h3 class="text-base font-medium text-gray-900 mb-2">情報が見つかりません</h3>
            <p class="text-sm text-gray-500">まだトレーニングを受けていらっしゃいません</p>
        </div>

    </div><!-- /.max-w-4xl -->
</div><!-- /.bg-white -->

<!-- 画像詳細モーダル -->
<div id="imageModal" class="hidden fixed inset-0 bg-black overflow-y-auto h-full w-full z-50 transition-opacity duration-300 opacity-0" onclick="closeImageModalGallery()">
    <div id="imageModalContent" class="relative top-10 mx-auto p-5 max-w-4xl transform transition-all duration-300 scale-95 opacity-0" onclick="event.stopPropagation()">
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <div class="flex-1">
                    <h3 id="imageModalTitle" class="text-lg font-semibold text-gray-900"></h3>
                    <p id="imageModalCounter" class="text-sm text-gray-500 mt-1"></p>
                </div>
                <button onclick="closeImageModalGallery()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-4 relative">
                <!-- 画像ナビゲーションボタン（画像エリア内に配置） -->
                <button id="prevImageBtn" onclick="event.stopPropagation(); navigateImage(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white bg-opacity-90 hover:bg-opacity-100 text-gray-800 rounded-full p-2 md:p-3 shadow-lg transition-all duration-200 hover:scale-110 z-10">
                    <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <button id="nextImageBtn" onclick="event.stopPropagation(); navigateImage(1)" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white bg-opacity-90 hover:bg-opacity-100 text-gray-800 rounded-full p-2 md:p-3 shadow-lg transition-all duration-200 hover:scale-110 z-10">
                    <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <img id="imageModalImg" src="" alt="" class="w-full h-auto max-h-[70vh] object-contain rounded-lg mb-4 transition-transform duration-200">
                <div id="imageModalDescription" class="text-gray-600"></div>
                <div id="imageModalType" class="text-sm text-gray-500 mt-2"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
async function goToReservation() {
    const token = localStorage.getItem('customer_token');

    if (!token) {
        // トークンがない場合はログインページにリダイレクト
        window.location.href = '/customer/login';
        return;
    }

    try {
        // カルテからの予約用コンテキストを生成
        const response = await fetch('/api/customer/reservation-context/medical-record', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                source: 'mypage'  // マイページからの予約であることを明示
            })
        });

        if (!response.ok) {
            if (response.status === 401) {
                // トークンが無効
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                window.location.href = '/customer/login';
                return;
            }
            throw new Error('Failed to create reservation context');
        }

        const data = await response.json();
        const encryptedContext = data.data.encrypted_context;

        // 新しいパラメータベースシステムを使用
        window.location.href = `/stores?ctx=${encodeURIComponent(encryptedContext)}`;

    } catch (error) {
        console.error('Error creating reservation context:', error);
        // フォールバック: 従来の方式
        window.location.href = '/stores?from_medical_record=1';
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    const token = localStorage.getItem('customer_token');
    const customerDataStr = localStorage.getItem('customer_data');

    // トークンがない場合はログインページにリダイレクト
    if (!token) {
        window.location.href = '/customer/login';
        return;
    }

    // 顧客情報をパース
    let customerData = null;
    if (customerDataStr) {
        try {
            customerData = JSON.parse(customerDataStr);
            document.getElementById('customer-info').textContent =
                `${customerData.last_name} ${customerData.first_name} 様のカルテ`;
        } catch (e) {
            console.error('Customer data parse error:', e);
        }
    }

    // カルテ情報を取得
    try {
        const headers = {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        };

        // 店舗IDがある場合はヘッダーに追加
        if (customerData && customerData.store_id) {
            headers['X-Store-Id'] = customerData.store_id;
            console.log('カルテ取得 - 店舗ID:', customerData.store_id);
        } else {
            console.log('カルテ取得 - 店舗IDなし（全店舗）');
        }

        const response = await fetch('/api/customer/medical-records', { headers });

        
        if (!response.ok) {
            if (response.status === 401) {
                // トークンが無効
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                window.location.href = '/customer/login';
                return;
            }
            throw new Error('Failed to fetch medical records');
        }
        
        const data = await response.json();
        const records = data.data || [];
        displayMedicalRecords(records);
        renderVisionCharts(records);

        // カルテから全ての表示可能画像を収集
        let allImages = [];
        records.forEach(record => {
            const visibleImages = record.visible_images || record.visibleImages || [];
            visibleImages.forEach(img => {
                allImages.push({
                    ...img,
                    source: 'medical_record',
                    record_date: record.treatment_date || record.record_date || record.created_at
                });
            });
        });

        // 顧客画像も取得して統合
        try {
            const imagesResponse = await fetch('/api/customer/images', { headers });
            if (imagesResponse.ok) {
                const imagesData = await imagesResponse.json();
                const customerImages = imagesData.data || [];
                customerImages.forEach(img => {
                    allImages.push({
                        ...img,
                        source: 'customer',
                        record_date: img.created_at
                    });
                });
            }
        } catch (imgError) {
            console.error('Error fetching customer images:', imgError);
        }

        // 全画像を表示
        displayAllImages(allImages);

    } catch (error) {
        console.error('Error fetching medical records:', error);
        document.getElementById('medical-records-container').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">カルテ情報の取得に失敗しました。再度お試しください。</p>
            </div>
        `;
    }
    
    // ログアウト処理（ボタンが存在する場合のみ）
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            localStorage.removeItem('customer_token');
            localStorage.removeItem('customer_data');
            window.location.href = '/customer/login';
        });
    }
});

function displayMedicalRecords(records) {
    const container = document.getElementById('medical-records-container');
    const emptyState = document.getElementById('empty-state');

    if (records.length === 0) {
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }

    // vision_recordsがJSON文字列の場合はパースする
    records.forEach(record => {
        if (record.vision_records && typeof record.vision_records === 'string') {
            try {
                record.vision_records = JSON.parse(record.vision_records);
            } catch (e) {
                console.error('Failed to parse vision_records:', e);
                record.vision_records = [];
            }
        }
        if (!Array.isArray(record.vision_records)) {
            record.vision_records = [];
        }
    });

    container.innerHTML = records.map(record => `
        <div class="border-b border-gray-100 py-6">
            <div class="pb-3 mb-3">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-base font-medium text-gray-900 mb-1">
                            ${formatDate(record.treatment_date || record.record_date || record.created_at)}
                        </h3>
                        ${record.reservation ? `
                            <div class="text-sm text-gray-600 space-y-1">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    ${record.reservation.store?.name || '店舗情報なし'}
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z" />
                                    </svg>
                                    ${record.reservation.menu?.name || 'メニュー情報なし'}
                                </div>
                                ${record.handled_by || record.created_by ? `
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        担当: ${record.handled_by || (record.created_by ? record.created_by.name : '')}
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                    <div class="text-right">
                        <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                            トレーニング記録
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-4">
                ${record.chief_complaint ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">主訴・お悩み</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.chief_complaint}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.symptoms ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">症状・現状</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.symptoms}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.diagnosis ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">診断・所見</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.diagnosis}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.treatment ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">治療・施術内容</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.treatment}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.prescription ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">処方・指導</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.prescription}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.medical_history ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">既往歴・医療履歴</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.medical_history}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${(record.visible_images || record.visibleImages) && (record.visible_images?.length > 0 || record.visibleImages?.length > 0) ? `
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">添付画像</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            ${(record.visible_images || record.visibleImages).map(image => `
                                <div class="relative group cursor-pointer" onclick="openRecordImageModal(${JSON.stringify(image).replace(/"/g, '&quot;')})">
                                    <img src="/storage/${image.file_path}" 
                                         alt="${escapeHtml(image.title || '画像')}" 
                                         class="w-full h-32 object-cover rounded-lg">
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-opacity rounded-lg flex items-center justify-center">
                                        <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                        </svg>
                                    </div>
                                    ${image.image_type_text ? `
                                        <span class="absolute top-2 left-2 bg-black bg-opacity-60 text-white text-xs px-2 py-1 rounded">
                                            ${image.image_type_text}
                                        </span>
                                    ` : ''}
                                    ${image.title ? `
                                        <p class="text-xs text-gray-600 mt-1 truncate">${escapeHtml(image.title)}</p>
                                    ` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
            
            ${record.vision_records && record.vision_records.length > 0 ? record.vision_records.map((vision, index) => {
                // 視力データの取得
                const hasNakedData = vision.before_naked_left || vision.before_naked_right || vision.after_naked_left || vision.after_naked_right;
                const hasCorrectedData = vision.before_corrected_left || vision.before_corrected_right || vision.after_corrected_left || vision.after_corrected_right;
                
                if (!hasNakedData && !hasCorrectedData) return '';
                
                return `
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">視力検査記録</h4>
                        
                        ${hasNakedData ? `
                            <div class="mb-3">
                                <p class="text-xs text-gray-600 mb-2">裸眼視力</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500"></th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術前</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術後</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">変化</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">左眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_naked_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_naked_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_naked_left && vision.after_naked_left ? (() => {
                                                        const change = (parseFloat(vision.after_naked_left) - parseFloat(vision.before_naked_left)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">右眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_naked_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_naked_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_naked_right && vision.after_naked_right ? (() => {
                                                        const change = (parseFloat(vision.after_naked_right) - parseFloat(vision.before_naked_right)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${hasCorrectedData ? `
                            <div class="mb-3">
                                <p class="text-xs text-gray-600 mb-2">矯正視力</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500"></th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術前</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術後</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">変化</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">左眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_corrected_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_corrected_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_corrected_left && vision.after_corrected_left ? (() => {
                                                        const change = (parseFloat(vision.after_corrected_left) - parseFloat(vision.before_corrected_left)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">右眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_corrected_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_corrected_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_corrected_right && vision.after_corrected_right ? (() => {
                                                        const change = (parseFloat(vision.after_corrected_right) - parseFloat(vision.before_corrected_right)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${vision.public_memo ? `
                            <div class="mt-3 p-3 bg-gray-50 rounded">
                                <p class="text-sm text-gray-700">${vision.public_memo}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('') : ''}

            ${record.presbyopia_before || record.presbyopia_after ? `
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <h4 class="text-sm font-medium text-gray-900 mb-3">老眼詳細測定</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">状態</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">A(95%)</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">B(50%)</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">C(25%)</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">D(12%)</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">E(6%)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${record.presbyopia_before ? `
                                    <tr>
                                        <td class="px-3 py-2 text-xs font-medium text-gray-900">施術前</td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_before.a_95_left || '−'}</div>
                                            <div>右: ${record.presbyopia_before.a_95_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_before.b_50_left || '−'}</div>
                                            <div>右: ${record.presbyopia_before.b_50_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_before.c_25_left || '−'}</div>
                                            <div>右: ${record.presbyopia_before.c_25_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_before.d_12_left || '−'}</div>
                                            <div>右: ${record.presbyopia_before.d_12_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_before.e_6_left || '−'}</div>
                                            <div>右: ${record.presbyopia_before.e_6_right || '−'}</div>
                                        </td>
                                    </tr>
                                ` : ''}
                                ${record.presbyopia_after ? `
                                    <tr>
                                        <td class="px-3 py-2 text-xs font-medium text-gray-900">施術後</td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_after.a_95_left || '−'}</div>
                                            <div>右: ${record.presbyopia_after.a_95_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_after.b_50_left || '−'}</div>
                                            <div>右: ${record.presbyopia_after.b_50_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_after.c_25_left || '−'}</div>
                                            <div>右: ${record.presbyopia_after.c_25_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_after.d_12_left || '−'}</div>
                                            <div>右: ${record.presbyopia_after.d_12_right || '−'}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <div>左: ${record.presbyopia_after.e_6_left || '−'}</div>
                                            <div>右: ${record.presbyopia_after.e_6_right || '−'}</div>
                                        </td>
                                    </tr>
                                ` : ''}
                            </tbody>
                        </table>
                    </div>
                </div>
            ` : ''}
            
            ${record.next_visit_date ? `
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        次回来院予定: ${formatDate(record.next_visit_date)}
                    </div>
                </div>
            ` : ''}
        </div>
    `).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'short'
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function openImageModal(imageUrl, title, description) {
    // モーダル要素を作成
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75';
    modal.onclick = function(e) {
        if (e.target === modal) {
            closeImageModal();
        }
    };
    
    modal.innerHTML = `
        <div class="relative max-w-4xl max-h-full">
            <button onclick="closeImageModal()" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img src="${imageUrl}" alt="${title}" class="max-w-full max-h-[80vh] object-contain">
            ${title || description ? `
                <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-60 text-white p-4">
                    ${title ? `<h3 class="text-lg font-semibold mb-1">${title}</h3>` : ''}
                    ${description ? `<p class="text-sm">${description}</p>` : ''}
                </div>
            ` : ''}
        </div>
    `;
    
    modal.id = 'image-modal';
    document.body.appendChild(modal);
}

function closeImageModal() {
    const modal = document.getElementById('image-modal');
    if (modal) {
        modal.remove();
    }
}

// ESCキーでモーダルを閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

// 視力グラフ切り替え関数
function switchVisionChart(type) {
    // 全てのタブとコンテンツを非アクティブに
    document.querySelectorAll('.vision-tab').forEach(tab => {
        tab.classList.remove('border-blue-500', 'text-blue-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });

    document.querySelectorAll('.chart-content').forEach(content => {
        content.classList.add('hidden');
    });

    // 選択されたタブとコンテンツをアクティブに
    const activeTab = document.getElementById(`tab-${type}`);
    const activeContent = document.getElementById(`${type}-vision-chart-wrapper`);

    if (activeTab) {
        activeTab.classList.remove('border-transparent', 'text-gray-500');
        activeTab.classList.add('border-blue-500', 'text-blue-600');
    }

    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

// 視力推移グラフを描画
function renderVisionCharts(records) {
    // 全カルテから視力記録を収集
    const allVisionRecords = [];

    records.forEach(record => {
        // vision_recordsがある場合
        if (record.vision_records && record.vision_records.length > 0) {
            record.vision_records.forEach(vision => {
                allVisionRecords.push({
                    ...vision,
                    // カルテの記録日を使用（vision.dateは使用しない）
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

    if (allVisionRecords.length === 0) {
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

    const chartContainer = document.getElementById('vision-chart-container');
    if (!chartContainer) return;

    // データがあればコンテナを表示
    if (hasNakedData || hasCorrectedData) {
        chartContainer.classList.remove('hidden');
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
                        font: {
                            size: window.innerWidth < 640 ? 10 : 12
                        },
                        padding: window.innerWidth < 640 ? 8 : 10,
                        boxWidth: window.innerWidth < 640 ? 30 : 40,
                        filter: function(item) {
                            return item.text !== undefined && item.text !== null && item.text !== '';
                        }
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
                        font: {
                            size: window.innerWidth < 640 ? 10 : 11
                        }
                    },
                    title: {
                        display: true,
                        text: '視力',
                        font: {
                            size: window.innerWidth < 640 ? 11 : 12
                        }
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: window.innerWidth < 640 ? 10 : 11
                        }
                    },
                    title: {
                        display: true,
                        text: '測定日',
                        font: {
                            size: window.innerWidth < 640 ? 11 : 12
                        }
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
        const nakedCanvas = document.getElementById('nakedVisionChart');

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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
        const correctedCanvas = document.getElementById('correctedVisionChart');

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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
                            tension: 0.4,
                            spanGaps: true
                        }
                    ]
                }
            });
        }
    }

    // 老眼グラフ（A95%の値を使用）
    const leftPresbyopiaBefore = [];
    const leftPresbyopiaAfter = [];
    const rightPresbyopiaBefore = [];
    const rightPresbyopiaAfter = [];
    let hasPresbyopiaData = false;

    records.forEach(record => {
        if (record.presbyopia_before || record.presbyopia_after) {
            hasPresbyopiaData = true;

            // 施術前のA95%値
            const leftPresbyB = record.presbyopia_before?.a_95_left ? parseFloat(record.presbyopia_before.a_95_left) : null;
            const rightPresbyB = record.presbyopia_before?.a_95_right ? parseFloat(record.presbyopia_before.a_95_right) : null;

            // 施術後のA95%値
            const leftPresbyA = record.presbyopia_after?.a_95_left ? parseFloat(record.presbyopia_after.a_95_left) : null;
            const rightPresbyA = record.presbyopia_after?.a_95_right ? parseFloat(record.presbyopia_after.a_95_right) : null;

            leftPresbyopiaBefore.push(leftPresbyB);
            leftPresbyopiaAfter.push(leftPresbyA);
            rightPresbyopiaBefore.push(rightPresbyB);
            rightPresbyopiaAfter.push(rightPresbyA);
        } else {
            // データがない場合はnullを追加して日付を揃える
            leftPresbyopiaBefore.push(null);
            leftPresbyopiaAfter.push(null);
            rightPresbyopiaBefore.push(null);
            rightPresbyopiaAfter.push(null);
        }
    });

    if (hasPresbyopiaData) {
        const presbyopiaCanvas = document.getElementById('presbyopiaVisionChart');

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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
                            pointHoverRadius: 8,
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
                                font: {
                                    size: window.innerWidth < 640 ? 10 : 11
                                },
                                callback: function(value) {
                                    return value + 'cm';
                                }
                            },
                            title: {
                                display: true,
                                text: '近見距離（cm）',
                                font: {
                                    size: window.innerWidth < 640 ? 11 : 12
                                }
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: window.innerWidth < 640 ? 10 : 11
                                }
                            },
                            title: {
                                display: true,
                                text: '測定日',
                                font: {
                                    size: window.innerWidth < 640 ? 11 : 12
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // グラフコンテナを表示
    if (hasNakedData || hasCorrectedData || hasPresbyopiaData) {
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
            const nakedTab = document.getElementById('tab-naked');
            if (nakedTab) nakedTab.style.display = 'none';
        }
        if (!hasCorrectedData) {
            const correctedTab = document.getElementById('tab-corrected');
            if (correctedTab) correctedTab.style.display = 'none';
        }
        if (!hasPresbyopiaData) {
            const presbyopiaTab = document.getElementById('tab-presbyopia');
            if (presbyopiaTab) presbyopiaTab.style.display = 'none';
        }

        // デフォルトタブを表示
        if (defaultTab) {
            switchVisionChart(defaultTab);
        }
    }
}

// グローバル変数で画像リストと現在のインデックスを保持
let currentImageIndex = 0;
let imagesList = [];

// カルテ画像モーダルを開く
function openRecordImageModal(image) {
    const imageTypeLabels = {
        'vision_test': '視力検査',
        'before': '施術前',
        'after': '施術後',
        'progress': '経過',
        'reference': '参考資料',
        'other': 'その他'
    };

    // 現在の画像のインデックスを見つける
    currentImageIndex = imagesList.findIndex(img => img.id === image.id);

    const modal = document.getElementById('imageModal');
    const modalContent = document.getElementById('imageModalContent');

    // 画像データを設定
    updateModalContent(image, imageTypeLabels);

    // ナビゲーションボタンの表示/非表示
    updateNavigationButtons();

    // モーダルを表示
    modal.classList.remove('hidden');

    // アニメーション開始（次のフレームで実行）
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('bg-opacity-75');
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    });

    // bodyのスクロールを無効化
    document.body.style.overflow = 'hidden';
}

// モーダルコンテンツを更新
function updateModalContent(image, imageTypeLabels) {
    document.getElementById('imageModalTitle').textContent = image.title || '画像';
    document.getElementById('imageModalImg').src = `/storage/${image.file_path}`;
    document.getElementById('imageModalImg').alt = image.title || '画像';
    document.getElementById('imageModalDescription').textContent = image.description || '';
    document.getElementById('imageModalType').textContent = `タイプ: ${imageTypeLabels[image.image_type] || 'その他'}`;
    document.getElementById('imageModalCounter').textContent = `${currentImageIndex + 1} / ${imagesList.length}`;
}

// 画像を前後に移動
function navigateImage(direction) {
    const imageTypeLabels = {
        'vision_test': '視力検査',
        'before': '施術前',
        'after': '施術後',
        'progress': '経過',
        'reference': '参考資料',
        'other': 'その他'
    };

    currentImageIndex += direction;

    // 範囲チェック
    if (currentImageIndex < 0) currentImageIndex = imagesList.length - 1;
    if (currentImageIndex >= imagesList.length) currentImageIndex = 0;

    const image = imagesList[currentImageIndex];
    updateModalContent(image, imageTypeLabels);
    updateNavigationButtons();
}

// ナビゲーションボタンの表示/非表示を更新
function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevImageBtn');
    const nextBtn = document.getElementById('nextImageBtn');

    // 画像が1枚しかない場合はボタンを非表示
    if (imagesList.length <= 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
    } else {
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'block';
    }
}

// 画像モーダルを閉じる (カルテ用)
function closeImageModalGallery() {
    const modal = document.getElementById('imageModal');
    const modalContent = document.getElementById('imageModalContent');

    // アニメーション開始
    modal.classList.add('opacity-0');
    modal.classList.remove('bg-opacity-75');
    modalContent.classList.add('scale-95', 'opacity-0');
    modalContent.classList.remove('scale-100', 'opacity-100');

    // アニメーション終了後に非表示
    setTimeout(() => {
        modal.classList.add('hidden');
        // bodyのスクロールを有効化
        document.body.style.overflow = '';
    }, 300);
}

// 全画像を統合表示する関数
function displayAllImages(images) {
    const container = document.getElementById('all-images-container');
    const grid = document.getElementById('all-images-grid');

    if (!images || images.length === 0) {
        container.classList.add('hidden');
        return;
    }

    // 画像を日付の新しい順にソート
    images.sort((a, b) => new Date(b.record_date) - new Date(a.record_date));

    // グローバル変数に画像リストを保存（モーダルナビゲーション用）
    imagesList = images;

    const imageTypeLabels = {
        'vision_test': '視力検査',
        'before': '施術前',
        'after': '施術後',
        'progress': '経過',
        'reference': '参考資料',
        'other': 'その他'
    };

    grid.innerHTML = images.map((image, index) => {
        const sourceLabel = image.source === 'medical_record' ? 'カルテ' : '顧客画像';
        const typeLabel = imageTypeLabels[image.image_type] || 'その他';
        const dateStr = image.record_date ? new Date(image.record_date).toLocaleDateString('ja-JP', { year: 'numeric', month: 'short', day: 'numeric' }) : '';

        return `
            <div class="relative group cursor-pointer" onclick="openAllImagesModal(${index})">
                <img src="/storage/${image.file_path}"
                     alt="${escapeHtml(image.title || '画像')}"
                     class="w-full h-32 object-cover rounded-lg shadow-sm">
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-opacity rounded-lg flex items-center justify-center">
                    <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                    </svg>
                </div>
                <div class="absolute top-2 left-2 flex gap-1">
                    <span class="bg-black bg-opacity-60 text-white text-xs px-2 py-1 rounded">
                        ${sourceLabel}
                    </span>
                    ${image.image_type ? `
                        <span class="bg-blue-600 bg-opacity-80 text-white text-xs px-2 py-1 rounded">
                            ${typeLabel}
                        </span>
                    ` : ''}
                </div>
                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-2 rounded-b-lg">
                    ${image.title ? `<p class="text-white text-xs truncate">${escapeHtml(image.title)}</p>` : ''}
                    <p class="text-gray-300 text-xs">${dateStr}</p>
                </div>
            </div>
        `;
    }).join('');

    container.classList.remove('hidden');
}

// 統合画像ギャラリーからモーダルを開く
function openAllImagesModal(index) {
    currentImageIndex = index;
    const image = imagesList[index];

    const imageTypeLabels = {
        'vision_test': '視力検査',
        'before': '施術前',
        'after': '施術後',
        'progress': '経過',
        'reference': '参考資料',
        'other': 'その他'
    };

    const modal = document.getElementById('imageModal');
    const modalContent = document.getElementById('imageModalContent');

    // 画像データを設定
    updateModalContent(image, imageTypeLabels);

    // ナビゲーションボタンの表示/非表示
    updateNavigationButtons();

    // モーダルを表示
    modal.classList.remove('hidden');

    // アニメーション開始（次のフレームで実行）
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('bg-opacity-75');
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    });

    // bodyのスクロールを無効化
    document.body.style.overflow = 'hidden';
}
</script>

{{-- モバイル用固定ナビゲーションバー --}}
@include('components.mobile-nav')
@endsection