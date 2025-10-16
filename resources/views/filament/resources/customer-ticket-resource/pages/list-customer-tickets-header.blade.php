<div>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-950 dark:text-white">顧客回数券管理</h1>
            <div class="mt-2">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">店舗：</label>
                    <select wire:model.live="storeFilter" class="border rounded px-3 py-1 text-sm">
                        @if($storeOptions && count($storeOptions) > 0)
                            @foreach($storeOptions as $storeId => $storeName)
                                <option value="{{ $storeId }}" {{ $selectedStore == $storeId ? 'selected' : '' }}>
                                    {{ $storeName ?? '' }}
                                </option>
                            @endforeach
                        @else
                            <option value="">店舗がありません</option>
                        @endif
                    </select>
                </div>
            </div>
        </div>

        <!-- 新規作成ボタン -->
        <div>
            <a href="{{ \App\Filament\Resources\CustomerTicketResource::getUrl('create') }}"
               class="inline-flex items-center justify-center gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 border-transparent text-white shadow focus:ring-primary-600 px-4 py-2 text-sm">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>新規作成</span>
            </a>
        </div>
    </div>
</div>
