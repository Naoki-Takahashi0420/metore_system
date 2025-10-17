<x-filament-panels::page>
    <div class="space-y-4">
        <!-- ツールバー -->
        <div class="flex justify-between items-center">
            <!-- フィルター -->
            <div class="flex gap-2">
                @foreach([
                    'all' => 'すべて',
                    'reservation' => '予約',
                    'email' => 'メール',
                    'admin_notification' => '管理者通知',
                    'auth' => '認証',
                    'error' => 'エラー'
                ] as $key => $label)
                    <button
                        wire:click="setFilter('{{ $key }}')"
                        type="button"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition {{ $filter === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 ring-1 ring-inset ring-gray-300' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- アクション -->
            <div class="flex gap-2">
                <button
                    wire:click="refreshLogs"
                    type="button"
                    class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white rounded-md hover:bg-gray-50 ring-1 ring-inset ring-gray-300"
                >
                    更新
                </button>
                <button
                    wire:click="clearLogs"
                    onclick="return confirm('本当にログをクリアしますか？')"
                    type="button"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-danger-600 rounded-md hover:bg-danger-700"
                >
                    ログクリア
                </button>
            </div>
        </div>

        <!-- ログ表示 -->
        <div class="space-y-2">
            @if(count($logs) === 0)
                <div class="bg-white p-8 rounded-lg border text-center text-gray-500 text-sm">
                    ログがありません
                </div>
            @else
                @foreach($logs as $log)
                    @php
                        $log = is_array($log) ? $log : [];

                        $levelColors = match($log['level'] ?? 'info') {
                            'error' => 'border-l-4 border-l-red-500',
                            'warning' => 'border-l-4 border-l-yellow-500',
                            'info' => 'border-l-4 border-l-blue-500',
                            'debug' => 'border-l-4 border-l-gray-400',
                            default => 'border-l-4 border-l-gray-300'
                        };

                        $badgeColor = match($log['type'] ?? 'other') {
                            'reservation' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
                            'email' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
                            'admin_notification' => 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
                            'auth' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
                            'error' => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
                            default => 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20'
                        };

                        $typeLabel = match($log['type'] ?? 'other') {
                            'reservation' => '予約',
                            'email' => 'メール送信',
                            'admin_notification' => '管理者通知',
                            'auth' => '認証',
                            'error' => 'エラー',
                            default => 'その他'
                        };

                        // 5W1H情報を取得
                        $fiveW1H = $log['five_w_one_h'] ?? [];
                        $who = $fiveW1H['who'] ?? null;
                        $what = $fiveW1H['what'] ?? null;
                        $when = $log['timestamp'] ?? date('Y-m-d H:i:s');
                        $where = $fiveW1H['where'] ?? null;
                        $why = $fiveW1H['why'] ?? null;
                        $how = $fiveW1H['how'] ?? null;
                        $content = $log['content'] ?? 'ログ内容がありません';
                    @endphp

                    <div class="bg-white border rounded-lg {{ $levelColors }} overflow-hidden">
                        <div class="p-4">
                            <!-- ヘッダー -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded {{ $badgeColor }}">
                                        {{ $typeLabel }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ $when }}
                                    </span>
                                </div>
                            </div>

                            <!-- 5W1H情報 -->
                            <div class="space-y-1.5 text-sm">
                                @if($who)
                                    <div class="flex">
                                        <span class="w-24 text-gray-500">Who:</span>
                                        <span class="text-gray-900 font-medium">{{ $who }}</span>
                                    </div>
                                @endif

                                @if($what)
                                    <div class="flex">
                                        <span class="w-24 text-gray-500">What:</span>
                                        <span class="text-gray-900 font-medium">{{ $what }}</span>
                                    </div>
                                @endif

                                @if($where)
                                    <div class="flex">
                                        <span class="w-24 text-gray-500">Where:</span>
                                        <span class="text-gray-900">{{ $where }}</span>
                                    </div>
                                @endif

                                @if($why)
                                    <div class="flex">
                                        <span class="w-24 text-gray-500">Why:</span>
                                        <span class="text-gray-900">{{ $why }}</span>
                                    </div>
                                @endif

                                @if($how)
                                    <div class="flex">
                                        <span class="w-24 text-gray-500">How:</span>
                                        <span class="text-gray-900">{{ $how }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- 詳細ログ -->
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700 select-none">
                                    詳細を表示
                                </summary>
                                <pre class="mt-2 p-3 bg-gray-50 text-gray-800 text-xs rounded border overflow-x-auto">{{ $content }}</pre>
                            </details>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <!-- ページング情報 -->
        @if(count($logs) > 0)
            <div class="text-center text-xs text-gray-500 mt-4 pb-4">
                最新100件を表示中
            </div>
        @endif
    </div>
</x-filament-panels::page>
