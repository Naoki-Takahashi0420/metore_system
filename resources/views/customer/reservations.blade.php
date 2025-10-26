@extends('layouts.app')

@section('title', '予約履歴')

@section('content')
<div class="bg-white min-h-screen pb-20 md:pb-0">
    <div class="max-w-4xl mx-auto px-4">
        <!-- Header -->
        <div class="py-6 border-b border-gray-100">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">予約履歴</h1>
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
                    <a href="/stores" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-800 transition-colors">
                        新規予約
                    </a>
                </div>
            </div>
        </div>

        <!-- Reservations List -->
        <div id="reservations-container" class="py-4">
            <div class="text-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 mx-auto"></div>
                <p class="text-gray-500 mt-3 text-sm">予約情報を読み込み中...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden py-16 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-base font-medium text-gray-900 mb-2">予約履歴がありません</h3>
            <p class="text-sm text-gray-500 mb-6">まだ予約をされていません</p>
            <a href="/stores" class="bg-gray-900 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-gray-800 transition-colors inline-block">
                初回予約をする
            </a>
        </div>
    </div>
</div>

<script>
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
                `${customer.last_name} ${customer.first_name} 様 (${customer.phone})`;
        } catch (e) {
            console.error('Customer data parse error:', e);
        }
    }
    
    // 予約履歴を取得
    try {
        const response = await fetch('/api/customer/reservations', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            if (response.status === 401) {
                // トークンが無効
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                window.location.href = '/customer/login';
                return;
            }
            throw new Error('Failed to fetch reservations');
        }
        
        const data = await response.json();
        displayReservations(data.data || []);
        
    } catch (error) {
        console.error('Error fetching reservations:', error);
        document.getElementById('reservations-container').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">予約履歴の取得に失敗しました。再度お試しください。</p>
            </div>
        `;
    }
    
    // ログアウト処理
    document.getElementById('logout-btn').addEventListener('click', function() {
        localStorage.removeItem('customer_token');
        localStorage.removeItem('customer_data');
        window.location.href = '/customer/login';
    });
});

// グローバル変数として予約一覧を保存（キャンセル処理で使用）
let allReservations = [];

function displayReservations(reservations) {
    const container = document.getElementById('reservations-container');
    const emptyState = document.getElementById('empty-state');

    // 予約一覧をグローバル変数に保存
    allReservations = reservations;

    if (reservations.length === 0) {
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }

    container.innerHTML = reservations.map(reservation => `
        <div class="border-b border-gray-100 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs px-2 py-0.5 rounded ${getStatusColor(reservation.status)}">
                            ${getStatusText(reservation.status)}
                        </span>
                        <span class="text-xs text-gray-500">${reservation.reservation_number}</span>
                    </div>
                    
                    <h3 class="font-medium text-gray-900 mb-1">${reservation.menu?.name || 'メニュー'}</h3>
                    
                    <div class="text-sm text-gray-600 space-y-0.5">
                        <div>${reservation.store?.name || '店舗'}</div>
                        <div>${formatDate(reservation.reservation_date)} ${formatTime(reservation.start_time)}</div>
                    </div>
                    
                    <div class="mt-2 text-sm font-medium text-gray-900">
                        ¥${Math.floor(reservation.total_amount || 0).toLocaleString()}
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    ${reservation.can_cancel ? `
                        <button onclick="cancelReservation(${reservation.id})"
                                class="text-red-600 hover:text-red-700 text-sm">
                            キャンセル
                        </button>
                    ` : ''}
                    <a href="/customer/reservations/${reservation.id}"
                       class="text-gray-600 hover:text-gray-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    `).join('');
}

function getStatusColor(status) {
    const colors = {
        'pending': 'bg-amber-50 text-amber-700',
        'confirmed': 'bg-emerald-50 text-emerald-700',
        'booked': 'bg-emerald-50 text-emerald-700',
        'in_progress': 'bg-blue-50 text-blue-700',
        'completed': 'bg-gray-100 text-gray-700',
        'cancelled': 'bg-red-50 text-red-700',
        'canceled': 'bg-red-50 text-red-700',
        'no_show': 'bg-red-50 text-red-700'
    };
    return colors[status] || 'bg-gray-100 text-gray-700';
}

function getStatusText(status) {
    const texts = {
        'booked': '予約確定',      
        'completed': '完了',        // カルテ入力済み＝来店済み
        'cancelled': 'キャンセル済み',
        // 旧データ用
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
    // 時間データから時刻部分を取得
    if (timeString.includes(' ')) {
        return timeString.split(' ').pop().substring(0, 5); // '2025-09-07 14:00:00' -> '14:00'
    }
    return timeString.substring(0, 5); // 既に '14:00:00' 形式の場合 -> '14:00'
}

// 注: canCancel は不要（APIから can_cancel フラグを受け取る）

function modifyReservation(reservationId) {
    alert('予約変更機能は現在準備中です。\nお手数ですが、店舗に直接お電話でご連絡ください。');
    // 将来的にはここで予約変更画面に遷移
    // window.location.href = `/customer/reservations/${reservationId}/edit`;
}

async function cancelReservation(reservationId) {
    // 予約情報を取得してキャンセル期限を確認
    const reservation = allReservations.find(r => r.id === reservationId);
    const deadlineHours = reservation?.cancellation_deadline_hours || 24;

    if (!confirm(`本当にこの予約をキャンセルしますか？\n${deadlineHours}時間前までのキャンセルのみ無料です。`)) {
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
        
        window.dispatchEvent(new CustomEvent('show-toast', {
            detail: {
                message: '予約をキャンセルしました',
                type: 'success'
            }
        }));
        
        // ページを再読み込み
        location.reload();
        
    } catch (error) {
        console.error('Cancel error:', error);
        window.dispatchEvent(new CustomEvent('show-toast', {
            detail: {
                message: 'キャンセルに失敗しました',
                type: 'error'
            }
        }));
    }
}

function modifyReservation(reservationId) {
    if (confirm('予約を変更しますか？\n新しい日時を選択する画面に移動します。')) {
        // Store reservation ID for modification
        sessionStorage.setItem('modifyingReservationId', reservationId);
        // Redirect to store selection to start modification flow
        window.location.href = '/stores?modify=' + reservationId;
    }
}
</script>

{{-- モバイル用固定ナビゲーションバー --}}
@include('components.mobile-nav')
@endsection