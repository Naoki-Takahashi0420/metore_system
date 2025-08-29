<div class="space-y-4">
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <h3 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">
            📊 対象顧客情報
        </h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <strong>ルール名:</strong> {{ $rule->name }}
            </div>
            <div>
                <strong>対象顧客数:</strong> {{ $customers->count() }}名
            </div>
        </div>
    </div>

    @if($customers->count() > 0)
        <div class="space-y-3">
            <h4 class="font-medium">対象顧客一覧（最大10名表示）</h4>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm border border-gray-200 dark:border-gray-700 rounded-lg">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left">顧客名</th>
                            <th class="px-3 py-2 text-left">保有ラベル</th>
                            <th class="px-3 py-2 text-left">最終予約</th>
                            <th class="px-3 py-2 text-left">予約回数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers->take(10) as $customer)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $customer->name }}</div>
                                <div class="text-xs text-gray-500">{{ $customer->email }}</div>
                            </td>
                            <td class="px-3 py-2">
                                @foreach($customer->labels()->active()->get() as $label)
                                <span class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded mr-1 mb-1">
                                    {{ $label->label_name }}
                                </span>
                                @endforeach
                            </td>
                            <td class="px-3 py-2 text-xs">
                                @php
                                    $lastReservation = $customer->reservations()->latest()->first();
                                @endphp
                                @if($lastReservation)
                                    {{ $lastReservation->created_at->format('Y/m/d') }}
                                    <div class="text-gray-500">
                                        {{ $lastReservation->created_at->diffForHumans() }}
                                    </div>
                                @else
                                    <span class="text-gray-400">なし</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                {{ $customer->reservations()->count() }}回
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if($customers->count() > 10)
            <p class="text-sm text-gray-500 text-center">
                他 {{ $customers->count() - 10 }}名の顧客が対象です
            </p>
            @endif
        </div>
    @else
        <div class="text-center py-8 text-gray-500">
            <div class="text-4xl mb-2">😐</div>
            <p>現在、この条件に該当する顧客はいません</p>
            <p class="text-sm">条件を見直してみてください</p>
        </div>
    @endif
</div>