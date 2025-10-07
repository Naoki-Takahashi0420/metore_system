@php
    $claudeEnabled = false;

    try {
        if (DB::getSchemaBuilder()->hasTable('settings')) {
            $setting = DB::table('settings')->where('key', 'claude.enabled')->value('value');
            $claudeEnabled = $setting === '1' || $setting === true;
        }
    } catch (\Exception $e) {
        // settingsテーブルが存在しない場合は無効
        $claudeEnabled = false;
    }
@endphp

@if($claudeEnabled)
<!-- Livewireコンポーネント読み込み -->
@livewire('help-chat-modal')

<!-- ヘルプボタン（右下固定） -->
<div class="fixed bottom-6 right-6 z-40">
    <button
        onclick="Livewire.dispatch('open-help-chat')"
        class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        title="AIヘルプチャット"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </button>
</div>
@endif
