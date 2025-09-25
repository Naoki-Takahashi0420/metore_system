<x-filament-panels::page>
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
        <div class="space-y-4">
            <!-- モード選択 -->
            <div class="flex items-center gap-4">
                <label class="flex items-center">
                    <input type="radio" wire:model.live="compareMode" value="0" class="mr-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">通常表示</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" wire:model.live="compareMode" value="1" class="mr-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">期間比較</span>
                </label>
            </div>

            <!-- 期間選択 -->
            <div class="flex flex-wrap gap-4">
                @if(!$compareMode)
                    <!-- 通常モード -->
                    <div class="flex gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">期間</label>
                            <select wire:model.live="period" class="w-48 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="month">今月</option>
                                <option value="last_month">先月</option>
                                <option value="quarter">今四半期</option>
                                <option value="year">今年</option>
                                <option value="custom">カスタム期間</option>
                            </select>
                        </div>

                        @if($period === 'custom')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">開始日</label>
                                <input type="date" wire:model.live="startDateA" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">終了日</label>
                                <input type="date" wire:model.live="endDateA" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            </div>
                        @endif
                    </div>
                @else
                    <!-- 比較モード -->
                    <div class="w-full space-y-3">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-blue-600 dark:text-blue-400 w-20">期間A</span>
                            <div class="flex gap-2">
                                <input type="date" wire:model.live="startDateA" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <span class="text-gray-500">〜</span>
                                <input type="date" wire:model.live="endDateA" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-green-600 dark:text-green-400 w-20">期間B</span>
                            <div class="flex gap-2">
                                <input type="date" wire:model.live="startDateB" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <span class="text-gray-500">〜</span>
                                <input type="date" wire:model.live="endDateB" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 店舗選択 -->
                <div class="ml-auto">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">店舗</label>
                    <select wire:model.live="store_id" class="w-48 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">全店舗</option>
                        @foreach(\App\Models\Store::pluck('name', 'id') as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($compareMode && $startDateA && $endDateA && $startDateB && $endDateB)
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    <span class="text-blue-600 dark:text-blue-400">期間A: {{ \Carbon\Carbon::parse($startDateA)->format('Y/m/d') }} 〜 {{ \Carbon\Carbon::parse($endDateA)->format('Y/m/d') }}</span>
                    と
                    <span class="text-green-600 dark:text-green-400">期間B: {{ \Carbon\Carbon::parse($startDateB)->format('Y/m/d') }} 〜 {{ \Carbon\Carbon::parse($endDateB)->format('Y/m/d') }}</span>
                    を比較中
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6">
        @if(!$compareMode)
            <!-- 通常モード -->
            @livewire('marketing.monthly-kpi-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
            @livewire('marketing.staff-performance-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
            @livewire('marketing.customer-analysis-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
            @livewire('marketing.conversion-funnel-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
        @else
            <!-- 比較モード -->
            @if($startDateA && $endDateA && $startDateB && $endDateB)
                <!-- KPI比較 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="border-2 border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-blue-600 dark:text-blue-400 mb-3">
                            期間A: {{ \Carbon\Carbon::parse($startDateA)->format('Y/m/d') }} 〜 {{ \Carbon\Carbon::parse($endDateA)->format('m/d') }}
                        </h3>
                        <livewire:marketing.monthly-kpi-stats
                            :period="'custom'"
                            :store_id="$store_id"
                            :startDate="$startDateA"
                            :endDate="$endDateA"
                            :key="'kpi-a-'.$startDateA.'-'.$endDateA" />
                    </div>
                    <div class="border-2 border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-green-600 dark:text-green-400 mb-3">
                            期間B: {{ \Carbon\Carbon::parse($startDateB)->format('Y/m/d') }} 〜 {{ \Carbon\Carbon::parse($endDateB)->format('m/d') }}
                        </h3>
                        <livewire:marketing.monthly-kpi-stats
                            :period="'custom'"
                            :store_id="$store_id"
                            :startDate="$startDateB"
                            :endDate="$endDateB"
                            :key="'kpi-b-'.$startDateB.'-'.$endDateB" />
                    </div>
                </div>

                <!-- スタッフパフォーマンス比較 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="border-2 border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <livewire:marketing.staff-performance-stats
                            :period="'custom'"
                            :store_id="$store_id"
                            :startDate="$startDateA"
                            :endDate="$endDateA"
                            :key="'staff-a-'.$startDateA.'-'.$endDateA" />
                    </div>
                    <div class="border-2 border-green-200 dark:border-green-800 rounded-lg p-4">
                        <livewire:marketing.staff-performance-stats
                            :period="'custom'"
                            :store_id="$store_id"
                            :startDate="$startDateB"
                            :endDate="$endDateB"
                            :key="'staff-b-'.$startDateB.'-'.$endDateB" />
                    </div>
                </div>
            @else
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">比較する期間を選択してください</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">期間AとBの開始日と終了日を選択すると、データが表示されます</p>
                </div>
            @endif
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endpush
</x-filament-panels::page>