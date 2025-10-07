<div>
    @if($isOpen)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- 背景オーバーレイ -->
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="close"></div>

            <!-- モーダルパネル -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <!-- ヘッダー -->
                <div class="bg-blue-600 px-4 py-3 sm:px-6 flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        AIヘルプチャット
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button wire:click="clearHistory" class="text-white hover:text-gray-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                        <button wire:click="close" class="text-white hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- チャット履歴 -->
                <div class="bg-gray-50 px-4 py-5 sm:p-6 h-96 overflow-y-auto" id="chat-history">
                    @foreach($conversationHistory as $index => $message)
                        <div class="mb-4 {{ $message['role'] === 'user' ? 'text-right' : 'text-left' }}">
                            <div class="inline-block max-w-3/4 {{ $message['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-white text-gray-800' }} rounded-lg px-4 py-2 shadow">
                                <div class="text-sm whitespace-pre-wrap">{!! nl2br(e($message['content'])) !!}</div>
                            </div>
                        </div>
                    @endforeach

                    @if($isLoading)
                        <div class="text-left mb-4">
                            <div class="inline-block bg-white rounded-lg px-4 py-2 shadow">
                                <div class="flex items-center space-x-2">
                                    <div class="animate-bounce w-2 h-2 bg-blue-600 rounded-full"></div>
                                    <div class="animate-bounce w-2 h-2 bg-blue-600 rounded-full" style="animation-delay: 0.1s"></div>
                                    <div class="animate-bounce w-2 h-2 bg-blue-600 rounded-full" style="animation-delay: 0.2s"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- エラーメッセージ -->
                @if($errorMessage)
                    <div class="bg-red-50 border-l-4 border-red-400 px-4 py-3">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 入力エリア -->
                <div class="bg-white px-4 py-3 sm:px-6 border-t border-gray-200">
                    <form wire:submit.prevent="sendMessage">
                        <div class="flex items-end space-x-2">
                            <div class="flex-1">
                                <textarea
                                    wire:model="message"
                                    rows="2"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    placeholder="質問を入力してください..."
                                    @if($isLoading) disabled @endif
                                ></textarea>
                            </div>
                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                @if($isLoading) disabled @endif
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // チャット履歴を最下部にスクロール
        document.addEventListener('livewire:update', () => {
            const chatHistory = document.getElementById('chat-history');
            if (chatHistory) {
                chatHistory.scrollTop = chatHistory.scrollHeight;
            }
        });
    </script>
    @endif
</div>
