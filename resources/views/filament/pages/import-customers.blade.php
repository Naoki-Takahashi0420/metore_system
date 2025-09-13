<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="import"
            >
                <span wire:loading.remove wire:target="import">
                    インポート実行
                </span>
                <span wire:loading wire:target="import">
                    処理中...
                </span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                wire:click="downloadTemplate"
            >
                テンプレートダウンロード
            </x-filament::button>
        </div>
    </form>

    @if($importResults)
        <div class="mt-8">
            <x-filament::section>
                <x-slot name="heading">
                    インポート結果
                </x-slot>

                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <div class="text-sm text-green-600 dark:text-green-400 font-medium">成功</div>
                            <div class="text-2xl font-bold text-green-700 dark:text-green-300">
                                {{ $importResults['success_count'] }}件
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                            <div class="text-sm text-yellow-600 dark:text-yellow-400 font-medium">スキップ</div>
                            <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">
                                {{ $importResults['skip_count'] }}件
                            </div>
                        </div>
                        
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                            <div class="text-sm text-red-600 dark:text-red-400 font-medium">エラー</div>
                            <div class="text-2xl font-bold text-red-700 dark:text-red-300">
                                {{ $importResults['error_count'] }}件
                            </div>
                        </div>
                    </div>

                    @if(!empty($importResults['errors']))
                        <div class="mt-4">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                エラー詳細（最初の10件）
                            </h3>
                            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                                <ul class="space-y-1 text-sm text-red-700 dark:text-red-300">
                                    @foreach(array_slice($importResults['errors'], 0, 10) as $error)
                                        <li>行 {{ $error['row'] }}: {{ $error['message'] }}</li>
                                    @endforeach
                                    @if(count($importResults['errors']) > 10)
                                        <li class="text-red-600 dark:text-red-400 font-medium">
                                            他 {{ count($importResults['errors']) - 10 }}件のエラー
                                        </li>
                                    @endif
                                </ul>
                            </div>
                            
                            @if(isset($importResults['error_file']))
                                <div class="mt-3">
                                    <x-filament::button
                                        type="button"
                                        size="sm"
                                        color="danger"
                                        wire:click="downloadErrorLog"
                                    >
                                        エラーログをダウンロード
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </x-filament::section>
        </div>
    @endif

    <script>
        window.addEventListener('download-file', event => {
            const { filename, content, mimeType } = event.detail[0];
            const blob = new Blob([content], { type: mimeType });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        });
    </script>
</x-filament-panels::page>