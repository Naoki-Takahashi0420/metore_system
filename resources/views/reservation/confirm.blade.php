@extends('layouts.app')

@section('title', '予約確認')

@section('content')
<div class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">予約内容の確認</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    以下の内容で予約を確定します。内容をご確認の上、「予約を確定する」ボタンを押してください。
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Reservation Details -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">予約詳細</h2>
            
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Service Details -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">サービス情報</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">店舗:</span>
                            <span id="store-name" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">メニュー:</span>
                            <span id="menu-name" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">予約日時:</span>
                            <span id="datetime" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">所要時間:</span>
                            <span id="duration" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start border-t pt-3">
                            <span class="text-gray-900 font-semibold">料金:</span>
                            <span id="price" class="text-xl font-bold text-primary-600"></span>
                        </div>
                    </div>
                </div>

                <!-- Customer Details -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">お客様情報</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">お名前:</span>
                            <span id="customer-name" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">ふりがな:</span>
                            <span id="customer-kana" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">携帯電話:</span>
                            <span id="customer-phone" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-gray-600">メール:</span>
                            <span id="customer-email" class="font-medium text-right"></span>
                        </div>
                        <div class="flex justify-between items-start" id="customer-concerns-row">
                            <span class="text-gray-600">視力のお悩み:</span>
                            <span id="customer-concerns" class="font-medium text-right"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h3 class="flex items-center text-blue-900 font-semibold mb-3">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                ご来店前の注意事項
            </h3>
            <div class="text-blue-800 space-y-2 text-sm">
                <p>• ご予約時間の10分前にお越しください。</p>
                <p>• コンタクトレンズをご使用の場合は、取り外しが可能な状態でお越しください。</p>
                <p>• トレーニング中に体調不良を感じた場合は、すぐにスタッフにお申し付けください。</p>
                <p>• キャンセルの場合は、予約日時の24時間前までにご連絡をお願いいたします。</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4">
            <button 
                onclick="goBack()" 
                class="flex-1 bg-gray-300 text-gray-700 py-3 px-6 rounded-lg font-semibold hover:bg-gray-400 transition-colors"
            >
                内容を修正する
            </button>
            <button 
                id="confirm-button"
                class="flex-1 bg-primary-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span id="button-text">予約を確定する</span>
                <span id="button-loading" class="hidden">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    処理中...
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="success-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 text-center">
        <div class="text-green-600 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        
        <h3 class="text-xl font-semibold text-gray-900 mb-4">予約が完了しました！</h3>
        
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <p class="text-sm text-gray-600 mb-2">予約番号</p>
            <p id="reservation-number" class="text-lg font-bold text-primary-600"></p>
        </div>
        
        <p class="text-sm text-gray-600 mb-6">
            予約確認メールを送信いたします。<br>
            ご不明な点がございましたら、お気軽にお問い合わせください。
        </p>
        
        <button 
            onclick="goToHome()" 
            class="w-full bg-primary-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-primary-700 transition-colors"
        >
            ホームに戻る
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadReservationData();
    setupConfirmButton();
});

function loadReservationData() {
    const selectedStore = JSON.parse(sessionStorage.getItem('selectedStore') || '{}');
    const selectedMenu = JSON.parse(sessionStorage.getItem('selectedMenu') || '{}');
    const selectedDateTime = JSON.parse(sessionStorage.getItem('selectedDateTime') || '{}');
    const customerData = JSON.parse(sessionStorage.getItem('customerData') || '{}');
    
    if (!selectedStore.id || !selectedMenu.id || !selectedDateTime.date || !customerData.phone) {
        window.location.href = '/stores';
        return;
    }
    
    // Populate service information
    document.getElementById('store-name').textContent = selectedStore.name;
    document.getElementById('menu-name').textContent = selectedMenu.name;
    document.getElementById('duration').textContent = selectedMenu.duration + '分';
    document.getElementById('price').innerHTML = '<span class="text-sm">¥</span>' + selectedMenu.price.toLocaleString();
    
    // Format and display date/time
    const dateTime = new Date(selectedDateTime.date).toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
    }) + ' ' + selectedDateTime.time;
    document.getElementById('datetime').textContent = dateTime;
    
    // Populate customer information
    document.getElementById('customer-name').textContent = 
        customerData.last_name + ' ' + customerData.first_name;
    document.getElementById('customer-kana').textContent = 
        customerData.last_name_kana + ' ' + customerData.first_name_kana;
    document.getElementById('customer-phone').textContent = customerData.phone;
    document.getElementById('customer-email').textContent = customerData.email || '未入力';
    
    // Display vision concerns
    const visionConcerns = customerData.vision_concerns;
    const concernsRow = document.getElementById('customer-concerns-row');
    if (visionConcerns && visionConcerns.length > 0) {
        document.getElementById('customer-concerns').textContent = visionConcerns.join(', ');
    } else {
        concernsRow.style.display = 'none';
    }
}

function setupConfirmButton() {
    const confirmButton = document.getElementById('confirm-button');
    const buttonText = document.getElementById('button-text');
    const buttonLoading = document.getElementById('button-loading');
    
    confirmButton.addEventListener('click', async function() {
        if (confirmButton.disabled) return;
        
        confirmButton.disabled = true;
        buttonText.classList.add('hidden');
        buttonLoading.classList.remove('hidden');
        
        try {
            const reservationData = {
                store: JSON.parse(sessionStorage.getItem('selectedStore') || '{}'),
                menu: JSON.parse(sessionStorage.getItem('selectedMenu') || '{}'),
                datetime: JSON.parse(sessionStorage.getItem('selectedDateTime') || '{}'),
                customer: JSON.parse(sessionStorage.getItem('customerData') || '{}')
            };
            
            // Format date to YYYY-MM-DD
            const reservationDate = new Date(reservationData.datetime.date);
            const formattedDate = reservationDate.toISOString().split('T')[0];
            
            const requestData = {
                store_id: reservationData.store.id,
                menu_id: reservationData.menu.id,
                reservation_date: formattedDate,
                start_time: reservationData.datetime.time + ':00',
                guest_count: 1,
                notes: reservationData.customer.notes || '',
                customer_data: reservationData.customer
            };
            
            console.log('Sending reservation data:', requestData);
            
            const response = await fetch('/api/reservations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(requestData)
            });
            
            if (response.ok) {
                const result = await response.json();
                showSuccessModal(result.data.reservation_number || generateReservationNumber());
                
                // Clear session data
                sessionStorage.removeItem('selectedStore');
                sessionStorage.removeItem('selectedMenu');
                sessionStorage.removeItem('selectedDateTime');
                sessionStorage.removeItem('customerData');
                
            } else {
                const errorData = await response.json();
                console.error('Reservation creation failed:', errorData);
                
                // 既存予約エラーの場合は特別な処理
                if (errorData.error && errorData.existing_reservation) {
                    const existingRes = errorData.existing_reservation;
                    const message = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h3 class="font-bold text-red-800 mb-2">既存の予約があります</h3>
                            <p class="text-red-700 mb-3">${errorData.message}</p>
                            <div class="bg-white rounded p-3 mb-3">
                                <p class="text-sm text-gray-700">
                                    <strong>予約番号:</strong> ${existingRes.reservation_number}<br>
                                    <strong>日時:</strong> ${existingRes.reservation_date} ${existingRes.start_time}<br>
                                    <strong>店舗:</strong> ${existingRes.store_name}<br>
                                    <strong>メニュー:</strong> ${existingRes.menu_name}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <a href="/admin" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                    予約を変更・確認する
                                </a>
                                <button onclick="location.href='/'" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                                    トップに戻る
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // エラーメッセージをモーダルで表示
                    showErrorModal(message);
                } else {
                    throw new Error(errorData.message || '予約の作成に失敗しました');
                }
            }
            
        } catch (error) {
            console.error('Error creating reservation:', error);
            
            window.dispatchEvent(new CustomEvent('show-toast', {
                detail: {
                    message: '予約の確定に失敗しました。もう一度お試しください。',
                    type: 'error'
                }
            }));
            
            confirmButton.disabled = false;
            buttonText.classList.remove('hidden');
            buttonLoading.classList.add('hidden');
        }
    });
}

function generateReservationNumber() {
    const now = new Date();
    const year = now.getFullYear().toString().slice(-2);
    const month = (now.getMonth() + 1).toString().padStart(2, '0');
    const day = now.getDate().toString().padStart(2, '0');
    const random = Math.floor(Math.random() * 9999).toString().padStart(4, '0');
    
    return `XS${year}${month}${day}${random}`;
}

function showSuccessModal(reservationNumber) {
    document.getElementById('reservation-number').textContent = reservationNumber;
    document.getElementById('success-modal').classList.remove('hidden');
    document.getElementById('success-modal').classList.add('flex');
}

function goBack() {
    window.location.href = '/reservation/customer';
}

function goToHome() {
    window.location.href = '/';
}

function showErrorModal(message) {
    // エラーモーダルを作成
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
    modal.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                ${message}
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // モーダル外クリックで閉じる
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
</script>
@endsection