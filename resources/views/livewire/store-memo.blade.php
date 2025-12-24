<div>
    <!-- メモアイコンボタン -->
    @if($storeId)
    <button
        wire:click="open"
        class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        title="店舗メモ"
    >
        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
    </button>
    @endif

    <!-- モーダル -->
    @if($isOpen)
    <div
        style="position: fixed !important; inset: 0 !important; z-index: 99999 !important;"
        x-data="{ show: true }"
        x-show="show"
        x-transition
    >
        <!-- 背景オーバーレイ -->
        <div
            style="position: fixed !important; inset: 0 !important; background-color: rgba(0, 0, 0, 0.4) !important;"
            wire:click="close"
        ></div>

        <!-- 付箋風モーダル -->
        <div
            style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; z-index: 100000 !important;"
            class="w-full max-w-md"
        >
            <div class="bg-yellow-100 rounded-lg shadow-2xl overflow-hidden" style="min-height: 300px;">
                <!-- ヘッダー（付箋の上部） -->
                <div class="bg-yellow-200 px-4 py-3 flex justify-between items-center border-b border-yellow-300">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-yellow-700 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        @if($canSelectStore)
                            <select
                                wire:change="selectStore($event.target.value)"
                                class="bg-yellow-100 border border-yellow-400 rounded px-2 py-1 text-yellow-800 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-yellow-500"
                            >
                                @foreach($availableStores as $id => $name)
                                    <option value="{{ $id }}" {{ $storeId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            <span class="ml-1 text-yellow-800 font-medium">メモ</span>
                        @else
                            <span class="font-medium text-yellow-800">{{ $storeName }} メモ</span>
                        @endif
                    </div>
                    <button
                        wire:click="close"
                        class="text-yellow-700 hover:text-yellow-900 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- メモ本体 -->
                <div class="p-4">
                    <textarea
                        wire:model="memo"
                        class="w-full bg-transparent border-none resize-none focus:outline-none focus:ring-0 text-gray-800 placeholder-yellow-600"
                        style="height: 400px; font-family: 'Hiragino Kaku Gothic Pro', 'メイリオ', sans-serif;"
                        placeholder="ここにメモを入力...&#10;&#10;例：&#10;・視力測定 3,300円&#10;・トレーニング 5,500円&#10;・初回特別価格 2,200円"
                    ></textarea>
                </div>

                <!-- フッター -->
                <div class="bg-yellow-200 px-4 py-3 flex justify-between items-center border-t border-yellow-300">
                    @if($successMessage)
                        <span class="text-sm text-green-700 font-medium">{{ $successMessage }}</span>
                    @else
                        <span class="text-xs text-yellow-600">改行で複数行入力できます</span>
                    @endif

                    <button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="save">保存</span>
                        <span wire:loading wire:target="save">保存中...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
