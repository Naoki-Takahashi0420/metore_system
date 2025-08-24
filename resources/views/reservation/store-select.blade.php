<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>店舗選択 - Xsyumeno</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .store-card {
            transition: all 0.3s ease;
        }
        .store-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- ヘッダー -->
        <div class="text-center mb-8 fade-in">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">店舗を選択</h1>
            <p class="text-gray-600">ご希望の店舗をお選びください</p>
        </div>

        <!-- 店舗リスト -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6" id="storeList">
            @forelse($stores as $index => $store)
                <div class="store-card bg-white rounded-lg shadow-md overflow-hidden cursor-pointer border-2 border-transparent hover:border-blue-500"
                     style="animation-delay: {{ $index * 0.1 }}s; opacity: 0; animation: fadeIn 0.5s ease-out {{ $index * 0.1 }}s forwards;"
                     onclick="selectStore({{ $store->id }})">
                    
                    @if($store->image_path)
                        <div class="h-48 bg-gray-200">
                            <img src="/storage/{{ $store->image_path }}" alt="{{ $store->name }}" 
                                 class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="h-48 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                            <svg class="w-24 h-24 text-white opacity-50" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
                            </svg>
                        </div>
                    @endif
                    
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">{{ $store->name }}</h3>
                        
                        @if($store->address)
                            <div class="flex items-start mb-2">
                                <svg class="w-4 h-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span class="text-sm text-gray-600">{{ $store->address }}</span>
                            </div>
                        @endif
                        
                        @if($store->phone)
                            <div class="flex items-center mb-2">
                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <span class="text-sm text-gray-600">{{ $store->phone }}</span>
                            </div>
                        @endif
                        
                        <!-- 営業時間 -->
                        @if($store->business_hours)
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <p class="text-xs text-gray-500 mb-1">営業時間</p>
                                @php
                                    $today = now()->dayOfWeek;
                                    $days = ['日', '月', '火', '水', '木', '金', '土'];
                                    $todayHours = $store->business_hours[$today] ?? null;
                                @endphp
                                @if($todayHours && isset($todayHours['open']) && isset($todayHours['close']))
                                    <p class="text-sm font-medium text-gray-700">
                                        本日: {{ $todayHours['open'] }} - {{ $todayHours['close'] }}
                                    </p>
                                @else
                                    <p class="text-sm text-red-500">本日: 休業日</p>
                                @endif
                            </div>
                        @endif
                        
                        <div class="mt-4">
                            <button class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold">
                                この店舗を選択
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <p class="text-gray-500">現在、予約可能な店舗がありません。</p>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- 隠しフォーム -->
        <form id="storeForm" action="{{ route('reservation.store-store') }}" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="store_id" id="selectedStoreId">
        </form>
    </div>

    <script>
        function selectStore(storeId) {
            // ローディング表示
            const storeCards = document.querySelectorAll('.store-card');
            storeCards.forEach(card => {
                if (!card.getAttribute('onclick').includes(storeId)) {
                    card.style.opacity = '0.5';
                    card.style.pointerEvents = 'none';
                }
            });
            
            // フォーム送信
            document.getElementById('selectedStoreId').value = storeId;
            document.getElementById('storeForm').submit();
        }
        
        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.store-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                }, index * 100);
            });
        });
    </script>
</body>
</html>