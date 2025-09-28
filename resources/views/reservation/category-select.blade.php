@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-4 md:py-8">
    {{-- ステップインジケーター --}}
    {{-- モバイル版：シンプルな表示 --}}
    <div class="block sm:hidden mb-6">
        <div class="flex justify-center items-center">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                <div class="w-8 h-8 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">2</div>
                <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 text-xs flex items-center justify-center">3</div>
                <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 text-xs flex items-center justify-center">4</div>
            </div>
        </div>
        <p class="text-center text-sm mt-2 font-bold">ステップ2: コース選択</p>
    </div>

    {{-- PC版：詳細表示 --}}
    <div class="hidden sm:block mb-8">
        <div class="flex items-center justify-center">
            <div class="flex items-center">
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">✓</div>
                    <span class="ml-2 text-base text-gray-500">店舗</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-blue-500 text-white flex items-center justify-center text-lg font-bold">2</div>
                    <span class="ml-2 text-base font-bold">コース</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-gray-500 flex items-center justify-center text-lg font-bold">3</div>
                    <span class="ml-2 text-base text-gray-500">時間・料金</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-gray-500 flex items-center justify-center text-lg font-bold">4</div>
                    <span class="ml-2 text-base text-gray-500">日時選択</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-4 md:p-8">
        <h1 class="text-xl md:text-3xl font-bold text-center mb-2">コースをお選びください</h1>
        <p class="text-center text-gray-600 mb-6 md:mb-8 text-sm md:text-lg">{{ $store->name }}のメニューカテゴリー</p>

        {{-- PCでは2列、モバイルでは1列のグリッドレイアウト --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($categories as $category)
                <form action="{{ route('reservation.select-time') }}" method="POST" class="category-form">
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $category->id }}">
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
                    
                    <button type="submit" class="w-full text-left group hover:shadow-xl transition-all duration-300">
                        <div class="border-2 border-gray-200 rounded-lg overflow-hidden group-hover:border-blue-500 transition-all">
                            {{-- カテゴリー画像（16:9アスペクト比固定） --}}
                            @if($category->image_path)
                                <div class="relative aspect-[16/9] bg-gray-100 overflow-hidden">
                                    <img src="{{ Storage::url($category->image_path) }}" 
                                         alt="{{ $category->name }}" 
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                                    <div class="absolute bottom-0 left-0 right-0 p-4">
                                        <h3 class="text-white text-xl md:text-2xl font-bold drop-shadow-lg">
                                            {{ $category->name }}
                                        </h3>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="p-4 md:p-6 group-hover:bg-blue-50 transition-all">
                                @if(!$category->image_path)
                                    <h3 class="text-lg md:text-2xl font-bold mb-2 md:mb-3 group-hover:text-blue-600">
                                        {{ $category->name }}
                                    </h3>
                                @endif
                                
                                @if($category->description)
                                    <p class="text-gray-600 mb-3 md:mb-4 text-sm md:text-base leading-relaxed">
                                        {{ $category->description }}
                                    </p>
                                @endif
                                
                                {{-- 代表的なメニュー表示 --}}
                                @php
                                    $sampleMenus = $category->menus()->where('is_available', true)->take(3)->get();
                                @endphp
                                @if($sampleMenus->isNotEmpty())
                                    <div class="mb-3">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($sampleMenus as $menu)
                                                <span class="inline-block px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs md:text-sm">
                                                    {{ $menu->name }}
                                                </span>
                                            @endforeach
                                            @if($category->menus_count > 3)
                                                <span class="inline-block px-2 py-1 bg-gray-100 text-gray-500 rounded text-xs md:text-sm">
                                                    他{{ $category->menus_count - 3 }}種類
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="flex items-center justify-between">
                                    <div class="text-xs md:text-sm text-gray-500">
                                        @if($category->menus_count > 0)
                                            <span class="inline-flex items-center px-2 md:px-3 py-1 rounded-full text-xs md:text-sm font-medium bg-green-100 text-green-800">
                                                {{ $category->menus_count }}種類のメニュー
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="text-blue-500 group-hover:text-blue-700">
                                        <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>

        @if($categories->isEmpty())
            <div class="text-center py-12">
                <p class="text-xl text-gray-500">現在、予約可能なコースはありません。</p>
                <p class="text-gray-400 mt-2">別の店舗をお選びください。</p>
                <a href="/stores" class="mt-6 inline-block px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    店舗選択に戻る
                </a>
            </div>
        @endif

        <div class="mt-6 md:mt-8 text-center">
            <a href="/stores" class="text-gray-600 hover:text-gray-800 underline text-sm md:text-lg">
                ← 店舗選択に戻る
            </a>
        </div>
    </div>

    {{-- 注意事項 --}}
    <div class="mt-6 md:mt-8 bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4 md:p-6">
        <h4 class="font-bold text-base md:text-lg mb-2">📌 ご予約の流れ</h4>
        <ol class="list-decimal list-inside space-y-1 md:space-y-2 text-xs md:text-base text-gray-700">
            <li>ご希望のコースをお選びください</li>
            <li>施術時間と料金をご確認ください</li>
            <li>カレンダーから空き時間をお選びください</li>
            <li>お客様情報を入力して予約完了です</li>
        </ol>
    </div>
</div>
@endsection

