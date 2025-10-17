<x-filament-panels::page>
    <div class="space-y-6">
        <!-- フィルターボタン -->
        <div class="flex gap-2 flex-wrap">
            <button
                wire:click="setFilter('all')"
                class="px-4 py-2 rounded-lg {{ $filter === 'all' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                すべて
            </button>
            <button
                wire:click="setFilter('reservation')"
                class="px-4 py-2 rounded-lg {{ $filter === 'reservation' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                📅 予約
            </button>
            <button
                wire:click="setFilter('email')"
                class="px-4 py-2 rounded-lg {{ $filter === 'email' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                📧 メール送信
            </button>
            <button
                wire:click="setFilter('admin_notification')"
                class="px-4 py-2 rounded-lg {{ $filter === 'admin_notification' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                🔔 管理者通知
            </button>
            <button
                wire:click="setFilter('auth')"
                class="px-4 py-2 rounded-lg {{ $filter === 'auth' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                🔐 認証
            </button>
            <button
                wire:click="setFilter('error')"
                class="px-4 py-2 rounded-lg {{ $filter === 'error' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                ❌ エラー
            </button>
        </div>

        <!-- アクションボタン -->
        <div class="flex gap-2">
            <button
                wire:click="refreshLogs"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
            >
                🔄 更新
            </button>
            <button
                wire:click="clearLogs"
                onclick="return confirm('本当にログをクリアしますか？')"
                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
            >
                🗑️ ログクリア
            </button>
        </div>

        <!-- ログ表示 -->
        <div class="space-y-3">
            @if(count($logs) === 0)
                <div class="bg-gray-100 p-6 rounded-lg text-center text-gray-600">
                    ログがありません
                </div>
            @else
                @foreach($logs as $log)
                    @php
                        $bgColor = match($log['level']) {
                            'error' => 'bg-red-50 border-red-300',
                            'warning' => 'bg-yellow-50 border-yellow-300',
                            'info' => 'bg-blue-50 border-blue-300',
                            'debug' => 'bg-gray-50 border-gray-300',
                            default => 'bg-white border-gray-200'
                        };

                        $badgeColor = match($log['type']) {
                            'reservation' => 'bg-blue-100 text-blue-800',
                            'email' => 'bg-green-100 text-green-800',
                            'admin_notification' => 'bg-purple-100 text-purple-800',
                            'auth' => 'bg-yellow-100 text-yellow-800',
                            'error' => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800'
                        };

                        $typeLabel = match($log['type']) {
                            'reservation' => '📅 予約',
                            'email' => '📧 メール送信',
                            'admin_notification' => '🔔 管理者通知',
                            'auth' => '🔐 認証',
                            'error' => '❌ エラー',
                            default => '📄 その他'
                        };

                        // 5W1H情報を取得
                        $fiveW1H = $log['five_w_one_h'] ?? [];
                        $who = $fiveW1H['who'] ?? null;
                        $what = $fiveW1H['what'] ?? null;
                        $when = $log['timestamp'];
                        $where = $fiveW1H['where'] ?? null;
                        $why = $fiveW1H['why'] ?? null;
                        $how = $fiveW1H['how'] ?? null;
                    @endphp

                    <div class="border-2 {{ $bgColor }} rounded-lg p-4">
                        <!-- ヘッダー：タイプとタイムスタンプ -->
                        <div class="flex justify-between items-start mb-3">
                            <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $badgeColor }}">
                                {{ $typeLabel }}
                            </span>
                            <span class="text-sm text-gray-600">
                                ⏰ {{ $when }}
                            </span>
                        </div>

                        <!-- 5W1H情報 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3 text-sm">
                            @if($who)
                                <div class="bg-white bg-opacity-50 p-2 rounded">
                                    <span class="font-semibold text-gray-700">👤 Who（誰が）:</span>
                                    <span class="text-gray-900">{{ $who }}</span>
                                </div>
                            @endif

                            @if($what)
                                <div class="bg-white bg-opacity-50 p-2 rounded">
                                    <span class="font-semibold text-gray-700">📋 What（何を）:</span>
                                    <span class="text-gray-900">{{ $what }}</span>
                                </div>
                            @endif

                            @if($where)
                                <div class="bg-white bg-opacity-50 p-2 rounded">
                                    <span class="font-semibold text-gray-700">📍 Where（どこで）:</span>
                                    <span class="text-gray-900">{{ $where }}</span>
                                </div>
                            @endif

                            @if($why)
                                <div class="bg-white bg-opacity-50 p-2 rounded">
                                    <span class="font-semibold text-gray-700">❓ Why（なぜ）:</span>
                                    <span class="text-gray-900">{{ $why }}</span>
                                </div>
                            @endif

                            @if($how)
                                <div class="bg-white bg-opacity-50 p-2 rounded">
                                    <span class="font-semibold text-gray-700">🔧 How（どのように）:</span>
                                    <span class="text-gray-900">{{ $how }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- 詳細ログ（折りたたみ可能） -->
                        <details class="mt-2">
                            <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900">
                                📄 詳細を表示
                            </summary>
                            <pre class="mt-2 p-3 bg-gray-900 text-green-400 text-xs rounded overflow-x-auto">{{ $log['content'] }}</pre>
                        </details>
                    </div>
                @endforeach
            @endif
        </div>

        <!-- ページング情報 -->
        <div class="text-center text-sm text-gray-600">
            最新100件のログを表示中
        </div>
    </div>

    @push('scripts')
    <script>
        // Livewireのnotifyイベントを受け取る
        window.addEventListener('notify', event => {
            if (event.detail && event.detail.message) {
                alert(event.detail.message);
            }
        });
    </script>
    @endpush
</x-filament-panels::page>
