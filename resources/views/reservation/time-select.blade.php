@extends('layouts.app')

@section('content')
<style>
    .menu-tag {
        background: #f3f4f6;
        color: #374151;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
        margin-right: 4px;
    }
    
    .menu-tag.new {
        background: #fef3c7;
        color: #92400e;
    }
    
    .price-display {
        color: #1f2937;
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .reserve-button {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .reserve-button:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }
    
    .menu-item-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        position: relative;
        height: fit-content;
    }
    
    @media (min-width: 768px) {
        .menu-item-card {
            padding: 20px;
        }
    }
    
    .menu-item-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(59,130,246,0.15);
    }
    
    .menu-number {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #6b7280;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
    }
</style>

<div class="max-w-5xl mx-auto px-4 py-6 bg-gray-50">
    {{-- ステップインジケーター --}}
    {{-- エラー表示 --}}
    @if ($errors->any())
        <div class="mb-6 bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-yellow-800 font-bold text-lg mb-2">予約の注意事項</h3>
                    <ul class="text-yellow-700 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li class="text-base font-medium">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- モバイル版：シンプルな表示 --}}
    <div class="block sm:hidden mb-6">
        <div class="flex justify-center items-center">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                <div class="w-8 h-8 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">3</div>
                <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 text-xs flex items-center justify-center">4</div>
            </div>
        </div>
        <p class="text-center text-sm mt-2 font-bold">ステップ3: 時間選択</p>
    </div>

    {{-- PC版：詳細表示 --}}
    <div class="hidden sm:block mb-8">
        <div class="flex items-center justify-center">
            <div class="flex items-center">
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">1</div>
                    <span class="ml-2 text-base text-gray-500">店舗</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">2</div>
                    <span class="ml-2 text-base text-gray-500">コース</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-blue-500 text-white flex items-center justify-center text-lg font-bold">3</div>
                    <span class="ml-2 text-base font-bold">時間・料金</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-gray-500 flex items-center justify-center text-lg font-bold">4</div>
                    <span class="ml-2 text-base text-gray-500">日時選択</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ヘッダー --}}
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h1 class="text-xl font-bold mb-2">施術時間をお選びください</h1>
        <p class="text-sm text-gray-600">{{ $store->name }} / {{ $category->name }}</p>
    </div>

        @if($hasSubscription)
            <div class="mb-6 bg-green-50 border-2 border-green-300 rounded-lg p-4 text-center">
                <span class="text-green-700 font-bold text-lg">サブスクリプション会員様限定メニューも表示されています</span>
            </div>
        @endif

    {{-- メニューリスト（PCでは2列、モバイルでは1列） --}}
    <div class="bg-white rounded-lg p-4">
        @php $menuIndex = 1; @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @if(isset($sortedMenus))
            @foreach($sortedMenus as $menu)
                <div class="menu-item-card">
                    <div class="menu-number">{{ $menuIndex++ }}</div>
                    
                    {{-- タグ部分 --}}
                    <div class="mb-2 ml-10 md:ml-8">
                        @if($menu->is_subscription_only)
                            <span class="menu-tag">サブスク</span>
                        @endif
                        <span class="menu-tag">{{ $menu->duration_minutes }}分</span>
                        @if($menu->is_popular)
                            <span class="menu-tag new">人気No.1</span>
                        @endif
                    </div>
                    
                    <div class="px-2 md:px-0 md:ml-8">
                        {{-- 画像 (16:9) --}}
                        @if($menu->image_path)
                            <div class="mb-4">
                                <img src="{{ Storage::url($menu->image_path) }}" alt="{{ $menu->name }}"
                                    class="w-full h-auto aspect-video object-contain bg-white rounded-lg">
                            </div>
                        @endif
                        
                        {{-- コンテンツ部分 --}}
                        <div class="flex flex-col gap-4">
                            <div class="flex-1">
                                {{-- タイトル --}}
                                <h3 class="font-bold text-lg mb-2">【{{ $menu->name }}】</h3>
                                
                                {{-- 説明文 --}}
                                <div class="text-sm text-gray-700 mb-3 leading-relaxed">
                                    @if($menu->description)
                                        {{ $menu->description }}
                                    @else
                                        プロの施術で心身ともにリフレッシュ。{{ $menu->duration_minutes }}分間の充実したケアをご提供します。
                                    @endif
                                </div>
                                
                                {{-- メニュー詳細情報 --}}
                                <div class="text-xs text-gray-500 mb-4">
                                    <span class="text-blue-600">施術時間：</span>{{ $menu->duration_minutes }}分<br>
                                    <span class="text-blue-600">カテゴリー：</span>{{ $category->name }}<br>
                                    <span class="text-blue-600">店舗：</span>{{ $store->name }}
                                    @if($menu->requires_staff)
                                        <br><span class="text-blue-600">スタッフ指定：</span>必須
                                    @endif
                                </div>
                            </div>
                            
                            {{-- 価格とボタン --}}
                            <div class="flex items-center justify-between">
                                <div class="price-display">¥{{ number_format($menu->is_subscription ? $menu->subscription_monthly_price : $menu->price) }}@if($menu->is_subscription)<span class="text-xs">/月</span>@endif</div>
                                <button type="button" onclick="selectMenu({{ $menu->id }}, '{{ $menu->name }}', {{ $menu->is_subscription ? $menu->subscription_monthly_price : $menu->price }})"
                                    class="reserve-button">
                                    予約する
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            @foreach($menusByDuration as $duration => $menus)
                @foreach($menus as $menu)
                    <div class="menu-item-card">
                        <div class="menu-number">{{ $menuIndex++ }}</div>
                        
                        {{-- タグ部分 --}}
                        <div class="mb-2 ml-10 md:ml-8">
                            @if($menu->is_subscription_only)
                                <span class="menu-tag">サブスク</span>
                            @endif
                            <span class="menu-tag">{{ $menu->duration_minutes }}分</span>
                            @if($menu->is_popular)
                                <span class="menu-tag new">人気No.1</span>
                            @endif
                        </div>
                        
                        <div class="px-2 md:px-0 md:ml-8">
                            {{-- 画像 (16:9) --}}
                            <div class="mb-4">
                                @if($menu->image_path)
                                    <img src="{{ Storage::url($menu->image_path) }}" alt="{{ $menu->name }}" 
                                        class="w-full h-auto aspect-video object-contain bg-white rounded-lg">
                                @else
                                    <div class="w-full aspect-video bg-gray-200 rounded-lg"></div>
                                @endif
                            </div>
                            
                            {{-- コンテンツ部分 --}}
                            <div class="flex flex-col gap-4">
                                <div class="flex-1">
                                    {{-- タイトル --}}
                                    <h3 class="font-bold text-lg mb-2">【{{ $menu->name }}】</h3>
                                    
                                    {{-- 説明文 --}}
                                    <div class="text-sm text-gray-700 mb-3 leading-relaxed">
                                        @if($menu->description)
                                            {{ $menu->description }}
                                        @else
                                            プロの施術で心身ともにリフレッシュ。{{ $menu->duration_minutes }}分間の充実したケアをご提供します。
                                        @endif
                                    </div>
                                    
                                    {{-- メニュー詳細情報 --}}
                                    <div class="text-xs text-gray-500 mb-4">
                                        <span class="text-blue-600">施術時間：</span>{{ $menu->duration_minutes }}分<br>
                                        <span class="text-blue-600">カテゴリー：</span>{{ $category->name }}<br>
                                        <span class="text-blue-600">店舗：</span>{{ $store->name }}
                                        @if($menu->requires_staff)
                                            <br><span class="text-blue-600">スタッフ指定：</span>必須
                                        @endif
                                    </div>
                                </div>
                                
                                {{-- 価格とボタン --}}
                                <div class="flex items-center justify-between">
                                    <div class="price-display">¥{{ number_format($menu->is_subscription ? $menu->subscription_monthly_price : $menu->price) }}@if($menu->is_subscription)<span class="text-xs">/月</span>@endif</div>
                                    <button type="button" onclick="selectMenu({{ $menu->id }}, '{{ $menu->name }}', {{ $menu->is_subscription ? $menu->subscription_monthly_price : $menu->price }})"
                                        class="reserve-button">
                                        予約する
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endforeach
        @endif
        </div>
    </div>

        @if((isset($sortedMenus) && $sortedMenus->isEmpty()) || (!isset($sortedMenus) && $menusByDuration->isEmpty()))
            <div class="text-center py-12">
                <p class="text-xl text-gray-500">現在、予約可能なメニューはありません。</p>
                <p class="text-gray-400 mt-2">別のコースをお選びください。</p>
                <a href="{{ route('reservation.select-category') }}" class="mt-6 inline-block px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    コース選択に戻る
                </a>
            </div>
        @endif

        <div class="mt-8 text-center">
            <a href="{{ route('reservation.select-category') }}" class="text-gray-600 hover:text-gray-800 underline text-lg">
                ← コース選択に戻る
            </a>
        </div>
    </div>

    {{-- 料金についての説明 --}}
    <div class="mt-8 bg-blue-50 border-2 border-blue-200 rounded-lg p-6">
        <h4 class="font-bold text-lg mb-2">💰 料金について</h4>
        <ul class="space-y-2 text-gray-700">
            <li>• 表示価格は税込みです</li>
            <li>• 初回ご利用の方は特別割引がございます</li>
            @if($hasSubscription)
                <li>• <span class="text-green-700 font-semibold">サブスクリプション会員様は追加料金なしでご利用いただけます</span></li>
            @endif
            <li>• お支払いは現金またはクレジットカードをご利用いただけます</li>
        </ul>
    </div>
</div>

{{-- アップセルモーダル --}}
<div id="upsellModal" class="fixed inset-0 bg-white bg-opacity-30 backdrop-blur-md hidden z-50 flex items-center justify-center p-4" onclick="closeModalOnBackdrop(event)">
    <div class="bg-white w-full max-w-lg rounded-lg shadow-lg relative">
            {{-- ヘッダー --}}
            <div class="border-b p-5">
                <h3 class="text-lg font-bold">追加オプション</h3>
                <p class="text-sm text-gray-600 mt-1">ご一緒にいかがですか？</p>
            </div>
            
            {{-- オプションリスト --}}
            <div id="optionMenus" class="p-5 max-h-96 overflow-y-auto">
                <!-- オプションメニューがここに動的に挿入されます -->
            </div>
            
            {{-- フッター --}}
            <div class="border-t p-5">
                <button id="confirmOptionsBtn" onclick="confirmWithOptions()" class="w-full bg-gray-400 text-gray-600 font-bold py-3 rounded-lg mb-2 cursor-not-allowed" disabled>
                    追加して次へ
                </button>
                <button onclick="skipOptions()" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg">
                    追加しない
                </button>
            </div>
        </div>
    </div>
</div>

<form id="reservationForm" action="{{ route('reservation.store-menu') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="menu_id" id="selectedMenuId">
    <input type="hidden" name="option_ids" id="selectedOptionIds">
    {{-- コンテキストパラメータを必ず引き継ぐ --}}
    @if(isset($encryptedContext))
        <input type="hidden" name="ctx" value="{{ $encryptedContext }}">
    @endif
    {{-- レガシーパラメータも一時的に保持（後方互換性） --}}
    @if(isset($source))
        <input type="hidden" name="source" value="{{ $source }}">
    @endif
    @if(isset($customer_id))
        <input type="hidden" name="customer_id" value="{{ $customer_id }}">
    @endif
</form>

<script>
    let selectedMenuId = null;
    let selectedOptions = [];

    async function selectMenu(menuId, menuName, menuPrice) {
        selectedMenuId = menuId;
        
        // アップセルメニューを取得
        try {
            const response = await fetch(`/api/menus/upsell?store_id={{ $store->id }}&exclude=${menuId}`);
            const upsellMenus = await response.json();
            
            if (upsellMenus && upsellMenus.length > 0) {
                // オプションメニューがある場合、モーダルを表示
                showUpsellModal(upsellMenus);
            } else {
                // オプションがない場合、直接予約へ
                proceedWithSelection();
            }
        } catch (error) {
            console.error('Error fetching upsell menus:', error);
            // エラーの場合も予約へ進む
            proceedWithSelection();
        }
    }

    function showUpsellModal(upsellMenus) {
        const container = document.getElementById('optionMenus');
        container.innerHTML = '';
        selectedOptions = [];
        
        // オプションが1つの場合は横並び、複数の場合はグリッド表示
        const isMultiple = upsellMenus.length > 1;
        if (isMultiple) {
            container.className = 'p-5 grid grid-cols-1 md:grid-cols-2 gap-3 max-h-96 overflow-y-auto';
        } else {
            container.className = 'p-5 space-y-3 max-h-96 overflow-y-auto';
        }
        
        upsellMenus.forEach(menu => {
            const div = document.createElement('div');
            div.className = isMultiple ? 
                'border rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer bg-white flex flex-col' : 
                'border rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer bg-white';
            div.onclick = () => {
                const checkbox = div.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                toggleOption(menu.id);
            };
            
            // 複数の場合は縦レイアウト、1つの場合は横レイアウト
            if (isMultiple) {
                div.innerHTML = `
                    <div class="flex flex-col h-full">
                        ${menu.image_path ? `
                            <div class="w-full mb-3">
                                <img src="/storage/${menu.image_path}" alt="${menu.name}" class="w-full aspect-video object-cover rounded">
                            </div>
                        ` : ''}
                        <div class="flex-1 flex flex-col">
                            <div class="font-medium text-base mb-1">${menu.name}</div>
                            ${menu.upsell_description ? `<div class="text-sm text-gray-600 line-clamp-2 mb-2">${menu.upsell_description}</div>` : menu.description ? `<div class="text-sm text-gray-600 line-clamp-2 mb-2">${menu.description}</div>` : ''}
                            <div class="mt-auto">
                                <div class="text-lg font-bold text-gray-900 mb-2">¥${Math.floor(menu.price).toLocaleString()}</div>
                                <label class="flex items-center justify-center cursor-pointer bg-gray-100 hover:bg-gray-200 rounded py-2" onclick="event.stopPropagation()">
                                    <input type="checkbox" value="${menu.id}" onchange="toggleOption(${menu.id})" class="w-4 h-4 text-blue-600 rounded">
                                    <span class="ml-2 text-sm font-medium text-gray-700">追加する</span>
                                </label>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                div.innerHTML = `
                    <div class="flex gap-4">
                        ${menu.image_path ? `
                            <div class="w-32 flex-shrink-0">
                                <img src="/storage/${menu.image_path}" alt="${menu.name}" class="w-full aspect-video object-cover rounded">
                            </div>
                        ` : ''}
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-base mb-1">${menu.name}</div>
                            ${menu.upsell_description ? `<div class="text-sm text-gray-600 line-clamp-2 mb-2">${menu.upsell_description}</div>` : menu.description ? `<div class="text-sm text-gray-600 line-clamp-2 mb-2">${menu.description}</div>` : ''}
                            <div class="flex items-center justify-between mt-auto">
                                <div class="text-lg font-bold text-gray-900">¥${Math.floor(menu.price).toLocaleString()}</div>
                                <label class="flex items-center cursor-pointer" onclick="event.stopPropagation()">
                                    <input type="checkbox" value="${menu.id}" onchange="toggleOption(${menu.id})" class="w-4 h-4 text-blue-600 rounded">
                                    <span class="ml-2 text-sm text-gray-700">追加</span>
                                </label>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            container.appendChild(div);
        });
        
        document.getElementById('upsellModal').classList.remove('hidden');
        updateConfirmButton();
    }

    function toggleOption(optionId) {
        const index = selectedOptions.indexOf(optionId);
        if (index > -1) {
            selectedOptions.splice(index, 1);
        } else {
            selectedOptions.push(optionId);
        }
        updateConfirmButton();
    }

    function updateConfirmButton() {
        const confirmBtn = document.getElementById('confirmOptionsBtn');
        if (selectedOptions.length > 0) {
            confirmBtn.disabled = false;
            confirmBtn.className = 'w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg mb-2 cursor-pointer';
        } else {
            confirmBtn.disabled = true;
            confirmBtn.className = 'w-full bg-gray-400 text-gray-600 font-bold py-3 rounded-lg mb-2 cursor-not-allowed';
        }
    }

    function closeModalOnBackdrop(event) {
        if (event.target.id === 'upsellModal') {
            skipOptions();
        }
    }

    function skipOptions() {
        document.getElementById('upsellModal').classList.add('hidden');
        proceedWithSelection();
    }

    function confirmWithOptions() {
        document.getElementById('upsellModal').classList.add('hidden');
        proceedWithSelection();
    }

    function proceedWithSelection() {
        document.getElementById('selectedMenuId').value = selectedMenuId;
        document.getElementById('selectedOptionIds').value = selectedOptions.join(',');
        document.getElementById('reservationForm').submit();
    }
</script>
@endsection