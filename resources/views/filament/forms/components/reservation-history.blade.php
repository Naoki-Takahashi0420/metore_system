@php
    $reservations = $getViewData()['reservations'] ?? collect();
@endphp

<div class="space-y-3">
    @if($reservations->isEmpty())
        <div class="text-sm text-gray-500">
            予約履歴がありません。
        </div>
    @else
        <div class="text-sm text-gray-600 mb-3">
            最新10件の予約履歴
        </div>

        @foreach($reservations as $reservation)
            @php
                $statusBadge = match($reservation->status) {
                    'confirmed' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">確定</span>',
                    'booked' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">予約済</span>',
                    'completed' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">完了</span>',
                    'pending' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">保留</span>',
                    'cancelled', 'canceled' => '<span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">キャンセル</span>',
                    'no_show' => '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs">無断キャンセル</span>',
                    default => '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">' . $reservation->status . '</span>'
                };
            @endphp

            <div class="flex justify-between items-center p-3 bg-white border rounded-lg hover:shadow-sm transition-shadow">
                <div class="flex-1">
                    <div class="font-medium text-gray-900">
                        {{ \Carbon\Carbon::parse($reservation->reservation_date)->format('Y年m月d日') }}
                        {{ \Carbon\Carbon::parse($reservation->start_time)->format('H:i') }}〜{{ \Carbon\Carbon::parse($reservation->end_time)->format('H:i') }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span class="font-medium">{{ $reservation->menu->name ?? 'メニュー未設定' }}</span>
                        <span class="text-gray-400 mx-1">/</span>
                        {{ $reservation->store->name ?? '店舗未設定' }}
                    </div>
                    @if($reservation->staff)
                        <div class="text-xs text-gray-500 mt-1">
                            担当: {{ $reservation->staff->name }}
                        </div>
                    @endif
                </div>
                <div class="text-right">
                    {!! $statusBadge !!}
                    @if($reservation->total_amount)
                        <div class="text-sm font-medium text-gray-900 mt-2">
                            ¥{{ number_format($reservation->total_amount) }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="text-xs text-gray-500 text-center mt-3">
            ※ 最新10件のみ表示しています
        </div>
    @endif
</div>