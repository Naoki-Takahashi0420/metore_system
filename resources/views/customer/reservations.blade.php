@extends('layouts.app')

@section('title', '予約履歴')

@section('content')
<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">予約履歴</h1>
                    <p class="text-gray-600" id="customer-info">
                        読み込み中...
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="/customer/medical-records" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                        カルテ
                    </a>
                    <a href="{{ url('/reservation') }}" class="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                        新規予約
                    </a>
                    <button id="logout-btn" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
                        ログアウト
                    </button>
                </div>
            </div>
        </div>

        <!-- Reservations List -->
        <div id="reservations-container">
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">予約情報を読み込み中...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden bg-white rounded-lg shadow-md p-8 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">予約履歴がありません</h3>
            <p class="text-gray-500 mb-6">まだ予約をされていません。ぜひ最初の予約をお取りください。</p>
            <a href="{{ url('/reservation') }}" class="bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors inline-block">
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

function displayReservations(reservations) {
    const container = document.getElementById('reservations-container');
    const emptyState = document.getElementById('empty-state');
    
    if (reservations.length === 0) {
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }
    
    container.innerHTML = reservations.map(reservation => `
        <div class="bg-white rounded-lg shadow-md p-6 mb-4">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center mb-3">
                        <span class="text-xs font-medium px-2 py-1 rounded-full ${getStatusColor(reservation.status)}">
                            ${getStatusText(reservation.status)}
                        </span>
                        <span class="text-sm text-gray-500 ml-3">予約番号: ${reservation.reservation_number}</span>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="font-semibold text-gray-900 mb-2">${reservation.menu?.name || 'メニュー情報なし'}</h3>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    ${reservation.store?.name || '店舗情報なし'}
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    ${formatDate(reservation.reservation_date)}
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    ${formatTime(reservation.start_time)} - ${formatTime(reservation.end_time)}
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <div class="text-lg font-semibold text-gray-900">
                                ¥${reservation.total_amount?.toLocaleString() || '0'}
                            </div>
                        </div>
                    </div>
                    
                    ${reservation.notes ? `
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <p class="text-sm text-gray-600">
                                <strong>備考:</strong> ${reservation.notes}
                            </p>
                        </div>
                    ` : ''}
                </div>
                
                <div class="ml-4 flex flex-col space-y-2">
                    <a href="/customer/reservations/${reservation.id}" 
                       class="bg-primary-600 text-white px-3 py-1 rounded text-sm font-medium hover:bg-primary-700 transition-colors text-center">
                        詳細
                    </a>
                    ${canModify(reservation) ? `
                        <button onclick="modifyReservation(${reservation.id})" 
                                class="bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium hover:bg-blue-700 transition-colors">
                            変更
                        </button>
                    ` : ''}
                    ${canCancel(reservation) ? `
                        <button onclick="cancelReservation(${reservation.id})" 
                                class="bg-red-600 text-white px-3 py-1 rounded text-sm font-medium hover:bg-red-700 transition-colors">
                            キャンセル
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `).join('');
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
        month: 'long', 
        day: 'numeric',
        weekday: 'short'
    });
}

function formatTime(timeString) {
    return timeString.substring(0, 5); // HH:MM format
}

function canModify(reservation) {
    if (['cancelled', 'completed', 'no_show', 'in_progress'].includes(reservation.status)) {
        return false;
    }
    
    // 48時間前までは変更可能
    const reservationDateTime = new Date(reservation.reservation_date + 'T' + reservation.start_time);
    const now = new Date();
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);
    
    return hoursDiff > 48;
}

function canCancel(reservation) {
    if (['cancelled', 'completed', 'no_show'].includes(reservation.status)) {
        return false;
    }
    
    // 24時間前まではキャンセル可能
    const reservationDateTime = new Date(reservation.reservation_date + 'T' + reservation.start_time);
    const now = new Date();
    const hoursDiff = (reservationDateTime - now) / (1000 * 60 * 60);
    
    return hoursDiff > 24;
}

function modifyReservation(reservationId) {
    alert('予約変更機能は現在準備中です。\nお手数ですが、店舗に直接お電話でご連絡ください。');
    // 将来的にはここで予約変更画面に遷移
    // window.location.href = `/customer/reservations/${reservationId}/edit`;
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
@endsection