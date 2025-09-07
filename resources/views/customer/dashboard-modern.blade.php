@extends('layouts.app')

@section('title', 'マイページ')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- ヘッダー --}}
    <div class="bg-white border-b sticky top-0 z-40">
        <div class="max-w-lg mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">マイページ</h1>
                <button onclick="showNotifications()" class="relative p-2">
                    <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- プロフィールセクション --}}
    <div class="bg-white">
        <div class="max-w-lg mx-auto px-4 py-4">
            <div class="flex items-center space-x-4">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                    <span id="user-initial">山田</span>
                </div>
                <div class="flex-1">
                    <p class="text-lg font-semibold text-gray-900" id="customer-name">山田 太郎</p>
                    <p class="text-sm text-gray-500">会員ID: <span id="customer-id">00005</span></p>
                </div>
                <a href="/customer/profile" class="p-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>
    </div>


    {{-- クイックアクション --}}
    <div class="max-w-lg mx-auto px-4 pb-4">
        <div class="grid grid-cols-2 gap-3">
            <button onclick="quickReservation()" class="bg-blue-500 text-white rounded-xl p-4 flex flex-col items-center space-y-2 shadow-sm active:scale-95 transition-transform">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="text-sm font-medium">新規予約</span>
            </button>
            <button onclick="showQuickBooking()" class="bg-green-500 text-white rounded-xl p-4 flex flex-col items-center space-y-2 shadow-sm active:scale-95 transition-transform">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="text-sm font-medium">リピート予約</span>
            </button>
        </div>
    </div>

    {{-- 今後の予約 --}}
    <div class="bg-white mt-2">
        <div class="max-w-lg mx-auto">
            <div class="px-4 py-3 border-b">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">今後の予約</h2>
                    <button onclick="showAllReservations()" class="text-sm text-blue-600">すべて見る</button>
                </div>
            </div>
            <div id="upcoming-reservations" class="divide-y">
                {{-- 予約カードがここに表示される --}}
            </div>
        </div>
    </div>

    {{-- メニューグリッド --}}
    <div class="bg-white mt-2">
        <div class="max-w-lg mx-auto">
            <div class="px-4 py-3 border-b">
                <h2 class="font-semibold text-gray-900">メニュー</h2>
            </div>
            <div class="grid grid-cols-4 gap-1 p-4">
                <a href="/customer/medical-records" class="flex flex-col items-center p-3 space-y-2 hover:bg-gray-50 rounded-lg">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <span class="text-xs text-gray-700">カルテ</span>
                </a>
                
                <button onclick="showVisionProgress()" class="flex flex-col items-center p-3 space-y-2 hover:bg-gray-50 rounded-lg">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <span class="text-xs text-gray-700">視力推移</span>
                </button>
                
                <a href="/customer/coupons" class="flex flex-col items-center p-3 space-y-2 hover:bg-gray-50 rounded-lg">
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <span class="text-xs text-gray-700">クーポン</span>
                </a>
                
                <a href="/customer/settings" class="flex flex-col items-center p-3 space-y-2 hover:bg-gray-50 rounded-lg">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <span class="text-xs text-gray-700">設定</span>
                </a>
            </div>
        </div>
    </div>

    {{-- 履歴セクション --}}
    <div class="bg-white mt-2 mb-20">
        <div class="max-w-lg mx-auto">
            <div class="px-4 py-3 border-b">
                <h2 class="font-semibold text-gray-900">利用履歴</h2>
            </div>
            <div class="divide-y">
                <a href="/customer/reservations?filter=past" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">過去の予約</p>
                            <p class="text-xs text-gray-500">全<span id="past-count">0</span>件</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                
                <a href="/customer/payments" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">支払い履歴</p>
                            <p class="text-xs text-gray-500">今月: ¥15,000</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    {{-- ボトムナビゲーション --}}
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t">
        <div class="max-w-lg mx-auto">
            <div class="grid grid-cols-5 gap-1">
                <a href="/" class="flex flex-col items-center py-2 px-1 hover:bg-gray-50">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-xs text-gray-400 mt-1">ホーム</span>
                </a>
                
                <a href="/stores" class="flex flex-col items-center py-2 px-1 hover:bg-gray-50">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span class="text-xs text-gray-400 mt-1">探す</span>
                </a>
                
                <button onclick="quickReservation()" class="flex flex-col items-center py-2 px-1 hover:bg-gray-50">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center -mt-4 shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <span class="text-xs text-gray-400 mt-1">予約</span>
                </button>
                
                <a href="/customer/messages" class="flex flex-col items-center py-2 px-1 hover:bg-gray-50 relative">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span class="absolute top-1 right-4 h-2 w-2 bg-red-500 rounded-full"></span>
                    <span class="text-xs text-gray-400 mt-1">メッセージ</span>
                </a>
                
                <a href="/customer/dashboard" class="flex flex-col items-center py-2 px-1 hover:bg-gray-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="text-xs text-blue-500 mt-1">マイページ</span>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- 予約変更モーダル --}}
<div id="change-reservation-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeChangeModal()"></div>
    <div class="fixed bottom-0 left-0 right-0 bg-white rounded-t-3xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold">予約を変更</h3>
                <button onclick="closeChangeModal()" class="p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-4">
            {{-- 現在の予約情報 --}}
            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                <p class="text-sm text-gray-500 mb-2">現在の予約</p>
                <div id="current-reservation-info"></div>
            </div>
            
            {{-- 変更内容選択 --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">変更内容</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="selectChangeType('date')" class="change-type-btn border-2 border-gray-200 rounded-xl p-3 text-center hover:border-blue-500 hover:bg-blue-50">
                            <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm">日時変更</span>
                        </button>
                        <button onclick="selectChangeType('menu')" class="change-type-btn border-2 border-gray-200 rounded-xl p-3 text-center hover:border-blue-500 hover:bg-blue-50">
                            <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <span class="text-sm">メニュー変更</span>
                        </button>
                    </div>
                </div>
                
                {{-- 日時変更セクション --}}
                <div id="date-change-section" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">新しい日時</label>
                    <input type="date" id="new-date" class="w-full border rounded-xl px-4 py-3 mb-3">
                    <select id="new-time" class="w-full border rounded-xl px-4 py-3">
                        <option value="">時間を選択</option>
                        <option value="09:00">09:00</option>
                        <option value="10:00">10:00</option>
                        <option value="11:00">11:00</option>
                        <option value="13:00">13:00</option>
                        <option value="14:00">14:00</option>
                        <option value="15:00">15:00</option>
                        <option value="16:00">16:00</option>
                        <option value="17:00">17:00</option>
                    </select>
                </div>
                
                {{-- メニュー変更セクション --}}
                <div id="menu-change-section" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">新しいメニュー</label>
                    <div id="menu-options" class="space-y-2"></div>
                </div>
            </div>
            
            {{-- 確認ボタン --}}
            <button onclick="confirmChange()" class="w-full bg-blue-500 text-white rounded-xl py-4 font-medium mt-6 disabled:bg-gray-300" disabled>
                変更を確定する
            </button>
        </div>
    </div>
</div>

{{-- リピート予約モーダル --}}
<div id="quick-booking-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeQuickBooking()"></div>
    <div class="fixed bottom-0 left-0 right-0 bg-white rounded-t-3xl">
        <div class="p-4 border-b">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold">前回と同じ内容で予約</h3>
                <button onclick="closeQuickBooking()" class="p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="p-4">
            <div id="quick-booking-content" class="bg-gray-50 rounded-xl p-4 mb-4"></div>
            <button onclick="confirmQuickBooking()" class="w-full bg-green-500 text-white rounded-xl py-4 font-medium">
                この内容で予約画面へ
            </button>
        </div>
    </div>
</div>

{{-- 全予約表示モーダル --}}
<div id="all-reservations-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeAllReservations()"></div>
    <div class="fixed inset-0 bg-white overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-4 py-4 z-10">
            <div class="flex items-center">
                <button onclick="closeAllReservations()" class="p-2 -ml-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h3 class="text-lg font-bold ml-2">すべての予約</h3>
            </div>
        </div>
        
        <div class="px-4 py-3">
            <div class="flex gap-2 mb-4 overflow-x-auto">
                <button onclick="filterReservations('all')" class="filter-btn px-4 py-2 rounded-full text-sm font-medium bg-blue-500 text-white whitespace-nowrap">
                    すべて
                </button>
                <button onclick="filterReservations('upcoming')" class="filter-btn px-4 py-2 rounded-full text-sm font-medium bg-gray-100 text-gray-700 whitespace-nowrap">
                    今後の予約
                </button>
                <button onclick="filterReservations('past')" class="filter-btn px-4 py-2 rounded-full text-sm font-medium bg-gray-100 text-gray-700 whitespace-nowrap">
                    過去の予約
                </button>
            </div>
            
            <div id="all-reservations-list" class="space-y-3"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let allReservations = [];
let currentFilter = 'all';
let lastReservation = null;
let currentReservationId = null;

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
        document.getElementById('customer-name').textContent = customer.last_name + ' ' + customer.first_name;
        document.getElementById('customer-id').textContent = String(customer.id).padStart(5, '0');
        document.getElementById('user-initial').textContent = customer.last_name.charAt(0);
    }
}

// 予約読み込み
async function loadReservations() {
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch('/api/customer/reservations', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            allReservations = data.data || [];
            
            // 今後の予約を表示
            displayUpcomingReservations();
            
            // 統計更新
            updateStats();
            
            // 最後の予約を取得
            if (allReservations.length > 0) {
                lastReservation = allReservations[0];
            }
        }
    } catch (error) {
        console.error('Error loading reservations:', error);
    }
}

// 今後の予約表示
function displayUpcomingReservations() {
    const container = document.getElementById('upcoming-reservations');
    const upcoming = allReservations.filter(r => {
        const date = new Date(r.reservation_date);
        return date >= new Date() && r.status !== 'cancelled';
    }).slice(0, 3);
    
    if (upcoming.length === 0) {
        container.innerHTML = `
            <div class="p-4 text-center">
                <p class="text-gray-500 mb-3">予約はありません</p>
                <button onclick="quickReservation()" class="bg-blue-500 text-white px-6 py-2 rounded-full text-sm">
                    新規予約する
                </button>
            </div>
        `;
        return;
    }
    
    container.innerHTML = upcoming.map(r => `
        <div class="px-4 py-3 hover:bg-gray-50">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs px-2 py-0.5 rounded-full ${getStatusClass(r.status)}">
                            ${getStatusText(r.status)}
                        </span>
                        <p class="text-xs text-gray-500">${formatDate(r.reservation_date)}</p>
                    </div>
                    <p class="font-medium text-gray-900">${r.menu?.name || 'メニュー'}</p>
                    <p class="text-sm text-gray-600">${r.store?.name || '店舗'}</p>
                    <p class="text-sm font-medium text-gray-900 mt-1">¥${Math.floor(r.total_amount || 0).toLocaleString()}</p>
                </div>
                <div class="flex gap-1">
                    <button onclick="changeReservation(${r.id})" class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50">
                        変更
                    </button>
                    <button onclick="cancelReservation(${r.id})" class="px-3 py-1.5 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">
                        キャンセル
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// 統計更新
function updateStats() {
    const past = allReservations.filter(r => new Date(r.reservation_date) < new Date()).length;
    document.getElementById('past-count').textContent = past;
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
        showPhoneContactModal(reservation.store);
        return;
    }
    
    currentReservationId = id;
    
    // 現在の予約情報を表示
    document.getElementById('current-reservation-info').innerHTML = `
        <p class="font-medium">${formatDate(reservation.reservation_date)}</p>
        <p class="text-sm text-gray-600">${reservation.menu?.name} - ${reservation.store?.name}</p>
    `;
    
    // モーダル表示
    document.getElementById('change-reservation-modal').classList.remove('hidden');
}

// 変更タイプ選択
function selectChangeType(type) {
    // ボタンのアクティブ状態を更新
    document.querySelectorAll('.change-type-btn').forEach(btn => {
        btn.classList.remove('border-blue-500', 'bg-blue-50');
    });
    event.target.closest('.change-type-btn').classList.add('border-blue-500', 'bg-blue-50');
    
    // セクション表示切り替え
    document.getElementById('date-change-section').classList.toggle('hidden', type !== 'date');
    document.getElementById('menu-change-section').classList.toggle('hidden', type !== 'menu');
    
    // メニューオプション読み込み
    if (type === 'menu') {
        loadMenuOptions();
    }
    
    // 確定ボタンを有効化
    document.querySelector('#change-reservation-modal button[onclick="confirmChange()"]').disabled = false;
}

// メニューオプション読み込み
async function loadMenuOptions() {
    try {
        const response = await fetch('/api/menus');
        const data = await response.json();
        const menus = data.data || [];
        
        document.getElementById('menu-options').innerHTML = menus.map(menu => `
            <label class="flex items-center justify-between p-3 border rounded-xl hover:bg-gray-50 cursor-pointer">
                <div class="flex items-center">
                    <input type="radio" name="menu" value="${menu.id}" class="mr-3">
                    <div>
                        <p class="font-medium">${menu.name}</p>
                        <p class="text-sm text-gray-500">${menu.duration_minutes}分</p>
                    </div>
                </div>
                <p class="font-medium">¥${Math.floor(menu.price).toLocaleString()}</p>
            </label>
        `).join('');
    } catch (error) {
        console.error('Error loading menus:', error);
    }
}

// 変更確定
async function confirmChange() {
    const reservation = allReservations.find(r => r.id === currentReservationId);
    if (!reservation) return;
    
    const changeData = {};
    
    // 日時変更の場合
    if (!document.getElementById('date-change-section').classList.contains('hidden')) {
        const newDate = document.getElementById('new-date').value;
        const newTime = document.getElementById('new-time').value;
        
        if (newDate && newTime) {
            changeData.reservation_date = newDate;
            changeData.start_time = newTime + ':00';
        }
    }
    
    // メニュー変更の場合
    if (!document.getElementById('menu-change-section').classList.contains('hidden')) {
        const selectedMenu = document.querySelector('input[name="menu"]:checked');
        if (selectedMenu) {
            changeData.menu_id = selectedMenu.value;
        }
    }
    
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch(`/api/customer/reservations/${currentReservationId}`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(changeData)
        });
        
        if (response.ok) {
            alert('予約を変更しました');
            closeChangeModal();
            loadReservations();
        } else {
            const data = await response.json();
            alert(data.message || '変更に失敗しました');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('エラーが発生しました');
    }
}

// モーダルを閉じる
function closeChangeModal() {
    document.getElementById('change-reservation-modal').classList.add('hidden');
    currentReservationId = null;
}

// リピート予約表示
function showQuickBooking() {
    if (!lastReservation || !lastReservation.menu) {
        alert('予約履歴がありません');
        return;
    }
    
    document.getElementById('quick-booking-content').innerHTML = `
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-500">店舗</span>
                <span class="font-medium">${lastReservation.store?.name}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-500">メニュー</span>
                <span class="font-medium">${lastReservation.menu.name}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-500">料金</span>
                <span class="font-medium">¥${Math.floor(lastReservation.menu.price).toLocaleString()}</span>
            </div>
        </div>
    `;
    
    document.getElementById('quick-booking-modal').classList.remove('hidden');
}

function closeQuickBooking() {
    document.getElementById('quick-booking-modal').classList.add('hidden');
}

function confirmQuickBooking() {
    if (lastReservation && lastReservation.store && lastReservation.menu) {
        sessionStorage.setItem('selected_store', JSON.stringify({
            id: lastReservation.store_id,
            name: lastReservation.store.name
        }));
        sessionStorage.setItem('selected_menu', JSON.stringify({
            id: lastReservation.menu_id,
            name: lastReservation.menu.name,
            price: lastReservation.menu.price
        }));
        window.location.href = '/reservation/time';
    }
}

// 予約キャンセル
async function cancelReservation(id) {
    const reservation = allReservations.find(r => r.id === id);
    if (!reservation) return;
    
    // 24時間前チェック
    const reservationDate = new Date(reservation.reservation_date);
    const now = new Date();
    const hoursUntil = (reservationDate - now) / (1000 * 60 * 60);
    
    if (hoursUntil < 24) {
        showPhoneContactModal(reservation.store);
        return;
    }
    
    if (!confirm('予約をキャンセルしますか？')) return;
    
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch(`/api/customer/reservations/${id}/cancel`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            alert('予約をキャンセルしました');
            loadReservations();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// 電話連絡モーダル
function showPhoneContactModal(store) {
    alert(`予約の変更・キャンセルは24時間前までです。\n店舗へ直接お電話ください。\n\n${store.name}\n${store.phone}`);
}

// 全予約表示
function showAllReservations() {
    document.getElementById('all-reservations-modal').classList.remove('hidden');
    displayAllReservations();
}

function closeAllReservations() {
    document.getElementById('all-reservations-modal').classList.add('hidden');
}

function filterReservations(filter) {
    currentFilter = filter;
    
    // ボタンのスタイル更新
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('bg-blue-500', 'text-white');
        btn.classList.add('bg-gray-100', 'text-gray-700');
    });
    event.target.classList.remove('bg-gray-100', 'text-gray-700');
    event.target.classList.add('bg-blue-500', 'text-white');
    
    displayAllReservations();
}

function displayAllReservations() {
    let filtered = allReservations;
    
    if (currentFilter === 'upcoming') {
        filtered = allReservations.filter(r => new Date(r.reservation_date) >= new Date());
    } else if (currentFilter === 'past') {
        filtered = allReservations.filter(r => new Date(r.reservation_date) < new Date());
    }
    
    const container = document.getElementById('all-reservations-list');
    
    if (filtered.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-500 py-8">予約がありません</p>';
        return;
    }
    
    container.innerHTML = filtered.map(r => `
        <div class="bg-white border rounded-xl p-4">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <p class="font-medium">${formatDate(r.reservation_date)}</p>
                    <p class="text-sm text-gray-600">${r.store?.name}</p>
                </div>
                <span class="text-xs px-2 py-1 rounded-full ${getStatusClass(r.status)}">
                    ${getStatusText(r.status)}
                </span>
            </div>
            <p class="text-sm text-gray-700">${r.menu?.name}</p>
            <p class="text-sm font-medium mt-1">¥${Math.floor(r.total_amount || 0).toLocaleString()}</p>
        </div>
    `).join('');
}

// 視力推移表示
function showVisionProgress() {
    window.location.href = '/customer/vision-progress';
}

// 新規予約
function quickReservation() {
    window.location.href = '/stores';
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

// 通知表示（仮）
function showNotifications() {
    alert('新着通知はありません');
}

// 統計情報読み込み
async function loadStats() {
    // 実装済み
}
</script>
@endsection