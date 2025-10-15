@extends('layouts.app')

@section('title', 'ログイン')

@section('content')
<div class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-6 text-center">予約確認・ログイン</h1>
            
            <div class="mb-8">
                <p class="text-sm text-gray-600 text-center">
                    携帯電話番号でログインして、予約履歴やカルテをご確認いただけます。
                </p>
            </div>

            <form id="login-form">
                <div class="mb-6">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        携帯電話番号
                    </label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        required
                        pattern="[0-9]{10,11}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="09012345678"
                    >
                    <p class="mt-1 text-sm text-gray-500">ハイフンなしで入力してください。</p>
                </div>

                <button 
                    type="submit" 
                    id="send-otp-button"
                    class="w-full bg-primary-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    SMS認証コードを送信
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-600 text-center">
                    まだ予約をされていない方は
                </p>
                <a href="{{ url('/reservation') }}" class="block text-center mt-2 text-primary-600 hover:underline">
                    新規予約はこちら
                </a>
            </div>
        </div>
    </div>
</div>

<!-- OTP Modal -->
<div id="otp-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">SMS認証</h3>
        
        <p class="text-sm text-gray-600 mb-4">
            <span id="phone-display"></span> に送信された6桁の認証コードを入力してください。
        </p>
        
        @if(config('app.env') === 'local' && request()->server('HTTP_HOST') === '127.0.0.1:8000')
        <div class="mb-2">
            <p class="text-xs text-gray-500 text-center">
                開発環境: 認証コードは <span class="font-mono font-bold">123456</span> です
            </p>
        </div>
        @endif
        
        <div class="mb-4">
            <label for="otp-input" class="block text-sm font-medium text-gray-700 mb-2">
                認証コード（6桁）
            </label>
            <input
                type="text"
                id="otp-input"
                maxlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                class="w-full px-3 py-3 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="123456"
                autocomplete="one-time-code"
            >
            <p class="mt-1 text-sm text-gray-500">コピー&ペーストで入力できます</p>
        </div>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" id="remember-me" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                <span class="ml-2 text-sm text-gray-600">ログイン状態を保持する（30日間）</span>
            </label>
        </div>

        <div class="flex space-x-3">
            <button id="verify-otp" class="flex-1 bg-primary-600 text-white py-2 px-4 rounded hover:bg-primary-700 transition-colors">
                認証
            </button>
            <button id="resend-otp" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400 transition-colors">
                再送信
            </button>
        </div>
        
        <div id="otp-error" class="hidden mt-3 p-3 bg-red-50 text-red-800 text-sm rounded">
            認証コードが正しくありません。もう一度お試しください。
        </div>
        
        <button id="close-modal" class="mt-4 w-full text-gray-500 text-sm hover:text-gray-700">
            キャンセル
        </button>
    </div>
</div>

<!-- Store Selection Modal -->
<div id="store-selection-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">店舗を選択してください</h3>
            <p class="text-sm text-gray-600">
                複数の店舗でご利用いただいています。ログインする店舗を選択してください。
            </p>
        </div>

        <div id="store-list" class="space-y-3 mb-6">
            <!-- Stores will be populated here by JavaScript -->
        </div>

        <button id="close-store-selection-modal" class="w-full text-gray-500 text-sm hover:text-gray-700">
            キャンセル
        </button>
    </div>
</div>

<!-- New Customer Modal -->
<div id="new-customer-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">初回のお客様へ</h3>
            <p class="text-sm text-gray-600 mb-6">
                予約履歴が見つかりません。<br>
                初回のお客様は新規予約からお申し込みください。
            </p>
            <div class="flex flex-col space-y-3">
                <button id="go-to-booking" class="w-full bg-primary-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                    新規予約に進む
                </button>
                <button id="close-new-customer-modal" class="w-full text-gray-500 text-sm hover:text-gray-700">
                    閉じる
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 既存のトークンをチェック
    const existingToken = localStorage.getItem('customer_token');
    const tokenExpiry = localStorage.getItem('token_expiry');

    if (existingToken && tokenExpiry) {
        const expiryDate = new Date(tokenExpiry);
        const now = new Date();

        // トークンが有効期限内の場合、ダッシュボードへリダイレクト
        if (expiryDate > now) {
            console.log('Valid token found, redirecting to dashboard');
            window.location.href = '/customer/dashboard';
            return;
        } else {
            // 期限切れの場合はクリア
            console.log('Token expired, clearing localStorage');
            localStorage.removeItem('customer_token');
            localStorage.removeItem('customer_data');
            localStorage.removeItem('token_expiry');
            localStorage.removeItem('remember_me');
        }
    }

    const form = document.getElementById('login-form');
    const otpModal = document.getElementById('otp-modal');
    const verifyButton = document.getElementById('verify-otp');
    const resendButton = document.getElementById('resend-otp');
    const closeModalButton = document.getElementById('close-modal');
    const otpError = document.getElementById('otp-error');
    
    // OTP入力フィールド（新しい統一フィールド）
    const otpInput = document.getElementById('otp-input');
    
    let currentPhone = '';
    let currentTempToken = '';
    let currentStores = [];

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const phone = document.getElementById('phone').value;
        if (!phone) return;
        
        currentPhone = phone;
        
        try {
            const response = await fetch('/api/auth/customer/send-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ phone: phone })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                showOTPModal(phone);
                
                // Show debug info in development
                if (data.debug) {
                    console.log('Debug OTP:', data.debug.otp_code);
                }
            } else {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: {
                        message: data.message || 'SMS送信に失敗しました',
                        type: 'error'
                    }
                }));
            }
        } catch (error) {
            console.error('Error sending OTP:', error);
            window.dispatchEvent(new CustomEvent('show-toast', {
                detail: {
                    message: 'エラーが発生しました',
                    type: 'error'
                }
            }));
        }
    });
    
    // OTP入力フィールドの処理（新しいコピペ対応版）
    if (otpInput) {
        // 数字のみ入力許可
        otpInput.addEventListener('input', function(e) {
            // 数字以外を削除し、6桁に制限
            e.target.value = e.target.value.replace(/[^\d]/g, '').slice(0, 6);
        });

        // キーダウンイベント（数字以外の入力を防ぐ）
        otpInput.addEventListener('keydown', function(e) {
            // 数字、バックスペース、削除、矢印キー、タブは許可
            if (!/^\d$/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) {
                e.preventDefault();
            }
        });

        // ペースト対応
        otpInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text');
            const digits = pastedData.replace(/\D/g, '').slice(0, 6);
            e.target.value = digits;
        });

        // フォーカス時に全選択
        otpInput.addEventListener('focus', function() {
            this.select();
        });

        // 6桁入力完了時の自動認証（オプション）
        otpInput.addEventListener('input', function(e) {
            if (e.target.value.length === 6) {
                // 自動認証は無効化（ユーザーが明示的にボタンを押す方が安全）
                // verifyButton.click();
            }
        });
    }
    
    // Verify OTP
    verifyButton.addEventListener('click', async function() {
        const otp = otpInput.value;
        const rememberMe = document.getElementById('remember-me').checked;

        if (otp.length !== 6) {
            otpError.classList.remove('hidden');
            otpError.textContent = '6桁の認証コードを入力してください';
            return;
        }
        
        try {
            const response = await fetch('/api/auth/customer/verify-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    phone: currentPhone,
                    otp_code: otp,
                    remember_me: rememberMe
                })
            });
            
            const data = await response.json();

            if (response.ok && data.success) {
                // 複数店舗がある場合は店舗選択画面へ
                if (data.data.requires_store_selection) {
                    currentTempToken = data.data.temp_token;
                    currentStores = data.data.stores;
                    hideOTPModal();
                    showStoreSelectionModal(data.data.stores);
                } else if (data.data.is_new_customer) {
                    // New customer - needs registration
                    sessionStorage.setItem('temp_token', data.data.temp_token);
                    window.location.href = '/customer/register';
                } else {
                    // Existing customer - save token and redirect
                    localStorage.setItem('customer_token', data.data.token);
                    localStorage.setItem('customer_data', JSON.stringify(data.data.customer));

                    // Remember Me設定を保存
                    if (rememberMe) {
                        localStorage.setItem('remember_me', 'true');
                        localStorage.setItem('token_expiry', new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString());
                    } else {
                        localStorage.setItem('remember_me', 'false');
                        localStorage.setItem('token_expiry', new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString());
                    }

                    console.log('Login successful, token saved:', data.data.token);
                    window.location.href = '/customer/dashboard';
                }
            } else {
                // Check for specific error codes
                if (data.error && data.error.code === 'NO_RESERVATION_HISTORY') {
                    hideOTPModal();
                    showNewCustomerModal();
                } else {
                    otpError.classList.remove('hidden');
                    otpError.textContent = data.error?.message || '認証に失敗しました';
                }
            }
        } catch (error) {
            console.error('Error verifying OTP:', error);
            otpError.classList.remove('hidden');
        }
    });
    
    // Resend OTP
    resendButton.addEventListener('click', async function() {
        try {
            const response = await fetch('/api/auth/customer/send-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ phone: currentPhone })
            });
            
            if (response.ok) {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: {
                        message: '認証コードを再送信しました',
                        type: 'success'
                    }
                }));
                
                // Clear input
                otpInput.value = '';
                otpInput.focus();
                otpError.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error resending OTP:', error);
        }
    });
    
    // Close modal
    closeModalButton.addEventListener('click', function() {
        otpModal.classList.remove('flex');
        otpModal.classList.add('hidden');
        otpInput.value = '';
        otpError.classList.add('hidden');
    });
    
    function showOTPModal(phone) {
        document.getElementById('phone-display').textContent = phone;
        otpModal.classList.remove('hidden');
        otpModal.classList.add('flex');
        otpInput.focus();
    }
    
    function hideOTPModal() {
        otpModal.classList.remove('flex');
        otpModal.classList.add('hidden');
        otpInput.value = '';
        otpError.classList.add('hidden');
    }
    
    function showNewCustomerModal() {
        const newCustomerModal = document.getElementById('new-customer-modal');
        newCustomerModal.classList.remove('hidden');
        newCustomerModal.classList.add('flex');
    }

    function showStoreSelectionModal(stores) {
        const modal = document.getElementById('store-selection-modal');
        const storeList = document.getElementById('store-list');

        // 店舗リストをクリア
        storeList.innerHTML = '';

        // 各店舗のボタンを作成
        stores.forEach(store => {
            const button = document.createElement('button');
            button.className = 'w-full bg-white border-2 border-gray-200 rounded-lg p-4 text-left hover:border-primary-500 hover:bg-primary-50 transition-colors';
            button.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900">${store.store_name}</p>
                        <p class="text-sm text-gray-500 mt-1">こちらの店舗でログイン</p>
                    </div>
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            `;
            button.addEventListener('click', () => selectStore(store.customer_id));
            storeList.appendChild(button);
        });

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function hideStoreSelectionModal() {
        const modal = document.getElementById('store-selection-modal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    async function selectStore(customerId) {
        try {
            const rememberMe = document.getElementById('remember-me').checked;

            const response = await fetch('/api/auth/customer/select-store', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    temp_token: currentTempToken,
                    customer_id: customerId
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // トークンとユーザー情報を保存
                localStorage.setItem('customer_token', data.data.token);
                localStorage.setItem('customer_data', JSON.stringify(data.data.customer));

                // Remember Me設定を保存
                if (rememberMe) {
                    localStorage.setItem('remember_me', 'true');
                    localStorage.setItem('token_expiry', new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString());
                } else {
                    localStorage.setItem('remember_me', 'false');
                    localStorage.setItem('token_expiry', new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString());
                }

                console.log('Store selected, token saved:', data.data.token);
                window.location.href = '/customer/dashboard';
            } else {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: {
                        message: data.error?.message || '店舗選択に失敗しました',
                        type: 'error'
                    }
                }));
            }
        } catch (error) {
            console.error('Error selecting store:', error);
            window.dispatchEvent(new CustomEvent('show-toast', {
                detail: {
                    message: 'エラーが発生しました',
                    type: 'error'
                }
            }));
        }
    }

    // Store selection modal event listeners
    const closeStoreSelectionModalButton = document.getElementById('close-store-selection-modal');
    if (closeStoreSelectionModalButton) {
        closeStoreSelectionModalButton.addEventListener('click', function() {
            hideStoreSelectionModal();
        });
    }

    // New customer modal event listeners
    const goToBookingButton = document.getElementById('go-to-booking');
    const closeNewCustomerModalButton = document.getElementById('close-new-customer-modal');

    if (goToBookingButton) {
        goToBookingButton.addEventListener('click', function() {
            window.location.href = '/reservation';
        });
    }

    if (closeNewCustomerModalButton) {
        closeNewCustomerModalButton.addEventListener('click', function() {
            const newCustomerModal = document.getElementById('new-customer-modal');
            newCustomerModal.classList.remove('flex');
            newCustomerModal.classList.add('hidden');
        });
    }
});
</script>
@endsection