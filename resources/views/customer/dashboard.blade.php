@extends('layouts.app')

@section('title', 'マイページ')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
<div class="bg-gray-50 min-h-screen py-8 pb-20">
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

        <!-- 次回の予約 -->
        <div id="next-reservation" class="hidden bg-white border border-gray-200 rounded-xl shadow-sm mb-4 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold mb-3 text-gray-900">次回のご予約</h2>
                    <div id="next-reservation-details">
                        <!-- 動的に挿入 -->
                    </div>
                </div>
                <div class="flex gap-2">
                    <button id="next-change-btn" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        日程変更
                    </button>
                    <button id="next-cancel-btn" class="border border-red-200 hover:bg-red-50 text-red-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        キャンセル
                    </button>
                </div>
            </div>
        </div>

        <!-- サブスクリプション会員用ボタン -->
        <div id="subscription-section" class="hidden bg-gray-900 text-white rounded-xl shadow-sm mb-4 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-lg font-semibold mb-2">サブスクリプション会員</h2>
                    <p class="text-gray-300 text-sm" id="subscription-details">プランの詳細</p>
                </div>
                <button id="subscription-booking-btn" class="bg-white text-gray-900 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    サブスク予約を取る
                </button>
            </div>
        </div>

        <!-- メインメニュー -->
        <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-8">
            <!-- 新規予約 -->
            <a href="#" onclick="goToReservation(); return false;" class="bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md hover:border-gray-300 transition-all duration-200">
                <div class="p-3 sm:p-6">
                    <div class="flex flex-col items-center sm:flex-row sm:items-center sm:justify-between mb-2 sm:mb-4">
                        <div class="bg-gray-100 p-2 sm:p-3 rounded-lg mb-2 sm:mb-0">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="hidden sm:inline-block bg-gray-900 text-white px-3 py-1 rounded-full text-sm font-medium">予約する</span>
                    </div>
                    <h3 class="text-sm sm:text-xl font-bold mb-1 sm:mb-2 text-gray-900 text-center sm:text-left">予約する</h3>
                    <p class="hidden sm:block text-gray-600 text-sm">次回の予約を取る</p>
                </div>
            </a>

            <!-- カルテ -->
            <a href="/customer/medical-records" class="bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md hover:border-gray-300 transition-all duration-200">
                <div class="p-3 sm:p-6">
                    <div class="flex flex-col items-center sm:flex-row sm:items-center sm:justify-between mb-2 sm:mb-4">
                        <div class="bg-gray-100 p-2 sm:p-3 rounded-lg mb-2 sm:mb-0">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span id="karte-count" class="hidden sm:inline-block bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">-</span>
                    </div>
                    <h3 class="text-sm sm:text-xl font-bold mb-1 sm:mb-2 text-gray-900 text-center sm:text-left">カルテ</h3>
                    <p class="hidden sm:block text-gray-600 text-sm">施術記録を確認</p>
                </div>
            </a>

            <!-- 予約変更・キャンセル -->
            <a href="#reservations" class="bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md hover:border-gray-300 transition-all duration-200">
                <div class="p-3 sm:p-6">
                    <div class="flex flex-col items-center sm:flex-row sm:items-center sm:justify-between mb-2 sm:mb-4">
                        <div class="bg-gray-100 p-2 sm:p-3 rounded-lg mb-2 sm:mb-0">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span id="active-reservation-count" class="hidden sm:inline-block bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">-</span>
                    </div>
                    <h3 class="text-sm sm:text-xl font-bold mb-1 sm:mb-2 text-gray-900 text-center sm:text-left">予約管理</h3>
                    <p class="hidden sm:block text-gray-600 text-sm">予約の変更・キャンセル</p>
                </div>
            </a>
        </div>


        <!-- 予約一覧セクション -->
        <div id="reservations" class="bg-white rounded-lg shadow-md p-4 md:p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                <h2 class="text-lg md:text-xl font-bold text-gray-900">予約一覧</h2>
                <div class="flex gap-1 sm:gap-2 w-full sm:w-auto">
                    <button onclick="filterReservations('all')" class="filter-btn active px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-lg transition-colors flex-1 sm:flex-initial" data-filter="all">
                        すべて
                    </button>
                    <button onclick="filterReservations('upcoming')" class="filter-btn px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-lg transition-colors flex-1 sm:flex-initial" data-filter="upcoming">
                        今後の予約
                    </button>
                    <button onclick="filterReservations('past')" class="filter-btn px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-lg transition-colors flex-1 sm:flex-initial" data-filter="past">
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

<!-- キャンセル確認モーダル -->
<div id="cancelModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">予約キャンセル確認</h3>
            <div id="cancelModalContent">
                <p class="text-sm text-gray-600 mb-4">この予約をキャンセルしてもよろしいですか？</p>
                <p class="text-sm text-red-600 mb-4">※24時間前を過ぎたキャンセルは料金が発生する場合があります</p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">キャンセル理由（任意）</label>
                    <textarea id="cancelReason" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="キャンセル理由をお聞かせください"></textarea>
                </div>
                <div class="flex gap-3">
                    <button onclick="closeCancelModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        戻る
                    </button>
                    <button onclick="confirmCancel()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        キャンセルする
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 固定ナビゲーション -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-50">
    <div class="w-full">
        <div class="grid grid-cols-4 gap-0">
            <a href="#" onclick="goToReservation(); return false;" class="flex flex-col items-center justify-center py-3 hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6 text-gray-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="text-xs text-gray-600">予約</span>
            </a>
            <a href="#reservations" class="flex flex-col items-center justify-center py-3 hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6 text-gray-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-xs text-gray-600">予約一覧</span>
            </a>
            <a href="/customer/medical-records" class="flex flex-col items-center justify-center py-3 hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6 text-gray-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="text-xs text-gray-600">カルテ</span>
            </a>
            <a href="#" onclick="document.getElementById('logout-btn').click(); return false;" class="flex flex-col items-center justify-center py-3 hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6 text-gray-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span class="text-xs text-gray-600">ログアウト</span>
            </a>
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
        console.log('=== 予約データ取得開始 ===');
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
        console.log('=== API Response Debug ===');
        console.log('Full response:', data);
        console.log('Data array:', data.data);
        console.log('Data length:', data.data ? data.data.length : 0);
        
        // 各予約の詳細をログ出力
        if (data.data && data.data.length > 0) {
            data.data.forEach((res, index) => {
                console.log(`予約${index + 1}:`, {
                    id: res.id,
                    date: res.reservation_date,
                    time: res.start_time,
                    status: res.status,
                    menu: res.menu?.name
                });
            });
        }
        
        allReservations = data.data || [];
        console.log('allReservations set to:', allReservations);
        console.log('allReservations length:', allReservations.length);
        
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
        
        // サブスクリプション情報の取得
        console.log('=== サブスクリプション取得開始 ===');
        const subscriptionResponse = await fetch('/api/customer/subscriptions', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('Subscription API Response:', subscriptionResponse.status);
        
        if (subscriptionResponse.ok) {
            const subscriptionData = await subscriptionResponse.json();
            console.log('Subscription data:', subscriptionData);
            const activeSubscription = subscriptionData.data?.find(s => s.status === 'active');
            console.log('Active subscription:', activeSubscription);
            
            if (activeSubscription) {
                document.getElementById('subscription-section').classList.remove('hidden');
                document.getElementById('subscription-details').textContent = 
                    `${activeSubscription.plan?.name || 'プラン'} - 残り${activeSubscription.remaining_sessions || 0}回`;
                
                // サブスク予約ボタンのイベント
                window.activeSubscription = activeSubscription; // グローバルに保存
                document.getElementById('subscription-booking-btn').onclick = function() {
                    goToSubscriptionBooking();
                };
            }
        }
        
        // 次の予約を表示
        displayNextReservation();
        
    } catch (error) {
        console.error('Error fetching stats:', error);
    }
}

function displayNextReservation() {
    const now = new Date();
    console.log('=== 次回予約の表示処理 ===');
    console.log('現在時刻:', now.toISOString());
    
    // デバッグ用: 全予約をログ出力
    allReservations.forEach(r => {
        const dateStr = r.reservation_date.split('T')[0];
        const timeStr = r.start_time;
        console.log(`予約ID ${r.id}: 日付=${dateStr}, 時刻=${timeStr}, ステータス=${r.status}`);
        
        // 日付を正しく解析
        const year = parseInt(dateStr.split('-')[0]);
        const month = parseInt(dateStr.split('-')[1]) - 1; // JavaScriptの月は0-11
        const day = parseInt(dateStr.split('-')[2]);
        const hours = parseInt(timeStr.split(':')[0]);
        const minutes = parseInt(timeStr.split(':')[1]);
        
        const reservationDate = new Date(year, month, day, hours, minutes);
        console.log(`  → パース結果: ${reservationDate.toISOString()}`);
        console.log(`  → 未来の予約?: ${reservationDate > now}`);
    });
    
    const upcomingReservations = allReservations
        .filter(r => {
            // 日付フォーマットを正規化して正しくパース
            const dateStr = r.reservation_date.split('T')[0];
            const [year, month, day] = dateStr.split('-').map(Number);
            const [hours, minutes] = r.start_time.split(':').map(Number);
            
            // 月は0-11なので1を引く
            const reservationDate = new Date(year, month - 1, day, hours, minutes);
            // 今日の予約も含める（>=にする）
            return reservationDate >= now && !['cancelled', 'canceled'].includes(r.status);
        })
        .sort((a, b) => {
            const dateStrA = a.reservation_date.split('T')[0];
            const dateStrB = b.reservation_date.split('T')[0];
            const [yearA, monthA, dayA] = dateStrA.split('-').map(Number);
            const [yearB, monthB, dayB] = dateStrB.split('-').map(Number);
            const [hoursA, minutesA] = a.start_time.split(':').map(Number);
            const [hoursB, minutesB] = b.start_time.split(':').map(Number);
            
            const dateA = new Date(yearA, monthA - 1, dayA, hoursA, minutesA);
            const dateB = new Date(yearB, monthB - 1, dayB, hoursB, minutesB);
            return dateA - dateB;
        });
    
    if (upcomingReservations.length > 0) {
        const nextReservation = upcomingReservations[0];
        const nextReservationElement = document.getElementById('next-reservation');
        nextReservationElement.classList.remove('hidden');
        
        document.getElementById('next-reservation-details').innerHTML = `
            <div class="space-y-2">
                <p class="text-2xl font-bold text-gray-900">${nextReservation.menu?.name || 'メニュー'}</p>
                <div class="flex flex-wrap gap-4 text-gray-600">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        ${formatDate(nextReservation.reservation_date)}
                    </span>
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ${formatTime(nextReservation.start_time)}
                    </span>
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        </svg>
                        ${nextReservation.store?.name || '店舗'}
                    </span>
                </div>
            </div>
        `;
        
        // ボタンのイベント
        document.getElementById('next-change-btn').onclick = () => changeReservationDate(nextReservation.id);
        document.getElementById('next-cancel-btn').onclick = () => cancelReservation(nextReservation.id);
    }
}

function updateReservationCount() {
    const now = new Date();
    const activeCount = allReservations.filter(r => {
        // 日付フォーマットを正規化して正しくパース
        const dateStr = r.reservation_date.split('T')[0];
        const [year, month, day] = dateStr.split('-').map(Number);
        const [hours, minutes] = r.start_time.split(':').map(Number);
        const reservationDate = new Date(year, month - 1, day, hours, minutes);
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
    
    console.log('=== displayReservations called ===');
    console.log('allReservations at display:', allReservations);
    console.log('allReservations length:', allReservations.length);
    console.log('Current filter:', currentFilter);
    
    // フィルタリング
    let filteredReservations = allReservations;
    if (currentFilter === 'upcoming') {
        filteredReservations = allReservations.filter(r => {
            // 日付フォーマットを正規化して正しくパース
            const dateStr = r.reservation_date.split('T')[0];
            const [year, month, day] = dateStr.split('-').map(Number);
            const [hours, minutes] = r.start_time.split(':').map(Number);
            const reservationDate = new Date(year, month - 1, day, hours, minutes);
            return reservationDate > now;
        });
    } else if (currentFilter === 'past') {
        filteredReservations = allReservations.filter(r => {
            // 日付フォーマットを正規化して正しくパース
            const dateStr = r.reservation_date.split('T')[0];
            const [year, month, day] = dateStr.split('-').map(Number);
            const [hours, minutes] = r.start_time.split(':').map(Number);
            const reservationDate = new Date(year, month - 1, day, hours, minutes);
            return reservationDate <= now;
        });
    }
    
    console.log('Filtered reservations:', filteredReservations);
    console.log('Filtered count:', filteredReservations.length);
    
    if (filteredReservations.length === 0) {
        console.log('No reservations to display - showing empty state');
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    emptyState.classList.add('hidden');
    
    container.innerHTML = filteredReservations.map(reservation => {
        // 日付フォーマットを正規化して正しくパース
        const dateStr = reservation.reservation_date.split('T')[0];
        const [year, month, day] = dateStr.split('-').map(Number);
        const [hours, minutes] = reservation.start_time.split(':').map(Number);
        const reservationDate = new Date(year, month - 1, day, hours, minutes);
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
                                class="border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                            日程変更
                        </button>
                        <button onclick="cancelReservation(${reservation.id})" 
                                class="border border-red-300 text-red-600 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
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
    // タイムスタンプから日付部分のみ抽出
    const dateStr = dateString.split('T')[0];
    const date = new Date(dateStr);
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
    
    // 日付フォーマットを正規化して正しくパース
    const dateStr = reservation.reservation_date.split('T')[0];
    const [year, month, day] = dateStr.split('-').map(Number);
    const [hours, minutes] = reservation.start_time.split(':').map(Number);
    const reservationDateTime = new Date(year, month - 1, day, hours, minutes);
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
    // 店舗選択ページへ遷移
    window.location.href = '/stores';
}

// サブスク予約へ遷移
function goToSubscriptionBooking() {
    // 顧客データとサブスク情報を取得
    const customerData = localStorage.getItem('customer_data');
    const activeSubscription = window.activeSubscription;
    
    if (customerData && activeSubscription) {
        const customer = JSON.parse(customerData);
        
        // セッションストレージに必要な情報を保存
        sessionStorage.setItem('existing_customer_id', customer.id);
        sessionStorage.setItem('from_mypage', 'true');
        sessionStorage.setItem('is_subscription_booking', 'true');
        sessionStorage.setItem('selected_store_id', activeSubscription.store?.id);
        sessionStorage.setItem('selected_store_name', activeSubscription.store?.name);
        sessionStorage.setItem('selected_menu_id', activeSubscription.plan?.menu_id);
        sessionStorage.setItem('subscription_data', JSON.stringify(activeSubscription));
        
        // 直接カレンダーページへ遷移（店舗とメニューは自動選択済み）
        window.location.href = '/reservation/calendar';
    } else {
        alert('サブスクリプション情報が見つかりません');
    }
}

// 日程変更
function changeReservationDate(reservationId) {
    const reservation = allReservations.find(r => r.id === reservationId);
    
    if (!reservation) {
        alert('予約情報が見つかりません');
        return;
    }
    
    // 24時間前チェック
    const dateStr = reservation.reservation_date.split('T')[0];
    const [year, month, day] = dateStr.split('-').map(Number);
    const [hours, minutes] = reservation.start_time.split(':').map(Number);
    const reservationDateTime = new Date(year, month - 1, day, hours, minutes);
    const now = new Date();
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);
    
    if (hoursDiff <= 24) {
        alert('予約の24時間前を過ぎているため、変更できません。\nお電話でお問い合わせください。');
        return;
    }
    
    // セッションストレージに予約変更情報を保存
    sessionStorage.setItem('change_reservation_id', reservationId);
    sessionStorage.setItem('change_reservation_data', JSON.stringify(reservation));
    sessionStorage.setItem('from_mypage', 'true');
    sessionStorage.setItem('is_reservation_change', 'true');
    
    // 顧客情報も保存
    const customerData = localStorage.getItem('customer_data');
    if (customerData) {
        const customer = JSON.parse(customerData);
        sessionStorage.setItem('existing_customer_id', customer.id);
    }
    
    // フォームを作成してPOSTでセッション情報を送信
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/reservation/prepare-change';
    
    // CSRFトークン
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    }
    
    // 予約情報
    const fields = {
        'reservation_id': reservationId,
        'store_id': reservation.store_id,
        'menu_id': reservation.menu_id,
        'store_name': reservation.store?.name || '',
        'menu_name': reservation.menu?.name || '',
        'menu_price': reservation.menu?.price || 0,
        'menu_duration': reservation.menu?.duration || 60
    };
    
    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

let currentReservationId = null;
let currentReservation = null;

function cancelReservation(reservationId) {
    currentReservationId = reservationId;
    currentReservation = allReservations.find(r => r.id === reservationId);
    
    if (!currentReservation) {
        alert('予約情報が見つかりません');
        return;
    }
    
    // 24時間前チェック
    const dateStr = currentReservation.reservation_date.split('T')[0];
    const [year, month, day] = dateStr.split('-').map(Number);
    const [hours, minutes] = currentReservation.start_time.split(':').map(Number);
    const reservationDateTime = new Date(year, month - 1, day, hours, minutes);
    const now = new Date();
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);
    
    if (hoursDiff <= 24) {
        // 24時間以内の場合は電話案内モーダルを表示
        const modalContent = document.getElementById('cancelModalContent');
        modalContent.innerHTML = `
            <div class="text-center">
                <div class="mb-4">
                    <svg class="w-16 h-16 text-yellow-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <p class="text-lg font-semibold mb-2">予約まで24時間を切っています</p>
                <p class="text-sm text-gray-600 mb-4">キャンセルをご希望の場合は、お手数ですが店舗へ直接お電話でご連絡ください。</p>
                
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <p class="font-semibold text-gray-900">${currentReservation.store?.name || '店舗'}</p>
                    <a href="tel:${currentReservation.store?.phone}" class="text-blue-600 text-xl font-bold hover:underline">
                        📞 ${currentReservation.store?.phone || '電話番号'}
                    </a>
                </div>
                
                <button onclick="closeCancelModal()" class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    閉じる
                </button>
            </div>
        `;
        document.getElementById('cancelModal').classList.remove('hidden');
        return;
    }
    
    // 24時間以上前の場合は通常のキャンセルモーダル
    const modalContent = document.getElementById('cancelModalContent');
    modalContent.innerHTML = `
        <p class="text-sm text-gray-600 mb-4">この予約をキャンセルしてもよろしいですか？</p>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">キャンセル理由（任意）</label>
            <textarea id="cancelReason" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="キャンセル理由をお聞かせください"></textarea>
        </div>
        <div class="flex gap-3">
            <button onclick="closeCancelModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                戻る
            </button>
            <button onclick="confirmCancel()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                キャンセルする
            </button>
        </div>
    `;
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.getElementById('cancelReason').value = '';
    currentReservationId = null;
    currentReservation = null;
}

async function confirmCancel() {
    const reason = document.getElementById('cancelReason').value;
    
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch(`/api/customer/reservations/${currentReservationId}/cancel`, {
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
        closeCancelModal();
        
        // データを再取得
        await fetchReservations();
        
    } catch (error) {
        console.error('Cancel error:', error);
        alert('キャンセルに失敗しました。もう一度お試しください。');
    }
}

// モーダル外クリックで閉じる
document.addEventListener('click', function(event) {
    if (event.target === document.getElementById('changeModal')) {
        closeChangeModal();
    }
    if (event.target === document.getElementById('cancelModal')) {
        closeCancelModal();
    }
});

// ESCキーでモーダルを閉じる
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeChangeModal();
        closeCancelModal();
    }
});
</script>
@endsection