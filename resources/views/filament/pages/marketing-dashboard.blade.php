<x-filament-panels::page>
    {{-- メインタブナビゲーション --}}
    @php
        $mainTab = request()->get('tab', 'new-tracking');
    @endphp
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
            <li class="mr-2">
                <a href="?tab=new-tracking"
                   class="inline-flex items-center p-4 border-b-2 rounded-t-lg {{ $mainTab === 'new-tracking' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    新規顧客追跡
                </a>
            </li>
            <li class="mr-2">
                <a href="?tab=kpi"
                   class="inline-flex items-center p-4 border-b-2 rounded-t-lg {{ $mainTab === 'kpi' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    KPI分析
                </a>
            </li>
        </ul>
    </div>

    {{-- フィルタセクション --}}
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        @if(!$compareMode)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                {{-- 店舗セレクト --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">店舗</label>
                    <select wire:model.live="store_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">全店舗</option>
                        @foreach(\App\Models\Store::orderBy('name')->pluck('name', 'id') as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 開始日 --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-1">
                        <x-heroicon-o-calendar class="w-4 h-4" />
                        開始日
                    </label>
                    <input type="date" wire:model.live="startDateA" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>

                {{-- 終了日 --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-1">
                        <x-heroicon-o-calendar class="w-4 h-4" />
                        終了日
                    </label>
                    <input type="date" wire:model.live="endDateA" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
            </div>

            {{-- クイックボタン --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">クイック選択</label>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem;">
                    <button wire:click="setToday" style="background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); color: #ffffff !important;" class="px-4 py-2.5 text-sm font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 flex items-center gap-2 border-2 border-blue-900">
                        <x-heroicon-o-calendar-days class="w-5 h-5" style="color: #ffffff !important;" />
                        <span style="color: #ffffff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">今日</span>
                    </button>
                    <button wire:click="setThisMonth" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: #ffffff !important;" class="px-4 py-2.5 text-sm font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 flex items-center gap-2 border-2 border-green-900">
                        <x-heroicon-o-chart-bar class="w-5 h-5" style="color: #ffffff !important;" />
                        <span style="color: #ffffff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">今月</span>
                    </button>
                    <button wire:click="setLastMonth" style="background: #7c3aed; color: #ffffff !important;" class="px-4 py-2 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-1 border-2 border-purple-800 hover:bg-purple-700">
                        <span style="color: #ffffff !important;">先月</span>
                    </button>
                    <button wire:click="setLast30Days" style="background: #ea580c; color: #ffffff !important;" class="px-4 py-2 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-1 border-2 border-orange-800 hover:bg-orange-700">
                        <span style="color: #ffffff !important;">30日</span>
                    </button>
                    <button wire:click="setLast6Months" style="background: #0891b2; color: #ffffff !important;" class="px-4 py-2 text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-1 border-2 border-cyan-800 hover:bg-cyan-700">
                        <span style="color: #ffffff !important;">6ヶ月</span>
                    </button>
                </div>
            </div>

            {{-- 現在のフィルタ表示 --}}
            @if($startDateA && $endDateA)
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-4">
                    期間: <span class="font-semibold">{{ $startDateA }}</span> 〜 <span class="font-semibold">{{ $endDateA }}</span>
                    （{{ \Carbon\Carbon::parse($startDateA)->diffInDays(\Carbon\Carbon::parse($endDateA)) + 1 }}日間）
                </div>
            @endif

            {{-- 期間比較モード切り替え（KPIタブのみ） --}}
            @if($mainTab === 'kpi')
            <div class="mt-4">
                <button wire:click="$set('compareMode', true)" type="button"
                        class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                    + 期間比較モードに切り替え
                </button>
            </div>
            @endif
        @else
            <!-- 比較モード -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">期間比較モード</h3>
                    <button wire:click="$set('compareMode', false)" type="button"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        通常モードに戻る
                    </button>
                </div>

                <!-- 店舗選択 -->
                <div class="max-w-xs">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">店舗</label>
                    <select wire:model.live="store_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">全店舗</option>
                        @foreach(\App\Models\Store::orderBy('name')->pluck('name', 'id') as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- 期間A -->
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <label class="block text-sm font-medium text-blue-700 dark:text-blue-300 mb-3">期間A</label>
                    <div class="flex gap-3 items-center">
                        <input type="date" wire:model.live="startDateA"
                               class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                               max="{{ now()->format('Y-m-d') }}">
                        <span class="text-gray-500 dark:text-gray-400">〜</span>
                        <input type="date" wire:model.live="endDateA"
                               class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                               max="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>

                <!-- 期間B -->
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <label class="block text-sm font-medium text-green-700 dark:text-green-300 mb-3">期間B</label>
                    <div class="flex gap-3 items-center">
                        <input type="date" wire:model.live="startDateB"
                               class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                               max="{{ now()->format('Y-m-d') }}">
                        <span class="text-gray-500 dark:text-gray-400">〜</span>
                        <input type="date" wire:model.live="endDateB"
                               class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                               max="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>

                @if($startDateA && $endDateA && $startDateB && $endDateB)
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="text-blue-600 dark:text-blue-400 font-medium">期間A: {{ \Carbon\Carbon::parse($startDateA)->format('Y/m/d') }} 〜 {{ \Carbon\Carbon::parse($endDateA)->format('Y/m/d') }}</span>
                        と
                        <span class="text-green-600 dark:text-green-400 font-medium">期間B: {{ \Carbon\Carbon::parse($startDateB)->format('Y/m/d') }} 〜 {{ \Carbon\Carbon::parse($endDateB)->format('Y/m/d') }}</span>
                        を比較中
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6">
        @if($mainTab === 'new-tracking')
            {{-- 新規顧客追跡タブ --}}
            @livewire('marketing.new-customer-tracking-table', [
                'startDate' => $startDateA,
                'endDate' => $endDateA,
                'store_id' => $store_id
            ], key('new-tracking-' . $startDateA . '-' . $endDateA . '-' . $store_id))
        @elseif($mainTab === 'kpi')
            {{-- KPI分析タブ --}}
            @if(!$compareMode)
                {{-- 通常モード --}}
                @livewire('marketing.monthly-kpi-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
                @livewire('marketing.complete-funnel-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
                @livewire('marketing.medical-record-conversion-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
                @livewire('marketing.staff-performance-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
                @livewire('marketing.customer-analysis-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
                @livewire('marketing.conversion-funnel-stats', ['period' => $period, 'store_id' => $store_id, 'startDate' => $startDateA, 'endDate' => $endDateA])
            @else
                {{-- 比較モード --}}
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
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
            // Chart.js読み込み直後にグローバル設定を強制適用
            console.log('🎯 Chart.js読み込み完了 - グローバル設定を適用');

            if (typeof Chart !== 'undefined') {
                // 完全にアニメーションを無効化
                Chart.defaults.animation = false;
                Chart.defaults.animations = false;
                Chart.defaults.transitions = false;

                // より深いレベルでアニメーションを無効化
                Chart.defaults.elements = Chart.defaults.elements || {};
                Chart.defaults.elements.line = Chart.defaults.elements.line || {};
                Chart.defaults.elements.line.tension = 0; // 曲線アニメーション無効化

                Chart.defaults.elements.point = Chart.defaults.elements.point || {};
                Chart.defaults.elements.point.radius = 3; // ポイントアニメーション無効化

                // レスポンシブ設定（maintainAspectRatioはデフォルトのtrueを維持）
                Chart.defaults.responsive = true;

                console.log('✅ Chart.defaults完全設定:', {
                    animation: Chart.defaults.animation,
                    animations: Chart.defaults.animations,
                    transitions: Chart.defaults.transitions
                });

                window.chartGlobalDefaultsSet = true;
            } else {
                console.error('❌ Chart.js読み込み失敗！');
            }
        </script>
    @endpush
</x-filament-panels::page>