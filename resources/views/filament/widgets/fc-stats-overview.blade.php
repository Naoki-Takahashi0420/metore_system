<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-3">
            {{-- 今月の発注件数 --}}
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <div class="flex-1">
                        <div class="flex items-baseline gap-x-2">
                            <span class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                                {{ $thisMonthOrdersCount }}件
                            </span>
                        </div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            今月の発注件数
                        </p>
                    </div>
                    <div>
                        <x-filament::icon
                            icon="heroicon-o-shopping-cart"
                            class="h-12 w-12 text-success-500"
                        />
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    合計金額: ¥{{ number_format($thisMonthOrdersAmount) }}
                </div>
            </div>

            {{-- 未発送の発注 --}}
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <div class="flex-1">
                        <div class="flex items-baseline gap-x-2">
                            <span class="text-3xl font-semibold tracking-tight {{ $unshippedOrders > 0 ? 'text-warning-600' : 'text-success-600' }}">
                                {{ $unshippedOrders }}件
                            </span>
                        </div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            未発送の発注
                        </p>
                    </div>
                    <div>
                        <x-filament::icon
                            icon="heroicon-o-truck"
                            class="h-12 w-12 {{ $unshippedOrders > 0 ? 'text-warning-500' : 'text-success-500' }}"
                        />
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    発送待ち
                </div>
            </div>

            {{-- 未払いの請求書 --}}
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <div class="flex-1">
                        <div class="flex items-baseline gap-x-2">
                            <span class="text-3xl font-semibold tracking-tight {{ $unpaidInvoicesCount > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                {{ $unpaidInvoicesCount }}件
                            </span>
                        </div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            未払いの請求書
                        </p>
                    </div>
                    <div>
                        <x-filament::icon
                            icon="heroicon-o-document-text"
                            class="h-12 w-12 {{ $unpaidInvoicesCount > 0 ? 'text-danger-500' : 'text-success-500' }}"
                        />
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    未払い金額: ¥{{ number_format($unpaidInvoicesAmount) }}
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
