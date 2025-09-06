<x-filament-panels::page>
    @if(auth()->user()->hasRole('super_admin'))
        <div class="mb-4">
            <label for="store-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                店舗選択
            </label>
            <select 
                id="store-select"
                wire:model.live="selectedStoreId"
                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
            >
                @foreach(\App\Models\Store::orderBy('name')->get() as $store)
                    <option value="{{ $store->id }}">{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    
    {{-- Widgetsはヘッダーで自動的に読み込まれます --}}
</x-filament-panels::page>