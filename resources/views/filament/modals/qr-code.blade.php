<div class="text-center space-y-6">
    <!-- Customer Information -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-2">顧客情報</h3>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            <strong>顧客名:</strong> {{ $customer->name }}
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            <strong>目的:</strong> {{ $token->purpose }}
        </p>
        @if($token->store)
        <p class="text-sm text-gray-600 dark:text-gray-300">
            <strong>店舗:</strong> {{ $token->store->name }}
        </p>
        @endif
    </div>

    <!-- QR Code -->
    <div class="bg-white p-6 rounded-lg shadow-sm">
        {!! $qrCode !!}
    </div>

    <!-- URL -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <p class="text-sm font-medium mb-2">アクセスURL:</p>
        <p class="text-xs text-gray-600 dark:text-gray-300 break-all font-mono">
            {{ $url }}
        </p>
    </div>

    <!-- Instructions -->
    <div class="text-sm text-gray-500 dark:text-gray-400 space-y-2">
        <p>このQRコードを顧客にお渡しください。</p>
        <p>スマートフォンでスキャンすると予約ページに直接アクセスできます。</p>
        @if($token->max_usage)
        <p class="text-orange-600 dark:text-orange-400">
            ⚠️ このトークンは最大{{ $token->max_usage }}回まで使用可能です。
        </p>
        @endif
        @if($token->expires_at)
        <p class="text-orange-600 dark:text-orange-400">
            ⚠️ 有効期限: {{ $token->expires_at->format('Y年m月d日 H:i') }}
        </p>
        @endif
    </div>
</div>