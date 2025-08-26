@extends('layouts.app')

@section('title', '店舗一覧')

@section('content')
<div class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">店舗を選択</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    ご利用店舗をお選びください。
                </p>
            </div>
        </div>
    </div>

    <!-- Store Selection -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div id="stores-container" class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Stores will be loaded here via JavaScript -->
        </div>
        
        <!-- Loading state -->
        <div id="loading" class="text-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">店舗情報を読み込んでいます...</p>
        </div>
        
        <!-- Error state -->
        <div id="error" class="hidden text-center py-20">
            <div class="text-red-600 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-lg font-semibold text-gray-900 mb-2">データの読み込みに失敗しました</p>
            <p class="text-gray-600 mb-6">しばらく時間をおいて再度お試しください。</p>
            <button onclick="loadStores()" class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                再試行
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStores();
});

async function loadStores() {
    const container = document.getElementById('stores-container');
    const loading = document.getElementById('loading');
    const error = document.getElementById('error');
    
    // Reset states
    container.innerHTML = '';
    loading.classList.remove('hidden');
    error.classList.add('hidden');
    
    try {
        const response = await fetch('/api/stores');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        const stores = data.data || data; // Handle different response formats
        
        if (!stores || stores.length === 0) {
            throw new Error('No stores found');
        }
        
        container.innerHTML = stores.map(store => `
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                ${store.image_path ? `
                    <div class="bg-gray-200 overflow-hidden" style="aspect-ratio: 16/9;">
                        <img src="/storage/${store.image_path}" alt="${store.name}" class="w-full h-full object-cover">
                    </div>
                ` : `
                    <div class="bg-gradient-to-br from-blue-100 to-blue-50 flex items-center justify-center" style="aspect-ratio: 16/9;">
                        <svg class="w-20 h-20 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                `}
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">${store.name}</h3>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 mr-3 text-primary-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div>
                                <p class="text-gray-700">${store.address}</p>
                                ${store.access_info ? `<p class="text-sm text-gray-500 mt-1">${store.access_info}</p>` : ''}
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="text-gray-700">${store.phone}</span>
                        </div>
                        
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-gray-700">${getOpeningHours(store.opening_hours || store.business_hours)}</span>
                        </div>
                        
                        ${store.capacity ? `
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-gray-700">最大収容人数: ${store.capacity}名</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${store.description ? `
                    <div class="mb-6">
                        <p class="text-sm text-gray-600">${store.description}</p>
                    </div>
                    ` : ''}
                    
                    <button 
                        onclick="selectStore(${store.id}, '${store.name}')" 
                        class="w-full bg-primary-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-primary-700 transition-colors"
                    >
                        この店舗を選択
                    </button>
                </div>
            </div>
        `).join('');
        
        loading.classList.add('hidden');
        
    } catch (err) {
        console.error('Error loading stores:', err);
        loading.classList.add('hidden');
        error.classList.remove('hidden');
    }
}

function getOpeningHours(openingHours) {
    if (!openingHours) return '営業時間: お問い合わせください';
    
    if (typeof openingHours === 'string') {
        try {
            openingHours = JSON.parse(openingHours);
        } catch (e) {
            return openingHours;
        }
    }
    
    // business_hours形式（配列）の場合の変換
    if (Array.isArray(openingHours)) {
        const converted = {};
        openingHours.forEach(item => {
            if (item.day) {
                if (item.is_closed) {
                    converted[item.day] = null;
                } else {
                    converted[item.day] = {
                        open: item.open_time,
                        close: item.close_time
                    };
                }
            }
        });
        openingHours = converted;
    }
    
    if (typeof openingHours === 'object') {
        const dayNames = {
            'monday': '月',
            'tuesday': '火', 
            'wednesday': '水',
            'thursday': '木',
            'friday': '金',
            'saturday': '土',
            'sunday': '日'
        };
        
        const today = new Date();
        const todayName = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][today.getDay()];
        const todayHours = openingHours[todayName];
        
        // 営業時間を文字列に変換する関数
        const formatHours = (hours) => {
            if (!hours) return '休業日';
            if (typeof hours === 'string') return hours;
            if (typeof hours === 'object' && hours.open && hours.close) {
                return `${hours.open}-${hours.close}`;
            }
            return '休業日';
        };
        
        // 今日の営業時間を表示
        let result = '';
        const todayFormatted = formatHours(todayHours);
        if (todayFormatted === '休業日') {
            result = `<span class="text-red-600">本日休業日</span>`;
        } else {
            result = `本日: ${todayFormatted}`;
        }
        
        // 営業時間のパターンを分析
        const formattedDays = {};
        Object.entries(openingHours).forEach(([day, hours]) => {
            formattedDays[day] = formatHours(hours);
        });
        
        const uniqueHours = [...new Set(Object.values(formattedDays))];
        
        if (uniqueHours.length === 1 && uniqueHours[0] !== '休業日') {
            return `営業時間: ${uniqueHours[0]} (毎日)`;
        }
        
        // 平日・土日の営業時間パターンを検出
        const weekdayHours = formattedDays.monday; // 月曜を平日の代表として使用
        const saturdayHours = formattedDays.saturday;
        const sundayHours = formattedDays.sunday;
        
        let additionalInfo = '';
        if (weekdayHours && weekdayHours !== '休業日') {
            if (saturdayHours === weekdayHours && sundayHours === weekdayHours) {
                additionalInfo = ` (毎日同じ)`;
            } else if (saturdayHours !== weekdayHours || sundayHours !== weekdayHours) {
                additionalInfo = ` (土日は異なる)`;
            }
        }
        
        return `<span>${result}${additionalInfo}</span>`;
    }
    
    return '営業時間: お問い合わせください';
}

function selectStore(storeId, storeName) {
    // Store selection via form POST to maintain PHP session
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/reservation/store-selection';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = csrfToken.content;
        form.appendChild(tokenInput);
    }
    
    const storeInput = document.createElement('input');
    storeInput.type = 'hidden';
    storeInput.name = 'store_id';
    storeInput.value = storeId;
    form.appendChild(storeInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection