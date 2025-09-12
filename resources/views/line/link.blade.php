<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>LINE連携 - 目のトレーニング</title>
    
    <meta name="description" content="LINEアカウントと顧客情報を連携します">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('test/metore_logo.png') }}">
    <meta name="theme-color" content="#059669">
    
    <!-- TailwindCSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- LIFF SDK -->
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-green-50 to-green-100 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-6">
        <div class="max-w-md w-full space-y-8">
            <!-- ロゴ -->
            <div class="text-center">
                <img class="mx-auto h-16 w-auto" src="{{ asset('test/metore_logo.png') }}" alt="目のトレーニング">
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    LINE連携
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    LINEアカウントを連携して通知を受け取れます
                </p>
            </div>
            
            <!-- メイン画面 -->
            <div class="bg-white shadow-xl rounded-lg p-6">
                <!-- 初期化中 -->
                <div id="loading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto"></div>
                    <p class="mt-4 text-gray-600">初期化中...</p>
                </div>
                
                <!-- ログイン画面 -->
                <div id="login-screen" class="hidden text-center py-8">
                    <div class="mb-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">LINEログインが必要です</h3>
                        <p class="text-sm text-gray-600">
                            アカウント連携にはLINEでのログインが必要です
                        </p>
                    </div>
                    <button id="login-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        LINEでログイン
                    </button>
                </div>
                
                <!-- 連携画面 -->
                <div id="link-screen" class="hidden">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">アカウント連携</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            LINEアカウントと顧客情報を連携します
                        </p>
                        <div id="user-info" class="bg-gray-50 rounded-lg p-4 mb-4">
                            <p class="text-sm text-gray-600">連携するLINEアカウント:</p>
                            <p id="line-display-name" class="font-medium text-gray-900"></p>
                        </div>
                    </div>
                    
                    <button id="link-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        アカウントを連携する
                    </button>
                </div>
                
                <!-- 処理中画面 -->
                <div id="processing" class="hidden text-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto"></div>
                    <p class="mt-4 text-gray-600">連携処理中...</p>
                </div>
                
                <!-- 成功画面 -->
                <div id="success" class="hidden text-center py-8">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">連携完了!</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        LINEアカウントの連携が完了しました。<br>
                        予約の確認やリマインダーをLINEで受け取れます。
                    </p>
                    <button onclick="liff.closeWindow()" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        閉じる
                    </button>
                </div>
                
                <!-- エラー画面 -->
                <div id="error" class="hidden text-center py-8">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">エラーが発生しました</h3>
                    <p id="error-message" class="text-sm text-gray-600 mb-4">
                        連携処理中にエラーが発生しました。
                    </p>
                    <button onclick="location.reload()" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                        再試行
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // グローバル変数
        let customerToken = '';
        let liffInitialized = false;

        // URLパラメータからトークンを取得
        function getCustomerToken() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('token');
        }

        // 画面表示制御
        function showScreen(screenId) {
            const screens = ['loading', 'login-screen', 'link-screen', 'processing', 'success', 'error'];
            screens.forEach(id => {
                document.getElementById(id).classList.add('hidden');
            });
            document.getElementById(screenId).classList.remove('hidden');
        }

        // エラー表示
        function showError(message) {
            document.getElementById('error-message').textContent = message;
            showScreen('error');
        }

        // LIFF初期化
        async function initializeLiff() {
            try {
                const liffId = '{{ config("services.line.liff_id") }}';
                console.log('Initializing LIFF with ID:', liffId);
                
                await liff.init({
                    liffId: liffId
                });
                
                liffInitialized = true;
                console.log('LIFF initialized successfully');
                
                // ログイン状態チェック
                if (liff.isLoggedIn()) {
                    console.log('User is logged in');
                    await showLinkScreen();
                } else {
                    console.log('User not logged in');
                    showScreen('login-screen');
                }
                
            } catch (error) {
                console.error('LIFF initialization failed:', error);
                showError('LINE初期化エラー: ' + error.message);
            }
        }

        // 連携画面表示
        async function showLinkScreen() {
            try {
                const profile = await liff.getProfile();
                console.log('User profile:', profile);
                
                document.getElementById('line-display-name').textContent = profile.displayName;
                showScreen('link-screen');
                
            } catch (error) {
                console.error('Failed to get profile:', error);
                showError('プロフィール取得エラー: ' + error.message);
            }
        }

        // ログイン処理
        function handleLogin() {
            if (!liffInitialized) {
                showError('LIFFが初期化されていません');
                return;
            }
            
            try {
                liff.login();
            } catch (error) {
                console.error('Login failed:', error);
                showError('ログインエラー: ' + error.message);
            }
        }

        // 連携処理
        async function handleLink() {
            if (!liffInitialized) {
                showError('LIFFが初期化されていません');
                return;
            }
            
            try {
                showScreen('processing');
                
                // IDトークン取得
                const idToken = liff.getIDToken();
                if (!idToken) {
                    throw new Error('IDトークンが取得できません');
                }
                
                console.log('ID token obtained, sending link request...');
                
                // API呼び出し
                const response = await fetch('/api/line/link', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        id_token: idToken,
                        customer_token: customerToken
                    })
                });
                
                const data = await response.json();
                console.log('API response:', data);
                
                if (data.success) {
                    showScreen('success');
                } else {
                    throw new Error(data.error || '連携に失敗しました');
                }
                
            } catch (error) {
                console.error('Link failed:', error);
                showError('連携エラー: ' + error.message);
            }
        }

        // 初期化処理
        window.addEventListener('DOMContentLoaded', function() {
            customerToken = getCustomerToken();
            
            if (!customerToken) {
                showError('無効なアクセスです。正しいURLからアクセスしてください。');
                return;
            }
            
            console.log('Customer token:', customerToken);
            
            // イベントリスナー設定
            document.getElementById('login-btn').addEventListener('click', handleLogin);
            document.getElementById('link-btn').addEventListener('click', handleLink);
            
            // LIFF初期化
            initializeLiff();
        });
    </script>
</body>
</html>