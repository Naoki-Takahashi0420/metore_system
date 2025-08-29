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
                    <div class="rounded-full h-12 w-12 bg-blue-500 text-white flex items-center justify-center text-lg font-bold">2</div>
                    <span class="ml-2 font-bold">コース選択</span>
                </div>
                <div class="mx-4 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-gray-500 flex items-center justify-center text-lg font-bold">3</div>
                    <span class="ml-2 text-gray-500">時間・料金</span>
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
        <h1 class="text-3xl font-bold text-center mb-2">コースをお選びください</h1>
        <p class="text-center text-gray-600 mb-8 text-lg">{{ $store->name }}のメニューカテゴリー</p>

        <div class="grid md:grid-cols-2 gap-6">
            @foreach($categories as $category)
                <form action="{{ route('reservation.select-time') }}" method="POST">
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $category->id }}">
                    
                    <button type="submit" class="w-full text-left group hover:shadow-xl transition-all duration-300">
                        <div class="border-2 border-gray-200 rounded-lg p-6 group-hover:border-blue-500 group-hover:bg-blue-50 transition-all">
                            <h3 class="text-2xl font-bold mb-3 group-hover:text-blue-600">
                                {{ $category->name }}
                            </h3>
                            
                            @if($category->description)
                                <p class="text-gray-600 mb-4 text-lg leading-relaxed">
                                    {{ $category->description }}
                                </p>
                            @endif
                            
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-500">
                                    @if($category->menus_count > 0)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            {{ $category->menus_count }}種類のメニュー
                                        </span>
                                    @endif
                                </div>
                                
                                <div class="text-blue-500 group-hover:text-blue-700">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
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
                <a href="{{ route('reservation.select-store') }}" class="mt-6 inline-block px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    店舗選択に戻る
                </a>
            </div>
        @endif

        <div class="mt-8 text-center">
            <a href="{{ route('reservation.select-store') }}" class="text-gray-600 hover:text-gray-800 underline text-lg">
                ← 店舗選択に戻る
            </a>
        </div>
    </div>

    {{-- 注意事項 --}}
    <div class="mt-8 bg-yellow-50 border-2 border-yellow-200 rounded-lg p-6">
        <h4 class="font-bold text-lg mb-2">📌 ご予約の流れ</h4>
        <ol class="list-decimal list-inside space-y-2 text-gray-700">
            <li>ご希望のコースをお選びください</li>
            <li>施術時間と料金をご確認ください</li>
            <li>カレンダーから空き時間をお選びください</li>
            <li>お客様情報を入力して予約完了です</li>
        </ol>
    </div>
</div>
@endsection