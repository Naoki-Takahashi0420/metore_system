<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE設定テスト</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6 max-w-6xl">
        <h1 class="text-3xl font-bold mb-8 text-gray-800">🔧 LINE Bot 動作確認ページ</h1>

        <!-- 設定状態 -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">📋 設定状態</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Channel ID</p>
                    <p class="font-mono">
                        @if($configStatus['channel_id'])
                            <span class="text-green-600">✅ 設定済み</span>
                        @else
                            <span class="text-red-600">❌ 未設定</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Channel Secret</p>
                    <p class="font-mono">
                        @if($configStatus['channel_secret'])
                            <span class="text-green-600">✅ 設定済み</span>
                        @else
                            <span class="text-red-600">❌ 未設定</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Access Token</p>
                    <p class="font-mono">
                        @if($configStatus['channel_access_token'])
                            <span class="text-green-600">✅ 設定済み</span>
                        @else
                            <span class="text-red-600">❌ 未設定</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Webhook URL</p>
                    <p class="font-mono text-xs break-all">{{ $configStatus['webhook_url'] }}</p>
                </div>
            </div>
            
            <div class="mt-4">
                <button onclick="testWebhook()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    🔍 LINE Bot接続テスト
                </button>
            </div>
            <div id="webhook-result" class="mt-4"></div>
        </div>

        <!-- LINE登録状況 -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">📊 LINE登録状況</h2>
            <p class="text-2xl font-bold text-green-600">{{ $lineUsersCount }} 人</p>
            <p class="text-sm text-gray-600">LINE登録済み顧客数</p>
        </div>

        <!-- メッセージはいつ送信される？ -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">📅 メッセージ送信タイミング</h2>
            <div class="space-y-4">
                <div>
                    <h3 class="font-bold text-lg">1. ウェルカムメッセージ</h3>
                    <p class="text-gray-700">→ LINE友だち追加の<span class="font-bold text-blue-600">即時</span></p>
                </div>
                <div>
                    <h3 class="font-bold text-lg">2. 予約リマインダー</h3>
                    <p class="text-gray-700">→ 予約<span class="font-bold text-blue-600">前日の10:00</span></p>
                    <p class="text-sm text-gray-500 ml-4">※予約が「確定」ステータスの場合のみ</p>
                </div>
                <div>
                    <h3 class="font-bold text-lg">3. 来店お礼</h3>
                    <p class="text-gray-700">→ 来店<span class="font-bold text-blue-600">2-3時間後</span></p>
                    <p class="text-sm text-gray-500 ml-4">※実際に来店完了した場合のみ</p>
                </div>
            </div>
        </div>

        <!-- テストメッセージ送信 -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">📨 テストメッセージ送信</h2>
            
            @if($recentLineUsers->isEmpty())
                <p class="text-gray-500">LINE登録済みの顧客がいません。</p>
            @else
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">送信先顧客</label>
                    <select id="customer_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        @foreach($recentLineUsers as $customer)
                            <option value="{{ $customer->id }}">
                                {{ $customer->full_name }} ({{ $customer->line_user_id }})
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">メッセージ</label>
                    <textarea id="message" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        placeholder="テストメッセージを入力してください">これはLINE Botのテストメッセージです。
正常に受信できていますか？</textarea>
                </div>
                
                <button onclick="sendTestMessage()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    送信テスト
                </button>
                
                <div id="test-result" class="mt-4"></div>
            @endif
        </div>

        <!-- 設定の意味 -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">❓ LINE設定の意味</h2>
            <dl class="space-y-4">
                <div>
                    <dt class="font-bold">通知優先度設定</dt>
                    <dd class="ml-4 text-gray-700">LINE登録している顧客にはLINEで通知、未登録ならSMSで通知します。</dd>
                </div>
                <div>
                    <dt class="font-bold">配信禁止時間帯</dt>
                    <dd class="ml-4 text-gray-700">深夜など、メッセージを送らない時間帯を設定できます。（デフォルト: 22:00-8:00）</dd>
                </div>
                <div>
                    <dt class="font-bold">流入経路トラッキング</dt>
                    <dd class="ml-4 text-gray-700">どの店舗のQRコードから登録したかを記録し、店舗別のキャンペーンが可能になります。</dd>
                </div>
            </dl>
        </div>

        <!-- トラブルシューティング -->
        <div class="bg-red-50 border-l-4 border-red-400 p-6">
            <h2 class="text-xl font-bold mb-4">🚨 メッセージが届かない場合</h2>
            <ol class="list-decimal ml-6 space-y-2">
                <li><strong>LINE Developer Console</strong>で Webhook URLが設定されているか確認</li>
                <li>Webhook URL: <code class="bg-gray-100 px-2 py-1">{{ url('/api/line/webhook') }}</code></li>
                <li>「Webhook送信」が<strong>オン</strong>になっているか確認</li>
                <li>「応答メッセージ」を<strong>オフ</strong>にする</li>
                <li>Channel Access Tokenが正しいか確認</li>
                <li>友だち追加されているか確認</li>
            </ol>
        </div>
    </div>

    <script>
        function testWebhook() {
            fetch('/admin/line-test/webhook', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('webhook-result');
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            ✅ ${data.message}<br>
                            Bot名: ${data.bot_info?.displayName || 'N/A'}<br>
                            ID: ${data.bot_info?.userId || 'N/A'}
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            ❌ ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('webhook-result').innerHTML = `
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        エラーが発生しました: ${error.message}
                    </div>
                `;
            });
        }

        function sendTestMessage() {
            const customerId = document.getElementById('customer_id').value;
            const message = document.getElementById('message').value;

            fetch('/admin/line-test/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    customer_id: customerId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('test-result');
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            ✅ ${data.message}
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            ❌ ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('test-result').innerHTML = `
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        エラーが発生しました: ${error.message}
                    </div>
                `;
            });
        }
    </script>
</body>
</html>