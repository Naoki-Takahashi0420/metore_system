<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-PW6PX69M');</script>
    <!-- End Google Tag Manager -->

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約完了 - 目のトレーニング</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PW6PX69M"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white rounded-lg shadow-sm p-8">
            <!-- 完了アイコン -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                    <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">
                    @if(session('success'))
                        {{ session('success') }}
                    @else
                        予約が完了しました
                    @endif
                </h1>
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

                    @if($reservation->staff_id && $reservation->staff)
                    <div class="flex justify-between">
                        <span class="text-gray-600">担当スタッフ</span>
                        <span>{{ $reservation->staff->name }}</span>
                    </div>
                    @endif

                    @php
                        // reservationOptionsを安全に取得
                        $optionMenus = collect([]);
                        $optionPrice = 0;
                        try {
                            $optionMenus = $reservation->getOptionMenusSafely();
                            $optionPrice = $reservation->getOptionsTotalPrice();
                        } catch (\Exception $e) {
                            \Log::error('Error displaying option menus in complete page', [
                                'reservation_id' => $reservation->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    @endphp

                    @if($optionMenus->count() > 0)
                    <div class="mt-3 pl-4 border-l-2 border-gray-200">
                        <div class="text-sm text-gray-600 mb-1">追加オプション:</div>
                        @foreach($optionMenus as $option)
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">• {{ $option->name }}</span>
                            <span>+¥{{ number_format($option->pivot->price ?? $option->price ?? 0) }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    <div class="pt-3 mt-3 border-t border-gray-200">
                        @if($optionPrice > 0)
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">基本料金</span>
                            <span>¥{{ number_format($reservation->menu->is_subscription ? $reservation->menu->subscription_monthly_price : $reservation->menu->price ?? 0) }}@if($reservation->menu->is_subscription)<span class="text-xs">/月</span>@endif</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-600">オプション料金</span>
                            <span>+¥{{ number_format($optionPrice) }}</span>
                        </div>
                        @endif

                        <div class="flex justify-between font-semibold">
                            <span class="text-gray-700">{{ $optionPrice > 0 ? '合計金額' : '料金' }}</span>
                            <span class="text-lg">¥{{ number_format($reservation->total_amount) }}</span>
                        </div>
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

            <!-- LINE連携 -->
            @if($reservation->store->line_enabled && $reservation->store->line_liff_id)
                @if(!$reservation->customer->isLinkedToLine())
                <div class="bg-blue-50 border border-blue-200 rounded-md p-6 mb-6 text-center">
                    <h3 class="font-semibold text-blue-800 mb-3">📱 LINE連携でもっと便利に！</h3>

                    <!-- 1タップ連携ボタン -->
                    <div class="mb-4">
                        <a href="https://liff.line.me/{{ $reservation->store->line_liff_id }}?liff.state={{ urlencode('reservation=' . $reservation->reservation_number) }}"
                           class="inline-flex items-center gap-2 px-8 py-4 bg-green-500 text-white font-bold text-lg rounded-lg hover:bg-green-600 shadow-lg w-full justify-center">
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM16.84 9.93L15.37 11.4L16.84 12.87C17.03 13.06 17.03 13.37 16.84 13.56L15.37 15.03L13.9 13.56L12.43 15.03L10.96 13.56C10.77 13.37 10.77 13.06 10.96 12.87L12.43 11.4L10.96 9.93C10.77 9.74 10.77 9.43 10.96 9.24L12.43 7.77L13.9 9.24L15.37 7.77L16.84 9.24C17.03 9.43 17.03 9.74 16.84 9.93Z"/>
                            </svg>
                            LINEと連携する
                        </a>
                        <p class="text-sm text-gray-600 mt-2 text-center">タップして自動で連携完了</p>
                    </div>


                    <p class="text-sm text-blue-700 mb-3">LINE連携すると以下のサービスをご利用いただけます：</p>
                    <ul class="text-sm text-blue-700 space-y-1 text-left max-w-md mx-auto">
                        <li>• 予約の確認・変更・キャンセル</li>
                        <li>• 来店前日のリマインダー通知</li>
                        <li>• お得なキャンペーン情報</li>
                        <li>• 予約詳細の自動送信</li>
                    </ul>
                    <p class="text-xs text-blue-600 mt-3">※既にLINE友達の方もタップするだけで連携完了</p>
                </div>
                @else
                <div class="bg-green-50 border border-green-200 rounded-md p-6 mb-6 text-center">
                    <h3 class="font-semibold text-green-800 mb-2">✅ LINE連携済み</h3>
                    <p class="text-sm text-green-700">予約の確認・変更はLINEトーク画面からご利用いただけます</p>
                </div>
                @endif
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
                <a href="/customer/login"
                   id="mypage-button"
                   class="inline-block px-6 py-3 bg-green-500 text-white rounded-md hover:bg-green-600 text-center">
                    マイページへ
                </a>
                <button onclick="window.print()" 
                        class="inline-block px-6 py-3 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    予約内容を印刷
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 予約完了時に顧客情報をセッションに保存（初回予約の人もマイページにアクセスできるように）
            const reservationCustomer = {
                phone: '{{ $reservation->customer->phone }}',
                last_name: '{{ $reservation->customer->last_name }}',
                first_name: '{{ $reservation->customer->first_name }}',
                id: {{ $reservation->customer->id }}
            };
            
            // 初回予約の場合は、マイページアクセス用の情報を保存
            if (!localStorage.getItem('customer_token')) {
                // 電話番号を一時的に保存（ログイン時に使用）
                sessionStorage.setItem('temp_customer_phone', reservationCustomer.phone);
                sessionStorage.setItem('temp_customer_data', JSON.stringify(reservationCustomer));
            }
            
        });
    </script>
</body>
</html>