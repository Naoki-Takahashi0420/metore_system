@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    {{-- ステップインジケーター --}}
    <div class="mb-8">
        <div class="flex items-center justify-center">
            <div class="flex items-center">
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">1</div>
                    <span class="ml-2 text-gray-500">店舗</span>
                </div>
                <div class="mx-4 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">2</div>
                    <span class="ml-2 text-gray-500">コース</span>
                </div>
                <div class="mx-4 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-blue-500 text-white flex items-center justify-center text-lg font-bold">3</div>
                    <span class="ml-2 font-bold">時間・料金</span>
                </div>
                <div class="mx-4 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-gray-500 flex items-center justify-center text-lg font-bold">4</div>
                    <span class="ml-2 text-gray-500">日時選択</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-3xl font-bold text-center mb-2">施術時間をお選びください</h1>
        <p class="text-center text-gray-600 mb-2 text-lg">{{ $store->name }}</p>
        <p class="text-center text-blue-600 mb-8 text-xl font-semibold">{{ $category->name }}</p>

        @if($hasSubscription)
            <div class="mb-6 bg-green-50 border-2 border-green-300 rounded-lg p-4 text-center">
                <span class="text-green-700 font-bold text-lg">✨ サブスクリプション会員様限定メニューも表示されています</span>
            </div>
        @endif

        <div class="space-y-8">
            @foreach($menusByDuration as $duration => $menus)
                <div class="border-2 border-gray-200 rounded-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-center bg-gray-100 rounded-lg py-3">
                        @if($duration > 0)
                            ⏰ {{ $duration }}分コース
                        @else
                            オプションメニュー
                        @endif
                    </h2>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        @foreach($menus as $menu)
                            <form action="{{ route('reservation.store-menu') }}" method="POST">
                                @csrf
                                <input type="hidden" name="menu_id" value="{{ $menu->id }}">
                                
                                <button type="submit" class="w-full text-left group">
                                    <div class="border rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 transition-all group-hover:shadow-lg">
                                        <div class="flex justify-between items-start mb-2">
                                            <h3 class="text-lg font-semibold group-hover:text-blue-600">
                                                {{ $menu->name }}
                                            </h3>
                                            <div class="text-right">
                                                @if($menu->is_subscription_only)
                                                    <span class="inline-block px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full mb-1">
                                                        サブスク限定
                                                    </span>
                                                @endif
                                                <p class="text-2xl font-bold text-blue-600">
                                                    ¥{{ number_format($menu->price) }}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        @if($menu->description)
                                            <p class="text-gray-600 text-sm mb-3">
                                                {{ $menu->description }}
                                            </p>
                                        @endif
                                        
                                        <div class="flex justify-between items-center">
                                            <div class="text-sm text-gray-500">
                                                @if($menu->duration_minutes)
                                                    施術時間: {{ $menu->duration_minutes }}分
                                                @endif
                                            </div>
                                            
                                            <span class="text-blue-500 group-hover:text-blue-700 text-sm font-semibold">
                                                選択する →
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if($menusByDuration->isEmpty())
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
@endsection