<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" wire:click="closeModal"></div>
                
                <div class="relative bg-white rounded-lg max-w-2xl w-full">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold">
                            シフト編集 - {{ \Carbon\Carbon::parse($selectedDate)->format('Y年n月j日') }}
                        </h3>
                        <button 
                            wire:click="closeModal"
                            class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div class="space-y-4">
                            @foreach($staffShifts as $userId => $shift)
                                <div class="grid grid-cols-12 gap-3 items-center p-3 bg-gray-50 rounded">
                                    <div class="col-span-3 font-medium">
                                        {{ $shift['name'] }}
                                    </div>
                                    
                                    <div class="col-span-3">
                                        <input 
                                            type="time" 
                                            wire:model="staffShifts.{{ $userId }}.start_time"
                                            class="w-full px-3 py-1 border rounded"
                                            {{ $shift['is_holiday'] ? 'disabled' : '' }}
                                        >
                                    </div>
                                    
                                    <div class="col-span-1 text-center">
                                        〜
                                    </div>
                                    
                                    <div class="col-span-3">
                                        <input 
                                            type="time" 
                                            wire:model="staffShifts.{{ $userId }}.end_time"
                                            class="w-full px-3 py-1 border rounded"
                                            {{ $shift['is_holiday'] ? 'disabled' : '' }}
                                        >
                                    </div>
                                    
                                    <div class="col-span-2">
                                        <label class="flex items-center">
                                            <input 
                                                type="checkbox" 
                                                wire:model="staffShifts.{{ $userId }}.is_holiday"
                                                class="mr-2"
                                            >
                                            休み
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
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
                                保存
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>