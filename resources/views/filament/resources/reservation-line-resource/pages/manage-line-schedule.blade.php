<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h2 class="text-lg font-semibold mb-4">{{ $record->line_name }} のスケジュール管理</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <span class="text-sm text-gray-500">店舗</span>
                    <p class="font-medium">{{ $record->store->name }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">ライン種別</span>
                    <p class="font-medium">{{ $record->line_type === 'main' ? '本ライン' : '予備ライン' }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">同時施術可能数</span>
                    <p class="font-medium">{{ $record->capacity }}名</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500">ステータス</span>
                    <p class="font-medium">
                        @if($record->is_active)
                            <span class="text-green-600">有効</span>
                        @else
                            <span class="text-red-600">無効</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
        
        {{ $this->table }}
    </div>
</x-filament-panels::page>