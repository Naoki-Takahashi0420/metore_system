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

    {{-- タイムラインリンク --}}
    <div class="fi-wi-stats-overview-stats-ctn grid gap-6 md:grid-cols-1">
        <div class="rounded-xl bg-blue-50 p-4 border border-blue-200">
            <p class="text-sm text-blue-800">
                📅 タイムライン表示は
                <a href="/admin" class="font-semibold underline hover:text-blue-600">ダッシュボード</a>
                でご確認ください
            </p>
        </div>
    </div>

    {{-- スペース --}}
    <div class="h-6"></div>

    {{-- 予約一覧テーブル --}}
    {{ $this->table }}
</x-filament-panels::page>