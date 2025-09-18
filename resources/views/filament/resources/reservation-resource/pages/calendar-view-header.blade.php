<div class="mb-4">
    <label for="store-filter" class="block text-sm font-medium text-gray-700 mb-2">
        店舗フィルター:
    </label>
    <select
        id="store-filter"
        wire:model.live="storeFilter"
        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
    >
        @foreach($storeOptions as $id => $name)
            <option value="{{ $id }}" @if($selectedStore == $id) selected @endif>
                {{ $name }}
            </option>
        @endforeach
    </select>
</div>