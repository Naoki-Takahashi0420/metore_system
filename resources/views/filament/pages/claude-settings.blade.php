<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-between items-center mt-6">
            <x-filament::button
                type="button"
                wire:click="testConnection"
                color="gray"
                outlined
            >
                接続テスト
            </x-filament::button>

            <x-filament::button type="submit">
                設定を保存
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            使い方
        </x-slot>

        <div class="prose dark:prose-invert max-w-none">
            <h3>Claude APIキーの取得方法</h3>
            <ol>
                <li><a href="https://console.anthropic.com/" target="_blank" class="text-primary-600">Anthropic Console</a>にアクセス</li>
                <li>「API Keys」セクションで新しいキーを作成</li>
                <li>作成したキーをコピーして上記のフィールドに貼り付け</li>
                <li>「接続テスト」ボタンで動作確認</li>
                <li>「設定を保存」で有効化</li>
            </ol>

            <h3>ヘルプチャットの使い方</h3>
            <p>設定を有効化すると、管理画面の右下に「?」ボタンが表示されます。クリックするとAIヘルプチャットが開き、システムの使い方を質問できます。</p>

            <h3>料金について</h3>
            <p>Claude API（claude-3-5-sonnet）の料金目安：</p>
            <ul>
                <li>入力: $3.00 / 1M トークン</li>
                <li>出力: $15.00 / 1M トークン</li>
                <li>1回の質問: 約$0.01-0.05（マニュアルの量による）</li>
                <li>月間1000回利用: 約$10-50</li>
            </ul>
            <p class="text-sm text-gray-600">※ Prompt Cachingを使用することでコストを最大90%削減できます。</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
