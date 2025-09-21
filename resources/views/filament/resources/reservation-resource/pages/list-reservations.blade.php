<x-filament-panels::page>
    {{-- ヘッダーアクション（新規予約ボタン等） --}}
    @if (count($actions = $this->getCachedHeaderActions()))
        <x-slot name="headerActions">
            @foreach ($actions as $action)
                {{ $action }}
            @endforeach
        </x-slot>
    @endif

    {{-- ダッシュボードと同じ構成 --}}

    {{-- タイムラインウィジェット --}}
    <div class="fi-wi-stats-overview-stats-ctn grid gap-6 md:grid-cols-1">
        @livewire(\App\Filament\Widgets\TodayReservationTimelineWidget::class)
    </div>

    {{-- スペース --}}
    <div class="h-6"></div>

    {{-- 予約一覧テーブル --}}
    <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-ta-header-ctn divide-y divide-gray-200 dark:divide-white/10">
            <div class="fi-ta-header flex flex-col gap-3 p-4 sm:px-6 sm:flex-row sm:items-center">
                <div class="grid gap-y-1">
                    <h3 class="fi-ta-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        予約一覧
                    </h3>
                    <p class="fi-ta-header-description text-sm text-gray-600 dark:text-gray-400">
                        今日の予約: {{ \App\Models\Reservation::whereDate('reservation_date', now())->count() }}件
                    </p>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>