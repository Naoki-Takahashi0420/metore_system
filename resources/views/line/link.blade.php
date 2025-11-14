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
    <script src="https://cdn.tailwindcss.com"></script>
    
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
                        <div id="reservation-info" class="bg-blue-50 rounded-lg p-4 mb-4">
                            <p class="text-sm text-gray-600">予約情報を読み込み中...</p>
                        </div>
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
                    <button id="close-btn" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
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

        // URLパラメータから予約番号を取得
        function getReservationNumber() {
            const urlParams = new URLSearchParams(window.location.search);

            // 1. 通常のreservationパラメータ
            let reservation = urlParams.get('reservation');
            if (reservation) {
                console.log('Found reservation in URL:', reservation);
                // sessionStorageに保存（LINE認証後も使えるように）
                sessionStorage.setItem('reservation_number', reservation);
                return reservation;
            }

            // 2. liff.stateパラメータから取得
            const liffState = urlParams.get('liff.state');
            if (liffState) {
                const decodedState = decodeURIComponent(liffState);
                console.log('Decoded liff.state:', decodedState);

                const match = decodedState.match(/reservation=([^&]+)/);
                if (match) {
                    console.log('Found reservation in liff.state:', match[1]);
                    // sessionStorageに保存
                    sessionStorage.setItem('reservation_number', match[1]);
                    return match[1];
                }
            }

            // 3. セッションストレージから取得（LIFF認証後のリダイレクト対策）
            const sessionReservation = sessionStorage.getItem('reservation_number');
            if (sessionReservation) {
                console.log('Found reservation in sessionStorage:', sessionReservation);
                return sessionReservation;
            }
            
            // 4. 初回アクセス時に予約番号をセッションストレージに保存
            const hash = window.location.hash;
            if (hash && hash.includes('reservation=')) {
                const match = hash.match(/reservation=([^&]+)/);
                if (match) {
                    sessionStorage.setItem('reservation_number', match[1]);
                    console.log('Saved reservation to sessionStorage:', match[1]);
                    return match[1];
                }
            }
            
            // 5. document.referrerから取得（最後の手段）
            if (document.referrer && document.referrer.includes('reservation=')) {
                const match = document.referrer.match(/reservation=([^&]+)/);
                if (match) {
                    sessionStorage.setItem('reservation_number', match[1]);
                    console.log('Found reservation in referrer:', match[1]);
                    return match[1];
                }
            }
            
            console.log('No reservation number found in any location');
            console.log('Current URL:', window.location.href);
            console.log('Referrer:', document.referrer);
            
            return null;
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
                console.log('=== LIFF Initialization Started ===');
                console.log('Current URL:', window.location.href);
                console.log('URL search:', window.location.search);

                // 初回アクセス時に予約番号を保存
                const urlParams = new URLSearchParams(window.location.search);
                const reservation = urlParams.get('reservation');
                if (reservation) {
                    sessionStorage.setItem('reservation_number', reservation);
                    console.log('Saved initial reservation:', reservation);
                }

                // 予約番号から予約情報を取得
                const reservationNumber = getReservationNumber();
                console.log('Retrieved reservation number:', reservationNumber);

                if (!reservationNumber) {
                    console.error('No reservation number found!');
                    throw new Error('予約番号が指定されていません');
                }

                console.log('Fetching reservation info for:', reservationNumber);
                
                // 予約情報を取得して店舗のLIFF IDを取得
                const response = await fetch(`/api/reservation/${reservationNumber}/store-info`);
                console.log('API response status:', response.status);

                if (!response.ok) {
                    console.error('API request failed:', response.status, response.statusText);
                    const errorData = await response.json().catch(() => ({}));
                    console.error('Error data:', errorData);
                    throw new Error(errorData.error || '予約が見つかりません');
                }

                const data = await response.json();
                console.log('API response data:', data);

                if (!data.success) {
                    console.error('API returned success=false:', data);
                    throw new Error(data.error || '予約情報の取得に失敗しました');
                }
                
                const liffId = data.liff_id;
                if (!liffId) {
                    throw new Error('LIFF IDが設定されていません');
                }
                
                console.log('Initializing LIFF with ID:', liffId);
                
                await liff.init({
                    liffId: liffId
                });
                
                liffInitialized = true;
                console.log('LIFF initialized successfully');
                
                // 予約情報を画面に表示
                document.getElementById('reservation-info').innerHTML = `
                    <p class="text-sm text-gray-600">予約番号:</p>
                    <p class="font-medium text-gray-900">${reservationNumber}</p>
                    <p class="text-xs text-gray-500 mt-1">${data.store_name}</p>
                `;
                
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
                
                const reservationNumber = getReservationNumber();
                
                // API呼び出し（予約番号ベース）
                const response = await fetch('/api/line/link-reservation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        id_token: idToken,
                        reservation_number: reservationNumber
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
            console.log('Current URL:', window.location.href);
            console.log('Search params:', window.location.search);
            
            const reservationNumber = getReservationNumber();
            console.log('Extracted reservation number:', reservationNumber);
            
            if (!reservationNumber) {
                // テスト用：予約番号がない場合はダミーで処理
                console.log('No reservation number - using test mode');
                showError('テストモード：予約番号がありません。URL: ' + window.location.href);
                return;
            }
            
            console.log('Reservation number:', reservationNumber);
            
            // イベントリスナー設定
            document.getElementById('login-btn').addEventListener('click', handleLogin);
            document.getElementById('link-btn').addEventListener('click', handleLink);
            
            // 閉じるボタンのイベントリスナー
            document.getElementById('close-btn')?.addEventListener('click', function() {
                if (liffInitialized && liff.isInClient()) {
                    // LINEアプリ内の場合
                    liff.closeWindow();
                } else {
                    // ブラウザの場合
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.close();
                        // window.close()が効かない場合の代替
                        window.location.href = '/';
                    }
                }
            });
            
            // LIFF初期化
            initializeLiff();
        });
    </script>
</body>
</html>