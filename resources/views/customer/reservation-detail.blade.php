@extends('layouts.app')

@section('title', '予約詳細')

@section('content')
<div class="bg-gray-50 min-h-screen py-8 pb-24 md:pb-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center space-x-3">
                <a href="/customer/reservations" class="text-gray-500 hover:text-primary-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">予約詳細</h1>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loading-state" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
            <p class="text-gray-500 mt-2">予約情報を読み込み中...</p>
        </div>

        <!-- Error State -->
        <div id="error-state" class="hidden bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <p class="text-red-800">予約情報の取得に失敗しました。</p>
            </div>
        </div>

        <!-- Reservation Detail -->
        <div id="reservation-detail" class="hidden space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center space-x-3 mb-2">
                            <span id="status-badge" class="text-xs font-medium px-3 py-1 rounded-full"></span>
                            <span class="text-sm text-gray-500" id="reservation-number"></span>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900" id="menu-name"></h2>
                        <p class="text-gray-600" id="store-name"></p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-gray-900" id="total-amount"></div>
                    </div>
                </div>
            </div>

            <!-- Reservation Info -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">予約情報</h3>
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <div>
                                    <div class="text-sm text-gray-500">予約日</div>
                                    <div class="font-medium" id="reservation-date"></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <div class="text-sm text-gray-500">時間</div>
                                    <div class="font-medium" id="reservation-time"></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <div>
                                    <div class="text-sm text-gray-500">来店人数</div>
                                    <div class="font-medium" id="guest-count"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                </svg>
                                <div>
                                    <div class="text-sm text-gray-500">店舗</div>
                                    <div class="font-medium" id="store-details"></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center" id="staff-info" style="display: none;">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <div>
                                    <div class="text-sm text-gray-500">担当スタッフ</div>
                                    <div class="font-medium" id="staff-name"></div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu Details -->
            <div class="bg-white rounded-lg shadow-md p-6" id="menu-details-card">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">メニュー詳細</h3>
                <div id="menu-details-content">
                    <!-- メニュー詳細がここに表示されます -->
                </div>
            </div>

            <!-- Notes -->
            <div class="bg-white rounded-lg shadow-md p-6" id="notes-card" style="display: none;">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">備考・メモ</h3>
                <div class="space-y-3">
                    <div id="customer-notes" style="display: none;">
                        <div class="text-sm text-gray-500 mb-1">お客様からの備考</div>
                        <div class="bg-gray-50 p-3 rounded-lg" id="customer-notes-content"></div>
                    </div>
                    <div id="cancel-reason" style="display: none;">
                        <div class="text-sm text-gray-500 mb-1">キャンセル理由</div>
                        <div class="bg-red-50 p-3 rounded-lg text-red-800" id="cancel-reason-content"></div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">操作</h3>
                <div class="flex flex-wrap gap-3">
                    <button id="cancel-btn" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition-colors" style="display: none;">
                        予約をキャンセル
                    </button>
                    <a id="receipt-btn" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors cursor-pointer" style="display: none;" target="_blank">
                        領収証を表示
                    </a>
                    <a href="/customer/reservations" class="bg-gray-500 text-white px-4 py-2 rounded-lg font-medium hover:bg-gray-600 transition-colors">
                        戻る
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const reservationId = window.location.pathname.split('/').pop();
    const token = localStorage.getItem('customer_token');
    
    // トークンがない場合はログインページにリダイレクト
    if (!token) {
        window.location.href = '/customer/login';
        return;
    }
    
    try {
        const response = await fetch(`/api/customer/reservations/${reservationId}`, {
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
            throw new Error('Failed to fetch reservation');
        }
        
        const data = await response.json();
        displayReservationDetail(data.data);
        
    } catch (error) {
        console.error('Error fetching reservation:', error);
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-state').classList.remove('hidden');
    }
});

function displayReservationDetail(reservation) {
    // Hide loading state
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('reservation-detail').classList.remove('hidden');
    
    // Status badge
    const statusBadge = document.getElementById('status-badge');
    statusBadge.textContent = getStatusText(reservation.status);
    statusBadge.className = `text-xs font-medium px-3 py-1 rounded-full ${getStatusColor(reservation.status)}`;
    
    // Basic info
    document.getElementById('reservation-number').textContent = `予約番号: ${reservation.reservation_number}`;
    document.getElementById('menu-name').textContent = reservation.menu?.name || 'メニュー情報なし';
    document.getElementById('store-name').textContent = reservation.store?.name || '店舗情報なし';
    const isSubscription = reservation.total_amount === 0 || reservation.menu?.name?.includes('サブスク');
    document.getElementById('total-amount').textContent = isSubscription ? 'サブスク利用' : `¥${reservation.total_amount?.toLocaleString() || '0'}`;
    
    // Reservation details
    document.getElementById('reservation-date').textContent = formatDate(reservation.reservation_date);
    document.getElementById('reservation-time').textContent = `${formatTime(reservation.start_time)} - ${formatTime(reservation.end_time)}`;
    document.getElementById('guest-count').textContent = `${reservation.guest_count || 1}名`;
    document.getElementById('store-details').textContent = reservation.store?.name || '店舗情報なし';
    
    // Staff info
    if (reservation.staff) {
        document.getElementById('staff-info').style.display = 'flex';
        document.getElementById('staff-name').textContent = reservation.staff.name;
    }
    
    // キャンセルボタンの表示制御
    if (canCancel(reservation)) {
        const cancelBtn = document.getElementById('cancel-btn');
        cancelBtn.style.display = 'inline-block';
        cancelBtn.onclick = () => cancelReservation(reservation.id);
    }
    
    // Menu details
    if (reservation.menu) {
        const menuContent = document.getElementById('menu-details-content');
        const isSubscription = reservation.total_amount === 0 || reservation.menu.name?.includes('サブスク');
        
        menuContent.innerHTML = `
            <div class="space-y-3">
                <div>
                    <div class="text-sm text-gray-500">メニュー名</div>
                    <div class="font-medium">${reservation.menu.name}</div>
                </div>
                ${reservation.menu.description ? `
                <div>
                    <div class="text-sm text-gray-500">詳細</div>
                    <div class="text-gray-700">${reservation.menu.description}</div>
                </div>
                ` : ''}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-gray-500">所要時間</div>
                        <div class="font-medium">${reservation.menu.duration || reservation.menu.duration_minutes || '60'}分</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">料金</div>
                        <div class="font-medium">${isSubscription ? 'サブスク利用' : `¥${reservation.menu.price?.toLocaleString() || '0'}`}</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Notes
    let hasNotes = false;
    if (reservation.notes) {
        document.getElementById('customer-notes').style.display = 'block';
        document.getElementById('customer-notes-content').textContent = reservation.notes;
        hasNotes = true;
    }
    
    if (reservation.cancel_reason) {
        document.getElementById('cancel-reason').style.display = 'block';
        document.getElementById('cancel-reason-content').textContent = reservation.cancel_reason;
        hasNotes = true;
    }
    
    if (hasNotes) {
        document.getElementById('notes-card').style.display = 'block';
    }
    
    // Cancel button
    if (canCancel(reservation)) {
        const cancelBtn = document.getElementById('cancel-btn');
        cancelBtn.style.display = 'block';
        cancelBtn.addEventListener('click', () => cancelReservation(reservation.id));
    }

    // Receipt button (show only for completed reservations)
    if (reservation.status === 'completed') {
        const receiptBtn = document.getElementById('receipt-btn');
        receiptBtn.style.display = 'inline-block';
        receiptBtn.href = `/receipt/reservation/${reservation.id}`;
    }
}

function getStatusColor(status) {
    const colors = {
        'booked': 'bg-green-100 text-green-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'confirmed': 'bg-green-100 text-green-800',
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
        'pending': '確認待ち',
        'confirmed': '確定',
        'in_progress': '施術中',
        'completed': '完了',
        'cancelled': 'キャンセル済み',
        'canceled': 'キャンセル済み',
        'no_show': '無断キャンセル'
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
</script>

{{-- モバイル用固定ナビゲーションバー --}}
@include('components.mobile-nav')
@endsection