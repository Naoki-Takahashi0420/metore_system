<div class="p-4">
    <img src="{{ $imageUrl }}" alt="{{ $record->title ?? '画像' }}" class="w-full h-auto rounded-lg">
    
    @if($record->description)
        <div class="mt-4">
            <h3 class="font-semibold text-gray-700">説明</h3>
            <p class="text-gray-600">{{ $record->description }}</p>
        </div>
    @endif
    
    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
        <div>
            <span class="font-semibold text-gray-700">タイプ:</span>
            <span class="text-gray-600">
                @switch($record->image_type)
                    @case('before')
                        施術前
                        @break
                    @case('after')
                        施術後
                        @break
                    @case('progress')
                        経過
                        @break
                    @case('reference')
                        参考
                        @break
                    @default
                        その他
                @endswitch
            </span>
        </div>
        <div>
            <span class="font-semibold text-gray-700">顧客表示:</span>
            <span class="text-gray-600">{{ $record->is_visible_to_customer ? '表示' : '非表示' }}</span>
        </div>
    </div>
</div>