@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">オプション選択</h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">選択中のメニュー</h2>
        <div class="flex justify-between items-center">
            <div>
                <p class="text-lg">{{ $menu->name }}</p>
                <p class="text-gray-600">{{ $menu->duration_minutes }}分 / ¥{{ number_format($menu->price) }}</p>
            </div>
        </div>
    </div>

    <form action="{{ route('reservation.store-options') }}" method="POST" id="optionForm">
        @csrf
        <input type="hidden" name="menu_id" value="{{ $menu->id }}">
        
        @if($menu->options->where('is_required', true)->count() > 0)
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 text-red-600">必須オプション</h3>
                <div class="space-y-4">
                    @foreach($menu->options->where('is_required', true)->sortBy('sort_order') as $option)
                        <div class="border rounded-lg p-4 bg-red-50">
                            <div class="flex items-start">
                                <input type="hidden" name="options[{{ $option->id }}][selected]" value="1">
                                <div class="flex-1">
                                    <label class="font-medium">
                                        {{ $option->name }}
                                        <span class="text-red-500 text-sm">（必須）</span>
                                    </label>
                                    @if($option->description)
                                        <p class="text-sm text-gray-600 mt-1">{{ $option->description }}</p>
                                    @endif
                                    <div class="mt-2 flex items-center justify-between">
                                        <div class="text-sm">
                                            @if($option->price > 0)
                                                <span class="font-medium">+¥{{ number_format($option->price) }}</span>
                                            @endif
                                            @if($option->duration_minutes > 0)
                                                <span class="text-gray-600 ml-2">+{{ $option->duration_minutes }}分</span>
                                            @endif
                                        </div>
                                        @if($option->max_quantity > 1)
                                            <div class="flex items-center">
                                                <label class="text-sm mr-2">数量:</label>
                                                <select name="options[{{ $option->id }}][quantity]" 
                                                        class="form-select text-sm rounded border-gray-300"
                                                        data-option-id="{{ $option->id }}"
                                                        data-price="{{ $option->price }}"
                                                        data-duration="{{ $option->duration_minutes }}">
                                                    @for($i = 1; $i <= $option->max_quantity; $i++)
                                                        <option value="{{ $i }}">{{ $i }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                        @else
                                            <input type="hidden" name="options[{{ $option->id }}][quantity]" value="1">
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($menu->options->where('is_required', false)->count() > 0)
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">追加オプション（任意）</h3>
                <div class="space-y-4">
                    @foreach($menu->options->where('is_required', false)->sortBy('sort_order') as $option)
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition">
                            <div class="flex items-start">
                                <input type="checkbox" 
                                       name="options[{{ $option->id }}][selected]" 
                                       value="1"
                                       id="option_{{ $option->id }}"
                                       class="mt-1 mr-3 option-checkbox"
                                       data-option-id="{{ $option->id }}"
                                       data-price="{{ $option->price }}"
                                       data-duration="{{ $option->duration_minutes }}">
                                <div class="flex-1">
                                    <label for="option_{{ $option->id }}" class="font-medium cursor-pointer">
                                        {{ $option->name }}
                                    </label>
                                    @if($option->description)
                                        <p class="text-sm text-gray-600 mt-1">{{ $option->description }}</p>
                                    @endif
                                    <div class="mt-2 flex items-center justify-between">
                                        <div class="text-sm">
                                            @if($option->price > 0)
                                                <span class="font-medium text-green-600">+¥{{ number_format($option->price) }}</span>
                                            @else
                                                <span class="text-gray-500">無料</span>
                                            @endif
                                            @if($option->duration_minutes > 0)
                                                <span class="text-gray-600 ml-2">+{{ $option->duration_minutes }}分</span>
                                            @endif
                                        </div>
                                        @if($option->max_quantity > 1)
                                            <div class="flex items-center quantity-selector" style="display: none;">
                                                <label class="text-sm mr-2">数量:</label>
                                                <select name="options[{{ $option->id }}][quantity]" 
                                                        class="form-select text-sm rounded border-gray-300 option-quantity"
                                                        data-option-id="{{ $option->id }}">
                                                    @for($i = 1; $i <= $option->max_quantity; $i++)
                                                        <option value="{{ $i }}">{{ $i }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                        @else
                                            <input type="hidden" name="options[{{ $option->id }}][quantity]" value="1">
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-md p-6 mb-6 sticky bottom-0">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">合計</h3>
                <div class="text-right">
                    <p class="text-2xl font-bold" id="totalPrice">¥{{ number_format($menu->price) }}</p>
                    <p class="text-gray-600" id="totalDuration">{{ $menu->duration_minutes }}分</p>
                </div>
            </div>
            
            <div class="flex gap-4">
                <button type="button" onclick="history.back()" class="flex-1 bg-gray-200 text-gray-800 py-3 px-6 rounded-lg hover:bg-gray-300 transition">
                    戻る
                </button>
                <button type="submit" class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg hover:bg-blue-700 transition">
                    次へ進む
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const basePrice = {{ $menu->price }};
    const baseDuration = {{ $menu->duration_minutes }};
    let totalPrice = basePrice;
    let totalDuration = baseDuration;
    
    // 必須オプションの初期計算
    @foreach($menu->options->where('is_required', true) as $option)
        totalPrice += {{ $option->price }};
        totalDuration += {{ $option->duration_minutes }};
    @endforeach
    
    updateTotals();
    
    // チェックボックスの変更イベント
    document.querySelectorAll('.option-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const optionId = this.dataset.optionId;
            const price = parseInt(this.dataset.price);
            const duration = parseInt(this.dataset.duration);
            const quantitySelector = this.closest('.border').querySelector('.quantity-selector');
            
            if (this.checked) {
                if (quantitySelector) {
                    quantitySelector.style.display = 'flex';
                }
                totalPrice += price;
                totalDuration += duration;
            } else {
                if (quantitySelector) {
                    quantitySelector.style.display = 'none';
                }
                totalPrice -= price;
                totalDuration -= duration;
            }
            
            updateTotals();
        });
    });
    
    // 数量変更イベント
    document.querySelectorAll('select[data-price]').forEach(select => {
        const originalValue = select.value;
        select.addEventListener('change', function() {
            const price = parseInt(this.dataset.price);
            const duration = parseInt(this.dataset.duration);
            const oldQuantity = parseInt(originalValue);
            const newQuantity = parseInt(this.value);
            const diff = newQuantity - oldQuantity;
            
            totalPrice += price * diff;
            totalDuration += duration * diff;
            
            this.dataset.originalValue = newQuantity;
            updateTotals();
        });
    });
    
    function updateTotals() {
        document.getElementById('totalPrice').textContent = '¥' + totalPrice.toLocaleString();
        document.getElementById('totalDuration').textContent = totalDuration + '分';
    }
});
</script>
@endsection