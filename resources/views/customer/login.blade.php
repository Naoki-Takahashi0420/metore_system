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
        
        <div class="flex space-x-2 mb-4">
            <input type="text" id="otp1" maxlength="1" class="w-12 h-12 text-center text-xl border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <input type="text" id="otp2" maxlength="1" class="w-12 h-12 text-center text-xl border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <input type="text" id="otp3" maxlength="1" class="w-12 h-12 text-center text-xl border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <input type="text" id="otp4" maxlength="1" class="w-12 h-12 text-center text-xl border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <input type="text" id="otp5" maxlength="1" class="w-12 h-12 text-center text-xl border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <input type="text" id="otp6" maxlength="1" class="w-12 h-12 text-center text-xl border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
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
    const form = document.getElementById('login-form');
    const otpModal = document.getElementById('otp-modal');
    const verifyButton = document.getElementById('verify-otp');
    const resendButton = document.getElementById('resend-otp');
    const closeModalButton = document.getElementById('close-modal');
    const otpError = document.getElementById('otp-error');
    
    // OTP入力フィールドを個別に取得（確実にするため）
    const otp1 = document.getElementById('otp1');
    const otp2 = document.getElementById('otp2');
    const otp3 = document.getElementById('otp3');
    const otp4 = document.getElementById('otp4');
    const otp5 = document.getElementById('otp5');
    const otp6 = document.getElementById('otp6');
    const otpInputs = [otp1, otp2, otp3, otp4, otp5, otp6];
    
    let currentPhone = '';
    
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
    
    // OTP input handling - 改善版
    otpInputs.forEach((input, index) => {
        // 入力制限と自動移動
        input.addEventListener('keyup', function(e) {
            const value = e.target.value;
            
            // 数字以外を削除
            if (value && !/^\d$/.test(value)) {
                e.target.value = value.replace(/[^\d]/g, '').slice(0, 1);
            }
            
            // 数字が入力されたら次のフィールドへ
            if (e.target.value.length === 1) {
                if (index < otpInputs.length - 1) {
                    // 次のフィールドにフォーカス
                    setTimeout(() => {
                        otpInputs[index + 1].focus();
                    }, 10);
                } else {
                    // 最後のフィールドの場合、全体をチェック
                    const otp = otpInputs.map(inp => inp.value).join('');
                    if (otp.length === 6) {
                        // 自動で認証ボタンをクリック（オプション）
                        // verifyButton.click();
                    }
                }
            }
        });
        
        // バックスペース処理
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                if (!e.target.value && index > 0) {
                    // 前のフィールドに移動
                    e.preventDefault();
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                }
            }
            // 数字キー以外の入力を防ぐ（タブ、バックスペース、削除キーは許可）
            else if (!/^\d$/.test(e.key) && !['Tab', 'Delete', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                e.preventDefault();
            }
        });
        
        // ペースト対応
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text');
            const digits = pastedData.replace(/\D/g, '').slice(0, 6);
            
            // 各フィールドに1文字ずつ設定
            digits.split('').forEach((digit, i) => {
                if (otpInputs[i]) {
                    otpInputs[i].value = digit;
                }
            });
            
            // 最後に入力されたフィールドの次、または最後のフィールドにフォーカス
            const nextIndex = Math.min(digits.length, otpInputs.length - 1);
            otpInputs[nextIndex].focus();
        });
        
        // フォーカス時の処理
        input.addEventListener('focus', function() {
            // フィールドが既に埋まっている場合は選択状態にする
            if (this.value) {
                this.select();
            }
        });
        
        // 値の変更を監視（プログラムによる変更も含む）
        input.addEventListener('input', function(e) {
            // 複数文字が入力された場合、最初の1文字のみ残す
            if (e.target.value.length > 1) {
                e.target.value = e.target.value[0];
            }
        });
    });
    
    // Verify OTP
    verifyButton.addEventListener('click', async function() {
        const otp = Array.from(otpInputs).map(input => input.value).join('');
        if (otp.length !== 6) {
            otpError.classList.remove('hidden');
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
                    otp_code: otp
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                if (data.data.is_new_customer) {
                    // New customer - needs registration
                    sessionStorage.setItem('temp_token', data.data.temp_token);
                    window.location.href = '/customer/register';
                } else {
                    // Existing customer - save token and redirect
                    localStorage.setItem('customer_token', data.data.token);
                    localStorage.setItem('customer_data', JSON.stringify(data.data.customer));
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
                
                // Clear inputs
                otpInputs.forEach(input => input.value = '');
                otpInputs[0].focus();
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
        otpInputs.forEach(input => input.value = '');
        otpError.classList.add('hidden');
    });
    
    function showOTPModal(phone) {
        document.getElementById('phone-display').textContent = phone;
        otpModal.classList.remove('hidden');
        otpModal.classList.add('flex');
        otpInputs[0].focus();
    }
    
    function hideOTPModal() {
        otpModal.classList.remove('flex');
        otpModal.classList.add('hidden');
        otpInputs.forEach(input => input.value = '');
        otpError.classList.add('hidden');
    }
    
    function showNewCustomerModal() {
        const newCustomerModal = document.getElementById('new-customer-modal');
        newCustomerModal.classList.remove('hidden');
        newCustomerModal.classList.add('flex');
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