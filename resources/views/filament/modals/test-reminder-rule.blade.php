<div class="space-y-6">
    <!-- ルール概要 -->
    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
        <h3 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-3 flex items-center">
            🧪 ルールテスト結果
        </h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <strong>ルール名:</strong> {{ $rule->name }}
            </div>
            <div>
                <strong>対象顧客数:</strong> 
                <span class="font-bold text-lg {{ $targetCount > 0 ? 'text-green-600' : 'text-gray-500' }}">
                    {{ $targetCount }}名
                </span>
            </div>
            <div>
                <strong>対象ラベル:</strong> 
                @if($rule->target_labels && count($rule->target_labels) > 0)
                    {{ implode(', ', $rule->target_labels) }}
                @else
                    <span class="text-gray-500">全顧客</span>
                @endif
            </div>
            <div>
                <strong>最大送信回数:</strong> 
                {{ $rule->max_sends_per_customer }}回
            </div>
        </div>
    </div>

    <!-- 条件詳細 -->
    @if($rule->trigger_conditions)
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <h4 class="font-medium mb-3">実行条件</h4>
        <div class="space-y-2 text-sm">
            @foreach($rule->trigger_conditions as $condition)
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                <span>
                    @switch($condition['type'])
                        @case('days_since_last_reservation')
                            最終予約から{{ $condition['operator'] }} {{ $condition['value'] }}日
                            @break
                        @case('no_show_count')
                            ノーショー回数が{{ $condition['operator'] }} {{ $condition['value'] }}回
                            @break
                        @case('total_reservations')
                            総予約回数が{{ $condition['operator'] }} {{ $condition['value'] }}回
                            @break
                        @default
                            {{ $condition['type'] }} {{ $condition['operator'] }} {{ $condition['value'] }}
                    @endswitch
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- 対象顧客プレビュー -->
    @if($targetCount > 0)
        <div class="space-y-3">
            <h4 class="font-medium">対象顧客プレビュー（最初の10名）</h4>
            
            <div class="grid gap-3">
                @foreach($targets as $customer)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium">{{ $customer->name }}</div>
                            <div class="text-sm text-gray-500">{{ $customer->email }}</div>
                        </div>
                        <div class="text-right text-sm">
                            <div>予約: {{ $customer->reservations()->count() }}回</div>
                            @php
                                $lastReservation = $customer->reservations()->latest()->first();
                            @endphp
                            @if($lastReservation)
                            <div class="text-gray-500">
                                最終: {{ $lastReservation->created_at->diffForHumans() }}
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- 顧客ラベル -->
                    <div class="mt-2">
                        @foreach($customer->labels()->active()->get() as $label)
                        <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded mr-1">
                            {{ $label->label_name }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            
            @if($targetCount > 10)
            <p class="text-center text-sm text-gray-500">
                他 {{ $targetCount - 10 }}名が対象です
            </p>
            @endif
        </div>
    @else
        <div class="text-center py-8 text-gray-500">
            <div class="text-4xl mb-2">🤔</div>
            <p class="font-medium">対象顧客が見つかりません</p>
            <p class="text-sm">条件を調整するか、顧客ラベルを確認してください</p>
        </div>
    @endif

    <!-- アクション提案 -->
    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
        <h4 class="font-medium mb-2">💡 推奨アクション</h4>
        <div class="text-sm space-y-1">
            @if($targetCount === 0)
                <p>・条件を緩和するか、対象ラベルを見直してください</p>
                <p>・顧客ラベルが正しく付与されているか確認してください</p>
            @elseif($targetCount > 100)
                <p class="text-orange-600">・対象顧客が多すぎます。条件を厳しくすることを推奨します</p>
                <p>・段階的に送信するか、ラベルをより細分化してください</p>
            @else
                <p class="text-green-600">・適切な対象顧客数です。このまま運用できます</p>
                <p>・テスト送信で効果を確認してから本格運用しましょう</p>
            @endif
        </div>
    </div>
</div>