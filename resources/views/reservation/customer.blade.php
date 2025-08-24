@extends('layouts.app')

@section('title', '顧客情報入力')

@section('content')
<div class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumb -->
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
                <a href="{{ url('/stores') }}" class="hover:text-primary-600">店舗選択</a>
                <span>></span>
                <a href="{{ url('/reservation/menu') }}" class="hover:text-primary-600">メニュー選択</a>
                <span>></span>
                <a href="{{ url('/reservation/datetime') }}" class="hover:text-primary-600">日時選択</a>
                <span>></span>
                <span class="text-gray-900">顧客情報入力</span>
            </div>
            
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">顧客情報を入力</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    予約に必要な情報を入力してください。携帯電話番号にはSMS認証コードが送信されます。
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Customer Form -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                <form id="customer-form">
                    <div class="space-y-6">
                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">基本情報</h3>
                            
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        姓 <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="last_name" 
                                        name="last_name" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="山田"
                                    >
                                </div>
                                
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        名 <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="first_name" 
                                        name="first_name" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="太郎"
                                    >
                                </div>
                            </div>
                            
                            <div class="grid md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label for="last_name_kana" class="block text-sm font-medium text-gray-700 mb-2">
                                        せい（ふりがな） <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="last_name_kana" 
                                        name="last_name_kana" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="やまだ"
                                    >
                                </div>
                                
                                <div>
                                    <label for="first_name_kana" class="block text-sm font-medium text-gray-700 mb-2">
                                        めい（ふりがな） <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="first_name_kana" 
                                        name="first_name_kana" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="たろう"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">連絡先</h3>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    携帯電話番号 <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="tel" 
                                        id="phone" 
                                        name="phone" 
                                        required
                                        pattern="[0-9\-]+"
                                        class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="090-1234-5678"
                                    >
                                    <div id="phone-check-loading" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-600"></div>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">予約確認の連絡に使用します。</p>
                                
                                <!-- Phone check result -->
                                <div id="phone-check-result" class="hidden mt-2"></div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    メールアドレス（任意）
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="example@email.com"
                                >
                                <p class="mt-1 text-sm text-gray-500">予約確認メールの送信に使用します。</p>
                            </div>
                        </div>

                        <!-- Personal Details -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">詳細情報</h3>
                            
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-2">
                                        生年月日
                                    </label>
                                    <input 
                                        type="date" 
                                        id="birth_date" 
                                        name="birth_date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    >
                                </div>
                                
                                <div>
                                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                                        性別
                                    </label>
                                    <select 
                                        id="gender" 
                                        name="gender"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    >
                                        <option value="">選択してください</option>
                                        <option value="male">男性</option>
                                        <option value="female">女性</option>
                                        <option value="other">その他</option>
                                        <option value="prefer_not_to_say">回答しない</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Medical Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">健康・視力に関する情報</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="vision_concerns" class="block text-sm font-medium text-gray-700 mb-2">
                                        現在の視力に関するお悩み
                                    </label>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="vision_concerns" value="近視" class="mr-2 text-primary-600 focus:ring-primary-500">
                                            <span class="text-sm text-gray-700">近視</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="vision_concerns" value="遠視" class="mr-2 text-primary-600 focus:ring-primary-500">
                                            <span class="text-sm text-gray-700">遠視</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="vision_concerns" value="乱視" class="mr-2 text-primary-600 focus:ring-primary-500">
                                            <span class="text-sm text-gray-700">乱視</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="vision_concerns" value="眼精疲労" class="mr-2 text-primary-600 focus:ring-primary-500">
                                            <span class="text-sm text-gray-700">眼精疲労</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="vision_concerns" value="ドライアイ" class="mr-2 text-primary-600 focus:ring-primary-500">
                                            <span class="text-sm text-gray-700">ドライアイ</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="medical_history" class="block text-sm font-medium text-gray-700 mb-2">
                                        眼科系の既往歴・現在治療中の疾患
                                    </label>
                                    <textarea 
                                        id="medical_history" 
                                        name="medical_history" 
                                        rows="3"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="緑内障、白内障、網膜剥離等がございましたらご記入ください"
                                    ></textarea>
                                </div>
                                
                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        その他ご要望・備考
                                    </label>
                                    <textarea 
                                        id="notes" 
                                        name="notes" 
                                        rows="3"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                        placeholder="ご質問やご要望がございましたらご記入ください"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Agreement -->
                        <div>
                            <div class="flex items-start">
                                <input 
                                    type="checkbox" 
                                    id="agree_terms" 
                                    name="agree_terms" 
                                    required
                                    class="mt-1 mr-3 text-primary-600 focus:ring-primary-500"
                                >
                                <label for="agree_terms" class="text-sm text-gray-700">
                                    <span class="text-red-500">*</span>
                                    <a href="#" class="text-primary-600 hover:underline">利用規約</a>及び
                                    <a href="#" class="text-primary-600 hover:underline">プライバシーポリシー</a>
                                    に同意します
                                </label>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            id="submit-button"
                            class="w-full bg-primary-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            予約確認へ進む
                        </button>
                    </div>
                </form>
            </div>

            <!-- Reservation Summary -->
            <div class="bg-white rounded-lg shadow-md p-6 h-fit">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">予約内容</h3>
                
                <div id="booking-summary" class="space-y-3 text-sm">
                    <!-- Summary will be populated here -->
                </div>
                
                <div class="border-t mt-4 pt-4">
                    <div class="flex justify-between items-center text-lg font-semibold">
                        <span>合計金額:</span>
                        <span id="total-price" class="text-primary-600"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadBookingSummary();
    setupForm();
});

function loadBookingSummary() {
    const selectedStore = JSON.parse(sessionStorage.getItem('selectedStore') || '{}');
    const selectedMenu = JSON.parse(sessionStorage.getItem('selectedMenu') || '{}');
    const selectedDateTime = JSON.parse(sessionStorage.getItem('selectedDateTime') || '{}');
    
    if (!selectedStore.id || !selectedMenu.id || !selectedDateTime.date) {
        window.location.href = '/stores';
        return;
    }
    
    const summary = document.getElementById('booking-summary');
    const dateTime = new Date(selectedDateTime.date).toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
    }) + ' ' + selectedDateTime.time;
    
    summary.innerHTML = `
        <div class="flex justify-between">
            <span class="text-gray-600">店舗:</span>
            <span class="font-medium">${selectedStore.name}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-600">メニュー:</span>
            <span class="font-medium">${selectedMenu.name}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-600">日時:</span>
            <span class="font-medium">${dateTime}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-600">所要時間:</span>
            <span class="font-medium">${selectedMenu.duration}分</span>
        </div>
    `;
    
    document.getElementById('total-price').textContent = '¥' + selectedMenu.price.toLocaleString();
}

function setupForm() {
    const form = document.getElementById('customer-form');
    const phoneInput = document.getElementById('phone');
    let phoneCheckTimer;
    
    // Phone number change handler
    phoneInput.addEventListener('input', function() {
        clearTimeout(phoneCheckTimer);
        const phone = this.value.trim();
        
        if (phone.length >= 10) {
            phoneCheckTimer = setTimeout(() => checkPhoneNumber(phone), 800);
        } else {
            hidePhoneCheckResult();
        }
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const phone = formData.get('phone');
        
        if (!phone) {
            window.dispatchEvent(new CustomEvent('show-toast', {
                detail: {
                    message: '携帯電話番号を入力してください',
                    type: 'error'
                }
            }));
            return;
        }
        
        // Store customer data
        const customerData = {
            last_name: formData.get('last_name'),
            first_name: formData.get('first_name'),
            last_name_kana: formData.get('last_name_kana'),
            first_name_kana: formData.get('first_name_kana'),
            phone: phone,
            email: formData.get('email'),
            birth_date: formData.get('birth_date'),
            gender: formData.get('gender'),
            vision_concerns: Array.from(document.querySelectorAll('input[name="vision_concerns"]:checked')).map(cb => cb.value),
            medical_history: formData.get('medical_history'),
            notes: formData.get('notes')
        };
        
        sessionStorage.setItem('customerData', JSON.stringify(customerData));
        
        // Redirect to confirmation page
        window.location.href = '/reservation/confirm';
    });
}

async function checkPhoneNumber(phone) {
    const loading = document.getElementById('phone-check-loading');
    const resultDiv = document.getElementById('phone-check-result');
    
    loading.classList.remove('hidden');
    
    try {
        const response = await fetch('/api/customer/check-phone', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ phone })
        });
        
        const data = await response.json();
        
        if (data.has_pending_reservations && data.is_returning_customer) {
            // 2回目以降の顧客（既存予約あり）
            showPhoneCheckResult('error', `
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-orange-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <div>
                            <h4 class="font-semibold text-orange-800">2回目以降のお客様です</h4>
                            <p class="text-sm text-orange-700 mt-1">${data.message}</p>
                            <div class="mt-2">
                                <p class="text-sm font-medium text-orange-800">現在のご予約:</p>
                                ${data.future_reservations.map(res => `
                                    <div class="text-sm text-orange-700 ml-2">
                                        • ${formatReservationDate(res.reservation_date)} ${res.start_time} - ${res.menu_name}
                                    </div>
                                `).join('')}
                            </div>
                            <div class="mt-3 flex flex-col gap-2">
                                <a href="/admin" class="text-center bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">
                                    マイページで予約を管理
                                </a>
                                <p class="text-xs text-orange-600 text-center">
                                    ※2回目以降のお客様は、マイページから予約の変更・追加を行ってください
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            // Disable form submission
            document.getElementById('customer-form').querySelector('button[type="submit"]').disabled = true;
        } else if (data.has_pending_reservations && data.block_reservation) {
            // 今後の予約はあるが、当日ではない場合（情報表示のみ）
            fillCustomerData(data.customer);
            showPhoneCheckResult('info', `
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1">
                            <h4 class="font-semibold text-blue-800">今後のご予約があります</h4>
                            <p class="text-sm text-blue-700 mt-1">お客様情報を自動入力しました。新規予約も可能です。</p>
                            <div class="mt-2">
                                <p class="text-sm font-medium text-blue-800">予定されている予約:</p>
                                ${data.future_reservations.slice(0, 3).map(res => `
                                    <div class="text-sm text-blue-700 ml-2">
                                        • ${formatReservationDate(res.reservation_date)} - ${res.menu_name}
                                    </div>
                                `).join('')}
                                ${data.future_reservations.length > 3 ? `
                                    <div class="text-sm text-blue-700 ml-2">
                                        他${data.future_reservations.length - 3}件
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `);
        } else if (data.exists) {
            // Fill existing customer data
            fillCustomerData(data.customer);
            showPhoneCheckResult('success', `
                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="text-sm text-green-800">2回目以降のお客様情報を自動入力しました</span>
                    </div>
                </div>
            `);
        } else {
            showPhoneCheckResult('info', `
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm text-blue-800">新規のお客様です。情報をご入力ください</span>
                    </div>
                </div>
            `);
        }
    } catch (error) {
        console.error('Phone check error:', error);
        showPhoneCheckResult('error', `
            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                <span class="text-sm text-red-800">電話番号の確認に失敗しました</span>
            </div>
        `);
    }
    
    loading.classList.add('hidden');
}

function showPhoneCheckResult(type, content) {
    const resultDiv = document.getElementById('phone-check-result');
    resultDiv.innerHTML = content;
    resultDiv.classList.remove('hidden');
}

function hidePhoneCheckResult() {
    const resultDiv = document.getElementById('phone-check-result');
    resultDiv.classList.add('hidden');
    document.getElementById('customer-form').querySelector('button[type="submit"]').disabled = false;
}

function continueAsNewCustomer() {
    // 電話番号をクリア
    document.getElementById('phone').value = '';
    
    // エラーメッセージを非表示
    hidePhoneCheckResult();
    
    // フォームを再度有効化
    document.getElementById('customer-form').querySelector('button[type="submit"]').disabled = false;
    
    // 電話番号フィールドにフォーカス
    document.getElementById('phone').focus();
    
    // 既存の顧客情報をクリア
    document.getElementById('last_name').value = '';
    document.getElementById('first_name').value = '';
    document.getElementById('last_name_kana').value = '';
    document.getElementById('first_name_kana').value = '';
    document.getElementById('email').value = '';
}

function fillCustomerData(customer) {
    if (customer.last_name) document.getElementById('last_name').value = customer.last_name;
    if (customer.first_name) document.getElementById('first_name').value = customer.first_name;
    if (customer.last_name_kana) document.getElementById('last_name_kana').value = customer.last_name_kana;
    if (customer.first_name_kana) document.getElementById('first_name_kana').value = customer.first_name_kana;
    if (customer.email) document.getElementById('email').value = customer.email;
}

function continueAsNewCustomer() {
    document.getElementById('phone').value = '';
    hidePhoneCheckResult();
}

function formatReservationDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        month: 'short',
        day: 'numeric'
    });
}

</script>
@endsection