<x-filament-panels::page>
    <div class="space-y-4">
        {{-- タブナビゲーション --}}
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button wire:click="setTab('general')"
                            class="group relative min-w-0 flex-1 overflow-hidden py-4 px-6 text-center text-sm font-medium hover:bg-gray-50 focus:z-10 transition-colors {{ $tab === 'general' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700' }}">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            お知らせ
                        </span>
                    </button>

                    <button wire:click="setTab('order')"
                            class="group relative min-w-0 flex-1 overflow-hidden py-4 px-6 text-center text-sm font-medium hover:bg-gray-50 focus:z-10 transition-colors {{ $tab === 'order' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700' }}">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            発注通知
                        </span>
                    </button>
                </nav>
            </div>
        </div>

        {{-- フィルタとアクション --}}
        <div class="flex items-center justify-between bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-2">
                <button wire:click="$set('filter', 'all')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'all' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    すべて
                </button>
                <button wire:click="$set('filter', 'unread')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'unread' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    未読のみ
                </button>
                <button wire:click="$set('filter', 'read')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'read' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    既読のみ
                </button>
            </div>

            <button wire:click="markAllAsRead"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                すべて既読にする
            </button>
        </div>

        {{-- お知らせ一覧 --}}
        @php
            $announcements = $this->getAnnouncements();
        @endphp

        @if($announcements->count() > 0)
            <div class="space-y-3">
                @foreach($announcements as $announcement)
                    <div class="bg-white rounded-lg border border-gray-200 hover:shadow-md transition-shadow"
                         x-data
                         x-init="@if(!$announcement->isReadBy(auth()->id())) $wire.markAsRead({{ $announcement->id }}) @endif">
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 mt-1">
                                    @if($announcement->priority === 'urgent')
                                        <span class="inline-flex items-center justify-center w-3 h-3 bg-red-500 rounded-full"></span>
                                    @elseif($announcement->priority === 'important')
                                        <span class="inline-flex items-center justify-center w-3 h-3 bg-yellow-500 rounded-full"></span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-3 h-3 bg-blue-500 rounded-full"></span>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                {{ $announcement->title }}
                                            </h3>
                                            @if($announcement->priority === 'urgent')
                                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">
                                                    緊急
                                                </span>
                                            @elseif($announcement->priority === 'important')
                                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded">
                                                    重要
                                                </span>
                                            @endif
                                            @if(!$announcement->isReadBy(auth()->id()))
                                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                                    未読
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded">
                                                    既読
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-500 flex-shrink-0">
                                            {{ $announcement->published_at->format('Y年m月d日 H:i') }}
                                        </p>
                                    </div>

                                    <div class="prose prose-sm max-w-none text-gray-700">
                                        {!! linkify_urls($announcement->content) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ページネーション --}}
            <div class="mt-4">
                {{ $announcements->links() }}
            </div>
        @else
            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="mt-4 text-lg text-gray-500">
                    @if($tab === 'order')
                        @if($filter === 'unread')
                            未読の発注通知はありません
                        @elseif($filter === 'read')
                            既読の発注通知はありません
                        @else
                            発注通知はありません
                        @endif
                    @else
                        @if($filter === 'unread')
                            未読のお知らせはありません
                        @elseif($filter === 'read')
                            既読のお知らせはありません
                        @else
                            お知らせはありません
                        @endif
                    @endif
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
