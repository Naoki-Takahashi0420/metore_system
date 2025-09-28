<x-filament-panels::page>
    @if ($this->hasInfolist())
        {{ $this->infolist }}
    @else
        <x-filament::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
        />
    @endif

    <x-filament-actions::modals />

    {{-- 予約変更完了通知モーダル --}}
    @if(session('reservation_updated'))
        <div x-data="{ open: true }" x-show="open" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="open = false"></div>

                <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">
                                予約変更完了
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-600 whitespace-pre-line">{{ session('success') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <a href="{{ route('filament.admin.resources.reservations.view', session('reservation_id')) }}"
                           class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm">
                            予約詳細を見る
                        </a>
                        <button type="button" @click="open = false"
                                class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-1 sm:mt-0 sm:text-sm">
                            閉じる
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // 5秒後に自動的にモーダルを閉じる
            setTimeout(function() {
                document.querySelector('[x-data]').__x.$data.open = false;
            }, 5000);
        </script>
    @endif

    {{-- 通常のフラッシュメッセージ（モーダル非表示の場合） --}}
    @if(session('success') && !session('reservation_updated'))
        <div x-data="{ show: true }" x-show="show"
             class="fixed top-4 right-4 z-50 max-w-sm p-4 bg-green-100 border border-green-400 rounded-lg shadow-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
                <div class="ml-auto">
                    <button @click="show = false" class="text-green-400 hover:text-green-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>