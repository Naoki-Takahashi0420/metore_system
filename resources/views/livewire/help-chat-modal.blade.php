<div>
    @if($isOpen)
    <div style="position: fixed !important; inset: 0 !important; z-index: 99998 !important; overflow-y: auto;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- 背景オーバーレイ -->
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div style="position: fixed !important; inset: 0 !important; background-color: rgba(0, 0, 0, 0.5) !important; transition: opacity 0.3s;" wire:click="close"></div>

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

                            <!-- 最新のアシスタント回答の直後にフィードバックボタンを表示 -->
                            @if($message['role'] === 'assistant' && $index === count($conversationHistory) - 1 && $currentLogId && !$showFeedbackForm)
                                <div class="mt-2 flex items-center space-x-2">
                                    <button
                                        wire:click="markResolved"
                                        class="text-xs bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded-full transition"
                                    >
                                        ✓ 解決しました
                                    </button>
                                    <button
                                        wire:click="showFeedback"
                                        class="text-xs bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-full transition"
                                    >
                                        ✗ 解決しませんでした
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if($isLoading)
                        <div class="text-left mb-4">
                            <div class="inline-block bg-blue-50 rounded-lg px-6 py-4 shadow">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 border-3 border-blue-200 rounded-full animate-spin border-t-blue-600"></div>
                                    <div>
                                        <p class="text-sm font-medium text-blue-900">回答を作成中...</p>
                                        <p class="text-xs text-blue-600 mt-1">AIが考えています</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- フィードバックフォーム -->
                @if($showFeedbackForm)
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 px-4 py-3">
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                どのような点が解決しませんでしたか？管理者に報告されます。
                            </label>
                            <textarea
                                wire:model="feedbackMessage"
                                rows="3"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="例：この画面の表示方法がわからない、エラーが解決しない、など"
                            ></textarea>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                wire:click="submitFeedback"
                                class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                            >
                                送信
                            </button>
                            <button
                                wire:click="cancelFeedback"
                                class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                キャンセル
                            </button>
                        </div>
                    </div>
                @endif

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
                    @if($isLoading)
                        <!-- ローディング表示 -->
                        <div class="flex items-center justify-center py-8 space-x-3">
                            <div class="relative">
                                <div class="w-12 h-12 border-4 border-blue-200 rounded-full animate-spin border-t-blue-600"></div>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-medium text-gray-700">AIが回答を作成中...</p>
                                <p class="text-xs text-gray-500 mt-1">しばらくお待ちください</p>
                            </div>
                        </div>
                    @else
                        <form wire:submit.prevent="sendMessage">
                            <div class="flex items-end space-x-2">
                                <div class="flex-1">
                                    <textarea
                                        wire:model="message"
                                        rows="2"
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        placeholder="質問を入力してください..."
                                    ></textarea>
                                </div>
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    @endif
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
