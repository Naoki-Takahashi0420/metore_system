<div>
    <!-- ローディングオーバーレイ -->
    <div wire:loading style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); text-align: center; min-width: 300px;">
            <div style="margin-bottom: 15px;">
                <svg style="width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto; color: #3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <p style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 5px;">読み込み中...</p>
            <p style="font-size: 14px; color: #6b7280;">データを取得しています</p>
        </div>
    </div>

    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-950 dark:text-white">予約管理</h1>
            <div class="mt-2">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    <select wire:model.live="storeFilter" class="border rounded px-3 py-1 text-sm">
                        @foreach($storeOptions as $storeId => $storeName)
                            <option value="{{ $storeId }}" {{ $selectedStore == $storeId ? 'selected' : '' }}>
                                {{ $storeName }}
                            </option>
                        @endforeach
                    </select>
                    <!-- ローディング中テキスト -->
                    <span wire:loading wire:target="storeFilter" style="font-size: 14px; color: #3b82f6; font-weight: 500; animation: pulse 1.5s ease-in-out infinite;">
                        データ読み込み中...
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>