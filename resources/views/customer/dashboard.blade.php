@extends('layouts.app')

@section('title', 'マイページ')

@section('content')
<div class="bg-gray-50 min-h-screen py-4 md:py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- ヘッダー -->
        <div class="bg-white rounded-lg shadow-md p-4 md:p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">マイページ</h1>
                    <p class="text-sm md:text-base text-gray-600" id="customer-info">
                        読み込み中...
                    </p>
                </div>
                <button id="logout-btn" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
                    ログアウト
                </button>
            </div>
        </div>

        <!-- メインメニュー -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-8">
            <!-- 新規予約 -->
            <a href="#" onclick="goToReservation(); return false;" class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-white/20 p-3 rounded-lg">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="bg-white/30 px-3 py-1 rounded-full text-sm font-medium">予約する</span>
                    </div>
                    <h3 class="text-xl font-bold mb-2">予約する</h3>
                    <p class="text-white/90 text-sm">次回の予約を取る</p>
                </div>
            </a>

            <!-- カルテ -->
            <a href="/customer/medical-records" class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-white/20 p-3 rounded-lg">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span id="karte-count" class="bg-white/30 px-3 py-1 rounded-full text-sm font-medium">-</span>
                    </div>
                    <h3 class="text-xl font-bold mb-2">カルテ</h3>
                    <p class="text-white/90 text-sm">施術記録を確認</p>
                </div>
            </a>

            <!-- 予約変更・キャンセル -->
            <a href="#reservations" class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-white/20 p-3 rounded-lg">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span id="active-reservation-count" class="bg-white/30 px-3 py-1 rounded-full text-sm font-medium">-</span>
                    </div>
                    <h3 class="text-xl font-bold mb-2">予約管理</h3>
                    <p class="text-white/90 text-sm">予約の変更・キャンセル</p>
                </div>
            </a>
        </div>


        <!-- 予約一覧セクション -->
        <div id="reservations" class="bg-white rounded-lg shadow-md p-4 md:p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg md:text-xl font-bold text-gray-900">予約一覧</h2>
                <div class="flex gap-2">
                    <button onclick="filterReservations('all')" class="filter-btn active px-3 py-1 text-sm rounded-lg transition-colors" data-filter="all">
                        すべて
                    </button>
                    <button onclick="filterReservations('upcoming')" class="filter-btn px-3 py-1 text-sm rounded-lg transition-colors" data-filter="upcoming">
                        今後の予約
                    </button>
                    <button onclick="filterReservations('past')" class="filter-btn px-3 py-1 text-sm rounded-lg transition-colors" data-filter="past">
                        過去の予約
                    </button>
                </div>
            </div>

            <!-- 予約リスト -->
            <div id="reservations-container">
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                    <p class="text-gray-500 mt-2">予約情報を読み込み中...</p>
                </div>
            </div>

            <!-- 空の状態 -->
            <div id="empty-state" class="hidden text-center py-12">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">予約がありません</h3>
                <p class="text-gray-500 mb-6">新しい予約を取りましょう</p>
                <a href="/reservation/store" class="bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors inline-block">
                    予約する
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.filter-btn {
    background-color: #f3f4f6;
    color: #6b7280;
}
.filter-btn.active {
    background-color: #3b82f6;
    color: white;
}
.filter-btn:hover:not(.active) {
    background-color: #e5e7eb;
}
</style>

<script>
let allReservations = [];
let currentFilter = 'all';

document.addEventListener('DOMContentLoaded', async function() {
    const token = localStorage.getItem('customer_token');
    const customerData = localStorage.getItem('customer_data');
    
    // トークンがない場合はログインページにリダイレクト
    if (!token) {
        window.location.href = '/customer/login';
        return;
    }
    
    // 顧客情報を表示
    if (customerData) {
        try {
            const customer = JSON.parse(customerData);
            document.getElementById('customer-info').textContent = 
                `${customer.last_name} ${customer.first_name} 様`;
        } catch (e) {
            console.error('Customer data parse error:', e);
        }
    }
    
    // データ取得
    await fetchReservations();
    await fetchStats();
    
    // ログアウト処理
    document.getElementById('logout-btn').addEventListener('click', function() {
        if (confirm('ログアウトしますか？')) {
            localStorage.removeItem('customer_token');
            localStorage.removeItem('customer_data');
            window.location.href = '/customer/login';
        }
    });
});

async function fetchReservations() {
    try {
        const token = localStorage.getItem('customer_token');
        console.log('Token:', token ? 'Found (' + token.substring(0, 20) + '...)' : 'Not found');
        
        if (!token) {
            console.error('No token found in localStorage');
            document.getElementById('reservations-container').innerHTML = `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800">ログインしてください。</p>
                </div>
            `;
            return;
        }
        
        const response = await fetch('/api/customer/reservations', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            if (response.status === 401) {
                console.error('Token is invalid or expired');
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                window.location.href = '/customer/login';
                return;
            }
            const errorText = await response.text();
            console.error('API Error:', errorText);
            throw new Error(`Failed to fetch reservations: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Reservations data:', data);
        allReservations = data.data || [];
        displayReservations();
        updateReservationCount();
        
    } catch (error) {
        console.error('Error fetching reservations:', error);
        document.getElementById('reservations-container').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">予約履歴の取得に失敗しました。</p>
                <p class="text-red-600 text-sm mt-1">エラー: ${error.message}</p>
            </div>
        `;
    }
}

async function fetchStats() {
    try {
        const token = localStorage.getItem('customer_token');
        
        // カルテ数の取得
        const karteResponse = await fetch('/api/customer/medical-records', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (karteResponse.ok) {
            const karteData = await karteResponse.json();
            const karteCount = karteData.data?.length || 0;
            document.getElementById('karte-count').textContent = `${karteCount}件`;
        }
        
    } catch (error) {
        console.error('Error fetching stats:', error);
    }
}

function updateReservationCount() {
    const now = new Date();
    const activeCount = allReservations.filter(r => {
        const reservationDate = new Date(r.reservation_date + 'T' + r.start_time);
        return reservationDate > now && !['cancelled', 'canceled'].includes(r.status);
    }).length;
    
    document.getElementById('active-reservation-count').textContent = `${activeCount}件`;
}

function filterReservations(filter) {
    currentFilter = filter;
    
    // ボタンのアクティブ状態を更新
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === filter) {
            btn.classList.add('active');
        }
    });
    
    displayReservations();
}

function displayReservations() {
    const container = document.getElementById('reservations-container');
    const emptyState = document.getElementById('empty-state');
    const now = new Date();
    
    // フィルタリング
    let filteredReservations = allReservations;
    if (currentFilter === 'upcoming') {
        filteredReservations = allReservations.filter(r => {
            const reservationDate = new Date(r.reservation_date + 'T' + r.start_time);
            return reservationDate > now;
        });
    } else if (currentFilter === 'past') {
        filteredReservations = allReservations.filter(r => {
            const reservationDate = new Date(r.reservation_date + 'T' + r.start_time);
            return reservationDate <= now;
        });
    }
    
    if (filteredReservations.length === 0) {
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    emptyState.classList.add('hidden');
    
    container.innerHTML = filteredReservations.map(reservation => {
        const reservationDate = new Date(reservation.reservation_date + 'T' + reservation.start_time);
        const isPast = reservationDate <= now;
        
        return `
        <div class="border border-gray-200 rounded-lg p-4 mb-4 ${isPast ? 'bg-gray-50' : 'bg-white'}">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="text-xs font-medium px-2 py-1 rounded-full ${getStatusColor(reservation.status)}">
                            ${getStatusText(reservation.status)}
                        </span>
                        <span class="text-xs text-gray-500">予約番号: ${reservation.reservation_number}</span>
                    </div>
                    
                    <h3 class="font-semibold text-gray-900 mb-2">${reservation.menu?.name || 'メニュー情報なし'}</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-600">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            ${reservation.store?.name || '店舗情報なし'}
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            ${formatDate(reservation.reservation_date)}
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            ${formatTime(reservation.start_time)} - ${formatTime(reservation.end_time)}
                        </div>
                    </div>
                    
                    <div class="mt-2 text-lg font-semibold text-gray-900">
                        ¥${Math.floor(reservation.total_amount || 0).toLocaleString()}
                    </div>
                </div>
                
                <div class="flex gap-2">
                    ${!isPast && canCancel(reservation) ? `
                        <button onclick="changeReservationDate(${reservation.id})" 
                                class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                            日程変更
                        </button>
                        <button onclick="cancelReservation(${reservation.id})" 
                                class="bg-red-600 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                            キャンセル
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `}).join('');
}

function getStatusColor(status) {
    const colors = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'confirmed': 'bg-green-100 text-green-800',
        'booked': 'bg-green-100 text-green-800',
        'in_progress': 'bg-blue-100 text-blue-800',
        'completed': 'bg-gray-100 text-gray-800',
        'cancelled': 'bg-red-100 text-red-800',
        'canceled': 'bg-red-100 text-red-800',
        'no_show': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function getStatusText(status) {
    const texts = {
        'booked': '予約確定',      
        'completed': '完了',
        'cancelled': 'キャンセル済み',
        'pending': '予約確定',      
        'confirmed': '予約確定',
        'in_progress': '完了',
        'canceled': 'キャンセル済み',
        'no_show': 'キャンセル済み'
    };
    return texts[status] || status;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', { 
        year: 'numeric', 
        month: 'numeric', 
        day: 'numeric',
        weekday: 'short'
    });
}

function formatTime(timeString) {
    return timeString.substring(0, 5);
}

function canCancel(reservation) {
    if (['cancelled', 'canceled', 'completed', 'no_show'].includes(reservation.status)) {
        return false;
    }
    
    const reservationDateTime = new Date(reservation.reservation_date + 'T' + reservation.start_time);
    const now = new Date();
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);
    
    return hoursDiff > 24;
}

// マイページから既存顧客として予約
function goToReservation() {
    // 顧客データを取得
    const customerData = localStorage.getItem('customer_data');
    if (customerData) {
        const customer = JSON.parse(customerData);
        // セッションストレージに既存顧客情報を保存
        sessionStorage.setItem('existing_customer_id', customer.id);
        sessionStorage.setItem('from_mypage', 'true');
    }
    // 予約ページへ遷移
    window.location.href = '/reservation/store';
}

// 日程変更
function changeReservationDate(reservationId) {
    alert('日程変更機能は準備中です。\nお電話でお問い合わせください。');
    // TODO: 日程変更ページへの遷移を実装
    // window.location.href = `/customer/reservations/${reservationId}/change`;
}

async function cancelReservation(reservationId) {
    if (!confirm('本当にこの予約をキャンセルしますか？\n24時間前までのキャンセルのみ無料です。')) {
        return;
    }
    
    const reason = prompt('キャンセル理由をお聞かせください（任意）:');
    
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch(`/api/customer/reservations/${reservationId}/cancel`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cancel_reason: reason || '顧客都合'
            })
        });
        
        if (!response.ok) {
            throw new Error('キャンセルに失敗しました');
        }
        
        alert('予約をキャンセルしました');
        
        // データを再取得
        await fetchReservations();
        
    } catch (error) {
        console.error('Cancel error:', error);
        alert('キャンセルに失敗しました。もう一度お試しください。');
    }
}
</script>
@endsection