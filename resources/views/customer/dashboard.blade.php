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
                    <p class="text-xs md:text-sm text-gray-500 mt-1" id="store-info">
                        <!-- 店舗情報が動的に挿入されます -->
                    </p>
                </div>
                <div class="flex gap-2">
                    <button id="store-switcher-btn" class="hidden bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        店舗切替
                    </button>
                    <button id="logout-btn" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
                        ログアウト
                    </button>
                </div>
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
        <div id="subscription-section" class="hidden bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl shadow-sm mb-4 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-sm font-medium mb-2 text-blue-600">メニュー</h2>
                    <div id="subscription-details" class="text-gray-800">プランの詳細</div>
                </div>
                <button id="subscription-booking-btn" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors shadow-md">
                    サブスクリプション予約
                </button>
            </div>
        </div>

        <!-- 回数券セクション -->
        <div id="tickets-section" class="hidden bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl shadow-sm mb-4 p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex-1">
                    <h2 class="text-sm font-medium mb-2 text-green-600">回数券</h2>
                    <div id="tickets-summary" class="text-gray-800">
                        <!-- 動的に挿入 -->
                    </div>
                </div>
                <a id="ticket-action-btn" href="/customer/tickets" class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors shadow-md text-center">
                    回数券で予約する
                </a>
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
                <a href="#" onclick="goToReservation(); return false;" class="bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors inline-block">
                    予約する
                </a>
            </div>
        </div>
    </div>
</div>

<!-- キャンセル確認モーダル -->
<div id="cancelModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="relative mx-auto border w-full max-w-md max-h-[90vh] overflow-y-auto shadow-lg rounded-md bg-white">
        <div class="p-5">
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

<!-- Store Switcher Modal -->
<div id="storeSwitcherModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="relative mx-auto border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">店舗を切り替える</h3>
                <p class="text-sm text-gray-600">
                    切り替え先の店舗を選択してください。<br>
                    SMS認証が必要です。
                </p>
            </div>

            <div id="store-switcher-step-1">
                <div id="available-stores-list" class="space-y-3 mb-6">
                    <!-- Stores will be populated here -->
                </div>
                <button onclick="closeStoreSwitcherModal()" class="w-full text-gray-500 text-sm hover:text-gray-700">
                    キャンセル
                </button>
            </div>

            <div id="store-switcher-step-2" class="hidden">
                <p class="text-sm text-gray-600 mb-4">
                    <span id="switcher-phone-display"></span> に送信された6桁の認証コードを入力してください。
                </p>

                <div class="mb-4">
                    <label for="switcher-otp-input" class="block text-sm font-medium text-gray-700 mb-2">
                        認証コード（6桁）
                    </label>
                    <input
                        type="text"
                        id="switcher-otp-input"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        class="w-full px-3 py-3 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="123456"
                    >
                </div>

                <div class="flex space-x-3">
                    <button onclick="verifySwitchOtp()" class="flex-1 bg-primary-600 text-white py-2 px-4 rounded hover:bg-primary-700 transition-colors">
                        認証して切り替え
                    </button>
                    <button onclick="resendSwitchOtp()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400 transition-colors">
                        再送信
                    </button>
                </div>

                <div id="switcher-error" class="hidden mt-3 p-3 bg-red-50 text-red-800 text-sm rounded"></div>

                <button onclick="backToStoreList()" class="mt-4 w-full text-gray-500 text-sm hover:text-gray-700">
                    店舗選択に戻る
                </button>
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

function goToReservation() {
    // ローカルストレージから顧客情報を取得
    const customerData = localStorage.getItem('customer_data');

    if (customerData) {
        try {
            const customer = JSON.parse(customerData);
            const customerId = customer.id;

            // 顧客IDがある場合は、URLパラメータ付きで遷移
            if (customerId) {
                window.location.href = `/reservation/store?customer_id=${customerId}`;
            } else {
                window.location.href = '/reservation/store';
            }
        } catch (e) {
            console.error('Error parsing customer data:', e);
            window.location.href = '/reservation/store';
        }
    } else {
        window.location.href = '/reservation/store';
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    const token = localStorage.getItem('customer_token');
    const customerData = localStorage.getItem('customer_data');
    const tokenExpiry = localStorage.getItem('token_expiry');
    const rememberMe = localStorage.getItem('remember_me');

    // デバッグ情報をコンソールに出力
    console.log('=== Customer Login Debug Info ===');
    console.log('Token exists:', !!token);
    console.log('Token length:', token ? token.length : 0);
    console.log('Token expiry:', tokenExpiry);
    console.log('Remember me:', rememberMe);
    console.log('Current time:', new Date().toISOString());

    if (tokenExpiry) {
        const expiryDate = new Date(tokenExpiry);
        const now = new Date();
        console.log('Expiry date:', expiryDate.toISOString());
        console.log('Is expired:', expiryDate < now);
        console.log('Time until expiry:', Math.round((expiryDate - now) / (1000 * 60 * 60)), 'hours');
    }

    // トークンの存在チェック（有効期限はサーバー側で検証）
    if (!token) {
        console.log('=== No token found - redirecting to login ===');
        localStorage.removeItem('customer_token');
        localStorage.removeItem('customer_data');
        localStorage.removeItem('token_expiry');
        localStorage.removeItem('remember_me');
        window.location.href = '/customer/login';
        return;
    }

    console.log('=== Token valid - proceeding with dashboard load ===');
    
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
        console.log('Using token:', token);
        console.log('Token length:', token ? token.length : 'undefined');
        console.log('Fetching from:', '/api/customer/subscriptions-token');
        
        const subscriptionResponse = await fetch('/api/customer/subscriptions-token', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('Subscription API Response:', subscriptionResponse.status);
        console.log('Subscription Response Headers:', Object.fromEntries(subscriptionResponse.headers));
        
        if (subscriptionResponse.ok) {
            const subscriptionData = await subscriptionResponse.json();
            console.log('Subscription data:', subscriptionData);
            console.log('Subscription data.data:', subscriptionData.data);
            console.log('Subscription data.data length:', subscriptionData.data ? subscriptionData.data.length : 'undefined');
            
            const activeSubscription = subscriptionData.data?.find(s => s.status === 'active' && !s.is_paused);
            console.log('Active subscription:', activeSubscription);
            console.log('Found active subscription?', !!activeSubscription);

            if (activeSubscription) {
                console.log('=== サブスクセクション表示開始 ===');
                const subscriptionSection = document.getElementById('subscription-section');
                console.log('subscription-section element:', subscriptionSection);
                console.log('Element classes before:', subscriptionSection.className);
                subscriptionSection.classList.remove('hidden');
                console.log('Element classes after:', subscriptionSection.className);
                console.log('Element display style:', window.getComputedStyle(subscriptionSection).display);
                console.log('Element visibility:', window.getComputedStyle(subscriptionSection).visibility);
                
                // メニュー名と料金を取得
                const menuName = activeSubscription.menu?.name || activeSubscription.plan?.name || 'サブスクプラン';
                const duration = activeSubscription.menu?.duration || 60;
                const monthlyPrice = activeSubscription.monthly_price || 0;
                
                // 次回リセット日の表示文字列
                let resetDateText = '';
                if (activeSubscription.next_reset_date) {
                    const resetDate = new Date(activeSubscription.next_reset_date);
                    const year = resetDate.getFullYear();
                    const month = resetDate.getMonth() + 1;
                    const day = resetDate.getDate();
                    resetDateText = `次回リセット: ${year}年${month}月${day}日`;
                }

                document.getElementById('subscription-details').innerHTML = `
                    <div class="text-sm text-gray-600 mb-1">${menuName} (${duration}分)</div>
                    <div class="flex items-baseline gap-3 mb-2">
                        <div class="text-gray-800">
                            <span class="text-2xl font-bold text-blue-600">${activeSubscription.remaining_sessions || 0}</span>
                            <span class="text-sm text-gray-600">/ ${activeSubscription.monthly_limit || 0}回</span>
                        </div>
                        <div class="text-sm text-blue-600 font-medium">
                            ${Math.floor(monthlyPrice).toLocaleString()}円/月
                        </div>
                    </div>
                    ${resetDateText ? `<div class="text-xs text-gray-500">${resetDateText}</div>` : ''}
                `;
                
                // サブスク予約ボタンのイベント
                window.activeSubscription = activeSubscription; // グローバルに保存
                const subscriptionBtn = document.getElementById('subscription-booking-btn');
                console.log('サブスクボタン要素:', subscriptionBtn);
                if (subscriptionBtn) {
                    subscriptionBtn.onclick = function() {
                        console.log('サブスク予約ボタンがクリックされました');
                        goToSubscriptionBooking();
                    };
                } else {
                    console.error('サブスク予約ボタンが見つかりません');
                }
            }
        } else {
            console.error('Subscription API failed:', subscriptionResponse.status);
            console.error('Subscription Response Headers:', Object.fromEntries(subscriptionResponse.headers));
            try {
                const errorText = await subscriptionResponse.text();
                console.error('Error response text:', errorText);
                // JSONとしてパース可能か試す
                try {
                    const errorJson = JSON.parse(errorText);
                    console.error('Error response JSON:', errorJson);
                } catch (e) {
                    console.error('Error response is not JSON');
                }
            } catch (e) {
                console.error('Failed to get error response text:', e);
            }
        }

        // 回数券情報の取得
        const ticketsResponse = await fetch('/api/customer/tickets-token', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (ticketsResponse.ok) {
            const ticketsData = await ticketsResponse.json();
            const activeTickets = ticketsData.tickets?.filter(t => t.status === 'active' && t.remaining_count > 0) || [];

            if (activeTickets.length > 0) {
                const ticketsSection = document.getElementById('tickets-section');
                ticketsSection.classList.remove('hidden');

                const totalRemaining = activeTickets.reduce((sum, t) => sum + t.remaining_count, 0);
                const expiringSoonCount = activeTickets.filter(t => t.is_expiring_soon).length;

                let ticketsSummaryHTML = `
                    <div class="text-sm text-gray-600 mb-1">有効な回数券: ${activeTickets.length}枚</div>
                    <div class="flex items-baseline gap-3 mb-2">
                        <div class="text-gray-800">
                            <span class="text-2xl font-bold text-green-600">${totalRemaining}</span>
                            <span class="text-sm text-gray-600">回分</span>
                        </div>
                `;

                if (expiringSoonCount > 0) {
                    ticketsSummaryHTML += `
                        <div class="text-sm text-yellow-600 font-medium">
                            ${expiringSoonCount}枚が期限間近
                        </div>
                    `;
                }

                ticketsSummaryHTML += '</div>';

                // 最も近い有効期限を表示
                const ticketsWithExpiry = activeTickets.filter(t => t.expires_at);
                if (ticketsWithExpiry.length > 0) {
                    ticketsWithExpiry.sort((a, b) => new Date(a.expires_at) - new Date(b.expires_at));
                    const nearestExpiry = ticketsWithExpiry[0];
                    ticketsSummaryHTML += `
                        <div class="text-xs text-gray-500">
                            最短有効期限: ${nearestExpiry.expires_at}
                        </div>
                    `;
                }

                document.getElementById('tickets-summary').innerHTML = ticketsSummaryHTML;

                // ボタンのリンク先を設定（1枚だけなら直接予約、複数なら回数券ページへ）
                const ticketActionBtn = document.getElementById('ticket-action-btn');
                if (activeTickets.length === 1) {
                    ticketActionBtn.href = `/reservation/category?ticket_id=${activeTickets[0].id}`;
                } else {
                    ticketActionBtn.href = '/customer/tickets';
                }
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
            const reservationDateOnly = new Date(year, month - 1, day);
            const todayDateOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());

            // 今日以降の日付の予約、または今日の予約（時間に関係なく）を含める
            return reservationDateOnly >= todayDateOnly && !['cancelled', 'canceled'].includes(r.status);
        })
        .sort((a, b) => {
            const datePartA = a.reservation_date.split(' ')[0];
            const datePartB = b.reservation_date.split(' ')[0];
            const [yearA, monthA, dayA] = datePartA.split('-').map(Number);
            const [yearB, monthB, dayB] = datePartB.split('-').map(Number);
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
                ${nextReservation.option_menus && nextReservation.option_menus.length > 0 ? `
                    <div class="flex flex-wrap gap-2 mt-1">
                        ${nextReservation.option_menus.map(option => `
                            <span class="inline-block bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded">
                                +${option.name}
                            </span>
                        `).join('')}
                    </div>
                ` : ''}
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
                <div class="text-lg font-semibold text-gray-900 mt-2">
                    ${(() => {
                        const hasOptions = nextReservation.option_menus && nextReservation.option_menus.length > 0;
                        if (hasOptions) {
                            const basePrice = nextReservation.menu?.is_subscription ?
                                (nextReservation.menu?.subscription_monthly_price || 0) :
                                (nextReservation.menu?.price || 0);
                            const optionTotal = nextReservation.option_menus.reduce((sum, opt) => sum + (opt.pivot?.price || 0), 0);
                            return `合計: ¥${Math.floor(nextReservation.total_amount || 0).toLocaleString()}`;
                        }
                        return `¥${Math.floor(nextReservation.total_amount || 0).toLocaleString()}`;
                    })()}
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
        const datePart = r.reservation_date.split(' ')[0];
        const [year, month, day] = datePart.split('-').map(Number);
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
    console.log('Current time:', now.toISOString());
    
    // フィルタリング
    let filteredReservations = allReservations;
    if (currentFilter === 'upcoming') {
        filteredReservations = allReservations.filter(r => {
            // 日付フォーマットを正規化して正しくパース
            const datePart = r.reservation_date.split(' ')[0];
            const [year, month, day] = datePart.split('-').map(Number);
            const [hours, minutes] = r.start_time.split(':').map(Number);
            const reservationDate = new Date(year, month - 1, day, hours, minutes);
            return reservationDate > now;
        });
    } else if (currentFilter === 'past') {
        filteredReservations = allReservations.filter(r => {
            // 日付フォーマットを正規化して正しくパース
            const datePart = r.reservation_date.split(' ')[0];
            const [year, month, day] = datePart.split('-').map(Number);
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
    
    container.innerHTML = filteredReservations.map((reservation, index) => {
        // 日付フォーマットを正規化して正しくパース
        // APIから「2025-09-19 00:00:00」形式で来る場合に対応
        const datePart = reservation.reservation_date.split(' ')[0];
        const [year, month, day] = datePart.split('-').map(Number);
        const [hours, minutes] = reservation.start_time.split(':').map(Number);
        const reservationDate = new Date(year, month - 1, day, hours, minutes);
        const isPast = reservationDate <= now;
        const isToday = datePart === now.toISOString().split('T')[0];
        const isTomorrow = datePart === new Date(now.getTime() + 24*60*60*1000).toISOString().split('T')[0];

        // サブスク予約かどうかを判定
        const isSubscription = reservation.is_subscription || reservation.subscription_id;

        // デバッグ情報
        console.log(`=== 予約${index + 1} ===`);
        console.log('Reservation ID:', reservation.id);
        console.log('Status:', reservation.status);
        console.log('Date:', datePart, 'Time:', reservation.start_time);
        console.log('Reservation DateTime:', reservationDate.toISOString());
        console.log('isPast:', isPast);
        console.log('canCancel result:', canCancel(reservation));
        console.log('Will show buttons:', !isPast && canCancel(reservation));
        
        return `
        <div class="border ${isPast ? 'border-gray-200' : isToday ? 'border-blue-400 border-2' : isTomorrow ? 'border-blue-300' : 'border-gray-200'} rounded-lg p-4 mb-4 ${isPast ? 'bg-gray-50' : isToday ? 'bg-blue-50' : 'bg-white'} relative">
            ${isToday ? '<span class="absolute -top-2 left-4 bg-blue-500 text-white text-xs px-2 py-0.5 rounded">本日</span>' : ''}
            ${isTomorrow ? '<span class="absolute -top-2 left-4 bg-blue-400 text-white text-xs px-2 py-0.5 rounded">明日</span>' : ''}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="text-xs font-medium px-2 py-1 rounded-full ${getStatusColor(reservation.status)}">
                            ${getStatusText(reservation.status)}
                        </span>
                        ${isSubscription ? '<span class="text-xs font-medium px-2 py-1 rounded-full bg-purple-100 text-purple-700">サブスク利用</span>' : ''}
                        <span class="text-xs text-gray-500">予約番号: ${reservation.reservation_number}</span>
                    </div>
                    
                    <h3 class="font-semibold text-gray-900 mb-2 text-lg">${reservation.menu?.name || 'メニュー情報なし'}</h3>

                    ${reservation.option_menus && reservation.option_menus.length > 0 ? `
                        <div class="ml-4 mb-2">
                            <div class="text-xs text-gray-600 mb-1">追加オプション:</div>
                            <div class="flex flex-wrap gap-2">
                                ${reservation.option_menus.map(option => `
                                    <span class="inline-block bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">
                                        ${option.name} (+¥${Math.floor(option.pivot.price).toLocaleString()})
                                    </span>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}

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
                    
                    <div class="mt-3 flex items-center gap-4">
                        <div class="text-lg font-semibold ${isSubscription && reservation.total_amount == 0 ? 'text-purple-600' : 'text-gray-900'}">
                            ${(() => {
                                // サブスク契約者がサブスクメニューを予約し、オプションなしの場合
                                if (isSubscription && reservation.total_amount == 0) {
                                    return '<span class="text-sm font-normal text-gray-600 line-through mr-1">通常料金</span>サブスク利用 ¥0';
                                }

                                const hasOptions = reservation.option_menus && reservation.option_menus.length > 0;
                                const basePrice = reservation.menu?.is_subscription ?
                                    (reservation.menu?.subscription_monthly_price || 0) :
                                    (reservation.menu?.price || 0);
                                const optionTotal = hasOptions ?
                                    reservation.option_menus.reduce((sum, opt) => sum + (opt.pivot?.price || 0), 0) : 0;

                                // オプション付きの場合は詳細表示
                                if (hasOptions && optionTotal > 0) {
                                    return `
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-sm text-gray-500">基本 ¥${Math.floor(basePrice).toLocaleString()}</span>
                                            <span class="text-sm text-gray-500">+ オプション ¥${Math.floor(optionTotal).toLocaleString()}</span>
                                            <span>=</span>
                                            <span>¥${Math.floor(reservation.total_amount || 0).toLocaleString()}</span>
                                        </div>
                                    `;
                                }

                                // 通常表示
                                return `¥${Math.floor(reservation.total_amount || 0).toLocaleString()}`;
                            })()}
                        </div>
                        ${reservation.staff?.name ? `
                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                担当: ${reservation.staff.name}
                            </div>
                        ` : ''}
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
    // 日付部分のみ抽出（'T'または' 'で分割）
    const dateStr = dateString.split(/[T ]/)[0];
    
    // タイムゾーンの問題を避けるため、年月日を個別に抽出してローカル時間でDateオブジェクトを作成
    const [year, month, day] = dateStr.split('-').map(Number);
    const date = new Date(year, month - 1, day); // monthは0ベースなので-1
    
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
    console.log('=== canCancel called ===');
    console.log('Reservation ID:', reservation.id);
    console.log('Status:', reservation.status);

    if (['cancelled', 'canceled', 'completed', 'no_show'].includes(reservation.status)) {
        console.log('Cannot cancel: invalid status');
        return false;
    }

    // 日付フォーマットを正規化して正しくパース
    const datePart = reservation.reservation_date.split(' ')[0];
    const [year, month, day] = datePart.split('-').map(Number);
    const [hours, minutes] = reservation.start_time.split(':').map(Number);
    const reservationDateTime = new Date(year, month - 1, day, hours, minutes);
    const now = new Date();

    const isFuture = reservationDateTime > now;
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);

    console.log('Reservation DateTime:', reservationDateTime.toISOString());
    console.log('Current DateTime:', now.toISOString());
    console.log('Is future:', isFuture);
    console.log('Hours difference:', hoursDiff.toFixed(2));
    console.log('Will return:', isFuture);

    // 24時間以内でもボタンは表示する（タップすると電話案内モーダルが出る）
    return isFuture;
}

// マイページから既存顧客として予約
async function goToReservation() {
    const token = localStorage.getItem('customer_token');

    if (!token) {
        // トークンがない場合はログインページにリダイレクト
        window.location.href = '/customer/login';
        return;
    }

    try {
        // マイページからの予約用コンテキストを生成（直近の店舗が自動選択される）
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
        // エラー時はコンテキストなしで予約ページへ
        window.location.href = '/stores';
    }
}

// 予約重複チェック関数
function hasConflictingReservations() {
    const now = new Date();
    const todayStr = now.toISOString().split('T')[0]; // YYYY-MM-DD形式
    
    // 今日以降の予約をチェック
    const futureReservations = allReservations.filter(reservation => {
        const reservationDate = reservation.reservation_date.split('T')[0];
        return reservationDate >= todayStr && reservation.status !== 'cancelled';
    });
    
    if (futureReservations.length > 0) {
        // 予約がある場合の警告メッセージ
        const reservationList = futureReservations.slice(0, 3).map(res => {
            const date = new Date(res.reservation_date).toLocaleDateString('ja-JP');
            const time = res.start_time.substring(0, 5); // HH:MM形式
            return `${date} ${time}`;
        }).join('\n');
        
        const message = `既に以下の予約があります：\n${reservationList}${futureReservations.length > 3 ? '\n...' : ''}\n\n新しい予約を追加しますか？`;
        
        return !confirm(message);
    }
    
    return false; // 重複なし
}

// サブスク予約へ遷移（パラメータベース）
async function goToSubscriptionBooking() {
    console.log('=== goToSubscriptionBooking() 呼び出し ===');

    // 予約重複チェック
    if (hasConflictingReservations()) {
        console.log('予約重複のため中止');
        return;
    }

    const customerData = localStorage.getItem('customer_data');
    const token = localStorage.getItem('customer_token');
    const activeSubscription = window.activeSubscription;

    console.log('=== サブスク予約開始（パラメータベース） ===');
    console.log('customerData:', customerData ? 'あり' : 'なし');
    console.log('token:', token ? 'あり (' + token.substring(0, 20) + '...)' : 'なし');
    console.log('activeSubscription:', activeSubscription);

    if (!customerData || !activeSubscription || !token) {
        alert('サブスクリプション情報が見つかりません');
        return;
    }

    try {
        const customer = JSON.parse(customerData);
        const storeId = activeSubscription.store_id || activeSubscription.store?.id;
        const menuId = activeSubscription.menu_id || activeSubscription.plan?.menu_id;

        console.log('サブスク予約コンテキスト生成リクエスト:', {
            customer_id: customer.id,
            subscription_id: activeSubscription.id,
            store_id: storeId,
            menu_id: menuId
        });

        // サブスク予約用の暗号化コンテキストを生成
        const response = await fetch('/api/customer/reservation-context/subscription', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                subscription_id: activeSubscription.id,
                store_id: storeId,
                menu_id: menuId
            })
        });

        if (!response.ok) {
            if (response.status === 401) {
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                window.location.href = '/customer/login';
                return;
            }
            throw new Error('コンテキスト生成に失敗しました');
        }

        const data = await response.json();
        const encryptedContext = data.data.encrypted_context;

        console.log('暗号化コンテキスト取得成功、カレンダーへ遷移');
        window.location.href = `/stores?ctx=${encodeURIComponent(encryptedContext)}`;

    } catch (error) {
        console.error('サブスク予約エラー:', error);
        alert('サブスク予約の準備に失敗しました。再度お試しください。');
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
    const datePart = reservation.reservation_date.split(' ')[0];
    const [year, month, day] = datePart.split('-').map(Number);
    const [hours, minutes] = reservation.start_time.split(':').map(Number);
    const reservationDateTime = new Date(year, month - 1, day, hours, minutes);
    const now = new Date();
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);

    if (hoursDiff <= 24) {
        // 24時間以内の場合は電話案内モーダルを表示
        showPhoneContactModal(reservation, '日程変更');
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

// 電話連絡を促すモーダルを表示
function showPhoneContactModal(reservation, actionType) {
    const modalContent = document.getElementById('cancelModalContent');
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="mb-4">
                <svg class="w-16 h-16 text-yellow-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <p class="text-lg font-semibold mb-2">予約まで24時間を切っています</p>
            <p class="text-sm text-gray-600 mb-4">${actionType}をご希望の場合は、お手数ですが店舗へ直接お電話でご連絡ください。</p>

            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <p class="font-semibold text-gray-900">${reservation.store?.name || '店舗'}</p>
                <a href="tel:${reservation.store?.phone}" class="text-blue-600 text-xl font-bold hover:underline">
                    📞 ${reservation.store?.phone || '電話番号'}
                </a>
            </div>

            <button onclick="closeCancelModal()" class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                閉じる
            </button>
        </div>
    `;
    document.getElementById('cancelModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // 背景のスクロールを無効化
}

function cancelReservation(reservationId) {
    currentReservationId = reservationId;
    currentReservation = allReservations.find(r => r.id === reservationId);

    if (!currentReservation) {
        alert('予約情報が見つかりません');
        return;
    }

    // 24時間前チェック
    const datePart = currentReservation.reservation_date.split(' ')[0];
    const [year, month, day] = datePart.split('-').map(Number);
    const [hours, minutes] = currentReservation.start_time.split(':').map(Number);
    const reservationDateTime = new Date(year, month - 1, day, hours, minutes);
    const now = new Date();
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);
    
    if (hoursDiff <= 24) {
        // 24時間以内の場合は電話案内モーダルを表示
        showPhoneContactModal(currentReservation, 'キャンセル');
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
    document.body.style.overflow = 'hidden'; // 背景のスクロールを無効化
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.body.style.overflow = ''; // 背景のスクロールを復元
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
        closeStoreSwitcherModal();
    }
});

// ===== 店舗切り替え機能 =====
let switcherCurrentPhone = '';
let switcherTargetCustomerId = null;
let switcherAvailableStores = [];

// 店舗切り替えボタンのイベントリスナー
document.getElementById('store-switcher-btn')?.addEventListener('click', async function() {
    await startStoreSwitcher();
});

// 店舗切り替え開始（ボタンクリック時）
async function startStoreSwitcher() {
    try {
        const customerData = JSON.parse(localStorage.getItem('customer_data'));
        if (!customerData || !customerData.phone) {
            alert('顧客情報が見つかりません');
            return;
        }

        switcherCurrentPhone = customerData.phone;

        // 利用可能な店舗を取得
        const token = localStorage.getItem('customer_token');
        const response = await fetch('/api/customer/available-stores', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('店舗情報の取得に失敗しました');
        }

        const data = await response.json();
        switcherAvailableStores = data.stores || [];

        if (switcherAvailableStores.length < 2) {
            alert('利用可能な店舗は1店舗のみです');
            return;
        }

        // 店舗選択モーダルを直接表示（OTP認証なし）
        showStoreSelectionModal();
    } catch (error) {
        console.error('Error starting store switcher:', error);
        alert('店舗切り替えに失敗しました');
    }
}

// 店舗選択モーダルを表示
function showStoreSelectionModal() {
    const modal = document.getElementById('storeSwitcherModal');
    const storesList = document.getElementById('available-stores-list');

    // Step 2を隠してStep 1を表示
    document.getElementById('store-switcher-step-1').classList.remove('hidden');
    document.getElementById('store-switcher-step-2').classList.add('hidden');

    // 店舗リストを作成
    storesList.innerHTML = `
        <p class="text-sm text-gray-600 mb-3">
            切り替え先の店舗を選択してください
        </p>
        ${switcherAvailableStores.map(store => `
            <button onclick="switchToStore(${store.customer_id}, ${store.store_id}, '${store.store_name}')" class="w-full bg-white border-2 border-gray-200 p-4 rounded-lg text-left hover:border-primary-500 hover:bg-primary-50 transition-colors mb-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900">${store.store_name}</p>
                        <p class="text-sm text-gray-500 mt-1">この店舗に切り替える</p>
                    </div>
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </button>
        `).join('')}
    `;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

// 店舗切り替え実行
async function switchToStore(customerId, storeId, storeName) {
    if (!confirm(`${storeName}に切り替えますか？`)) {
        return;
    }

    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch('/api/auth/customer/switch-store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                phone: switcherCurrentPhone,
                customer_id: customerId
            })
        });

        if (!response.ok) {
            throw new Error('店舗切り替えに失敗しました');
        }

        const data = await response.json();

        // 新しいトークンと顧客情報を保存
        localStorage.setItem('customer_token', data.token);
        localStorage.setItem('customer_data', JSON.stringify(data.customer));
        localStorage.setItem('store_data', JSON.stringify(data.store));

        // ページをリロード
        window.location.reload();
    } catch (error) {
        console.error('Error switching store:', error);
        alert('店舗切り替えに失敗しました');
    }
}

// 複数店舗があるかチェック（ページ読み込み時）
async function checkMultipleStores() {
    try {
        const customerData = JSON.parse(localStorage.getItem('customer_data'));
        if (!customerData || !customerData.phone) {
            console.log('顧客情報が見つかりません');
            return;
        }

        switcherCurrentPhone = customerData.phone;

        // 同じ電話番号を持つすべての顧客を取得
        const token = localStorage.getItem('customer_token');
        const response = await fetch('/api/customer/available-stores', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('店舗情報の取得に失敗しました');
        }

        const data = await response.json();

        // 複数店舗がある場合のみボタンを表示
        if (data.stores && data.stores.length > 1) {
            document.getElementById('store-switcher-btn').classList.remove('hidden');
            console.log('複数店舗利用可能:', data.stores);
        } else {
            console.log('利用店舗は1店舗のみ');
        }
    } catch (error) {
        console.error('Error checking stores:', error);
        // エラーは表示しない（店舗切替ボタンが表示されないだけ）
    }
}

// 店舗切り替えモーダルを表示
async function showStoreSwitcherModal() {
    const modal = document.getElementById('storeSwitcherModal');
    const storesList = document.getElementById('available-stores-list');

    // Step 2を隠してStep 1を表示
    document.getElementById('store-switcher-step-1').classList.remove('hidden');
    document.getElementById('store-switcher-step-2').classList.add('hidden');

    // OTP送信
    try {
        const response = await fetch('/api/auth/customer/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ phone: switcherCurrentPhone })
        });

        if (!response.ok) {
            alert('SMS送信に失敗しました');
            return;
        }

        // 同じ電話番号の顧客を取得（本来はAPIから取得すべき）
        // ここでは簡略化のため、手動で入力を促す
        switcherAvailableStores = [
            { customer_id: 1, store_name: '渋谷店' },
            { customer_id: 2, store_name: '秋葉原店' }
        ];

        // 店舗リストをクリア
        storesList.innerHTML = '';

        // 仮の店舗リスト表示（実際には API から取得した情報を使用）
        const currentCustomer = JSON.parse(localStorage.getItem('customer_data'));
        storesList.innerHTML = `
            <p class="text-sm text-gray-600 mb-3">
                認証コードを送信しました。<br>
                切り替え先の店舗を選択してください。
            </p>
            <button onclick="proceedToOtpStep()" class="w-full bg-primary-600 text-white p-4 rounded-lg text-left hover:bg-primary-700 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold">店舗を切り替える</p>
                        <p class="text-sm mt-1">認証コードを入力</p>
                    </div>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </button>
        `;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    } catch (error) {
        console.error('Error:', error);
        alert('エラーが発生しました');
    }
}

// OTP入力ステップへ進む
function proceedToOtpStep() {
    document.getElementById('store-switcher-step-1').classList.add('hidden');
    document.getElementById('store-switcher-step-2').classList.remove('hidden');
    document.getElementById('switcher-phone-display').textContent = switcherCurrentPhone;
    document.getElementById('switcher-otp-input').focus();
}

// OTP検証と店舗切り替え
async function verifySwitchOtp() {
    const otpCode = document.getElementById('switcher-otp-input').value;
    const errorDiv = document.getElementById('switcher-error');

    if (otpCode.length !== 6) {
        errorDiv.textContent = '6桁の認証コードを入力してください';
        errorDiv.classList.remove('hidden');
        return;
    }

    // ここでは customer_id を指定する必要があるが、
    // 簡略化のため現在のユーザーとは異なるIDを想定
    // 実際の実装では店舗選択UIで customer_id を選ばせる必要がある
    const currentCustomer = JSON.parse(localStorage.getItem('customer_data'));
    const targetCustomerId = currentCustomer.id; // 実際には別の customer_id

    try {
        const response = await fetch('/api/auth/customer/switch-store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Authorization': `Bearer ${localStorage.getItem('customer_token')}`
            },
            body: JSON.stringify({
                phone: switcherCurrentPhone,
                otp_code: otpCode,
                customer_id: targetCustomerId
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // 新しいトークンを保存
            localStorage.setItem('customer_token', data.data.token);
            localStorage.setItem('customer_data', JSON.stringify(data.data.customer));

            alert('店舗を切り替えました');
            closeStoreSwitcherModal();

            // ページをリロード
            window.location.reload();
        } else {
            errorDiv.textContent = data.error?.message || '認証に失敗しました';
            errorDiv.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error:', error);
        errorDiv.textContent = 'エラーが発生しました';
        errorDiv.classList.remove('hidden');
    }
}

// OTP再送信
async function resendSwitchOtp() {
    try {
        const response = await fetch('/api/auth/customer/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ phone: switcherCurrentPhone })
        });

        if (response.ok) {
            alert('認証コードを再送信しました');
            document.getElementById('switcher-otp-input').value = '';
            document.getElementById('switcher-error').classList.add('hidden');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('再送信に失敗しました');
    }
}

// 店舗リストに戻る
function backToStoreList() {
    document.getElementById('store-switcher-step-2').classList.add('hidden');
    document.getElementById('store-switcher-step-1').classList.remove('hidden');
    document.getElementById('switcher-otp-input').value = '';
    document.getElementById('switcher-error').classList.add('hidden');
}

// 店舗切り替えモーダルを閉じる
function closeStoreSwitcherModal() {
    const modal = document.getElementById('storeSwitcherModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    document.getElementById('switcher-otp-input').value = '';
    document.getElementById('switcher-error').classList.add('hidden');
}

// 初期化時に複数店舗チェック
async function initializeStoreSwitcher() {
    try {
        const customerData = JSON.parse(localStorage.getItem('customer_data'));
        if (!customerData || !customerData.phone) return;

        // 本来はAPIで同じ電話番号を持つ customer_id をすべて取得
        // 複数ある場合のみボタンを表示
        // 現時点では常に表示（テスト用）
        const switcherBtn = document.getElementById('store-switcher-btn');
        if (switcherBtn) {
            switcherBtn.classList.remove('hidden');
            switcherBtn.classList.add('flex');
        }
    } catch (error) {
        console.error('Error initializing store switcher:', error);
    }
}

// ページロード時に初期化
initializeStoreSwitcher();
</script>
@endsection