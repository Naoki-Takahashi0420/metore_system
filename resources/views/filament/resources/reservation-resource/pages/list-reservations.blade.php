<x-filament-panels::page>
    <div class="space-y-6">
        <!-- タイムラインウィジェットを表示 -->
        @livewire(\App\Filament\Widgets\TodayReservationTimelineWidget::class)

        <!-- 予約テーブル -->
        <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>