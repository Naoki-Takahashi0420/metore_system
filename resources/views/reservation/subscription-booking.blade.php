@extends('layouts.app')

@section('title', 'サブスク予約')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        {{-- ヘッダー --}}
        <div class="mb-6">
            <button onclick="history.back()" class="text-gray-600 hover:text-gray-900 mb-4 inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                </svg>
                戻る
            </button>
            <h1 class="text-2xl font-bold text-gray-900">サブスク予約</h1>
            <p class="text-gray-600 mt-2">日時を選択してください</p>
        </div>

        {{-- 予約情報確認 --}}
        <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">予約内容</h2>
            <div class="space-y-3">
                <div>
                    <p class="text-sm text-gray-500">店舗</p>
                    <p class="text-gray-900 font-medium" id="store-name">読み込み中...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">メニュー</p>
                    <p class="text-gray-900 font-medium" id="menu-name">読み込み中...</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">お客様情報</p>
                    <p class="text-gray-900" id="customer-info">読み込み中...</p>
                </div>
            </div>
        </div>

        {{-- 週間カレンダー --}}
        <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
            {{-- 週間ナビゲーション --}}
            <div class="flex justify-between items-center mb-6">
                <button onclick="changeWeek(-1)" id="prev-week" class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300">
                    ← 前の一週間
                </button>
                
                <h2 class="text-xl font-bold" id="current-month">2025年9月</h2>
                
                <button onclick="changeWeek(1)" id="next-week" class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300">
                    次の一週間 →
                </button>
            </div>

            {{-- 予約可能時間テーブル --}}
            <div class="overflow-x-auto">
                <table class="w-full" id="availability-table">
                    <thead class="bg-gray-100">
                        <tr id="date-header">
                            <th class="py-3 px-2 text-sm font-medium text-gray-700 border-r"></th>
                            {{-- 日付ヘッダーがここに動的に生成される --}}
                        </tr>
                    </thead>
                    <tbody id="time-slots-body">
                        {{-- 時間枠がここに動的に生成される --}}
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 選択中の日時表示 --}}
        <div id="selected-info" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-600 mb-1">選択した日時</p>
                    <p class="text-lg font-semibold text-gray-900" id="selected-datetime"></p>
                </div>
                <button onclick="clearSelection()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- 確認ボタン --}}
        <div id="confirm-section" class="hidden">
            <button onclick="confirmReservation()" class="w-full bg-gray-900 text-white py-3 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                予約を確定する
            </button>
        </div>
    </div>
</div>

<style>
    .time-slot {
        cursor: pointer;
        transition: all 0.2s;
    }
    .time-slot:hover:not(.unavailable) {
        transform: scale(1.1);
    }
    .unavailable {
        cursor: not-allowed;
        opacity: 0.5;
    }
    .selected {
        background-color: #3b82f6 !important;
        color: white !important;
    }
</style>

<script>
let weekOffset = 0;
let selectedDate = null;
let selectedTime = null;
let storeId = null;
let menuId = null;
let customerId = null;
const maxWeeks = 4; // 最大4週間先まで

document.addEventListener('DOMContentLoaded', async function() {
    // URLパラメータをチェック（変更モードかどうか）
    const urlParams = new URLSearchParams(window.location.search);
    const isChangeMode = urlParams.get('change') === 'true' || sessionStorage.getItem('isChangingReservation') === 'true';
    
    // セッションストレージから情報を取得
    storeId = sessionStorage.getItem('subscription_store_id');
    menuId = sessionStorage.getItem('subscription_menu_id');
    const menuName = sessionStorage.getItem('subscription_menu_name');
    const storeName = sessionStorage.getItem('subscription_store_name');
    customerId = sessionStorage.getItem('existing_customer_id');
    
    // 顧客情報を取得
    const customerData = JSON.parse(localStorage.getItem('customer_data') || '{}');
    
    // 変更モードの場合の処理
    let changingReservation = null;
    if (isChangeMode) {
        const changingData = sessionStorage.getItem('changingReservation');
        if (changingData) {
            changingReservation = JSON.parse(changingData);
            // タイトルと説明を変更
            document.querySelector('h1').textContent = '予約日時の変更';
            document.querySelector('h1').nextElementSibling.textContent = '新しい日時を選択してください';
            // 確定ボタンのテキストを変更
            setTimeout(() => {
                const confirmBtn = document.querySelector('#confirm-section button');
                if (confirmBtn) {
                    confirmBtn.textContent = '変更を確定する';
                }
            }, 100);
        }
    }
    
    // デバッグ情報
    console.log('Store ID:', storeId);
    console.log('Store Name:', storeName);
    console.log('Menu ID:', menuId);
    console.log('Menu Name:', menuName);
    console.log('Customer ID:', customerId);
    console.log('Token exists:', !!localStorage.getItem('customer_token'));
    
    if (!customerId) {
        alert('予約情報が不足しています。マイページから再度お試しください。');
        window.location.href = '/customer/dashboard';
        return;
    }
    
    // 店舗名を表示（セッションストレージから取得済み、またはAPIから取得）
    if (storeName) {
        document.getElementById('store-name').textContent = storeName;
    } else if (storeId) {
        // 店舗情報を取得
        try {
            const response = await fetch(`/api/stores/${storeId}`);
            if (response.ok) {
                const store = await response.json();
                document.getElementById('store-name').textContent = store.name || store.data?.name || '店舗情報取得中...';
            } else {
                document.getElementById('store-name').textContent = '店舗情報の取得に失敗しました';
            }
        } catch (error) {
            console.error('店舗情報の取得に失敗:', error);
            document.getElementById('store-name').textContent = '店舗情報の取得に失敗しました';
        }
    } else {
        document.getElementById('store-name').textContent = '店舗情報なし';
    }
    
    // メニュー名を表示
    document.getElementById('menu-name').textContent = menuName || 'サブスクメニュー';
    
    // 顧客情報を表示
    document.getElementById('customer-info').textContent = 
        `${customerData.last_name} ${customerData.first_name} 様 (${customerData.phone})`;
    
    // カレンダーを初期化
    renderWeekCalendar();
});

function renderWeekCalendar() {
    const today = new Date();
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - today.getDay() + (weekOffset * 7));
    
    // 日付ヘッダーを生成
    const dateHeader = document.getElementById('date-header');
    dateHeader.innerHTML = '<th class="py-3 px-2 text-sm font-medium text-gray-700 border-r"></th>';
    
    const dates = [];
    const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    
    for (let i = 0; i < 7; i++) {
        const date = new Date(startOfWeek);
        date.setDate(startOfWeek.getDate() + i);
        dates.push(date);
        
        const isToday = date.toDateString() === today.toDateString();
        const dayOfWeek = date.getDay();
        const dayColorClass = dayOfWeek === 0 ? 'text-red-500' : dayOfWeek === 6 ? 'text-blue-500' : 'text-gray-700';
        
        const th = document.createElement('th');
        th.className = `py-2 px-2 text-center ${isToday ? 'bg-blue-50' : ''}`;
        th.innerHTML = `
            <div class="text-xs font-normal ${dayColorClass}">
                ${dayNames[dayOfWeek]}
            </div>
            <div class="text-lg font-bold ${dayColorClass}">
                ${date.getMonth() + 1}/${date.getDate()}
            </div>
        `;
        dateHeader.appendChild(th);
    }
    
    // 月表示を更新
    document.getElementById('current-month').textContent = 
        `${dates[0].getFullYear()}年${dates[0].getMonth() + 1}月`;
    
    // ナビゲーションボタンの表示/非表示
    document.getElementById('prev-week').style.visibility = weekOffset <= 0 ? 'hidden' : 'visible';
    document.getElementById('next-week').style.visibility = weekOffset >= (maxWeeks - 1) ? 'hidden' : 'visible';
    
    // 時間枠を生成
    generateTimeSlots(dates);
}

function generateTimeSlots(dates) {
    const timeSlotsBody = document.getElementById('time-slots-body');
    timeSlotsBody.innerHTML = '';
    
    const timeSlots = [];
    for (let hour = 10; hour <= 18; hour++) {
        timeSlots.push(`${hour}:00`);
        if (hour < 18) timeSlots.push(`${hour}:30`);
    }
    
    timeSlots.forEach(slot => {
        const tr = document.createElement('tr');
        tr.className = 'border-t';
        
        // 時間ラベル
        const timeTd = document.createElement('td');
        timeTd.className = 'py-3 px-2 text-sm font-medium text-gray-700 bg-gray-50 border-r';
        timeTd.textContent = slot;
        tr.appendChild(timeTd);
        
        // 各日付のスロット
        dates.forEach(date => {
            const td = document.createElement('td');
            const isToday = date.toDateString() === new Date().toDateString();
            td.className = `py-3 px-2 text-center ${isToday ? 'bg-blue-50' : ''}`;
            
            // 過去の日時は×を表示
            const slotDateTime = new Date(date);
            const [hours, minutes] = slot.split(':');
            slotDateTime.setHours(parseInt(hours), parseInt(minutes), 0, 0);
            
            if (slotDateTime < new Date()) {
                td.innerHTML = '<span class="text-gray-400 text-xl">×</span>';
            } else {
                // 予約可能な場合は○を表示
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'time-slot w-8 h-8 rounded-full bg-green-500 text-white font-bold hover:bg-green-600';
                button.innerHTML = '○';
                button.dataset.date = date.toISOString().split('T')[0];
                button.dataset.time = slot;
                button.onclick = function() { selectTimeSlot(this); };
                td.appendChild(button);
            }
            
            tr.appendChild(td);
        });
        
        timeSlotsBody.appendChild(tr);
    });
}

function changeWeek(direction) {
    weekOffset += direction;
    renderWeekCalendar();
}

function selectTimeSlot(button) {
    // 既存の選択をクリア
    document.querySelectorAll('.time-slot').forEach(btn => {
        btn.classList.remove('selected', 'bg-blue-600', 'ring-4', 'ring-blue-300');
        btn.classList.add('bg-green-500');
        btn.innerHTML = '○';
    });
    
    // 新しい選択を適用
    button.classList.remove('bg-green-500');
    button.classList.add('selected', 'bg-blue-600', 'ring-4', 'ring-blue-300');
    button.innerHTML = '✓';
    
    selectedDate = button.dataset.date;
    selectedTime = button.dataset.time;
    
    // 選択した日時を表示
    const date = new Date(selectedDate + 'T' + selectedTime);
    const dateStr = date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
    });
    
    document.getElementById('selected-datetime').textContent = `${dateStr} ${selectedTime}`;
    document.getElementById('selected-info').classList.remove('hidden');
    
    // 確認ボタンを表示
    document.getElementById('confirm-section').classList.remove('hidden');
    
    // 選択した位置にスクロール
    button.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function clearSelection() {
    // 選択をクリア
    document.querySelectorAll('.time-slot').forEach(btn => {
        btn.classList.remove('selected', 'bg-blue-600', 'ring-4', 'ring-blue-300');
        btn.classList.add('bg-green-500');
        btn.innerHTML = '○';
    });
    
    selectedDate = null;
    selectedTime = null;
    
    // 選択情報を非表示
    document.getElementById('selected-info').classList.add('hidden');
    document.getElementById('confirm-section').classList.add('hidden');
}

async function confirmReservation() {
    if (!selectedDate || !selectedTime) {
        alert('日時を選択してください');
        return;
    }
    
    const customerData = JSON.parse(localStorage.getItem('customer_data') || '{}');
    const token = localStorage.getItem('customer_token');
    const isChangeMode = sessionStorage.getItem('isChangingReservation') === 'true';
    const changingReservation = isChangeMode ? JSON.parse(sessionStorage.getItem('changingReservation') || '{}') : null;
    
    console.log('Confirming reservation with:', {
        storeId,
        menuId,
        customerId,
        selectedDate,
        selectedTime,
        isChangeMode,
        token: token ? 'exists' : 'missing'
    });
    
    // 変更モードの場合
    if (isChangeMode && changingReservation) {
        if (!confirm(`予約を ${selectedDate} ${selectedTime} に変更しますか？`)) {
            return;
        }
        
        try {
            // まず既存の予約をキャンセル
            const cancelResponse = await fetch(`/api/customer/reservations/${changingReservation.id}/cancel`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    cancel_reason: '日程変更のため'
                })
            });
            
            const cancelData = await cancelResponse.json();
            
            if (!cancelResponse.ok) {
                // 24時間以内の場合
                if (cancelData.require_phone_contact) {
                    alert(`予約まで24時間以内のため、変更は店舗へ直接お電話ください。\n電話番号: ${cancelData.store_phone}`);
                    return;
                }
                throw new Error(cancelData.message || 'キャンセルに失敗しました');
            }
        } catch (error) {
            console.error('キャンセルエラー:', error);
            alert('予約の変更に失敗しました。');
            return;
        }
    }
    
    const reservationData = {
        store_id: storeId || null,
        menu_id: menuId || null,
        customer_id: customerId,
        reservation_date: selectedDate,
        start_time: selectedTime,
        first_name: customerData.first_name,
        last_name: customerData.last_name,
        first_name_kana: customerData.first_name_kana,
        last_name_kana: customerData.last_name_kana,
        phone: customerData.phone,
        email: customerData.email,
        is_subscription: true
    };
    
    try {
        const response = await fetch('/api/customer/reservations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(reservationData)
        });
        
        if (!response.ok) {
            const errorData = await response.text();
            console.error('予約作成エラー:', response.status, errorData);
            throw new Error('予約の作成に失敗しました');
        }
        
        const result = await response.json();
        
        // セッションストレージをクリア
        sessionStorage.removeItem('subscription_reservation');
        sessionStorage.removeItem('subscription_store_id');
        sessionStorage.removeItem('subscription_menu_id');
        sessionStorage.removeItem('subscription_menu_name');
        sessionStorage.removeItem('changingReservation');
        sessionStorage.removeItem('isChangingReservation');
        
        // 完了メッセージ
        if (isChangeMode) {
            alert('予約を変更しました！');
        } else {
            alert('予約が完了しました！');
        }
        
        // ダッシュボードへ戻る
        window.location.href = '/customer/dashboard';
        
    } catch (error) {
        console.error('予約エラー:', error);
        alert('予約の作成に失敗しました。もう一度お試しください。');
    }
}
</script>
@endsection