<x-filament-widgets::widget>
    @if(count($failedSubscriptions) > 0)
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-lg font-semibold text-red-800 mb-2">
                        🚨 サブスク決済失敗 - 要対応（{{ count($failedSubscriptions) }}件）
                    </h3>
                    <p class="text-sm text-red-700 mb-4">
                        以下の顧客の決済が失敗しています。至急ご対応ください。
                    </p>

                    <div class="space-y-3">
                        @foreach($failedSubscriptions as $subscription)
                            <div class="bg-white rounded-lg p-4 border border-red-200 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h4 class="text-base font-semibold text-gray-900">
                                                {{ $subscription['customer_name'] }}
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                                決済失敗
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                            <div>
                                                <span class="text-gray-500">電話番号:</span>
                                                <span class="ml-1 text-gray-900">{{ $subscription['customer_phone'] ?? '未登録' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">プラン:</span>
                                                <span class="ml-1 text-gray-900">{{ $subscription['plan_name'] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">月額:</span>
                                                <span class="ml-1 text-gray-900 font-medium">¥{{ number_format($subscription['monthly_price']) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">失敗日時:</span>
                                                <span class="ml-1 text-gray-900">{{ $subscription['failed_at']->format('m/d H:i') }}</span>
                                            </div>
                                        </div>

                                        <div class="mt-2 flex items-center gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">失敗理由:</span>
                                                <span class="ml-1 text-red-600 font-medium">{{ $subscription['failed_reason'] }}</span>
                                            </div>
                                            @if($subscription['failed_notes'])
                                                <div class="text-gray-600">
                                                    <span class="text-gray-500">備考:</span>
                                                    <span class="ml-1">{{ $subscription['failed_notes'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="ml-4">
                                        <a href="{{ $this->getSubscriptionEditUrl($subscription['id']) }}"
                                           class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            対応する
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 pt-4 border-t border-red-200">
                        <p class="text-xs text-red-600">
                            💡 ヒント: 「対応する」ボタンをクリックすると、サブスク契約編集画面に移動します。決済が完了したら「決済復旧」ボタンで正常状態に戻してください。
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>