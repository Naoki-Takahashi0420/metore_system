@extends('layouts.app')

@section('title', '新規会員登録')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                新規会員登録
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                お客様情報をご入力ください
            </p>
        </div>

        <!-- エラーメッセージ表示エリア -->
        <div id="error-message" class="hidden bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <span class="block sm:inline" id="error-text"></span>
        </div>

        <!-- 成功メッセージ表示エリア -->
        <div id="success-message" class="hidden bg-green-50 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            <span class="block sm:inline" id="success-text"></span>
        </div>

        <form id="register-form" class="mt-8 space-y-6">
            <div class="space-y-4">
                <!-- お名前 -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">
                            姓 <span class="text-red-500">*</span>
                        </label>
                        <input id="last_name" name="last_name" type="text" required
                            class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                            placeholder="山田">
                    </div>
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">
                            名 <span class="text-red-500">*</span>
                        </label>
                        <input id="first_name" name="first_name" type="text" required
                            class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                            placeholder="太郎">
                    </div>
                </div>

                <!-- フリガナ -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="last_name_kana" class="block text-sm font-medium text-gray-700">
                            セイ <span class="text-red-500">*</span>
                        </label>
                        <input id="last_name_kana" name="last_name_kana" type="text" required
                            class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                            placeholder="ヤマダ">
                    </div>
                    <div>
                        <label for="first_name_kana" class="block text-sm font-medium text-gray-700">
                            メイ <span class="text-red-500">*</span>
                        </label>
                        <input id="first_name_kana" name="first_name_kana" type="text" required
                            class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                            placeholder="タロウ">
                    </div>
                </div>

                <!-- 電話番号 -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">
                        電話番号 <span class="text-red-500">*</span>
                    </label>
                    <input id="phone" name="phone" type="tel" required
                        class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                        placeholder="090-1234-5678">
                    <p class="mt-1 text-xs text-gray-500">SMS認証に使用します</p>
                </div>

                <!-- メールアドレス -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        メールアドレス
                    </label>
                    <input id="email" name="email" type="email"
                        class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                        placeholder="example@email.com">
                    <p class="mt-1 text-xs text-gray-500">予約確認メールをお送りします（任意）</p>
                </div>

                <!-- 生年月日 -->
                <div>
                    <label for="birth_date" class="block text-sm font-medium text-gray-700">
                        生年月日
                    </label>
                    <input id="birth_date" name="birth_date" type="date"
                        class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm">
                </div>

                <!-- 性別 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        性別
                    </label>
                    <div class="mt-2 space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="male" class="form-radio text-primary-600">
                            <span class="ml-2">男性</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="female" class="form-radio text-primary-600">
                            <span class="ml-2">女性</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="other" class="form-radio text-primary-600">
                            <span class="ml-2">その他</span>
                        </label>
                    </div>
                </div>

                <!-- 住所 -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">
                        住所
                    </label>
                    <textarea id="address" name="address" rows="2"
                        class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                        placeholder="東京都渋谷区..."></textarea>
                </div>
            </div>

            <div>
                <button type="submit" id="submit-btn"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    登録して認証コードを受け取る
                </button>
            </div>

            <div class="text-center">
                <a href="/customer/login" class="text-sm text-primary-600 hover:text-primary-500">
                    既に会員の方はこちら
                </a>
            </div>
        </form>

        <!-- OTP入力フォーム（初期は非表示） -->
        <div id="otp-form" class="hidden mt-8 space-y-6">
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    SMS認証コードを送信しました
                </p>
                <p class="text-xs text-gray-500 mt-1" id="phone-display"></p>
            </div>

            <div>
                <label for="otp" class="block text-sm font-medium text-gray-700">
                    認証コード（6桁）
                </label>
                <input id="otp" name="otp" type="text" maxlength="6" pattern="[0-9]{6}" required
                    class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm text-center text-lg"
                    placeholder="000000">
            </div>

            <div>
                <button type="button" id="verify-btn"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    認証して登録を完了する
                </button>
            </div>

            <div class="text-center">
                <button type="button" id="resend-btn" class="text-sm text-primary-600 hover:text-primary-500">
                    認証コードを再送信
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('register-form');
    const otpForm = document.getElementById('otp-form');
    const submitBtn = document.getElementById('submit-btn');
    const verifyBtn = document.getElementById('verify-btn');
    const resendBtn = document.getElementById('resend-btn');
    
    let customerData = {};

    // 登録フォーム送信
    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(registerForm);
        customerData = {
            last_name: formData.get('last_name'),
            first_name: formData.get('first_name'),
            last_name_kana: formData.get('last_name_kana'),
            first_name_kana: formData.get('first_name_kana'),
            phone: formData.get('phone'),
            email: formData.get('email'),
            birth_date: formData.get('birth_date'),
            gender: formData.get('gender'),
            address: formData.get('address')
        };
        
        submitBtn.disabled = true;
        submitBtn.textContent = '送信中...';
        
        try {
            // OTP送信
            const response = await fetch('/api/auth/customer/send-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: customerData.phone
                })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                showSuccess('認証コードを送信しました');
                registerForm.classList.add('hidden');
                otpForm.classList.remove('hidden');
                document.getElementById('phone-display').textContent = `送信先: ${customerData.phone}`;
                document.getElementById('otp').focus();
            } else {
                showError(data.message || '認証コードの送信に失敗しました');
                submitBtn.disabled = false;
                submitBtn.textContent = '登録して認証コードを受け取る';
            }
        } catch (error) {
            showError('通信エラーが発生しました');
            submitBtn.disabled = false;
            submitBtn.textContent = '登録して認証コードを受け取る';
        }
    });

    // OTP認証
    verifyBtn.addEventListener('click', async function() {
        const otp = document.getElementById('otp').value;
        
        if (!otp || otp.length !== 6) {
            showError('6桁の認証コードを入力してください');
            return;
        }
        
        verifyBtn.disabled = true;
        verifyBtn.textContent = '認証中...';
        
        try {
            // OTP検証と登録
            const response = await fetch('/api/auth/customer/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ...customerData,
                    otp: otp
                })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                // 登録成功
                localStorage.setItem('customer_token', data.token);
                localStorage.setItem('customer_data', JSON.stringify(data.customer));
                
                showSuccess('登録が完了しました！予約画面へ移動します...');
                
                setTimeout(() => {
                    window.location.href = '/reservation';
                }, 2000);
            } else {
                showError(data.message || '認証に失敗しました');
                verifyBtn.disabled = false;
                verifyBtn.textContent = '認証して登録を完了する';
            }
        } catch (error) {
            showError('通信エラーが発生しました');
            verifyBtn.disabled = false;
            verifyBtn.textContent = '認証して登録を完了する';
        }
    });

    // 認証コード再送信
    resendBtn.addEventListener('click', async function() {
        resendBtn.disabled = true;
        resendBtn.textContent = '送信中...';
        
        try {
            const response = await fetch('/api/auth/customer/send-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: customerData.phone
                })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                showSuccess('認証コードを再送信しました');
            } else {
                showError(data.message || '再送信に失敗しました');
            }
        } catch (error) {
            showError('通信エラーが発生しました');
        } finally {
            resendBtn.disabled = false;
            resendBtn.textContent = '認証コードを再送信';
        }
    });

    // エラーメッセージ表示
    function showError(message) {
        const errorDiv = document.getElementById('error-message');
        const errorText = document.getElementById('error-text');
        errorText.textContent = message;
        errorDiv.classList.remove('hidden');
        setTimeout(() => {
            errorDiv.classList.add('hidden');
        }, 5000);
    }

    // 成功メッセージ表示
    function showSuccess(message) {
        const successDiv = document.getElementById('success-message');
        const successText = document.getElementById('success-text');
        successText.textContent = message;
        successDiv.classList.remove('hidden');
        setTimeout(() => {
            successDiv.classList.add('hidden');
        }, 5000);
    }
});
</script>
@endsection