<div>
    <h1 class="text-2xl font-bold text-gray-950 dark:text-white mb-4">メニュー管理</h1>
    <div class="mb-4">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium">店舗：</label>
            <select wire:model.live="selectedStore" class="border rounded px-3 py-1 text-sm">
                @foreach($storeOptions as $storeId => $storeName)
                    <option value="{{ $storeId }}">{{ $storeName }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>