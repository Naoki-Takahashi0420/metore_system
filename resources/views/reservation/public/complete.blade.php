<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約完了 - 目のトレーニング</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @if($lineQrCodeUrl)
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    @endif
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white rounded-lg shadow-sm p-8">
            <!-- 完了アイコン -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                    <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">予約が完了しました</h1>
                <p class="text-gray-600">ご予約ありがとうございます</p>
            </div>

            <!-- 予約情報 -->
            <div class="border-t border-b border-gray-200 py-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">予約内容</h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">予約番号</span>
                        <span class="font-semibold text-lg">{{ $reservation->reservation_number }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">店舗</span>
                        <span>{{ $reservation->store->name }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">日時</span>
                        <span>
                            {{ \Carbon\Carbon::parse($reservation->reservation_date)->format('Y年n月j日') }}
                            {{ \Carbon\Carbon::parse($reservation->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($reservation->end_time)->format('H:i') }}
                        </span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">メニュー</span>
                        <span>{{ $reservation->menu->name ?? '-' }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">料金</span>
                        <span class="font-semibold">¥{{ number_format($reservation->total_amount) }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">お名前</span>
                        <span>{{ $reservation->customer->last_name }} {{ $reservation->customer->first_name }} 様</span>
                    </div>
                </div>
            </div>

            <!-- 注意事項 -->
            <div class="bg-amber-50 border border-amber-200 rounded-md p-4 mb-6">
                <h3 class="font-semibold text-amber-800 mb-2">ご来店時のお願い</h3>
                <ul class="text-sm text-amber-700 space-y-1">
                    <li>• 予約時間の5分前までにお越しください</li>
                    <li>• キャンセルの場合は前日までにご連絡ください</li>
                    <li>• 遅れる場合は必ずお電話でご連絡ください</li>
                </ul>
            </div>

            <!-- LINE友だち追加 -->
            @if($lineQrCodeUrl)
            <div class="bg-blue-50 border border-blue-200 rounded-md p-6 mb-6 text-center">
                <h3 class="font-semibold text-blue-800 mb-3">📱 LINE友だち追加でもっと便利に！</h3>
                <div class="mb-4">
                    <div id="line-qr-code" class="inline-block border border-gray-200 p-2 bg-white rounded"></div>
                </div>
                <p class="text-sm text-blue-700 mb-3">QRコードを読み取って{{ $reservation->store->name }}のLINE公式アカウントを友だち追加すると：</p>
                <ul class="text-sm text-blue-700 space-y-1 text-left max-w-md mx-auto">
                    <li>• 予約の変更・キャンセルがLINEで簡単に</li>
                    <li>• 来店前日にリマインダー通知</li>
                    <li>• お得なキャンペーン情報をお届け</li>
                </ul>
                <p class="text-xs text-blue-600 mt-3">※30日以内に友だち追加してください</p>
            </div>
            @endif

            <!-- 店舗情報 -->
            <div class="mb-6">
                <h3 class="font-semibold mb-2">店舗情報</h3>
                <div class="text-sm text-gray-600 space-y-1">
                    <p>{{ $reservation->store->name }}</p>
                    <p>〒{{ $reservation->store->postal_code }} {{ $reservation->store->address }}</p>
                    <p>TEL: {{ $reservation->store->phone }}</p>
                </div>
            </div>

            <!-- ボタン -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="/" 
                   class="inline-block px-6 py-3 bg-gray-500 text-white rounded-md hover:bg-gray-600 text-center">
                    トップページへ戻る
                </a>
                <button onclick="window.print()" 
                        class="inline-block px-6 py-3 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    予約内容を印刷
                </button>
            </div>
        </div>
    </div>

    @if($lineQrCodeUrl)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const qrElement = document.getElementById('line-qr-code');
            if (qrElement) {
                QRCode.toCanvas(qrElement, '{!! addslashes($lineQrCodeUrl) !!}', {
                    width: 200,
                    margin: 2,
                    color: {
                        dark: '#000000',
                        light: '#FFFFFF'
                    }
                }, function (error) {
                    if (error) {
                        console.error('QRCode generation error:', error);
                        qrElement.innerHTML = '<p class="text-red-500 text-sm">QRコードの生成に失敗しました</p>';
                    }
                });
            }
        });
    </script>
    @endif
</body>
</html>