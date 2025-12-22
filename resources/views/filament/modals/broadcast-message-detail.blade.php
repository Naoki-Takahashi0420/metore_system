<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm text-gray-500">ステータス</span>
            <p class="font-medium">
                @switch($record->status)
                    @case('draft')
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">下書き</span>
                        @break
                    @case('scheduled')
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">予約済み</span>
                        @break
                    @case('sending')
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">送信中</span>
                        @break
                    @case('sent')
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">送信完了</span>
                        @break
                    @case('failed')
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">送信失敗</span>
                        @break
                @endswitch
            </p>
        </div>
        <div>
            <span class="text-sm text-gray-500">作成日時</span>
            <p class="font-medium">{{ $record->created_at->format('Y/m/d H:i') }}</p>
        </div>
    </div>

    @if($record->scheduled_at)
    <div>
        <span class="text-sm text-gray-500">予約送信日時</span>
        <p class="font-medium">{{ $record->scheduled_at->format('Y/m/d H:i') }}</p>
    </div>
    @endif

    @if($record->sent_at)
    <div>
        <span class="text-sm text-gray-500">送信完了日時</span>
        <p class="font-medium">{{ $record->sent_at->format('Y/m/d H:i') }}</p>
    </div>
    @endif

    <div>
        <span class="text-sm text-gray-500">件名</span>
        <p class="font-medium">{{ $record->subject }}</p>
    </div>

    <div>
        <span class="text-sm text-gray-500">メッセージ本文</span>
        <div class="mt-1 p-3 bg-gray-50 rounded-lg whitespace-pre-wrap text-sm">{{ $record->message }}</div>
    </div>

    @if($record->status === 'sent' || $record->status === 'failed')
    <div class="border-t pt-4">
        <span class="text-sm text-gray-500 block mb-2">送信結果</span>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-gray-50 p-3 rounded-lg text-center">
                <span class="text-2xl font-bold text-gray-700">{{ $record->total_recipients }}</span>
                <span class="block text-xs text-gray-500">送信対象</span>
            </div>
            <div class="bg-green-50 p-3 rounded-lg text-center">
                <span class="text-2xl font-bold text-green-600">{{ $record->success_count }}</span>
                <span class="block text-xs text-gray-500">成功</span>
            </div>
            <div class="bg-blue-50 p-3 rounded-lg text-center">
                <span class="text-2xl font-bold text-blue-600">{{ $record->line_count }}</span>
                <span class="block text-xs text-gray-500">LINE</span>
            </div>
            <div class="bg-purple-50 p-3 rounded-lg text-center">
                <span class="text-2xl font-bold text-purple-600">{{ $record->email_count }}</span>
                <span class="block text-xs text-gray-500">メール</span>
            </div>
        </div>
        @if($record->failed_count > 0)
        <div class="mt-2 bg-red-50 p-3 rounded-lg text-center">
            <span class="text-lg font-bold text-red-600">{{ $record->failed_count }}</span>
            <span class="text-sm text-red-600">件の送信に失敗</span>
        </div>
        @endif
    </div>
    @endif
</div>
