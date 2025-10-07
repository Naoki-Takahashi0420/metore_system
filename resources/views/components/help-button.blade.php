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
<div style="position:fixed !important; bottom:24px !important; right:24px !important; z-index:99999 !important;">
    <button
        onclick="window.Livewire.dispatch('open-help-chat')"
        onmouseover="this.style.transform='scale(1.1)'"
        onmouseout="this.style.transform='scale(1)'"
        style="background:#2563eb; color:white; border-radius:9999px; padding:1rem; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); transition: all 0.2s; cursor: pointer;"
        title="AIヘルプチャット"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </button>
</div>
@endif
