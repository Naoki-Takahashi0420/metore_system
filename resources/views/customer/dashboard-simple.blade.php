@extends('layouts.app')

@section('title', 'マイページ')

@section('content')
<div class="bg-gray-50 min-h-screen py-6">
    <div class="max-w-4xl mx-auto px-4">
        {{-- ヘッダー情報 --}}
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">マイページ</h1>
                    <p class="text-lg text-gray-700" id="customer-name">読み込み中...</p>
                    <p class="text-sm text-gray-500">会員ID: <span id="customer-id" class="font-mono">-</span></p>
                    <div id="subscription-badge" class="hidden mt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                                <circle cx="4" cy="4" r="3" />
                            </svg>
                            <span id="subscription-label">サブスク契約中</span>
                        </span>
                    </div>
                </div>
                <button onclick="logout()" class="text-sm text-gray-600 hover:text-gray-900 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    ログアウト
                </button>
            </div>
        </div>

        {{-- サブスク情報カード --}}
        <div id="subscription-info" class="hidden bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg shadow-sm p-6 mb-6 border border-purple-200">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-1">
                        <span class="text-purple-600">🎆</span> <span id="subscription-plan-name">プラン名</span>
                    </h2>
                    <p class="text-sm text-gray-600 mb-2">
                        今月の利用: <span class="font-bold text-lg" id="subscription-usage">0/0回</span>
                    </p>
                    <p class="text-xs text-gray-500">
                        有効期限: <span id="subscription-end-date">-</span>まで
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 mb-1">残り</p>
                    <p class="text-2xl font-bold text-purple-600" id="subscription-remaining">-回</p>
                </div>
            </div>
        </div>

        {{-- ショートカットボタン --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <button onclick="goToReservation()" class="bg-blue-500 hover:bg-blue-600 text-white rounded-lg p-6 transition-colors">
                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <p class="font-semibold">予約する</p>
            </button>
            
            <a href="/customer/reservations" class="bg-green-500 hover:bg-green-600 text-white rounded-lg p-6 transition-colors text-center">
                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="font-semibold">予約を確認</p>
            </a>
            
            <a href="/customer/medical-records" class="bg-purple-500 hover:bg-purple-600 text-white rounded-lg p-6 transition-colors text-center">
                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="font-semibold">カルテ</p>
            </a>
        </div>

        {{-- 次回の予約 --}}
        <div id="reservations" class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">次回の予約</h2>
            
            <div id="next-reservation-container">
                {{-- 予約情報がここに動的に表示される --}}
                <div class="text-center py-8 text-gray-500">
                    予約情報を読み込み中...
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 電話案内モーダル --}}
<div id="phone-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <div class="text-center">
            <svg class="w-16 h-16 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3 class="text-lg font-bold text-gray-900 mb-2">予約が迫っています</h3>
            <p class="text-gray-600 mb-4">
                予約まで24時間を切っているため、<br>
                変更・キャンセルは店舗へ直接お電話ください。
            </p>
            <div class="bg-gray-100 rounded-lg p-4 mb-4">
                <p class="text-sm text-gray-600 mb-1">店舗電話番号</p>
                <p class="text-xl font-bold text-gray-900" id="store-phone">03-1234-5678</p>
            </div>
            <button onclick="closePhoneModal()" class="w-full bg-gray-600 text-white py-2 rounded-lg hover:bg-gray-700 transition-colors">
                閉じる
            </button>
        </div>
    </div>
</div>

<script>
let nextReservation = null;

document.addEventListener('DOMContentLoaded', async function() {
    const token = localStorage.getItem('customer_token');
    const customerData = localStorage.getItem('customer_data');
    
    if (!token) {
        window.location.href = '/customer/login';
        return;
    }
    
    // 顧客情報を表示
    if (customerData) {
        try {
            const customer = JSON.parse(customerData);
            document.getElementById('customer-name').textContent = 
                `${customer.last_name} ${customer.first_name} 様`;
            document.getElementById('customer-id').textContent = 
                customer.id.toString().padStart(6, '0');
        } catch (e) {
            console.error('Customer data parse error:', e);
        }
    }
    
    // 予約情報を取得
    await fetchNextReservation();
    
    // サブスク情報を取得
    await fetchSubscriptionInfo();
});

async function fetchSubscriptionInfo() {
    try {
        const token = localStorage.getItem('customer_token');
        if (!token) return;
        
        const response = await fetch('/api/customer/subscription', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.subscription) {
                // バッジ表示
                document.getElementById('subscription-badge').classList.remove('hidden');
                document.getElementById('subscription-info').classList.remove('hidden');
                
                // 情報更新
                document.getElementById('subscription-label').textContent = data.subscription.plan_name + '契約中';
                document.getElementById('subscription-plan-name').textContent = data.subscription.plan_name;
                
                // 利用状況
                const limit = data.subscription.monthly_limit || '無制限';
                const usage = data.subscription.current_month_visits || 0;
                document.getElementById('subscription-usage').textContent = `${usage}/${limit}回`;
                
                // 有効期限
                if (data.subscription.end_date) {
                    const endDate = new Date(data.subscription.end_date);
                    document.getElementById('subscription-end-date').textContent = 
                        endDate.toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' });
                } else {
                    document.getElementById('subscription-end-date').textContent = '無期限';
                }
                
                // 残り回数
                if (data.subscription.monthly_limit) {
                    const remaining = Math.max(0, data.subscription.monthly_limit - usage);
                    document.getElementById('subscription-remaining').textContent = remaining + '回';
                } else {
                    document.getElementById('subscription-remaining').textContent = '無制限';
                }
            }
        }
    } catch (error) {
        console.error('サブスク情報の取得エラー:', error);
    }
}

async function fetchNextReservation() {
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch('/api/customer/reservations', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            if (response.status === 401) {
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                window.location.href = '/customer/login';
                return;
            }
            throw new Error('Failed to fetch reservations');
        }
        
        const data = await response.json();
        const reservations = data.data || [];
        
        // 今後の予約のみフィルタ
        const now = new Date();
        console.log('All reservations:', reservations);
        
        const upcomingReservations = reservations.filter(r => {
            // 日付と時間を正しく結合
            const reservationDateStr = r.reservation_date.split(' ')[0]; // '2025-09-08'の部分を取得
            const startTimeStr = r.start_time.split(' ').pop(); // '12:30:00'の部分を取得
            const reservationDateTime = new Date(reservationDateStr + 'T' + startTimeStr);
            
            console.log(`予約${r.id}: ${reservationDateStr}T${startTimeStr} = ${reservationDateTime}, status: ${r.status}`);
            console.log(`現在時刻: ${now}`);
            console.log(`未来の予約?: ${reservationDateTime > now}`);
            
            return reservationDateTime > now && r.status === 'booked';
        }).sort((a, b) => {
            const dateA = new Date(a.reservation_date.split(' ')[0] + 'T' + a.start_time.split(' ').pop());
            const dateB = new Date(b.reservation_date.split(' ')[0] + 'T' + b.start_time.split(' ').pop());
            return dateA - dateB;
        });
        
        displayNextReservation(upcomingReservations[0]);
        
    } catch (error) {
        console.error('Error fetching reservations:', error);
        document.getElementById('next-reservation-container').innerHTML = `
            <div class="text-center py-8 text-gray-500">
                予約情報の取得に失敗しました
            </div>
        `;
    }
}

function displayNextReservation(reservation) {
    const container = document.getElementById('next-reservation-container');
    
    if (!reservation) {
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-gray-500 mb-4">予約がありません</p>
                <button onclick="goToReservation()" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                    予約を取る
                </button>
            </div>
        `;
        return;
    }
    
    nextReservation = reservation;
    const reservationDate = new Date(reservation.reservation_date + 'T' + reservation.start_time);
    const now = new Date();
    const hoursDiff = (reservationDate - now) / (1000 * 60 * 60);
    const canModify = hoursDiff > 24;
    
    // 日付フォーマット
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric', 
        weekday: 'long',
        hour: '2-digit',
        minute: '2-digit'
    };
    const formattedDate = reservationDate.toLocaleDateString('ja-JP', options);
    
    container.innerHTML = `
        <div class="border-2 border-blue-200 rounded-lg p-6 bg-blue-50">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-2xl font-bold text-gray-900 mb-2">${formattedDate}</p>
                    <p class="text-lg text-gray-700">${reservation.menu?.name || 'メニュー未設定'}</p>
                    <p class="text-gray-600">${reservation.store?.name || '店舗未設定'}</p>
                    ${!canModify ? `
                        <div class="mt-3 inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            24時間以内
                        </div>
                    ` : ''}
                </div>
                <div class="flex gap-2">
                    ${canModify ? `
                        <button onclick="changeReservationDate(${reservation.id})" 
                                class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            日程変更
                        </button>
                        <button onclick="cancelReservation(${reservation.id})" 
                                class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors">
                            キャンセル
                        </button>
                    ` : `
                        <button onclick="showPhoneModal('${reservation.store?.phone || '03-0000-0000'}')" 
                                class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors">
                            変更・キャンセル
                        </button>
                    `}
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="#" onclick="showAllReservations(); return false;" class="text-blue-600 hover:underline text-sm">
                すべての予約を見る →
            </a>
        </div>
    `;
}

// 既存顧客として予約
function goToReservation() {
    const customerData = localStorage.getItem('customer_data');
    if (customerData) {
        const customer = JSON.parse(customerData);
        sessionStorage.setItem('existing_customer_id', customer.id);
        sessionStorage.setItem('from_mypage', 'true');
    }
    window.location.href = '/stores';
}

// 日程変更
function changeReservationDate(reservationId) {
    alert('日程変更機能は準備中です。');
    // TODO: 実装
}

// キャンセル
async function cancelReservation(reservationId) {
    if (!confirm('本当にこの予約をキャンセルしますか？')) {
        return;
    }
    
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch(`/api/customer/reservations/${reservationId}/cancel`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });
        
        if (response.ok) {
            alert('予約をキャンセルしました。');
            await fetchNextReservation();
        } else {
            alert('キャンセルに失敗しました。');
        }
    } catch (error) {
        console.error('Error cancelling reservation:', error);
        alert('エラーが発生しました。');
    }
}

// 電話モーダル表示
function showPhoneModal(phoneNumber) {
    document.getElementById('store-phone').textContent = phoneNumber;
    const modal = document.getElementById('phone-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

// 電話モーダルを閉じる
function closePhoneModal() {
    const modal = document.getElementById('phone-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// すべての予約を表示（TODO: 実装）
function showAllReservations() {
    alert('準備中です');
}

// ログアウト
function logout() {
    if (confirm('ログアウトしますか？')) {
        localStorage.removeItem('customer_token');
        localStorage.removeItem('customer_data');
        window.location.href = '/customer/login';
    }
}
</script>
@endsection