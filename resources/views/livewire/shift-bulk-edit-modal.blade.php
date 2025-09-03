<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" wire:click="closeModal"></div>
                
                <div class="relative bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b sticky top-0 bg-white">
                        <h3 class="text-lg font-semibold">シフト一括編集</h3>
                        <button 
                            wire:click="closeModal"
                            class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <!-- 日付選択 -->
                        <div class="mb-6">
                            <h4 class="font-medium mb-3">1. 対象日を選択</h4>
                            
                            <div class="mb-3">
                                <button wire:click="selectWeekdays" class="px-3 py-1 mr-2 border rounded hover:bg-gray-100">平日を選択</button>
                                <button wire:click="selectWeekends" class="px-3 py-1 mr-2 border rounded hover:bg-gray-100">週末を選択</button>
                                <button wire:click="selectAll" class="px-3 py-1 mr-2 border rounded hover:bg-gray-100">全て選択</button>
                                <button wire:click="clearSelection" class="px-3 py-1 border rounded hover:bg-gray-100">選択解除</button>
                            </div>
                            
                            <div class="grid grid-cols-7 gap-2">
                                @foreach($daysInMonth as $day)
                                    <label class="flex items-center p-2 border rounded cursor-pointer hover:bg-gray-50">
                                        <input 
                                            type="checkbox" 
                                            wire:model="selectedDates"
                                            value="{{ $day['date'] }}"
                                            class="mr-2"
                                        >
                                        <span class="text-sm">
                                            {{ $day['label'] }}({{ $day['dayOfWeek'] }})
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        <!-- スタッフ設定 -->
                        <div class="mb-6">
                            <h4 class="font-medium mb-3">2. スタッフとシフトを設定</h4>
                            
                            <div class="space-y-3">
                                @foreach($staffShifts as $userId => $shift)
                                    <div class="grid grid-cols-12 gap-3 items-center p-3 bg-gray-50 rounded">
                                        <div class="col-span-1">
                                            <input 
                                                type="checkbox" 
                                                wire:model="staffShifts.{{ $userId }}.enabled"
                                            >
                                        </div>
                                        
                                        <div class="col-span-2 font-medium">
                                            {{ $shift['name'] }}
                                        </div>
                                        
                                        <div class="col-span-3">
                                            <select 
                                                wire:change="updatePattern({{ $userId }}, $event.target.value)"
                                                class="w-full px-3 py-1 border rounded"
                                                {{ !$shift['enabled'] ? 'disabled' : '' }}
                                            >
                                                <option value="">カスタム</option>
                                                <option value="full">終日(10:00-20:00)</option>
                                                <option value="morning">朝(10:00-15:00)</option>
                                                <option value="afternoon">昼(13:00-20:00)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-span-2">
                                            <input 
                                                type="time" 
                                                wire:model="staffShifts.{{ $userId }}.start_time"
                                                class="w-full px-3 py-1 border rounded"
                                                {{ !$shift['enabled'] ? 'disabled' : '' }}
                                            >
                                        </div>
                                        
                                        <div class="col-span-1 text-center">
                                            〜
                                        </div>
                                        
                                        <div class="col-span-2">
                                            <input 
                                                type="time" 
                                                wire:model="staffShifts.{{ $userId }}.end_time"
                                                class="w-full px-3 py-1 border rounded"
                                                {{ !$shift['enabled'] ? 'disabled' : '' }}
                                            >
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button 
                                wire:click="closeModal"
                                class="px-4 py-2 border rounded hover:bg-gray-100"
                            >
                                キャンセル
                            </button>
                            <button 
                                wire:click="save"
                                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            >
                                一括登録
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>