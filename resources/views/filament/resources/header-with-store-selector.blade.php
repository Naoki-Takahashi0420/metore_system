<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-2">
        <label class="text-sm font-medium">店舗：</label>
        <select wire:model.live="{{ $selectedStoreProperty ?? 'selectedStore' }}" class="border rounded px-3 py-1 text-sm">
            <option value="">全店舗</option>
            @foreach($stores as $store)
                <option value="{{ $store->id }}">{{ $store->name }}</option>
            @endforeach
        </select>
    </div>

    @if(isset($actions))
        <div>
            {{ $actions }}
        </div>
    @endif
</div>
