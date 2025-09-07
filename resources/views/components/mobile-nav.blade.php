{{-- モバイル用固定ナビゲーションバー --}}
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden z-50">
    <div class="grid grid-cols-4 h-16">
        {{-- ホーム --}}
        <a href="/customer/dashboard" class="flex flex-col items-center justify-center space-y-1 {{ request()->is('customer/dashboard') ? 'text-gray-900' : 'text-gray-500' }} hover:text-gray-900">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-xs">ホーム</span>
        </a>
        
        {{-- 予約 --}}
        <a href="/stores" class="flex flex-col items-center justify-center space-y-1 {{ request()->is('stores*') || request()->is('reservation*') ? 'text-gray-900' : 'text-gray-500' }} hover:text-gray-900">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
            </svg>
            <span class="text-xs">予約</span>
        </a>
        
        {{-- 予約履歴 --}}
        <a href="/customer/reservations" class="flex flex-col items-center justify-center space-y-1 {{ request()->is('customer/reservations*') ? 'text-gray-900' : 'text-gray-500' }} hover:text-gray-900">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="text-xs">履歴</span>
        </a>
        
        {{-- カルテ --}}
        <a href="/customer/medical-records" class="flex flex-col items-center justify-center space-y-1 {{ request()->is('customer/medical-records*') ? 'text-gray-900' : 'text-gray-500' }} hover:text-gray-900">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="text-xs">カルテ</span>
        </a>
    </div>
</div>

{{-- モバイルナビ用の余白 --}}
<div class="h-16 md:hidden"></div>