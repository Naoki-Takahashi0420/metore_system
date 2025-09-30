<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メニュー選択 - Xsyumeno</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .menu-image {
            aspect-ratio: 16/9;
            width: 192px; /* w-48 */
        }
        
        /* モーダル位置調整 */
        #optionModal {
            display: none;
        }
        
        #optionModal.flex {
            display: flex !important;
        }
        
        /* モーダル内のボタン調整 */
        #optionModal .flex.gap-3.justify-center {
            margin-bottom: 1rem;
            padding: 0 1rem;
        }
        
        /* モバイル対応 */
        @media (max-width: 640px) {
            /* モーダル内のボタンをモバイル対応 */
            #optionModal .flex.gap-3.justify-center {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                padding: 1rem;
                box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.1);
                z-index: 60;
                margin: 0;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }
            
            #optionModal .flex.gap-3.justify-center button {
                width: 100%;
                padding: 0.875rem;
                font-size: 0.875rem;
            }
            
            /* モーダルコンテンツの下部にパディング */
            .modal-content {
                padding-bottom: 120px;
            }
            /* メインコンテナのレイアウト */
            #menuList {
                display: flex !important;
                flex-direction: column !important;
                gap: 1rem !important;
            }
            
            #menuList > div {
                width: 100% !important;
                margin: 0 !important;
            }
            
            /* メニューアイテムのレイアウト統一 */
            .menu-item {
                display: block !important;
                width: 100% !important;
            }
            
            .menu-item > .flex {
                display: flex !important;
                flex-direction: column !important;
                width: 100% !important;
            }
            
            /* 画像エリアの統一 */
            .menu-image,
            .menu-item .menu-image {
                width: 100% !important;
                aspect-ratio: 16/9 !important;
                height: auto !important;
                flex-shrink: unset !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background-color: #f3f4f6 !important;
                overflow: hidden !important;
            }
            
            .menu-image img {
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
            }
            
            /* コンテンツエリアの統一 */
            .menu-item .p-6,
            .menu-item .flex-1 {
                width: 100% !important;
                padding: 1rem !important;
                display: block !important;
            }
            
            /* flex-1の中のflexコンテナのパディング削除 */
            .menu-item .flex-1 > .flex {
                padding: 0 !important;
            }
            
            /* タイトルと説明文の統一 */
            .menu-item h3 {
                font-size: 1rem !important;
                line-height: 1.4 !important;
                margin-bottom: 0.5rem !important;
            }
            
            .menu-item p {
                font-size: 0.875rem !important;
                line-height: 1.3 !important;
                margin-bottom: 0.75rem !important;
            }
            
            /* 価格と時間の行を統一 */
            .menu-item .flex.items-center.gap-4 {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                width: 100% !important;
                margin: 0.5rem 0 !important;
                padding: 0 !important;
            }
            
            /* 価格エリアの調整 */
            .menu-item .text-right {
                text-align: right !important;
                margin-left: auto !important;
                padding: 0 !important;
            }
            
            /* 価格表示の統一 */
            .menu-item .text-2xl,
            .menu-item .font-bold.text-blue-600 {
                font-size: 1.25rem !important;
            }
            
            /* 円マークの表示を確実にする */
            .menu-item .text-2xl span.text-sm {
                font-size: 0.875rem !important;
                display: inline !important;
                visibility: visible !important;
            }
            
            /* ボタンの統一 */
            .menu-item button {
                display: block !important;
                width: 100% !important;
                padding: 0.75rem !important;
                font-size: 0.875rem !important;
                margin-top: 0.75rem !important;
                background-color: #3b82f6 !important;
                color: white !important;
                border-radius: 0.5rem !important;
            }
            
            /* その他の要素の調整 */
            .menu-item .gap-4 {
                gap: 0.5rem !important;
            }
            
            /* 背景グラデーションの統一 */
            .bg-gradient-to-br {
                background: #f3f4f6 !important;
            }
        }
        
        .option-item {
            transition: all 0.2s ease-in-out;
        }
        
        .option-item:hover {
            transform: translateY(-2px);
        }
        
        .menu-item {
            transition: all 0.3s ease;
        }
        
        .menu-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in-up {
            animation: slideInUp 0.5s ease-out;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* モーダル関連のスタイル */
        .modal-overlay {
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            backdrop-filter: blur(4px);
        }
        
        .modal-overlay.show {
            opacity: 1;
        }
        
        .modal-content {
            transform: translateY(20px) scale(0.95);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modal-overlay.show .modal-content {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        
        /* オプションカードのホバー効果 */
        .option-card {
            transition: all 0.2s ease-in-out;
        }
        
        .option-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .option-card.selected {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-color: #22c55e;
        }
        
        /* アップセルメニュー画像 */
        .upsell-image {
            aspect-ratio: 16/9;
            width: 100%;
            height: auto;
            object-fit: cover;
        }
        
        /* アップセル画像コンテナも16:9に固定 */
        .upsell-image-container {
            aspect-ratio: 16/9;
            width: 100%;
            overflow: hidden;
            background-color: #f3f4f6;
        }
        
        /* チェックマークアニメーション */
        @keyframes checkmark {
            0% {
                stroke-dashoffset: 100;
            }
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        .checkmark-animate {
            stroke-dasharray: 100;
            animation: checkmark 0.3s ease-in-out forwards;
        }
        
        /* パルスアニメーション */
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .pulse-hover:hover {
            animation: pulse 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- ヘッダー -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">メニューを選択</h1>
            <p class="text-gray-600">ご希望のメニューをお選びください</p>
        </div>

        <!-- 進捗インジケーター -->
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold">1</div>
                <div class="w-20 h-1 bg-gray-300"></div>
                <div class="w-10 h-10 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center font-bold">2</div>
                <div class="w-20 h-1 bg-gray-300"></div>
                <div class="w-10 h-10 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center font-bold">3</div>
            </div>
        </div>
        <div class="flex justify-center mb-8 text-sm">
            <div class="text-center px-4">
                <div class="text-blue-500 font-semibold">メニュー選択</div>
            </div>
            <div class="text-center px-4">
                <div class="text-gray-400">日時選択</div>
            </div>
            <div class="text-center px-4">
                <div class="text-gray-400">情報入力</div>
            </div>
        </div>

        <!-- メニューリスト -->
        <div class="grid gap-4 fade-in" id="menuList">
            @forelse($menus as $menu)
                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition cursor-pointer border-2 border-transparent hover:border-blue-500 overflow-hidden menu-item"
                     data-menu-id="{{ $menu->id }}" data-menu-name="{{ $menu->name }}" data-menu-price="{{ $menu->is_subscription ? $menu->subscription_monthly_price : $menu->price }}" data-menu-duration="{{ $menu->duration }}" data-store-id="{{ $menu->store_id }}"
                     onclick="selectMenu({{ $menu->id }})">
                    <div class="flex">
                        @if($menu->image_path)
                            <div class="menu-image bg-gray-200 flex-shrink-0">
                                <img src="/storage/{{ $menu->image_path }}" alt="{{ $menu->name }}" class="w-full h-full object-cover rounded-l-lg">
                            </div>
                        @else
                            <div class="menu-image bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-16 h-16 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                        @endif
                        <div class="flex-1 p-6">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $menu->name }}</h3>
                                    @if($menu->description)
                                        <p class="text-gray-600 text-sm mb-3">{{ $menu->description }}</p>
                                    @endif
                                    <div class="flex items-center gap-4 text-sm">
                                        <span class="text-gray-500">
                                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $menu->duration }}分
                                        </span>
                                        @if($menu->category)
                                            <span class="bg-gray-100 px-2 py-1 rounded text-xs">
                                                @php
                                                    $categories = [
                                                        'vision_training' => '視力検査',
                                                        'special_training' => '特別トレーニング',
                                                        'eye_care' => 'アイケア',
                                                        'consultation' => 'カウンセリング',
                                                        'other' => 'その他'
                                                    ];
                                                @endphp
                                                {{ $categories[$menu->category] ?? $menu->category }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right ml-4">
                                    <div class="text-2xl font-bold text-blue-600">
                                        <span class="text-sm">¥</span>{{ number_format($menu->is_subscription ? $menu->subscription_monthly_price : $menu->price) }}
                                        @if($menu->is_subscription)
                                            <span class="text-xs">/月</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">税込</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <p class="text-gray-500">現在、選択可能なメニューがありません。</p>
                </div>
            @endforelse
        </div>

        <!-- オプション選択モーダル -->
        <div id="optionModal" class="modal-overlay fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4">
            <div class="modal-content bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
                <!-- モーダルヘッダー -->
                <div class="bg-gradient-to-r from-blue-500 to-green-500 text-white p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-bold mb-2">ご一緒にいかがですか？</h2>
                            <p class="text-blue-100">選択されたメニューと一緒に受けられる追加オプションです</p>
                        </div>
                        <button onclick="closeOptionModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- モーダルボディ -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <!-- 選択中のメニュー情報 -->
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-blue-600 mb-1">選択中のメニュー</p>
                                <p id="selectedMenuInfo" class="font-bold text-gray-800 text-lg"></p>
                            </div>
                            <div class="text-right">
                                <p id="basePrice" class="text-2xl font-bold text-blue-600"></p>
                                <p class="text-xs text-gray-500">基本料金</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- オプションリスト -->
                    <div id="optionList" class="grid gap-4">
                        <!-- オプションメニューがここに表示される -->
                    </div>
                </div>
                
                <!-- モーダルフッター -->
                <div class="bg-gray-50 px-6 py-4">
                    <!-- 合計金額表示 -->
                    <div class="bg-gradient-to-r from-blue-50 to-green-50 rounded-lg p-4 mb-4 border border-blue-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-gray-700 font-medium">合計金額</span>
                                <span id="selectedOptionsCount" class="text-sm text-gray-500 ml-2"></span>
                            </div>
                            <div class="text-right">
                                <p id="totalPrice" class="text-3xl font-bold text-green-600"></p>
                                <p class="text-sm text-gray-500">税込</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- アクションボタン -->
                    <div class="flex gap-3 justify-center">
                        <button type="button" onclick="proceedWithoutOptions()" 
                                class="px-8 py-3 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition-colors font-semibold">
                            追加なしで進む
                        </button>
                        <button type="button" onclick="proceedWithOptions()" 
                                class="pulse-hover px-8 py-3 bg-gradient-to-r from-blue-500 to-green-500 text-white rounded-lg hover:from-blue-600 hover:to-green-600 transition-all transform hover:scale-105 font-semibold shadow-lg">
                            選択したオプションで進む
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 隠しフォーム -->
        <form id="menuForm" action="{{ route('reservation.select-menu') }}" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="menu_id" id="selectedMenuId">
            <input type="hidden" name="option_ids" id="selectedOptionIds">
        </form>
    </div>

    <script>
        let selectedMenu = null;
        let selectedOptions = [];
        let upsellMenus = [];
        
        // ローディング削除関数を先に定義
        function removeExistingLoading() {
            const existingLoader = document.getElementById('loading-overlay');
            if (existingLoader) {
                existingLoader.remove();
            }
        }
        
        // ページ状態をリセットする関数
        function resetPageState() {
            // ローディングを削除
            removeExistingLoading();
            
            // モーダルを閉じる
            const modal = document.getElementById('optionModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex', 'show');
            }
            
            // メニューリストを再度有効化
            const menuList = document.getElementById('menuList');
            if (menuList) {
                menuList.style.opacity = '1';
                menuList.style.pointerEvents = 'auto';
            }
            
            // メニューのハイライトをリセット
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.style.border = '2px solid transparent';
                item.style.backgroundColor = '';
            });
            
            // 選択状態をリセット
            selectedMenu = null;
            selectedOptions = [];
            upsellMenus = [];
        }
        
        // ページ読み込み時にメニューアイテムにアニメーションを追加
        document.addEventListener('DOMContentLoaded', function() {
            // ページ状態をリセット
            resetPageState();
            
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // ページ表示時にもクリア（ブラウザバック対応）
        window.addEventListener('pageshow', function(event) {
            // キャッシュから復元された場合（ブラウザバック時）
            if (event.persisted) {
                resetPageState();
                // メニューアイテムを再表示
                const menuItems = document.querySelectorAll('.menu-item');
                menuItems.forEach(item => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                });
            }
        });
        
        // visibilitychange イベントでもクリア
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                removeExistingLoading();
            }
        });

        async function selectMenu(menuId) {
            // メニュー情報を取得
            const menuElement = document.querySelector(`[data-menu-id="${menuId}"]`);
            selectedMenu = {
                id: menuId,
                name: menuElement.dataset.menuName,
                price: parseFloat(menuElement.dataset.menuPrice),
                duration: parseInt(menuElement.dataset.menuDuration)
            };

            // メニューリストをフェードアウトして非表示
            const menuList = document.getElementById('menuList');
            menuList.style.transition = 'opacity 0.3s ease';
            menuList.style.opacity = '0.3';
            menuList.style.pointerEvents = 'none';
            
            // 選択されたメニューをハイライト
            menuElement.style.border = '2px solid #3b82f6';
            menuElement.style.backgroundColor = '#eff6ff';

            // オプションメニューを取得
            try {
                const storeId = menuElement.dataset.storeId;
                const response = await fetch(`/api/menus/upsell?exclude=${menuId}&store_id=${storeId}`);
                upsellMenus = await response.json();
                
                if (upsellMenus.length > 0) {
                    displayOptionSection();
                } else {
                    // オプションがない場合は直接送信
                    showLoading('日時選択ページに進みます...');
                    setTimeout(() => proceedWithoutOptions(), 1000);
                }
            } catch (error) {
                console.error('オプションメニューの取得に失敗:', error);
                proceedWithoutOptions();
            }
        }

        function displayOptionSection() {
            const modal = document.getElementById('optionModal');
            const optionList = document.getElementById('optionList');
            const selectedMenuInfo = document.getElementById('selectedMenuInfo');
            const basePrice = document.getElementById('basePrice');
            
            // 選択中のメニュー情報を表示
            selectedMenuInfo.textContent = `${selectedMenu.name} - ${selectedMenu.duration}分`;
            basePrice.innerHTML = `<span class="text-xs">¥</span>${Number(selectedMenu.price).toLocaleString('ja-JP')}`;
            updateTotalPrice();

            // オプションリストを作成（カード形式）
            optionList.innerHTML = upsellMenus.map(option => `
                <div class="option-card bg-white rounded-lg border-2 border-gray-200 hover:border-green-500 hover:shadow-md transition-all cursor-pointer overflow-hidden"
                     data-option-id="${option.id}" onclick="toggleOption(${option.id})">
                    ${option.image_path ? `
                        <div class="upsell-image-container">
                            <img src="/storage/${option.image_path}" alt="${option.name}" class="upsell-image">
                        </div>
                    ` : `
                        <div class="upsell-image-container bg-gradient-to-br from-green-50 to-green-100 flex items-center justify-center">
                            <svg class="w-16 h-16 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                    `}
                    <div class="p-4">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" id="option_${option.id}" class="mt-1 w-5 h-5 text-green-600 rounded focus:ring-green-500">
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 pr-4">
                                        <h4 class="font-semibold text-gray-800 mb-1 text-lg">${option.name}</h4>
                                        <p class="text-sm text-green-600 font-medium mb-2">${option.upsell_description || option.description}</p>
                                        <div class="flex items-center gap-4 text-sm text-gray-500">
                                            <span class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                ${option.duration}分
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="text-xl font-bold text-green-600">+<span class="text-xs">¥</span>${Number(option.price).toLocaleString('ja-JP')}</div>
                                        <div class="text-xs text-gray-500">税込</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            // モーダルを表示
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // アニメーション開始
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
            
            // ボタンの初期状態を設定
            updateButtonState();
        }
        
        // モーダルを閉じる関数を追加
        function closeOptionModal() {
            const modal = document.getElementById('optionModal');
            modal.classList.remove('show');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        function toggleOption(optionId) {
            const checkbox = document.getElementById(`option_${optionId}`);
            const optionItem = document.querySelector(`[data-option-id="${optionId}"]`);
            
            if (selectedOptions.includes(optionId)) {
                // 選択解除
                selectedOptions = selectedOptions.filter(id => id !== optionId);
                checkbox.checked = false;
                optionItem.classList.remove('selected', 'border-green-500', 'shadow-md');
                optionItem.classList.add('border-gray-200');
            } else {
                // 選択追加
                selectedOptions.push(optionId);
                checkbox.checked = true;
                optionItem.classList.remove('border-gray-200');
                optionItem.classList.add('selected', 'border-green-500', 'shadow-md');
                
                // 選択時のアニメーション効果
                optionItem.style.animation = 'pulse 0.3s ease-in-out';
                setTimeout(() => {
                    optionItem.style.animation = '';
                }, 300);
            }
            
            updateTotalPrice();
            updateButtonState();
        }
        
        function updateButtonState() {
            const proceedButton = document.querySelector('button[onclick="proceedWithOptions()"]');
            if (selectedOptions.length > 0) {
                proceedButton.textContent = `選択した${selectedOptions.length}件のオプションで進む`;
                proceedButton.classList.add('animate-pulse');
            } else {
                proceedButton.textContent = 'オプションを選択してください';
                proceedButton.classList.remove('animate-pulse');
            }
        }

        function updateTotalPrice() {
            let totalPrice = selectedMenu.price;
            let totalDuration = selectedMenu.duration;
            
            selectedOptions.forEach(optionId => {
                const option = upsellMenus.find(opt => opt.id === optionId);
                if (option) {
                    totalPrice += parseFloat(option.price);
                    totalDuration += parseInt(option.duration);
                }
            });

            const totalPriceElement = document.getElementById('totalPrice');
            const selectedOptionsCount = document.getElementById('selectedOptionsCount');
            
            totalPriceElement.innerHTML = `<span class="text-sm">¥</span>${Number(totalPrice).toLocaleString('ja-JP')}`;
            
            // オプション数の表示
            if (selectedOptions.length > 0) {
                selectedOptionsCount.textContent = `(オプション${selectedOptions.length}件追加・合計${totalDuration}分)`;
            } else {
                selectedOptionsCount.textContent = `(${totalDuration}分)`;
            }
        }

        function proceedWithoutOptions() {
            selectedOptions = [];
            closeOptionModal();
            // ローディングアニメーションを表示
            showLoading('選択したメニューで日時選択に進みます...');
            setTimeout(() => submitForm(), 500);
        }

        function proceedWithOptions() {
            if (selectedOptions.length === 0) {
                alert('オプションを選択するか、"追加なしで進む"をクリックしてください。');
                return;
            }
            closeOptionModal();
            // ローディングアニメーションを表示
            showLoading('選択したオプションで日時選択に進みます...');
            setTimeout(() => submitForm(), 500);
        }
        
        function showLoading(message) {
            // 既存のローディングを削除
            removeExistingLoading();
            
            // メインコンテナにローディング表示
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'loading-overlay';
            loadingDiv.className = 'fixed inset-0 bg-white bg-opacity-95 flex items-center justify-center z-50';
            loadingDiv.innerHTML = `
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mb-4"></div>
                    <p class="text-gray-600 font-semibold text-lg">${message}</p>
                </div>
            `;
            document.body.appendChild(loadingDiv);
        }

        function submitForm() {
            document.getElementById('selectedMenuId').value = selectedMenu.id;
            document.getElementById('selectedOptionIds').value = JSON.stringify(selectedOptions);
            document.getElementById('menuForm').submit();
        }
        
        // ESCキーでモーダルを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOptionModal();
            }
        });
        
        // モーダル外クリックで閉じる
        document.getElementById('optionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOptionModal();
            }
        });
    </script>
</body>
</html>