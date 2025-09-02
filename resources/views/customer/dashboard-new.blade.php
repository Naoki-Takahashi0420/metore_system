@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- ヘッダー --}}
    <div class="bg-white shadow-sm border-b">
        <div class="px-4 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-bold text-gray-900">こんにちは、<span id="customer-name">ゲスト</span>様</h1>
                    <p class="text-sm text-gray-500">マイページ</p>
                </div>
                <button onclick="logout()" class="text-sm text-gray-500 hover:text-gray-700">
                    ログアウト
                </button>
            </div>
        </div>
    </div>

    {{-- メインメニュー --}}
    <div class="px-4 py-6 space-y-4">
        {{-- 1. 予約する --}}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-4 border-b bg-blue-50">
                <h2 class="text-lg font-bold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    予約する
                </h2>
            </div>
            <div class="divide-y">
                <a href="/stores" class="block p-4 hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">新規予約</p>
                            <p class="text-sm text-gray-500">店舗・メニューを選んで予約</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
                <button onclick="showQuickBooking()" class="w-full text-left p-4 hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">前回と同じメニューで予約</p>
                            <p class="text-sm text-gray-500">
                                <span id="last-menu-info" class="text-blue-600">読み込み中...</span>
                            </p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        {{-- 2. 予約を変更・キャンセル --}}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-4 border-b bg-green-50">
                <h2 class="text-lg font-bold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    予約管理
                </h2>
            </div>
            
            {{-- 今後の予約 --}}
            <div class="p-4">
                <h3 class="text-sm font-medium text-gray-700 mb-3">今後の予約</h3>
                <div id="upcoming-reservations" class="space-y-3">
                    <div class="text-center py-8 text-gray-400">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-400 mx-auto"></div>
                        <p class="mt-2 text-sm">読み込み中...</p>
                    </div>
                </div>
                
                {{-- すべての予約を見る --}}
                <button onclick="showAllReservations()" class="w-full mt-4 text-center text-sm text-blue-600 hover:text-blue-800 font-medium">
                    すべての予約を見る →
                </button>
            </div>
        </div>

        {{-- 3. カルテを見る --}}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-4 border-b bg-purple-50">
                <h2 class="text-lg font-bold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    カルテ
                </h2>
            </div>
            <div class="divide-y">
                <a href="/customer/medical-records" class="block p-4 hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">カルテ一覧</p>
                            <p class="text-sm text-gray-500">
                                過去のカルテ: <span id="karte-count" class="font-medium">0</span>件
                            </p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
                <button onclick="showVisionProgress()" class="w-full text-left p-4 hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">視力推移グラフ</p>
                            <p class="text-sm text-gray-500">トレーニング効果を確認</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        {{-- その他のメニュー --}}
        <div class="bg-white rounded-lg shadow-sm divide-y">
            <a href="/customer/profile" class="block p-4 hover:bg-gray-50 transition">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="text-gray-700">プロフィール設定</span>
                </div>
            </a>
            <a href="/customer/notifications" class="block p-4 hover:bg-gray-50 transition">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="text-gray-700">通知設定</span>
                </div>
            </a>
        </div>
    </div>

    {{-- モーダル: すべての予約 --}}
    <div id="all-reservations-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeAllReservations()"></div>
        <div class="fixed inset-x-0 bottom-0 max-h-[80vh] bg-white rounded-t-xl overflow-hidden">
            <div class="sticky top-0 bg-white border-b p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold">予約一覧</h3>
                    <button onclick="closeAllReservations()" class="p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex gap-2 mt-3">
                    <button onclick="filterReservations('all')" class="filter-btn px-3 py-1 rounded-full text-sm font-medium bg-blue-600 text-white">すべて</button>
                    <button onclick="filterReservations('upcoming')" class="filter-btn px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-700">今後</button>
                    <button onclick="filterReservations('past')" class="filter-btn px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-700">過去</button>
                </div>
            </div>
            <div class="p-4 overflow-y-auto" style="max-height: calc(80vh - 120px);">
                <div id="all-reservations-list" class="space-y-3"></div>
            </div>
        </div>
    </div>

    {{-- モーダル: 前回と同じメニューで予約 --}}
    <div id="quick-booking-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeQuickBooking()"></div>
        <div class="fixed inset-x-4 top-1/2 -translate-y-1/2 max-w-md mx-auto bg-white rounded-lg p-6">
            <h3 class="text-lg font-bold mb-4">前回と同じメニューで予約</h3>
            <div id="quick-booking-content"></div>
            <div class="flex gap-3 mt-6">
                <button onclick="closeQuickBooking()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700">
                    キャンセル
                </button>
                <button onclick="confirmQuickBooking()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg">
                    予約画面へ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let allReservations = [];
let currentFilter = 'all';
let lastReservation = null;

// ページ読み込み時
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    loadCustomerInfo();
    loadReservations();
    loadStats();
});

// 認証チェック
function checkAuth() {
    const token = localStorage.getItem('customer_token');
    if (!token) {
        window.location.href = '/customer/login';
    }
}

// 顧客情報読み込み
function loadCustomerInfo() {
    const customerData = localStorage.getItem('customer_data');
    if (customerData) {
        const customer = JSON.parse(customerData);
        document.getElementById('customer-name').textContent = customer.last_name + customer.first_name;
    }
}

// 予約読み込み
async function loadReservations() {
    try {
        const token = localStorage.getItem('customer_token');
        console.log('Loading reservations with token:', token ? token.substring(0, 20) + '...' : 'NO TOKEN');
        
        const response = await fetch('/api/customer/reservations', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('Response status:', response.status);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Reservations data:', data);
            allReservations = data.data || [];
            
            // 今後の予約を表示
            displayUpcomingReservations();
            
            // 最後の予約を取得
            if (allReservations.length > 0) {
                lastReservation = allReservations[0];
                updateLastMenuInfo();
            }
        } else {
            // エラーの詳細を表示
            const errorText = await response.text();
            console.error('API Error:', response.status, errorText);
            
            // 401の場合は再ログインを促す
            if (response.status === 401) {
                alert('セッションが切れました。再度ログインしてください。');
                window.location.href = '/customer/login';
            } else {
                // エラー表示
                document.getElementById('upcoming-reservations').innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        <p>予約の取得に失敗しました</p>
                        <p class="text-sm mt-1">エラー: ${response.status}</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading reservations:', error);
        document.getElementById('upcoming-reservations').innerHTML = `
            <div class="text-center py-4 text-red-500">
                <p>ネットワークエラー</p>
                <p class="text-sm mt-1">${error.message}</p>
            </div>
        `;
    }
}

// 今後の予約表示
function displayUpcomingReservations() {
    const container = document.getElementById('upcoming-reservations');
    const upcoming = allReservations.filter(r => {
        const date = new Date(r.reservation_date);
        return date >= new Date() && r.status !== 'cancelled';
    }).slice(0, 2); // 最大2件表示
    
    if (upcoming.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-gray-500">
                <p>予約はありません</p>
                <a href="/stores" class="text-blue-600 text-sm mt-2 inline-block">新規予約する →</a>
            </div>
        `;
        return;
    }
    
    container.innerHTML = upcoming.map(r => `
        <div class="border rounded-lg p-3">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <p class="font-medium text-gray-900">${formatDate(r.reservation_date)}</p>
                    <p class="text-sm text-gray-600">${r.store?.name || '店舗名'}</p>
                    <p class="text-sm text-gray-500">${r.menu?.name || 'メニュー名'}</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="changeReservation(${r.id})" class="text-xs px-2 py-1 border rounded text-gray-600">
                        変更
                    </button>
                    <button onclick="cancelReservation(${r.id})" class="text-xs px-2 py-1 border border-red-300 rounded text-red-600">
                        キャンセル
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// 最後のメニュー情報更新
function updateLastMenuInfo() {
    const info = document.getElementById('last-menu-info');
    if (lastReservation && lastReservation.menu) {
        info.textContent = `${lastReservation.store?.name} - ${lastReservation.menu.name}`;
    } else {
        info.textContent = '履歴がありません';
    }
}

// 統計情報読み込み
async function loadStats() {
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch('/api/customer/medical-records', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            document.getElementById('karte-count').textContent = data.data?.length || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// すべての予約を表示
function showAllReservations() {
    document.getElementById('all-reservations-modal').classList.remove('hidden');
    displayAllReservations();
}

function closeAllReservations() {
    document.getElementById('all-reservations-modal').classList.add('hidden');
}

// 予約フィルター
function filterReservations(type) {
    currentFilter = type;
    
    // ボタンのスタイル更新
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('bg-blue-600', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });
    event.target.classList.remove('bg-gray-200', 'text-gray-700');
    event.target.classList.add('bg-blue-600', 'text-white');
    
    displayAllReservations();
}

// すべての予約を表示
function displayAllReservations() {
    const container = document.getElementById('all-reservations-list');
    let filtered = allReservations;
    
    if (currentFilter === 'upcoming') {
        filtered = allReservations.filter(r => new Date(r.reservation_date) >= new Date());
    } else if (currentFilter === 'past') {
        filtered = allReservations.filter(r => new Date(r.reservation_date) < new Date());
    }
    
    if (filtered.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-500 py-8">予約がありません</p>';
        return;
    }
    
    container.innerHTML = filtered.map(r => `
        <div class="border rounded-lg p-4">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <p class="font-medium">${formatDate(r.reservation_date)}</p>
                    <p class="text-sm text-gray-600">${r.store?.name || '店舗名'}</p>
                </div>
                <span class="text-xs px-2 py-1 rounded-full ${getStatusClass(r.status)}">
                    ${getStatusText(r.status)}
                </span>
            </div>
            <p class="text-sm text-gray-700">${r.menu?.name || 'メニュー'}</p>
            <p class="text-sm font-medium mt-1">¥${Math.floor(r.total_amount || 0).toLocaleString()}</p>
            ${r.status === 'confirmed' ? `
                <div class="flex gap-2 mt-3">
                    <button onclick="changeReservation(${r.id})" class="flex-1 text-sm px-3 py-1 border rounded">
                        変更
                    </button>
                    <button onclick="cancelReservation(${r.id})" class="flex-1 text-sm px-3 py-1 border border-red-300 text-red-600 rounded">
                        キャンセル
                    </button>
                </div>
            ` : ''}
        </div>
    `).join('');
}

// 前回と同じメニューで予約
function showQuickBooking() {
    if (!lastReservation || !lastReservation.menu) {
        alert('予約履歴がありません');
        return;
    }
    
    const modal = document.getElementById('quick-booking-modal');
    const content = document.getElementById('quick-booking-content');
    
    content.innerHTML = `
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">店舗</p>
                <p class="font-medium">${lastReservation.store?.name}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">メニュー</p>
                <p class="font-medium">${lastReservation.menu.name}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">料金</p>
                <p class="font-medium">¥${Math.floor(lastReservation.menu.price).toLocaleString()}</p>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
}

function closeQuickBooking() {
    document.getElementById('quick-booking-modal').classList.add('hidden');
}

function confirmQuickBooking() {
    // セッションに必要な情報を保存
    if (lastReservation && lastReservation.store && lastReservation.menu) {
        // 店舗情報を保存
        sessionStorage.setItem('selected_store', JSON.stringify({
            id: lastReservation.store_id,
            name: lastReservation.store.name,
            phone: lastReservation.store.phone,
            address: lastReservation.store.address
        }));
        
        // メニュー情報を保存
        sessionStorage.setItem('selected_menu', JSON.stringify({
            id: lastReservation.menu_id,
            name: lastReservation.menu.name,
            price: lastReservation.menu.price,
            duration_minutes: lastReservation.menu.duration_minutes || 60,
            category_id: lastReservation.menu.category_id
        }));
        
        // カテゴリー情報も保存（あれば）
        if (lastReservation.menu.category_id) {
            sessionStorage.setItem('selected_category', JSON.stringify({
                id: lastReservation.menu.category_id
            }));
        }
        
        // 時間選択画面へ遷移（カレンダーに直接行くのではなく、時間選択から）
        window.location.href = '/reservation/time';
    } else {
        alert('予約情報が不完全です。新規予約をお願いします。');
        window.location.href = '/stores';
    }
}

// 予約変更
function changeReservation(id) {
    const reservation = allReservations.find(r => r.id === id);
    if (!reservation) return;
    
    // 24時間前チェック
    const reservationDate = new Date(reservation.reservation_date);
    const now = new Date();
    const hoursUntil = (reservationDate - now) / (1000 * 60 * 60);
    
    if (hoursUntil < 24) {
        showPhoneContactModal(reservation.store, '予約変更');
        return;
    }
    
    // TODO: 予約変更モーダルを表示
    alert('予約変更機能は準備中です。\n\n現在は、一度キャンセルして新規予約をお願いします。');
}

// 予約キャンセル
async function cancelReservation(id) {
    const reservation = allReservations.find(r => r.id === id);
    if (!reservation) return;
    
    // 24時間前チェック（フロントエンドでも確認）
    const reservationDate = new Date(reservation.reservation_date);
    const now = new Date();
    const hoursUntil = (reservationDate - now) / (1000 * 60 * 60);
    
    if (hoursUntil < 24) {
        showPhoneContactModal(reservation.store, 'キャンセル');
        return;
    }
    
    if (!confirm('予約をキャンセルしますか？\n\nこの操作は取り消せません。')) return;
    
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch(`/api/customer/reservations/${id}/cancel`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cancel_reason: '顧客都合'
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            alert('予約をキャンセルしました');
            loadReservations();
            closeAllReservations();
        } else if (data.require_phone_contact) {
            showPhoneContactModal({
                name: data.store_name,
                phone: data.store_phone
            }, 'キャンセル');
        } else {
            alert(data.message || 'キャンセルに失敗しました');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('エラーが発生しました');
    }
}

// 店舗への電話案内モーダル
function showPhoneContactModal(store, action) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="this.parentElement.remove()"></div>
        <div class="relative bg-white rounded-lg p-6 max-w-sm w-full">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-yellow-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <h3 class="text-lg font-bold mb-2">店舗へお電話ください</h3>
                <p class="text-gray-600 mb-4">
                    予約の${action}は24時間前までとなっております。<br>
                    お手数ですが、店舗へ直接お電話ください。
                </p>
                <div class="bg-gray-100 rounded-lg p-4 mb-4">
                    <p class="font-medium">${store.name}</p>
                    <a href="tel:${store.phone}" class="text-2xl font-bold text-blue-600 hover:text-blue-800">
                        ${store.phone}
                    </a>
                </div>
                <button onclick="this.closest('.fixed').remove()" class="w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    閉じる
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// 視力推移グラフ
function showVisionProgress() {
    const customerData = JSON.parse(localStorage.getItem('customer_data'));
    if (customerData) {
        window.location.href = `/customer/${customerData.id}/vision-progress`;
    }
}

// ログアウト
function logout() {
    if (confirm('ログアウトしますか？')) {
        localStorage.removeItem('customer_token');
        localStorage.removeItem('customer_data');
        window.location.href = '/customer/login';
    }
}

// ユーティリティ関数
function formatDate(dateStr) {
    const date = new Date(dateStr);
    const month = date.getMonth() + 1;
    const day = date.getDate();
    const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    const weekday = weekdays[date.getDay()];
    const hours = date.getHours();
    const minutes = date.getMinutes();
    
    return `${month}月${day}日(${weekday}) ${hours}:${minutes.toString().padStart(2, '0')}`;
}

function getStatusClass(status) {
    switch(status) {
        case 'confirmed': return 'bg-green-100 text-green-800';
        case 'completed': return 'bg-gray-100 text-gray-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'confirmed': return '予約確定';
        case 'completed': return '完了';
        case 'cancelled': return 'キャンセル';
        default: return status;
    }
}
</script>
@endsection