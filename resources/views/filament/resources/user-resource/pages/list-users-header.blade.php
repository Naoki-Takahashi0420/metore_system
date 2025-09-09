<div>
    <div class="flex justify-between items-start mb-4">
        <h1 class="text-2xl font-bold text-gray-950 dark:text-white">ユーザー管理</h1>
        <div class="flex gap-2">
            @if(\App\Filament\Resources\UserResource::canCreate())
                <a href="{{ \App\Filament\Resources\UserResource::getUrl('create') }}" 
                   class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-primary fi-color-primary fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50 fi-ac-action fi-ac-btn-action">
                    <span class="fi-btn-label">新規作成</span>
                </a>
            @endif
        </div>
    </div>
    <div class="mb-4">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium">店舗：</label>
            <select wire:model.live="storeFilter" class="border rounded px-3 py-1 text-sm">
                @foreach($storeOptions as $storeId => $storeName)
                    <option value="{{ $storeId }}">{{ $storeName }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>