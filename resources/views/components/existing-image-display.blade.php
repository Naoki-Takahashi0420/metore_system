@if($exists && $file_path)
<div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
    <div class="flex items-center space-x-4">
        <div class="flex-shrink-0">
            <img src="{{ Storage::disk('public')->url($file_path) }}" 
                 alt="既存画像" 
                 class="w-20 h-20 object-cover rounded-md border border-gray-200"
                 onclick="window.open('{{ Storage::disk('public')->url($file_path) }}', '_blank')"
                 style="cursor: pointer;">
        </div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-900">既存の画像ファイル</p>
            <p class="text-sm text-gray-500">{{ basename($file_path) }}</p>
            <p class="text-xs text-blue-600 mt-1">クリックで拡大表示</p>
        </div>
    </div>
</div>
@endif