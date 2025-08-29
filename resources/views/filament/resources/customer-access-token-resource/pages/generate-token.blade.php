<x-filament-panels::page>
    <form wire:submit="generate">
        {{ $this->form }}
        
        <div class="mt-6 flex justify-end gap-x-3">
            <x-filament::button type="button" color="gray" wire:click="$dispatch('close-modal')">
                キャンセル
            </x-filament::button>
            
            <x-filament::button type="submit">
                トークンを生成
            </x-filament::button>
        </div>
    </form>
    
    {{-- 生成済みトークン一覧 --}}
    <div class="mt-8">
        <h3 class="text-lg font-medium mb-4">最近生成されたトークン</h3>
        
        <div class="space-y-3">
            @foreach(\App\Models\CustomerAccessToken::latest()->limit(5)->get() as $token)
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium">{{ $token->customer->name }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                用途: {{ $token->purpose }} | 
                                @if($token->store)
                                    店舗: {{ $token->store->name }}
                                @else
                                    全店舗
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                有効期限: {{ $token->expires_at?->format('Y/m/d') ?? '無期限' }}
                            </p>
                        </div>
                        
                        <div class="flex gap-2">
                            <x-filament::button
                                size="sm"
                                color="gray"
                                x-on:click="navigator.clipboard.writeText('{{ $token->getReservationUrl() }}')"
                            >
                                URLコピー
                            </x-filament::button>
                            
                            <x-filament::button
                                size="sm"
                                color="info"
                                x-on:click="$dispatch('open-modal', { id: 'qr-code-{{ $token->id }}' })"
                            >
                                QRコード
                            </x-filament::button>
                        </div>
                    </div>
                </div>
                
                {{-- QRコードモーダル --}}
                <x-filament::modal id="qr-code-{{ $token->id }}">
                    <x-slot name="heading">
                        QRコード - {{ $token->customer->name }}
                    </x-slot>
                    
                    <div class="text-center">
                        <div class="inline-block p-4 bg-white rounded">
                            {!! QrCode::size(200)->generate($token->getReservationUrl()) !!}
                        </div>
                        <p class="mt-4 text-sm text-gray-600 break-all">
                            {{ $token->getReservationUrl() }}
                        </p>
                    </div>
                </x-filament::modal>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>