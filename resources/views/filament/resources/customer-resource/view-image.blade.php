<div class="p-4">
    <div class="flex justify-center mb-4">
        <img src="{{ $imageUrl }}" alt="{{ $record->title ?? '画像' }}" class="max-w-full h-auto rounded-lg shadow-lg" style="max-height: 70vh;">
    </div>

    @if($record->title)
        <h3 class="text-lg font-semibold mb-2">{{ $record->title }}</h3>
    @endif

    @if($record->description)
        <p class="text-gray-600 dark:text-gray-400 mb-2">{{ $record->description }}</p>
    @endif

    <div class="grid grid-cols-2 gap-4 text-sm text-gray-500 dark:text-gray-400">
        @if($record->image_type)
            <div>
                <span class="font-semibold">タイプ:</span>
                @switch($record->image_type)
                    @case('vision_test') 視力検査 @break
                    @case('before') 施術前 @break
                    @case('after') 施術後 @break
                    @case('progress') 経過 @break
                    @case('reference') 参考資料 @break
                    @default その他
                @endswitch
            </div>
        @endif

        @if($record->uploader)
            <div>
                <span class="font-semibold">アップロード者:</span> {{ $record->uploader->name }}
            </div>
        @endif

        @if($record->created_at)
            <div>
                <span class="font-semibold">登録日:</span> {{ $record->created_at->format('Y/m/d H:i') }}
            </div>
        @endif

        <div>
            <span class="font-semibold">顧客表示:</span> {{ $record->is_visible_to_customer ? 'はい' : 'いいえ' }}
        </div>
    </div>
</div>
