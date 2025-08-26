<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>追加メニューのご提案 - Xsyumeno</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .upsell-menu-image {
            aspect-ratio: 16/9;
            width: 128px; /* w-32 */
        }
        
        /* モーダル関連のスタイル */
        .modal-overlay {
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
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
        
        /* カードのホバーアニメーション */
        .upsell-card {
            transition: all 0.2s ease-in-out;
        }
        
        .upsell-card:hover {
            transform: translateY(-2px);
        }
        
        /* スライドインアニメーション */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        /* パルスアニメーション（追加ボタン用） */
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- メインコンテンツ -->
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- ヘッダー -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">ご一緒にいかがですか？</h1>
            <p class="text-gray-600">さらに効果的なメニューをご用意しています</p>
        </div>

        <!-- 進捗インジケーター -->
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-gray-400 text-white rounded-full flex items-center justify-center font-bold">✓</div>
                <div class="w-20 h-1 bg-blue-500"></div>
                <div class="w-10 h-10 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold">1.5</div>
                <div class="w-20 h-1 bg-gray-300"></div>
                <div class="w-10 h-10 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center font-bold">2</div>
                <div class="w-20 h-1 bg-gray-300"></div>
                <div class="w-10 h-10 bg-gray-300 text-gray-500 rounded-full flex items-center justify-center font-bold">3</div>
            </div>
        </div>
        <div class="flex justify-center mb-8 text-sm">
            <div class="text-center px-4">
                <div class="text-gray-500">メニュー選択</div>
            </div>
            <div class="text-center px-4">
                <div class="text-blue-500 font-semibold">追加提案</div>
            </div>
            <div class="text-center px-4">
                <div class="text-gray-400">日時選択</div>
            </div>
            <div class="text-center px-4">
                <div class="text-gray-400">情報入力</div>
            </div>
        </div>

        <!-- 選択中のメニュー表示 -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-600 mb-1">選択中のメニュー</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $selectedMenu->name }}</p>
                    <p class="text-sm text-gray-600">{{ $selectedMenu->duration }}分 / ¥{{ number_format($selectedMenu->price) }}</p>
                </div>
                <a href="{{ route('reservation.menu') }}" class="text-blue-500 hover:text-blue-700 text-sm underline">
                    メニューを変更
                </a>
            </div>
        </div>

        <!-- アップセルメニューリスト -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">さらに効果を高めませんか？</h2>
            
            @if($upsellMenus->count() > 0)
                <div class="grid gap-4">
                    @foreach($upsellMenus as $menu)
                        <div class="upsell-card bg-white rounded-lg shadow-sm hover:shadow-md cursor-pointer border-2 border-transparent hover:border-green-500 overflow-hidden"
                             onclick="openUpsellModal({{ $menu->id }})">
                            <div class="flex">
                                @if($menu->image_path)
                                    <div class="upsell-menu-image bg-gray-200 flex-shrink-0">
                                        <img src="{{ asset('storage/' . $menu->image_path) }}" alt="{{ $menu->name }}" class="w-full h-full object-cover rounded-l-lg">
                                    </div>
                                @else
                                    <div class="upsell-menu-image bg-gradient-to-br from-green-50 to-green-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-12 h-12 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                    </div>
                                @endif
                                <div class="flex-1 p-6">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $menu->name }}</h3>
                                            @if($menu->upsell_description && $menu->upsell_description != 'null')
                                                <p class="text-green-600 text-sm mb-2 font-medium">{{ $menu->upsell_description }}</p>
                                            @endif
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
                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">追加オプション</span>
                                            </div>
                                        </div>
                                        <div class="text-right ml-4">
                                            <div class="text-2xl font-bold text-green-600"><span class="text-sm">¥</span>{{ number_format($menu->price) }}</div>
                                            <div class="text-xs text-gray-500 mt-1">税込</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <p class="text-gray-500">現在、追加メニューはありません。</p>
                </div>
            @endif
        </div>

        <!-- アクションボタン -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="skipUpsell()" 
                    class="px-8 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                このまま進む
            </button>
            <button onclick="proceedToDateTime()" 
                    class="px-8 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                日時選択へ進む
            </button>
        </div>

        <!-- 選択されたアップセルメニューの表示エリア -->
        <div id="selectedUpsells" class="mt-8 hidden">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">追加選択したメニュー</h3>
            <div id="upsellList" class="space-y-2"></div>
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex justify-between items-center">
                    <span class="font-semibold">合計金額：</span>
                    <span id="totalAmount" class="text-xl font-bold text-green-600"><span class="text-sm">¥</span>{{ number_format($selectedMenu->price) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- モーダルウィンドウ -->
    <div id="upsellModal" class="modal-overlay fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <!-- モーダルヘッダー -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 id="modalMenuName" class="text-2xl font-bold mb-2"></h3>
                        <p id="modalMenuDuration" class="text-green-100"></p>
                    </div>
                    <button onclick="closeUpsellModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- モーダルボディ -->
            <div class="p-6 overflow-y-auto max-h-[50vh]">
                <!-- メニュー画像 -->
                <div id="modalMenuImage" class="mb-6 rounded-lg overflow-hidden bg-gray-100 h-64 flex items-center justify-center">
                    <img id="modalImage" src="" alt="" class="w-full h-full object-cover hidden">
                    <div id="modalImagePlaceholder" class="text-gray-400">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
                
                <!-- 特別説明 -->
                <div id="modalUpsellDescription" class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 hidden">
                    <p class="text-green-700 font-medium"></p>
                </div>
                
                <!-- 詳細説明 -->
                <div id="modalDescription" class="text-gray-600 mb-6 leading-relaxed"></div>
                
                <!-- 価格表示 -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">追加料金</span>
                        <div class="text-right">
                            <div id="modalPrice" class="text-3xl font-bold text-green-600"></div>
                            <div class="text-sm text-gray-500">税込</div>
                        </div>
                    </div>
                </div>
                
                <!-- 効果・特徴 -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 mb-3">このメニューの特徴</h4>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-600">メインメニューとの相乗効果で、より高い効果を実感</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-600">プロフェッショナルによる丁寧な施術</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-600">今だけの特別価格でご提供</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- モーダルフッター -->
            <div class="bg-gray-50 px-6 py-4 flex justify-between items-center">
                <button onclick="closeUpsellModal()" class="px-6 py-2 text-gray-600 hover:text-gray-800 transition">
                    今回は見送る
                </button>
                <button id="addUpsellButton" onclick="confirmAddUpsell()" class="pulse-hover px-8 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-semibold shadow-lg">
                    このメニューを追加する
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedUpsells = [];
        let basePrice = {{ $selectedMenu->price }};
        let currentModalMenuId = null;
        const menuData = {!! json_encode($upsellMenus->keyBy('id')) !!};

        // モーダルを開く
        function openUpsellModal(menuId) {
            currentModalMenuId = menuId;
            const menu = menuData[menuId];
            const modal = document.getElementById('upsellModal');
            
            // モーダルの内容を設定
            document.getElementById('modalMenuName').textContent = menu.name;
            document.getElementById('modalMenuDuration').textContent = menu.duration + '分';
            document.getElementById('modalPrice').textContent = '+¥' + parseFloat(menu.price).toLocaleString();
            
            // 画像の設定
            const modalImage = document.getElementById('modalImage');
            const imagePlaceholder = document.getElementById('modalImagePlaceholder');
            if (menu.image_path) {
                modalImage.src = '/storage/' + menu.image_path;
                modalImage.classList.remove('hidden');
                imagePlaceholder.classList.add('hidden');
            } else {
                modalImage.classList.add('hidden');
                imagePlaceholder.classList.remove('hidden');
            }
            
            // 特別説明の設定
            const upsellDescContainer = document.getElementById('modalUpsellDescription');
            if (menu.upsell_description) {
                upsellDescContainer.querySelector('p').textContent = menu.upsell_description;
                upsellDescContainer.classList.remove('hidden');
            } else {
                upsellDescContainer.classList.add('hidden');
            }
            
            // 詳細説明の設定
            const descContainer = document.getElementById('modalDescription');
            descContainer.textContent = menu.description || 'このメニューは、お客様のご要望に合わせた特別な施術を提供します。';
            
            // 既に選択されているかチェック
            const isSelected = selectedUpsells.find(item => item.id === menuId);
            const addButton = document.getElementById('addUpsellButton');
            if (isSelected) {
                addButton.textContent = '選択済み';
                addButton.disabled = true;
                addButton.classList.add('bg-gray-400', 'cursor-not-allowed');
                addButton.classList.remove('bg-green-500', 'hover:bg-green-600');
            } else {
                addButton.textContent = 'このメニューを追加する';
                addButton.disabled = false;
                addButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                addButton.classList.add('bg-green-500', 'hover:bg-green-600');
            }
            
            // モーダルを表示
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // アニメーション開始
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        // モーダルを閉じる
        function closeUpsellModal() {
            const modal = document.getElementById('upsellModal');
            modal.classList.remove('show');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        // アップセルメニューを追加確認
        function confirmAddUpsell() {
            if (currentModalMenuId) {
                addUpsellMenu(currentModalMenuId);
                
                // 追加成功のフィードバック
                const addButton = document.getElementById('addUpsellButton');
                addButton.innerHTML = '<svg class="w-5 h-5 inline mr-2 checkmark-animate" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>追加しました！';
                addButton.disabled = true;
                addButton.classList.add('bg-gray-400', 'cursor-not-allowed');
                addButton.classList.remove('bg-green-500', 'hover:bg-green-600');
                
                // 1秒後にモーダルを閉じる
                setTimeout(() => {
                    closeUpsellModal();
                }, 1000);
            }
        }

        // アップセルメニューを追加
        function addUpsellMenu(menuId) {
            const menu = menuData[menuId];
            
            if (!selectedUpsells.find(item => item.id === menuId)) {
                selectedUpsells.push({
                    id: menuId,
                    name: menu.name,
                    price: parseFloat(menu.price)
                });
                updateUpsellDisplay();
                
                // カードにチェックマークを追加
                const card = document.querySelector(`[onclick="openUpsellModal(${menuId})"]`);
                if (card) {
                    card.classList.add('border-green-500', 'bg-green-50');
                }
            }
        }

        // アップセルメニューを削除
        function removeUpsellMenu(menuId) {
            selectedUpsells = selectedUpsells.filter(item => item.id !== menuId);
            updateUpsellDisplay();
            
            // カードからチェックマークを削除
            const card = document.querySelector(`[onclick="openUpsellModal(${menuId})"]`);
            if (card) {
                card.classList.remove('border-green-500', 'bg-green-50');
            }
        }

        // 表示を更新
        function updateUpsellDisplay() {
            const container = document.getElementById('selectedUpsells');
            const list = document.getElementById('upsellList');
            const totalElement = document.getElementById('totalAmount');

            if (selectedUpsells.length > 0) {
                container.classList.remove('hidden');
                container.classList.add('slide-in');
                list.innerHTML = selectedUpsells.map(item => `
                    <div class="flex justify-between items-center p-3 bg-white rounded border slide-in">
                        <span>${item.name}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">¥${item.price.toLocaleString()}</span>
                            <button onclick="removeUpsellMenu(${item.id})" class="text-red-500 hover:text-red-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `).join('');

                const total = basePrice + selectedUpsells.reduce((sum, item) => sum + item.price, 0);
                totalElement.textContent = '¥' + total.toLocaleString();
            } else {
                container.classList.add('hidden');
                container.classList.remove('slide-in');
            }
        }

        // スキップ処理
        function skipUpsell() {
            sessionStorage.setItem('selectedUpsells', JSON.stringify([]));
            window.location.href = '/reservation/datetime';
        }

        // 次へ進む処理
        function proceedToDateTime() {
            sessionStorage.setItem('selectedUpsells', JSON.stringify(selectedUpsells));
            window.location.href = '/reservation/datetime';
        }

        // ESCキーでモーダルを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUpsellModal();
            }
        });

        // モーダル外クリックで閉じる
        document.getElementById('upsellModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUpsellModal();
            }
        });
    </script>
</body>
</html>