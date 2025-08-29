<div class="space-y-6">
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <h3 class="font-semibold text-blue-800 dark:text-blue-200 mb-3">
            🌐 LINE全体設定（環境変数）
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            これらの設定は .env ファイルで管理されています。
            店舗が「全体設定を使用」を選択した場合、これらの値が使用されます。
        </p>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Channel ID
                </label>
                <div class="mt-1">
                    <code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                        {{ env('LINE_CHANNEL_ID', '未設定') }}
                    </code>
                </div>
            </div>

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Channel Secret
                </label>
                <div class="mt-1">
                    <code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                        {{ Str::limit(env('LINE_CHANNEL_SECRET', '未設定'), 10) }}...
                    </code>
                    <span class="text-xs text-gray-500 ml-2">（セキュリティのため一部表示）</span>
                </div>
            </div>

            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    友だち追加URL
                </label>
                <div class="mt-1">
                    <a href="{{ env('LINE_ADD_FRIEND_URL', '#') }}" 
                       target="_blank"
                       class="text-sm text-blue-600 hover:text-blue-800 underline">
                        {{ env('LINE_ADD_FRIEND_URL', '未設定') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
        <h4 class="font-medium text-yellow-800 dark:text-yellow-200 mb-2">
            ⚠️ 設定変更方法
        </h4>
        <ol class="text-sm space-y-1 text-gray-600 dark:text-gray-400">
            <li>1. サーバーの .env ファイルを編集</li>
            <li>2. LINE_CHANNEL_ID, LINE_CHANNEL_SECRET, LINE_CHANNEL_ACCESS_TOKEN を設定</li>
            <li>3. php artisan config:cache でキャッシュをクリア</li>
            <li>4. サービスを再起動</li>
        </ol>
    </div>

    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
        <h4 class="font-medium text-green-800 dark:text-green-200 mb-2">
            💡 推奨設定
        </h4>
        <ul class="text-sm space-y-1 text-gray-600 dark:text-gray-400">
            <li>• 本部の公式アカウントを全体設定に登録</li>
            <li>• 各店舗は必要に応じて独自設定を追加</li>
            <li>• 新規店舗はまず全体設定で運用開始</li>
            <li>• 運用が軌道に乗ったら店舗独自設定へ移行</li>
        </ul>
    </div>
</div>