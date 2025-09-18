<x-filament-panels::page>
    @if ($this->hasInfolist())
        {{ $this->infolist }}
    @else
        <x-filament::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
        />
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>