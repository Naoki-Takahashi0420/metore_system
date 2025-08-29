<x-filament-panels::page>
    <div class="space-y-8">
        
        {{-- ① 予約確認 --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold mb-4">① 予約確認</h2>
            <label class="flex items-center space-x-3">
                <input type="checkbox" 
                       wire:model="send_confirmation"
                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                <span>予約完了時に確認メッセージを送る</span>
            </label>
        </div>

        {{-- ② リマインダー --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold mb-4">② リマインダー</h2>
            <div class="space-y-3">
                <label class="flex items-center space-x-3">
                    <input type="checkbox" 
                           wire:model="reminder_24h"
                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                    <span>24時間前に送る</span>
                </label>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" 
                           wire:model="reminder_3h"
                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                    <span>3時間前に送る</span>
                </label>
            </div>
        </div>

        {{-- ③ プロモーション --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold mb-4">③ プロモーション（全員に送信）</h2>
            <div class="space-y-4">
                <textarea wire:model="promotion_message"
                          rows="4"
                          class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                          placeholder="キャンペーンのお知らせなど..."></textarea>
                <button wire:click="sendPromotion"
                        type="button"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                    今すぐ全員に送信
                </button>
            </div>
        </div>

        {{-- ④ 初回フォロー --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold mb-4">④ 初回客フォロー</h2>
            <div class="space-y-3">
                <label class="flex items-center space-x-3">
                    <input type="checkbox" 
                           wire:model="follow_30days"
                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                    <span>30日後にフォロー（2回目10%OFF）</span>
                </label>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" 
                           wire:model="follow_60days"
                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                    <span>60日後にフォロー（特別20%OFF）</span>
                </label>
            </div>
        </div>

        {{-- 保存ボタン --}}
        <div class="flex justify-end">
            <button wire:click="save"
                    type="button"
                    class="bg-primary-600 text-white px-8 py-3 rounded-lg hover:bg-primary-700">
                設定を保存
            </button>
        </div>
    </div>
</x-filament-panels::page>