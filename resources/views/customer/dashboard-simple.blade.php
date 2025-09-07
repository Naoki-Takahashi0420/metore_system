@extends('layouts.app')

@section('title', 'マイページ')

@section('content')
<div class="bg-white min-h-screen py-6 pb-20 md:pb-6">
    <div class="max-w-4xl mx-auto px-4">
        {{-- ヘッダー情報 --}}
        <div class="bg-white rounded-lg border border-gray-100 p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">マイページ</h1>
                    <p class="text-lg text-gray-700" id="customer-name">読み込み中...</p>
                    <p class="text-sm text-gray-500">会員ID: <span id="customer-id" class="font-mono">-</span></p>
                    <div id="subscription-badge" class="hidden mt-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
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
        <div id="subscription-info" class="hidden bg-white rounded-lg p-5 mb-4 border border-gray-200">
            <div class="mb-3">
                <h2 class="text-sm font-medium text-gray-900" id="subscription-plan-name">プラン名</h2>
            </div>
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm text-gray-600">
                    今月の利用 <span class="font-medium text-gray-900" id="subscription-usage">0/0回</span>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-500">残り</span>
                    <span class="text-lg font-bold text-gray-900 ml-1" id="subscription-remaining">-</span><span class="text-sm text-gray-600">回</span>
                </div>
            </div>
            <div class="text-sm text-gray-600 mb-4">
                有効期限 <span class="text-gray-500" id="subscription-end-date">-</span>
            </div>
            <button onclick="goToSubscriptionReservation()" class="w-full bg-gray-900 text-white px-4 py-3 rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium">
                次回予約をする
            </button>
        </div>

        {{-- ショートカットボタン --}}
        <div class="grid grid-cols-3 gap-3 mb-6">
            <button onclick="goToReservation()" class="bg-gray-900 hover:bg-gray-800 text-white rounded-lg p-5 transition-all">
                <svg class="w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                </svg>
                <p class="text-xs font-medium">予約する</p>
            </button>
            
            <a href="/customer/reservations" class="bg-white hover:bg-gray-50 text-gray-700 rounded-lg p-5 transition-all text-center border border-gray-200">
                <svg class="w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-xs font-medium">予約確認</p>
            </a>
            
            <a href="/customer/medical-records" class="bg-white hover:bg-gray-50 text-gray-700 rounded-lg p-5 transition-all text-center border border-gray-200">
                <svg class="w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-xs font-medium">カルテ</p>
            </a>
        </div>

        {{-- 次回の予約 --}}
        <div id="reservations" class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">次回の予約</h2>
            
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
            <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">予約が迫っています</h3>
            <p class="text-sm text-gray-600 mb-4">
                予約まで24時間を切っているため、<br>
                変更・キャンセルは店舗へ直接お電話ください。
            </p>
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <p class="text-xs text-gray-600 mb-1">店舗電話番号</p>
                <p class="text-lg font-semibold text-gray-900" id="store-phone">03-1234-5678</p>
            </div>
            <button onclick="closePhoneModal()" class="w-full bg-gray-700 text-white py-2 rounded-lg hover:bg-gray-800 transition-colors text-sm">
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
    console.log('サブスク情報取得開始');
    try {
        const token = localStorage.getItem('customer_token');
        if (!token) {
            console.log('トークンがありません');
            return;
        }
        
        // 顧客データから電話番号を取得してテストAPIを呼ぶ
        const customerData = JSON.parse(localStorage.getItem('customer_data') || '{}');
        const phone = customerData.phone;
        
        if (phone) {
            const response = await fetch(`/test/subscription/${phone}`);
            console.log('APIレスポンス:', response.status);
            
            if (response.ok) {
                const data = await response.json();
                console.log('サブスクデータ:', data);
                if (data.subscription) {
                // バッジ表示
                document.getElementById('subscription-badge').classList.remove('hidden');
                document.getElementById('subscription-info').classList.remove('hidden');
                
                // 情報更新（menu_nameまたはplan_nameを使用）
                const planName = data.subscription.menu_name || data.subscription.plan_name || 'サブスクプラン';
                document.getElementById('subscription-label').textContent = planName + '契約中';
                document.getElementById('subscription-plan-name').textContent = planName;
                
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
                
                // グローバル変数に保存（サブスク予約用）
                window.currentSubscription = data.subscription;
                }
            }
        } else {
            console.log('電話番号がありません');
        }
    } catch (error) {
        console.error('サブスク情報の取得エラー:', error);
    }
}

async function fetchNextReservation() {
    try {
        const token = localStorage.getItem('customer_token');
        const response = await fetch('/api/customer/reservations?' + new Date().getTime(), {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
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
        console.log('=== API Response Debug ===');
        console.log('All reservations:', reservations);
        console.log('Latest reservation:', reservations[0]);
        if (reservations[0]) {
            console.log('Raw reservation_date:', reservations[0].reservation_date);
            console.log('Raw start_time:', reservations[0].start_time);
            console.log('Status:', reservations[0].status);
            console.log('ID:', reservations[0].id);
        }
        
        const upcomingReservations = reservations.filter(r => {
            // 日付の処理
            let reservationDateStr = r.reservation_date;
            let startTimeStr = r.start_time;
            
            // reservation_dateがISO形式（2025-09-10T15:00:00.000000Z）の場合
            if (reservationDateStr.includes('T')) {
                // 日付部分のみ取得
                reservationDateStr = reservationDateStr.split('T')[0];
            } else if (reservationDateStr.includes(' ')) {
                // スペース区切りの場合
                reservationDateStr = reservationDateStr.split(' ')[0];
            }
            
            // start_timeの処理
            if (startTimeStr.includes('T')) {
                // ISO形式から時刻を取得
                const timePart = startTimeStr.split('T')[1];
                startTimeStr = timePart.split('.')[0]; // マイクロ秒を除去
                if (startTimeStr.endsWith('Z')) {
                    startTimeStr = startTimeStr.slice(0, -1);
                }
            } else if (startTimeStr.includes(' ')) {
                // スペース区切りの場合、時刻部分を取得
                startTimeStr = startTimeStr.split(' ').pop();
            }
            
            // 時刻が不正な場合のフォールバック
            if (!startTimeStr.match(/^\d{2}:\d{2}(:\d{2})?$/)) {
                startTimeStr = '12:00:00';
            }
            
            const reservationDateTime = new Date(reservationDateStr + 'T' + startTimeStr);
            
            console.log(`予約${r.id}: ${reservationDateStr}T${startTimeStr} = ${reservationDateTime}, status: ${r.status}`);
            console.log(`現在時刻: ${now}`);
            console.log(`未来の予約?: ${reservationDateTime > now}`);
            
            // bookedまたはconfirmedステータスの予約を表示
            return reservationDateTime > now && (r.status === 'booked' || r.status === 'confirmed');
        }).sort((a, b) => {
            // ソート用の日付を適切に作成
            const reservationDateStrA = a.reservation_date.split('T')[0];
            const startTimeStrA = a.start_time.includes('T') ? a.start_time.split('T')[1].substring(0, 8) : a.start_time.substring(0, 8);
            const dateA = new Date(reservationDateStrA + 'T' + startTimeStrA);
            
            const reservationDateStrB = b.reservation_date.split('T')[0];
            const startTimeStrB = b.start_time.includes('T') ? b.start_time.split('T')[1].substring(0, 8) : b.start_time.substring(0, 8);
            const dateB = new Date(reservationDateStrB + 'T' + startTimeStrB);
            
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
                <button onclick="goToReservation()" class="bg-gray-900 text-white px-6 py-2 rounded-lg hover:bg-gray-800 transition-colors text-sm">
                    予約を取る
                </button>
            </div>
        `;
        return;
    }
    
    nextReservation = reservation;
    // 日付と時刻を適切に結合
    let reservationDateStr = reservation.reservation_date;
    
    // 日付文字列の正規化
    if (reservationDateStr.includes('T')) {
        reservationDateStr = reservationDateStr.split('T')[0];
    } else if (reservationDateStr.includes(' ')) {
        reservationDateStr = reservationDateStr.split(' ')[0];
    }
    
    // 時刻文字列の正規化  
    let startTimeStr = reservation.start_time;
    if (startTimeStr.includes('T')) {
        startTimeStr = startTimeStr.split('T')[1].substring(0, 8);
    } else if (startTimeStr.includes(' ')) {
        startTimeStr = startTimeStr.split(' ').pop();
    }
    
    // 時刻が「HH:MM」形式の場合、秒を追加
    if (startTimeStr && startTimeStr.length === 5) {
        startTimeStr += ':00';
    }
    
    console.log('Date processing:', {
        original_date: reservation.reservation_date,
        original_time: reservation.start_time,
        processed_date: reservationDateStr,
        processed_time: startTimeStr,
        final_string: reservationDateStr + 'T' + startTimeStr
    });
    
    const reservationDate = new Date(reservationDateStr + 'T' + startTimeStr);
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
    
    // 日付と曜日を分けてフォーマット
    const dateObj = new Date(reservationDate);
    
    // 無効な日付の場合のデバッグログ
    if (isNaN(dateObj.getTime())) {
        console.error('Invalid date:', reservationDate, 'from reservation:', reservation);
        return; // 無効な日付の場合は処理を中止
    }
    
    const month = dateObj.getMonth() + 1;
    const date = dateObj.getDate();
    const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    const weekday = weekdays[dateObj.getDay()];
    const hours = dateObj.getHours();
    const minutes = dateObj.getMinutes().toString().padStart(2, '0');
    
    container.innerHTML = `
        <div class="bg-gray-50 rounded-lg p-4 mb-3">
            <div class="mb-3">
                <div class="text-sm font-medium text-gray-900">${reservation.menu?.name || 'メニュー'}</div>
                <div class="flex items-baseline gap-1 mt-1">
                    <span class="text-xs text-gray-500">今月の利用</span>
                    <span class="text-sm font-medium text-gray-900">${month}/${date}回</span>
                </div>
                <div class="text-xs text-gray-500 mt-1">有効期限 無期限</div>
            </div>
            <button onclick="goToSubscriptionReservation()" class="w-full bg-gray-900 text-white py-2.5 rounded-lg text-sm font-medium">
                次回予約をする
            </button>
        </div>
        
        <div class="border-t pt-3">
            <div class="text-sm font-medium text-gray-900 mb-3">次回の予約</div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">${month}月${date}日${weekday}曜日 ${hours}:${minutes}</div>
                        <div class="text-sm text-gray-700 mt-1">${reservation.menu?.name || 'メニュー'}</div>
                        <div class="text-sm text-gray-600">${reservation.store?.name || '店舗'}</div>
                    </div>
                </div>
                <div class="flex gap-2">
                    ${canModify ? `
                        <button onclick="changeReservationDate(${reservation.id})" 
                                class="flex-1 text-gray-700 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium">
                            日程変更
                        </button>
                        <button onclick="cancelReservation(${reservation.id})" 
                                class="flex-1 text-red-600 py-2 border border-red-200 rounded-lg hover:bg-red-50 transition-colors text-sm font-medium">
                            キャンセル
                        </button>
                    ` : `
                        <button onclick="showPhoneModal('${reservation.store?.phone || '03-0000-0000'}')" 
                                class="w-full bg-amber-500 text-white py-2 rounded-lg hover:bg-amber-600 transition-colors text-sm font-medium">
                            変更・キャンセル
                        </button>
                    `}
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <a href="/customer/reservations" class="text-gray-600 hover:text-gray-900 text-xs">
                    すべての予約を見る →
                </a>
            </div>
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

// サブスク専用予約
function goToSubscriptionReservation() {
    const customerData = localStorage.getItem('customer_data');
    const subscriptionData = window.currentSubscription;
    
    if (!customerData || !subscriptionData) {
        alert('サブスクリプション情報が取得できませんでした。');
        return;
    }
    
    const customer = JSON.parse(customerData);
    
    // セッションストレージに必要な情報を保存
    sessionStorage.setItem('existing_customer_id', customer.id);
    sessionStorage.setItem('subscription_reservation', 'true');
    sessionStorage.setItem('subscription_store_id', subscriptionData.store_id);
    sessionStorage.setItem('subscription_menu_id', subscriptionData.menu_id);
    sessionStorage.setItem('subscription_menu_name', subscriptionData.menu_name || subscriptionData.plan_name || 'サブスクメニュー');
    
    // 店舗情報があれば保存
    if (subscriptionData.store) {
        sessionStorage.setItem('subscription_store_name', subscriptionData.store.name);
    }
    
    // 直接カレンダー画面へ遷移
    window.location.href = `/customer/subscription-booking`;
}

// 日程変更
function changeReservationDate(reservationId) {
    // 予約情報を保存
    const changingReservation = nextReservation;
    if (!changingReservation) return;
    
    // 変更中の予約情報をセッションストレージに保存
    sessionStorage.setItem('changingReservation', JSON.stringify(changingReservation));
    sessionStorage.setItem('isChangingReservation', 'true');
    sessionStorage.setItem('subscription_store_id', changingReservation.store_id);
    sessionStorage.setItem('subscription_menu_id', changingReservation.menu_id);
    sessionStorage.setItem('subscription_store_name', changingReservation.store?.name || '');
    sessionStorage.setItem('subscription_menu_name', changingReservation.menu?.name || '');
    
    // サブスク予約画面を変更モードで使用
    window.location.href = `/customer/subscription-booking?change=true`;
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
            },
            body: JSON.stringify({
                cancel_reason: '顧客都合'
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            alert('予約をキャンセルしました。');
            await fetchNextReservation();
        } else {
            // 24時間以内の場合の処理
            if (data.require_phone_contact) {
                showPhoneModal(data.store_phone);
            } else {
                alert(data.message || 'キャンセルに失敗しました。');
            }
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

{{-- モバイル用固定ナビゲーションバー --}}
@include('components.mobile-nav')
@endsection