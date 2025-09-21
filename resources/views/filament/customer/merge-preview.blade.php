<div class="space-y-6">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="font-semibold text-blue-900 mb-2">📋 統合方式について</h4>
        <p class="text-sm text-blue-800">
            この統合では「空欄補完方式」を採用しています。<br>
            ・ 基準顧客の空欄を、統合対象顧客の情報で補完します<br>
            ・ 予約、カルテ、サブスク契約は全て基準顧客に移行されます<br>
            ・ 統合対象顧客は削除されます
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 基準顧客（現在の顧客） -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h4 class="font-semibold text-green-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                基準顧客（統合先）
            </h4>
            <div class="space-y-2 text-sm">
                <p><strong>氏名:</strong> {{ $customer->last_name }}{{ $customer->first_name }}</p>
                <p><strong>電話:</strong> {{ $customer->phone ?: '(未登録)' }}</p>
                <p><strong>メール:</strong> {{ $customer->email ?: '(未登録)' }}</p>
                <p><strong>住所:</strong> {{ $customer->address ?: '(未登録)' }}</p>
                <p><strong>予約数:</strong> {{ $customer->reservations()->count() }}件</p>
                <p><strong>最終来店:</strong>
                    @php
                        $lastReservation = $customer->reservations()->latest('reservation_date')->first();
                    @endphp
                    {{ $lastReservation ? $lastReservation->reservation_date->format('Y/m/d') : '来店なし' }}
                </p>
            </div>
        </div>

        <!-- 類似顧客一覧 -->
        <div class="space-y-4">
            <h4 class="font-semibold text-gray-900">類似顧客一覧</h4>
            @foreach($similarCustomers as $similar)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h5 class="font-medium text-yellow-900 mb-2">{{ $similar['name'] }}</h5>
                    <div class="space-y-1 text-sm text-yellow-800">
                        <p><strong>電話:</strong> {{ $similar['phone'] }}</p>
                        <p><strong>メール:</strong> {{ $similar['email'] }}</p>
                        <p><strong>予約数:</strong> {{ $similar['reservations_count'] }}件</p>
                        <p><strong>最終来店:</strong> {{ $similar['last_visit'] }}</p>
                        <p><strong>情報充実度:</strong> {{ $similar['completeness_score'] }}点</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 統合後のプレビュー -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            統合後の予想結果
        </h4>
        <div class="text-sm text-gray-700">
            <p class="mb-2">以下の流れで統合が実行されます：</p>
            <ol class="list-decimal list-inside space-y-1 ml-4">
                <li>基準顧客の空欄情報を統合対象顧客の情報で補完</li>
                <li>統合対象顧客の予約・カルテ・サブスク契約を基準顧客に移行</li>
                <li>統合対象顧客を削除</li>
                <li>統合完了通知を表示</li>
            </ol>

            <div class="mt-4 p-3 bg-white rounded border">
                <p><strong>⚠️ 注意事項:</strong></p>
                <ul class="list-disc list-inside mt-1 space-y-1 text-xs text-gray-600">
                    <li>この操作は取り消すことができません</li>
                    <li>統合対象顧客は完全に削除されます</li>
                    <li>予約履歴や顧客データは基準顧客に統合されます</li>
                </ul>
            </div>
        </div>
    </div>
</div>