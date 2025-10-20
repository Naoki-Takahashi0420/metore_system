<x-filament-widgets::widget>
    <div class="p-4">
        @if(count($announcements) > 0)
            <div x-data="{
                currentIndex: 0,
                announcements: {{ json_encode($announcements->map(function($a) {
                    return [
                        'id' => $a->id,
                        'title' => $a->title,
                        'priority' => $a->priority,
                        'content' => linkify_urls($a->content),
                        'published_at' => $a->published_at->format('Y年m月d日 H:i'),
                        'is_read' => $a->isReadBy(auth()->id())
                    ];
                })->values()) }},
                showModal: false,
                intervalId: null,
                init() {
                    this.startCarousel();
                },
                startCarousel() {
                    this.intervalId = setInterval(() => {
                        if (!this.showModal) {
                            this.currentIndex = (this.currentIndex + 1) % this.announcements.length;
                        }
                    }, 5000);
                },
                openModal() {
                    this.showModal = true;
                    $wire.markAsRead(this.announcements[this.currentIndex].id);
                }
            }">
                {{-- ヘッダー --}}
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                        本部からのお知らせ
                        @if($unreadCount > 0)
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full">
                                {{ $unreadCount }}
                            </span>
                        @endif
                    </h3>
                    <a href="/admin/announcements-list"
                       class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                        すべて見る
                    </a>
                </div>

                {{-- カルーセル（1件表示） --}}
                <div @click="openModal()" class="cursor-pointer hover:bg-gray-50 transition-colors rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center gap-3">
                        {{-- 優先度インジケーター --}}
                        <div class="flex-shrink-0">
                            <span x-show="announcements[currentIndex].priority === 'urgent'"
                                  class="inline-flex items-center justify-center w-2 h-2 bg-red-500 rounded-full"></span>
                            <span x-show="announcements[currentIndex].priority === 'important'"
                                  class="inline-flex items-center justify-center w-2 h-2 bg-yellow-500 rounded-full"></span>
                            <span x-show="announcements[currentIndex].priority === 'normal'"
                                  class="inline-flex items-center justify-center w-2 h-2 bg-blue-500 rounded-full"></span>
                        </div>

                        {{-- タイトルと優先度バッジ --}}
                        <div class="flex-1 min-w-0 flex items-center gap-2">
                            <h4 x-text="announcements[currentIndex].title"
                                class="text-sm font-medium text-gray-900 truncate"></h4>

                            <span x-show="announcements[currentIndex].priority === 'urgent'"
                                  class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 rounded flex-shrink-0">
                                緊急
                            </span>
                            <span x-show="announcements[currentIndex].priority === 'important'"
                                  class="px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800 rounded flex-shrink-0">
                                重要
                            </span>

                            <span x-show="!announcements[currentIndex].is_read"
                                  class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded flex-shrink-0">
                                未読
                            </span>
                        </div>

                        {{-- ページインジケーター --}}
                        <div class="flex gap-1 flex-shrink-0">
                            <template x-for="(announcement, index) in announcements" :key="index">
                                <div class="w-1.5 h-1.5 rounded-full transition-colors"
                                     :class="index === currentIndex ? 'bg-primary-600' : 'bg-gray-300'"></div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- モーダル --}}
                <div x-show="showModal"
                     x-cloak
                     @click.away="showModal = false"
                     class="fixed inset-0 z-50 overflow-y-auto"
                     style="display: none;">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                             @click="showModal = false"></div>

                        <div class="relative inline-block w-full max-w-2xl px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:p-6"
                             @click.stop>
                            {{-- タイトルと優先度バッジ --}}
                            <div class="mb-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span x-show="announcements[currentIndex].priority === 'urgent'"
                                          class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded">
                                        緊急
                                    </span>
                                    <span x-show="announcements[currentIndex].priority === 'important'"
                                          class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded">
                                        重要
                                    </span>
                                    <span x-show="announcements[currentIndex].priority === 'normal'"
                                          class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded">
                                        お知らせ
                                    </span>
                                </div>
                                <h2 x-text="announcements[currentIndex].title"
                                    class="text-2xl font-bold text-gray-900"></h2>
                                <p x-text="announcements[currentIndex].published_at"
                                   class="mt-1 text-sm text-gray-500"></p>
                            </div>

                            {{-- 本文 --}}
                            <div x-html="announcements[currentIndex].content"
                                 class="prose prose-sm max-w-none"></div>

                            {{-- 閉じるボタン --}}
                            <div class="mt-6">
                                <button @click="showModal = false"
                                        class="w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    閉じる
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="p-6 text-center text-gray-500">
                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="mt-2 text-xs">お知らせはありません</p>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
