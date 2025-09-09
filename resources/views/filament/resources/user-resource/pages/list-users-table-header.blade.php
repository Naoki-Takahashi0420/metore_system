<div class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
    <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">店舗フィルター：</label>
        <select wire:model.live="storeFilter" 
                class="fi-input block w-auto rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            @foreach($storeOptions as $storeId => $storeName)
                <option value="{{ $storeId }}">{{ $storeName }}</option>
            @endforeach
        </select>
    </div>
</div>