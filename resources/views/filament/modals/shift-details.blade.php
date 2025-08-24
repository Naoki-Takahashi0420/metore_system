@if($shift)
<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600">スタッフ</p>
            <p class="font-semibold">{{ $shift->user->name }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">店舗</p>
            <p class="font-semibold">{{ $shift->store->name }}</p>
        </div>
    </div>
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600">日付</p>
            <p class="font-semibold">{{ $shift->shift_date->format('Y年m月d日') }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">勤務時間</p>
            <p class="font-semibold">{{ Carbon\Carbon::parse($shift->start_time)->format('H:i') }} - {{ Carbon\Carbon::parse($shift->end_time)->format('H:i') }}</p>
        </div>
    </div>
    
    @if($shift->break_start && $shift->break_end)
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600">休憩時間</p>
            <p class="font-semibold">{{ Carbon\Carbon::parse($shift->break_start)->format('H:i') }} - {{ Carbon\Carbon::parse($shift->break_end)->format('H:i') }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600">実働時間</p>
            <p class="font-semibold">{{ $shift->working_hours }}時間</p>
        </div>
    </div>
    @endif
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600">ステータス</p>
            <p class="font-semibold">
                @switch($shift->status)
                    @case('scheduled')
                        <span class="text-gray-600">予定</span>
                        @break
                    @case('working')
                        <span class="text-amber-600">勤務中</span>
                        @break
                    @case('completed')
                        <span class="text-green-600">完了</span>
                        @break
                    @case('cancelled')
                        <span class="text-red-600">キャンセル</span>
                        @break
                @endswitch
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-600">予約受付</p>
            <p class="font-semibold">
                @if($shift->is_available_for_reservation)
                    <span class="text-green-600">受付可能</span>
                @else
                    <span class="text-gray-400">受付不可</span>
                @endif
            </p>
        </div>
    </div>
    
    @if($shift->notes)
    <div>
        <p class="text-sm text-gray-600">備考</p>
        <p class="font-semibold">{{ $shift->notes }}</p>
    </div>
    @endif
</div>
@else
<p class="text-gray-500">シフト情報が見つかりません</p>
@endif