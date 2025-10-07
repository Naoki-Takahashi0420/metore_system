@php
    $reservations = $reservations ?? collect([]);
    $today = \Carbon\Carbon::today();
@endphp

<div class="space-y-4">
    @foreach ($reservations as $reservation)
        @php
            $reservationDate = $reservation->reservation_date ? \Carbon\Carbon::parse($reservation->reservation_date) : null;
            $isFuture = $reservationDate && $reservationDate->isFuture();
            $isPast = $reservationDate && $reservationDate->isPast();
            $isToday = $reservationDate && $reservationDate->isToday();

            $statusLabel = match($reservation->status) {
                'confirmed' => '確定',
                'completed' => '完了',
                'cancelled' => 'キャンセル',
                'no_show' => '無断欠席',
                default => $reservation->status,
            };

            $statusColor = match($reservation->status) {
                'confirmed' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                'completed' => 'bg-green-50 text-green-700 ring-green-600/20',
                'cancelled' => 'bg-red-50 text-red-700 ring-red-600/20',
                'no_show' => 'bg-gray-50 text-gray-700 ring-gray-600/20',
                default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
            };
        @endphp

        <div class="border rounded-lg p-4 bg-white dark:bg-gray-800 {{ $isFuture ? 'ring-2 ring-blue-500' : '' }}">
            <div class="grid grid-cols-12 gap-4 items-start">
                {{-- 予約日時 --}}
                <div class="col-span-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">予約日時</dt>
                    <dd class="mt-1">
                        @if ($isFuture)
                            <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                {{ $reservationDate->format('Y/m/d') }}
                                @if ($reservation->start_time)
                                    {{ date('H:i', strtotime($reservation->start_time)) }}
                                @endif
                                (未来)
                            </span>
                        @elseif ($isToday)
                            <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-sm font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20">
                                {{ $reservationDate->format('Y/m/d') }}
                                @if ($reservation->start_time)
                                    {{ date('H:i', strtotime($reservation->start_time)) }}
                                @endif
                                (本日)
                            </span>
                        @else
                            <span class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $reservationDate?->format('Y/m/d') ?? '日付なし' }}
                                @if ($reservation->start_time)
                                    {{ date('H:i', strtotime($reservation->start_time)) }}
                                @endif
                            </span>
                        @endif
                    </dd>
                </div>

                {{-- 店舗 --}}
                <div class="col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">店舗</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $reservation->store?->name ?? '店舗情報なし' }}
                    </dd>
                </div>

                {{-- メニュー --}}
                <div class="col-span-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">メニュー</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $reservation->menu?->name ?? 'メニュー情報なし' }}
                        @if ($reservation->duration)
                            <span class="text-gray-500">({{ $reservation->duration }}分)</span>
                        @endif
                    </dd>
                </div>

                {{-- 担当スタッフ --}}
                <div class="col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">担当スタッフ</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $reservation->staff?->name ?? '未割当' }}
                    </dd>
                </div>

                {{-- ステータス --}}
                <div class="col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ステータス</dt>
                    <dd class="mt-1">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-sm font-medium ring-1 ring-inset {{ $statusColor }}">
                            {{ $statusLabel }}
                        </span>
                    </dd>
                </div>

                {{-- 備考（もしあれば） --}}
                @if ($reservation->notes)
                    <div class="col-span-12">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">備考</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            {{ $reservation->notes }}
                        </dd>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if ($reservations->isEmpty())
        <div class="text-center py-8 text-gray-500">
            予約がありません
        </div>
    @endif
</div>
