<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 店舗選択（柔軟な表示方式） --}}
        @if(auth()->user()->hasRole('super_admin'))
        @php
            $stores = $this->getStores();
            $storeCount = $stores->count();
            $currentStore = \App\Models\Store::find($selectedStore);
        @endphp
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
            <div class="flex items-center gap-2 mb-3">
                <x-heroicon-o-building-storefront class="w-5 h-5 text-gray-500" />
                <span class="font-semibold">店舗選択</span>
                @if($currentStore && $storeCount > 3)
                    <span class="text-sm text-gray-500">（現在: {{ $currentStore->name }}）</span>
                @endif
            </div>
            
            @if($storeCount <= 3)
                {{-- 3店舗以下：ボタン形式 --}}
                <div class="flex gap-2 flex-wrap">
                    @foreach($stores as $store)
                        <button
                            wire:click="selectStore({{ $store->id }})"
                            class="px-4 py-2 rounded-lg transition-colors {{ $selectedStore == $store->id ? 'bg-primary-600 text-white' : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600' }}"
                        >
                            {{ $store->name }}
                        </button>
                    @endforeach
                </div>
            @elseif($storeCount <= 8)
                {{-- 4-8店舗：ドロップダウン --}}
                <x-filament::dropdown placement="bottom-start">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                            <x-heroicon-o-building-storefront class="w-5 h-5" />
                            <span>{{ $currentStore ? $currentStore->name : '店舗を選択' }}</span>
                            <x-heroicon-m-chevron-down class="w-4 h-4" />
                        </button>
                    </x-slot>
                    
                    <div class="py-1">
                        @foreach($stores as $store)
                            @if($store->id != $selectedStore)
                            <button 
                                wire:click="selectStore({{ $store->id }})"
                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                @if($store->is_active)
                                    <x-heroicon-m-check-circle class="w-4 h-4 text-green-500" />
                                @else
                                    <x-heroicon-m-x-circle class="w-4 h-4 text-gray-400" />
                                @endif
                                {{ $store->name }}
                            </button>
                            @endif
                        @endforeach
                    </div>
                </x-filament::dropdown>
            @else
                {{-- 9店舗以上：検索可能なモーダル --}}
                <button
                    x-data
                    @click="$dispatch('open-modal', { id: 'store-selector-modal' })"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
                >
                    <x-heroicon-o-building-storefront class="w-5 h-5" />
                    <span>{{ $currentStore ? '店舗: ' . $currentStore->name : '店舗を選択' }}</span>
                    <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                </button>
                
                {{-- 検索可能なモーダル --}}
                <x-filament::modal id="store-selector-modal" width="md">
                    <x-slot name="heading">
                        店舗を選択
                    </x-slot>
                    
                    <div x-data="{ search: '' }" class="space-y-4">
                        <div class="relative">
                            <input
                                type="text"
                                x-model="search"
                                placeholder="店舗名で検索..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                            <x-heroicon-o-magnifying-glass class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
                        </div>
                        
                        <div class="max-h-96 overflow-y-auto space-y-1">
                            @foreach($stores as $store)
                            <button
                                wire:click="selectStoreFromModal({{ $store->id }})"
                                x-show="'{{ strtolower($store->name) }}'.includes(search.toLowerCase())"
                                class="w-full flex items-center justify-between px-4 py-3 text-left rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors {{ $selectedStore == $store->id ? 'bg-primary-100 dark:bg-primary-900' : '' }}"
                            >
                                <div class="flex items-center gap-3">
                                    @if($store->is_active)
                                        <x-heroicon-m-check-circle class="w-5 h-5 text-green-500" />
                                    @else
                                        <x-heroicon-m-x-circle class="w-5 h-5 text-gray-400" />
                                    @endif
                                    <span class="font-medium">{{ $store->name }}</span>
                                </div>
                                @if($selectedStore == $store->id)
                                    <x-heroicon-m-check class="w-5 h-5 text-primary-600" />
                                @endif
                            </button>
                            @endforeach
                        </div>
                    </div>
                </x-filament::modal>
            @endif
        </div>
        @endif

        {{-- メインコンテンツ --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- カテゴリーとメニュー一覧 --}}
            <div class="lg:col-span-2 space-y-4">
                @forelse($categories as $category)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        {{-- カテゴリーヘッダー --}}
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg font-bold">{{ $category['name'] }}</span>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $category['is_active'] ? '有効' : '無効' }}
                                    </span>
                                    @if($category['available_durations'])
                                        <span class="text-sm text-gray-500">
                                            時間: {{ collect($category['available_durations'])->map(fn($d) => "{$d}分")->join(', ') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    @if(auth()->user()->hasRole('super_admin'))
                                    <x-filament::dropdown>
                                        <x-slot name="trigger">
                                            <button class="text-green-600 hover:text-green-700" title="他店舗へ複製">
                                                <x-heroicon-o-document-duplicate class="w-5 h-5" />
                                            </button>
                                        </x-slot>
                                        
                                        <div class="p-2">
                                            <p class="text-sm font-semibold mb-2">複製先の店舗を選択</p>
                                            @foreach(\App\Models\Store::where('id', '!=', $selectedStore)->get() as $targetStore)
                                                <button 
                                                    wire:click="duplicateCategoryToStore({{ $category['id'] }}, {{ $targetStore->id }})"
                                                    class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded">
                                                    {{ $targetStore->name }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </x-filament::dropdown>
                                    @endif
                                    <a href="{{ route('filament.admin.resources.menu-categories.edit', $category['id']) }}" 
                                       class="text-primary-600 hover:text-primary-700">
                                        <x-heroicon-o-pencil class="w-5 h-5" />
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- メニューリスト --}}
                        <div class="p-4">
                            @if(count($category['menus']) > 0)
                                <div class="space-y-2">
                                    @foreach($category['menus'] as $menu)
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium">{{ $menu['name'] }}</span>
                                                    @if($menu['show_in_upsell'])
                                                        <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded">オプション</span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-4 mt-1 text-sm text-gray-600">
                                                    <span>{{ $menu['duration_minutes'] }}分</span>
                                                    <span>¥{{ number_format($menu['price']) }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    wire:click="quickToggleMenu({{ $menu['id'] }}, 'is_available')"
                                                    class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                                                    title="利用可能状態を切り替え"
                                                >
                                                    @if($menu['is_available'])
                                                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-600" />
                                                    @else
                                                        <x-heroicon-o-x-circle class="w-5 h-5 text-red-600" />
                                                    @endif
                                                </button>
                                                <a href="{{ route('filament.admin.resources.menus.edit', $menu['id']) }}" 
                                                   class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700">
                                                    <x-heroicon-o-pencil class="w-4 h-4 text-gray-600" />
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500">
                                    <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                                    <p>メニューがありません</p>
                                    <a href="{{ route('filament.admin.resources.menus.create') }}?category_id={{ $category['id'] }}" 
                                       class="mt-2 inline-block text-primary-600 hover:text-primary-700">
                                        メニューを追加
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center">
                        <x-heroicon-o-folder-open class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                        <p class="text-gray-500 mb-4">カテゴリーがありません</p>
                        <a href="{{ route('filament.admin.resources.menu-categories.create') }}" 
                           class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                            <x-heroicon-o-plus class="w-5 h-5" />
                            カテゴリーを作成
                        </a>
                    </div>
                @endforelse
            </div>

            {{-- クイックアクション --}}
            <div class="space-y-4">
                {{-- 統計情報 --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <h3 class="font-semibold mb-3">統計情報</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">カテゴリー数:</span>
                            <span class="font-medium">{{ count($categories) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">総メニュー数:</span>
                            <span class="font-medium">{{ collect($categories)->pluck('menus')->flatten()->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">有効メニュー数:</span>
                            <span class="font-medium">{{ collect($categories)->pluck('menus')->flatten()->where('is_available', true)->count() }}</span>
                        </div>
                    </div>
                </div>

                {{-- クイックリンク --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <h3 class="font-semibold mb-3">クイックアクション</h3>
                    <div class="space-y-2">
                        <a href="{{ route('filament.admin.resources.menu-categories.create') }}" 
                           class="flex items-center gap-2 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                            <x-heroicon-o-folder-plus class="w-5 h-5 text-primary-600" />
                            <span>新規カテゴリー</span>
                        </a>
                        <a href="{{ route('filament.admin.resources.menus.create') }}" 
                           class="flex items-center gap-2 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                            <x-heroicon-o-plus-circle class="w-5 h-5 text-primary-600" />
                            <span>新規メニュー</span>
                        </a>
                        <a href="{{ route('filament.admin.resources.menu-categories.index') }}" 
                           class="flex items-center gap-2 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                            <x-heroicon-o-cog class="w-5 h-5 text-gray-600" />
                            <span>カテゴリー詳細設定</span>
                        </a>
                        <a href="{{ route('filament.admin.resources.menus.index') }}" 
                           class="flex items-center gap-2 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                            <x-heroicon-o-list-bullet class="w-5 h-5 text-gray-600" />
                            <span>メニュー詳細一覧</span>
                        </a>
                    </div>
                </div>

                {{-- ヘルプ --}}
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <h3 class="font-semibold mb-2 text-blue-900 dark:text-blue-400">使い方</h3>
                    <ul class="text-sm space-y-1 text-blue-800 dark:text-blue-300">
                        <li>• カテゴリーで時間と料金を設定</li>
                        <li>• メニュー作成時に自動反映</li>
                        <li>• チェックマークで有効/無効切替</li>
                        <li>• 鉛筆アイコンで編集</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>