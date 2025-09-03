<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" wire:click="closeModal"></div>
                
                <div class="relative bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b sticky top-0 bg-white">
                        <h3 class="text-lg font-semibold">シフトパターン管理</h3>
                        <button 
                            wire:click="closeModal"
                            class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <!-- 新規パターン作成 -->
                        <div class="mb-6 p-4 bg-gray-50 rounded">
                            <h4 class="font-medium mb-3">新規パターン作成</h4>
                            
                            <div class="grid grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        パターン名 <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model="newPattern.name"
                                        class="w-full px-3 py-2 border rounded"
                                        placeholder="例: 通常シフト、早番、遅番"
                                    >
                                    @error('newPattern.name')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">色</label>
                                    <input 
                                        type="color" 
                                        wire:model="newPattern.color"
                                        class="w-full h-10 px-2 py-1 border rounded cursor-pointer"
                                    >
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-4 gap-4 mb-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        開始時刻 <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="time" 
                                        wire:model="newPattern.start_time"
                                        class="w-full px-3 py-2 border rounded"
                                    >
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        終了時刻 <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="time" 
                                        wire:model="newPattern.end_time"
                                        class="w-full px-3 py-2 border rounded"
                                    >
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">休憩開始</label>
                                    <input 
                                        type="time" 
                                        wire:model="newPattern.break_start"
                                        class="w-full px-3 py-2 border rounded"
                                    >
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">休憩終了</label>
                                    <input 
                                        type="time" 
                                        wire:model="newPattern.break_end"
                                        class="w-full px-3 py-2 border rounded"
                                    >
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">適用曜日（選択しない場合は全曜日）</label>
                                <div class="flex gap-2">
                                    @foreach(['月', '火', '水', '木', '金', '土', '日'] as $index => $day)
                                        <label class="flex items-center">
                                            <input 
                                                type="checkbox" 
                                                wire:model="newPattern.days_of_week"
                                                value="{{ $index + 1 }}"
                                                class="mr-1"
                                            >
                                            <span>{{ $day }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            
                            <button 
                                wire:click="createPattern"
                                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            >
                                パターン作成
                            </button>
                        </div>
                        
                        <!-- 既存パターン一覧 -->
                        <div>
                            <h4 class="font-medium mb-3">登録済みパターン</h4>
                            
                            @if(count($patterns) > 0)
                                <div class="space-y-2">
                                    @foreach($patterns as $pattern)
                                        @if($editingPattern && $editingPattern['id'] === $pattern['id'])
                                            <!-- 編集モード -->
                                            <div class="p-4 border rounded bg-blue-50">
                                                <div class="grid grid-cols-2 gap-4 mb-3">
                                                    <div>
                                                        <input 
                                                            type="text" 
                                                            wire:model="editingPattern.name"
                                                            class="w-full px-3 py-2 border rounded"
                                                        >
                                                    </div>
                                                    <div>
                                                        <input 
                                                            type="color" 
                                                            wire:model="editingPattern.color"
                                                            class="w-full h-10 px-2 py-1 border rounded"
                                                        >
                                                    </div>
                                                </div>
                                                
                                                <div class="grid grid-cols-4 gap-4 mb-3">
                                                    <div>
                                                        <input 
                                                            type="time" 
                                                            wire:model="editingPattern.start_time"
                                                            class="w-full px-3 py-2 border rounded"
                                                        >
                                                    </div>
                                                    <div>
                                                        <input 
                                                            type="time" 
                                                            wire:model="editingPattern.end_time"
                                                            class="w-full px-3 py-2 border rounded"
                                                        >
                                                    </div>
                                                    <div>
                                                        <input 
                                                            type="time" 
                                                            wire:model="editingPattern.break_start"
                                                            class="w-full px-3 py-2 border rounded"
                                                        >
                                                    </div>
                                                    <div>
                                                        <input 
                                                            type="time" 
                                                            wire:model="editingPattern.break_end"
                                                            class="w-full px-3 py-2 border rounded"
                                                        >
                                                    </div>
                                                </div>
                                                
                                                <div class="flex gap-2">
                                                    <button 
                                                        wire:click="updatePattern"
                                                        class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600"
                                                    >
                                                        保存
                                                    </button>
                                                    <button 
                                                        wire:click="cancelEdit"
                                                        class="px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600"
                                                    >
                                                        キャンセル
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <!-- 表示モード -->
                                            <div class="p-4 border rounded hover:bg-gray-50 flex justify-between items-center">
                                                <div class="flex items-center gap-4">
                                                    <div 
                                                        class="w-4 h-4 rounded"
                                                        style="background-color: {{ $pattern['color'] }}"
                                                    ></div>
                                                    <div>
                                                        <span class="font-medium">{{ $pattern['name'] }}</span>
                                                        <span class="text-gray-600 ml-4">
                                                            {{ substr($pattern['start_time'], 0, 5) }} - {{ substr($pattern['end_time'], 0, 5) }}
                                                        </span>
                                                        @if($pattern['break_start'])
                                                            <span class="text-gray-500 ml-2">
                                                                (休憩: {{ substr($pattern['break_start'], 0, 5) }}-{{ substr($pattern['break_end'], 0, 5) }})
                                                            </span>
                                                        @endif
                                                        @if(!empty($pattern['days_of_week']))
                                                            <span class="text-gray-500 ml-2">
                                                                [{{ implode(',', array_map(function($d) {
                                                                    return ['月','火','水','木','金','土','日'][$d - 1] ?? '';
                                                                }, $pattern['days_of_week'])) }}]
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                
                                                <div class="flex gap-2">
                                                    <button 
                                                        wire:click="editPattern({{ $pattern['id'] }})"
                                                        class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded"
                                                    >
                                                        編集
                                                    </button>
                                                    <button 
                                                        wire:click="deletePattern({{ $pattern['id'] }})"
                                                        onclick="return confirm('このパターンを削除しますか？')"
                                                        class="px-3 py-1 text-red-600 hover:bg-red-50 rounded"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-500">パターンが登録されていません</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>