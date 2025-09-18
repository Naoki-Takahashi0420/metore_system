@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-4 md:py-8">
    {{-- ステップインジケーター --}}
    {{-- モバイル版：シンプルな表示 --}}
    <div class="block sm:hidden mb-6">
        <div class="flex justify-center items-center">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                <div class="w-8 h-8 rounded-full bg-gray-300 text-white text-xs flex items-center justify-center">✓</div>
                <div class="w-8 h-8 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">3</div>
                <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 text-xs flex items-center justify-center">4</div>
                <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 text-xs flex items-center justify-center">5</div>
            </div>
        </div>
        <p class="text-center text-sm mt-2 font-bold">ステップ3: スタッフ選択</p>
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
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-white flex items-center justify-center text-lg font-bold">✓</div>
                    <span class="ml-2 text-base text-gray-500">コース</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-blue-500 text-white flex items-center justify-center text-lg font-bold">3</div>
                    <span class="ml-2 text-base font-bold">スタッフ選択</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-gray-500 flex items-center justify-center text-lg font-bold">4</div>
                    <span class="ml-2 text-base text-gray-500">時間・料金</span>
                </div>
                <div class="mx-3 text-gray-400">→</div>
                <div class="flex items-center">
                    <div class="rounded-full h-12 w-12 bg-gray-300 text-gray-500 flex items-center justify-center text-lg font-bold">5</div>
                    <span class="ml-2 text-base text-gray-500">日時選択</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-4 md:p-8">
        <h1 class="text-xl md:text-3xl font-bold text-center mb-2">担当スタッフをお選びください</h1>
        <div class="text-center text-gray-600 mb-6 md:mb-8 text-sm md:text-lg">
            <p>{{ $store->name }}</p>
            <p class="text-sm">{{ $menu->name }} - このメニューはスタッフの指定が必要です</p>
        </div>

        @if($staffs->isEmpty())
            <div class="text-center py-8">
                <div class="text-gray-500 mb-4">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">利用可能なスタッフがいません</h3>
                <p class="text-gray-600 mb-4">現在、このメニューを担当できるスタッフがおりません。</p>
                <a href="{{ route('reservation.select-category') }}" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    メニュー選択に戻る
                </a>
            </div>
        @else
            {{-- スタッフ一覧 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($staffs as $staff)
                    <form action="{{ route('reservation.store-staff') }}" method="POST" class="staff-form">
                        @csrf
                        <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                        <button type="submit" class="w-full text-left group hover:shadow-xl transition-all duration-300">
                            <div class="border-2 border-gray-200 rounded-lg overflow-hidden group-hover:border-blue-500 transition-all">
                                <div class="p-6 group-hover:bg-blue-50 transition-all">
                                    <div class="flex items-center mb-4">
                                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-lg font-bold group-hover:text-blue-600">{{ $staff->name }}</h3>
                                            <p class="text-sm text-gray-600">スタッフ</p>
                                        </div>
                                    </div>

                                    @if($staff->bio)
                                        <p class="text-sm text-gray-600 mb-3 line-clamp-3">{{ $staff->bio }}</p>
                                    @endif

                                    @if($staff->specialties && is_array($staff->specialties) && count($staff->specialties) > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($staff->specialties, 0, 3) as $specialty)
                                                <span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded group-hover:bg-blue-100 group-hover:text-blue-700">
                                                    {{ $specialty }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="mt-4 flex items-center justify-between">
                                        <span class="text-sm text-gray-500">このスタッフを選択</span>
                                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </form>
                @endforeach
            </div>

            {{-- 戻るボタン --}}
            <div class="mt-8 text-center">
                <a href="{{ route('reservation.select-category') }}" class="inline-flex items-center px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    メニュー選択に戻る
                </a>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // フォーム送信時のローディング処理
    const forms = document.querySelectorAll('.staff-form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const button = this.querySelector('button');
            button.disabled = true;
            button.classList.add('opacity-50');

            // ローディングテキストを表示
            const statusText = button.querySelector('.text-sm.text-gray-500');
            if (statusText) {
                statusText.textContent = '選択中...';
            }
        });
    });
});
</script>
@endsection