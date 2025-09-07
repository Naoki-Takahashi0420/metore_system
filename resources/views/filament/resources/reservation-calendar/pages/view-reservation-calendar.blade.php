<x-filament-panels::page>
    <div class="fi-page-header-heading-wrapper">
        @if(auth()->user()->hasRole('super_admin'))
            <div class="flex items-center gap-4 mb-4">
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        wire:model.live="selectedStoreId"
                        class="w-64"
                    >
                        <option value="">全店舗</option>
                        @foreach(\App\Models\Store::orderBy('name')->get() as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif
    </div>
    
    {{-- Widgetsはヘッダーで自動的に読み込まれます --}}
</x-filament-panels::page>