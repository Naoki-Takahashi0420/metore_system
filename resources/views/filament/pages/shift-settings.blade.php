<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 店舗選択 --}}
        @if(count($stores) > 1)
        <div>
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">対象店舗</label>
            <select wire:model.live="selectedStore" wire:change="changeStore" 
                class="w-full max-w-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                @foreach($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        
        {{-- シフトテンプレート設定 --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">シフトテンプレート</h3>
                <button wire:click="addTemplate" 
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                    + テンプレート追加
                </button>
            </div>
            
            <div class="space-y-4">
                @foreach($shiftTemplates as $index => $template)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">テンプレート名</label>
                            <input type="text" 
                                wire:model="shiftTemplates.{{ $index }}.name"
                                placeholder="例: 早番"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">開始時間</label>
                            <input type="time" 
                                wire:model="shiftTemplates.{{ $index }}.start_time"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">終了時間</label>
                            <input type="time" 
                                wire:model="shiftTemplates.{{ $index }}.end_time"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        </div>
                        
                        <div>
                            <button wire:click="removeTemplate({{ $index }})" 
                                class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm w-full">
                                削除
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
                
                @if(empty($shiftTemplates))
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    テンプレートがありません。「テンプレート追加」ボタンから追加してください。
                </div>
                @endif
            </div>
            
            <div class="mt-6 flex justify-end">
                <button wire:click="save" 
                    class="px-6 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                    保存
                </button>
            </div>
        </div>
        
        {{-- 説明 --}}
        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-4">
            <h4 class="font-semibold text-blue-900 dark:text-blue-200 mb-2">シフトテンプレートとは</h4>
            <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1">
                <li>• 店舗ごとによく使うシフトパターンを事前に登録できます</li>
                <li>• シフト管理画面でテンプレートを選択するだけで、簡単にシフトを作成できます</li>
                <li>• 休憩時間はシフト作成時に個別に設定できます</li>
                @if(auth()->user()->hasRole(['manager', 'staff']))
                <li class="text-orange-700 dark:text-orange-300">• スタッフは所属店舗のテンプレートのみ編集できます</li>
                @endif
            </ul>
        </div>
        
        {{-- 権限に関する注意事項 --}}
        @if(auth()->user()->hasRole('staff'))
        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded-lg p-3">
            <p class="text-sm text-yellow-800 dark:text-yellow-300">
                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                所属店舗のシフトテンプレートを編集できます。変更は即座に反映されますので、慎重に編集してください。
            </p>
        </div>
        @endif
    </div>
</x-filament-panels::page>