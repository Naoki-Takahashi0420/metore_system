<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" size="lg">
                設定を保存
            </x-filament::button>
        </div>
    </form>
    
    {{-- プロモーション送信セクション --}}
    <div class="mt-12 p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold mb-4">④ プロモーション一斉送信</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            全顧客に今すぐメッセージを送信します
        </p>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">メッセージ内容</label>
                <textarea wire:model="promotionMessage"
                          rows="5"
                          class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                          placeholder="お得なキャンペーンのお知らせです！&#10;&#10;@{{customer_name}}様&#10;今月限定で全メニュー20%OFF！"></textarea>
                <p class="text-xs text-gray-500 mt-1">
                    使用可能: @{{ customer_name }}
                </p>
            </div>
            
            <x-filament::button wire:click="sendPromotion" color="success" size="lg">
                今すぐ全員に送信
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>