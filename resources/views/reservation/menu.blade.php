@extends('layouts.app')

@section('title', 'メニュー選択')

@section('content')
<div class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumb -->
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
                <a href="{{ url('/stores') }}" class="hover:text-primary-600">店舗選択</a>
                <span>></span>
                <span class="text-gray-900">メニュー選択</span>
            </div>
            
            <div class="text-center">
                <div id="selected-store" class="mb-2">
                    <!-- Selected store info will be inserted here -->
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">メニューを選択</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    ご希望のサービスメニューを選択してください。お客様の症状やご要望に合わせて最適なプログラムをご提案します。
                </p>
            </div>
        </div>
    </div>

    <!-- Menu Categories Filter -->

    <!-- Menu Selection -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div id="menus-container" class="space-y-4 md:grid md:grid-cols-2 lg:grid-cols-3 md:gap-6 md:space-y-0">
            <!-- Menus will be loaded here via JavaScript -->
        </div>
        
        <!-- Loading state -->
        <div id="loading" class="text-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">メニュー情報を読み込んでいます...</p>
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
            <button onclick="loadMenus()" class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                再試行
            </button>
        </div>
        
        <!-- No menus state -->
        <div id="no-menus" class="hidden text-center py-20">
            <div class="text-gray-400 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <p class="text-lg font-semibold text-gray-900 mb-2">この条件に該当するメニューがありません</p>
            <p class="text-gray-600">別のカテゴリを選択するか、全てのメニューを表示してください。</p>
        </div>
    </div>
</div>

<script>
let allMenus = [];
let currentCategory = 'all';

document.addEventListener('DOMContentLoaded', function() {
    loadSelectedStore();
    loadMenus();
});

function loadSelectedStore() {
    const selectedStore = JSON.parse(sessionStorage.getItem('selectedStore') || '{}');
    const storeDiv = document.getElementById('selected-store');
    
    if (selectedStore.name) {
        storeDiv.innerHTML = `<p class="text-sm text-primary-600 font-medium">選択した店舗: ${selectedStore.name}</p>`;
    } else {
        // Redirect back to store selection if no store is selected
        window.location.href = '/stores';
    }
}

async function loadMenus() {
    const selectedStore = JSON.parse(sessionStorage.getItem('selectedStore') || '{}');
    if (!selectedStore.id) {
        window.location.href = '/stores';
        return;
    }
    
    const container = document.getElementById('menus-container');
    const loading = document.getElementById('loading');
    const error = document.getElementById('error');
    const noMenus = document.getElementById('no-menus');
    
    // Reset states
    container.innerHTML = '';
    loading.classList.remove('hidden');
    error.classList.add('hidden');
    noMenus.classList.add('hidden');
    
    try {
        const response = await fetch('/api/menus');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        const allMenusData = data.data || data;
        
        // Filter menus by selected store
        allMenus = allMenusData.filter(menu => menu.store_id === selectedStore.id);
        
        loading.classList.add('hidden');
        displayMenus(allMenus);
        
    } catch (err) {
        console.error('Error loading menus:', err);
        loading.classList.add('hidden');
        error.classList.remove('hidden');
    }
}

function displayMenus(menus) {
    const container = document.getElementById('menus-container');
    const noMenus = document.getElementById('no-menus');
    
    if (!menus || menus.length === 0) {
        container.innerHTML = '';
        noMenus.classList.remove('hidden');
        return;
    }
    
    noMenus.classList.add('hidden');
    
    container.innerHTML = menus.map(menu => `
        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
            <div class="p-4 sm:p-6">
                <div class="mb-2">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900">${menu.name}</h3>
                </div>
                
                <p class="text-sm text-gray-600 mb-3 line-clamp-2">${menu.description || ''}</p>
                
                <div class="space-y-1 mb-3">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500">所要時間:</span>
                        <span class="text-xs font-medium text-gray-900">${menu.duration}分</span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500">料金:</span>
                        <span class="text-base sm:text-lg font-bold text-primary-600">¥${menu.price.toLocaleString()}</span>
                    </div>
                    
                    ${menu.tags ? `
                    <div class="pt-1">
                        <div class="flex flex-wrap gap-1">
                            ${parseMenuTags(menu.tags).map(tag => `
                                <span class="px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">${tag}</span>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                ${menu.benefits ? `
                <div class="mb-3">
                    <h4 class="text-xs font-medium text-gray-900 mb-1">このメニューの効果:</h4>
                    <ul class="text-xs text-gray-600 space-y-0.5">
                        ${parseMenuTags(menu.benefits).slice(0, 3).map(benefit => `
                            <li class="flex items-start">
                                <svg class="w-3 h-3 mr-1 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="line-clamp-1">${benefit}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
                ` : ''}
                
                <button 
                    onclick="selectMenu(${menu.id}, '${menu.name}', ${menu.price}, ${menu.duration})" 
                    class="w-full bg-primary-600 text-white py-2 sm:py-3 px-3 sm:px-4 rounded-lg text-sm sm:text-base font-semibold hover:bg-primary-700 transition-colors"
                >
                    このメニューを選択
                </button>
            </div>
        </div>
    `).join('');
}

function parseMenuTags(tags) {
    if (!tags) return [];
    
    // Try to parse as JSON first
    try {
        const parsed = JSON.parse(tags);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        // If not JSON, try comma-separated values
        if (typeof tags === 'string') {
            return tags.split(',').map(tag => tag.trim()).filter(tag => tag);
        }
        return [];
    }
}


function selectMenu(menuId, menuName, price, duration) {
    const selectedStore = JSON.parse(sessionStorage.getItem('selectedStore') || '{}');
    
    // Store menu selection
    sessionStorage.setItem('selectedMenu', JSON.stringify({
        id: menuId,
        name: menuName,
        price: price,
        duration: duration
    }));
    
    // Show toast notification
    window.dispatchEvent(new CustomEvent('show-toast', {
        detail: {
            message: `${menuName}を選択しました`,
            type: 'success'
        }
    }));
    
    // Redirect to date/time selection
    setTimeout(() => {
        window.location.href = '/reservation/datetime';
    }, 1500);
}
</script>

<style>
.category-filter {
    background-color: #f3f4f6;
    color: #6b7280;
}

.category-filter.active {
    background-color: #3b82f6;
    color: white;
}

.category-filter:hover {
    background-color: #e5e7eb;
}

.category-filter.active:hover {
    background-color: #2563eb;
}

/* モバイル専用スタイル */
@media (max-width: 640px) {
    #menus-container {
        display: flex !important;
        flex-direction: column !important;
        gap: 1rem !important;
    }
    
    #menus-container > div {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .line-clamp-1 {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .line-clamp-2 {
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
}
</style>
@endsection