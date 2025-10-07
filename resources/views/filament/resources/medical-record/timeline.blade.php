@php
    $allRecords = $allRecords ?? collect([]);
    $currentRecord = $currentRecord ?? null;
@endphp

<div class="space-y-4">
    @foreach ($allRecords as $record)
        <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
            <div class="grid grid-cols-12 gap-4">
                {{-- 施術日 --}}
                <div class="col-span-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">施術日</dt>
                    <dd class="mt-1">
                        @if ($record->id === $currentRecord?->id)
                            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                {{ $record->treatment_date?->format('Y/m/d') ?? '日付なし' }} (現在表示中)
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20">
                                {{ $record->treatment_date?->format('Y/m/d') ?? '日付なし' }}
                            </span>
                        @endif
                    </dd>
                </div>

                {{-- 店舗 --}}
                <div class="col-span-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">店舗</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $record->reservation?->store?->name ?? '店舗情報なし' }}
                    </dd>
                </div>

                {{-- メニュー --}}
                <div class="col-span-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">メニュー</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $record->reservation?->menu?->name ?? 'メニュー情報なし' }}
                    </dd>
                </div>

                {{-- 担当者 --}}
                <div class="col-span-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">担当者</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $record->handled_by ?? ($record->createdBy?->name ?? '担当者情報なし') }}
                    </dd>
                </div>
            </div>
        </div>
    @endforeach

    @if ($allRecords->isEmpty())
        <div class="text-center py-8 text-gray-500">
            カルテがありません
        </div>
    @endif
</div>
