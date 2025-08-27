<div class="space-y-6">
    {{-- 基本情報 --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-3">基本情報</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500">顧客名</dt>
                    <dd class="text-sm text-gray-900">{{ $customer->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">電話番号</dt>
                    <dd class="text-sm text-gray-900">{{ $customer->phone ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">メールアドレス</dt>
                    <dd class="text-sm text-gray-900">{{ $customer->email ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-lg font-medium text-blue-900 mb-3">LINE情報</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="text-sm font-medium text-blue-500">LINE User ID</dt>
                    <dd class="text-xs font-mono text-blue-900 break-all">{{ $customer->line_user_id }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-blue-500">登録日時</dt>
                    <dd class="text-sm text-blue-900">{{ $customer->line_registered_at?->format('Y年m月d日 H:i') ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-blue-500">通知設定</dt>
                    <dd class="text-sm">
                        @if($customer->line_notifications_enabled)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                有効
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                無効
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- 流入情報 --}}
    <div class="bg-yellow-50 p-4 rounded-lg">
        <h3 class="text-lg font-medium text-yellow-900 mb-3">流入情報</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <dt class="text-sm font-medium text-yellow-500">登録経路</dt>
                <dd class="text-sm text-yellow-900">
                    @switch($customer->line_registration_source)
                        @case('reservation')
                            予約完了画面
                            @break
                        @case('qr_code')
                            QRコード
                            @break
                        @case('manual')
                            手動登録
                            @break
                        @case('campaign')
                            キャンペーン
                            @break
                        @default
                            不明
                    @endswitch
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-yellow-500">登録元店舗</dt>
                <dd class="text-sm text-yellow-900">{{ $customer->lineRegistrationStore?->name ?? '不明' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-yellow-500">元予約日</dt>
                <dd class="text-sm text-yellow-900">{{ $customer->lineRegistrationReservation?->reservation_date?->format('Y年m月d日') ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    {{-- キャンペーン履歴 --}}
    <div class="bg-purple-50 p-4 rounded-lg">
        <h3 class="text-lg font-medium text-purple-900 mb-3">キャンペーン履歴</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-purple-500">配信回数</dt>
                <dd class="text-2xl font-bold text-purple-900">{{ $customer->campaign_send_count ?? 0 }}回</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-purple-500">最終配信日時</dt>
                <dd class="text-sm text-purple-900">{{ $customer->last_campaign_sent_at?->format('Y年m月d日 H:i') ?? '未配信' }}</dd>
            </div>
        </dl>
    </div>

    {{-- 予約履歴（最近5件） --}}
    @if($customer->reservations->isNotEmpty())
    <div class="bg-green-50 p-4 rounded-lg">
        <h3 class="text-lg font-medium text-green-900 mb-3">最近の予約履歴</h3>
        <div class="space-y-2">
            @foreach($customer->reservations->take(5) as $reservation)
                <div class="flex justify-between items-center py-2 border-b border-green-200 last:border-b-0">
                    <div>
                        <span class="text-sm font-medium text-green-900">{{ $reservation->reservation_date->format('Y/m/d') }}</span>
                        <span class="text-sm text-green-700 ml-2">{{ $reservation->start_time }}</span>
                    </div>
                    <div>
                        <span class="text-sm text-green-700">{{ $reservation->menu?->name ?? '-' }}</span>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full
                            @if($reservation->status === 'completed') bg-green-100 text-green-800
                            @elseif($reservation->status === 'cancelled') bg-red-100 text-red-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            @switch($reservation->status)
                                @case('completed') 完了 @break
                                @case('cancelled') キャンセル @break
                                @case('confirmed') 確定 @break
                                @default 予約中
                            @endswitch
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 統計情報 --}}
    <div class="bg-gray-50 p-4 rounded-lg">
        <h3 class="text-lg font-medium text-gray-900 mb-3">統計情報</h3>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center">
                <dt class="text-sm font-medium text-gray-500">総予約回数</dt>
                <dd class="text-2xl font-bold text-gray-900">{{ $customer->reservations->count() }}</dd>
            </div>
            <div class="text-center">
                <dt class="text-sm font-medium text-gray-500">完了回数</dt>
                <dd class="text-2xl font-bold text-green-600">{{ $customer->reservations->where('status', 'completed')->count() }}</dd>
            </div>
            <div class="text-center">
                <dt class="text-sm font-medium text-gray-500">キャンセル回数</dt>
                <dd class="text-2xl font-bold text-red-600">{{ $customer->reservations->where('status', 'cancelled')->count() }}</dd>
            </div>
            <div class="text-center">
                <dt class="text-sm font-medium text-gray-500">登録経過日数</dt>
                <dd class="text-2xl font-bold text-blue-600">{{ $customer->line_registered_at ? $customer->line_registered_at->diffInDays(now()) : '-' }}</dd>
            </div>
        </dl>
    </div>
</div>