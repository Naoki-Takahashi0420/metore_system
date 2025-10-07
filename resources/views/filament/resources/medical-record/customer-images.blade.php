@php
    $images = $images ?? collect([]);
@endphp

<div class="space-y-4">
    @foreach ($images as $image)
        <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
            <div class="grid grid-cols-4 gap-4 items-center">
                {{-- 画像 --}}
                <div class="col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">画像</dt>
                    <dd>
                        @if ($image->file_path)
                            <img src="{{ asset('storage/' . $image->file_path) }}"
                                 alt="{{ $image->title ?? '顧客画像' }}"
                                 class="w-full max-h-48 object-contain rounded-lg bg-gray-50 dark:bg-gray-900">
                        @else
                            <div class="w-full h-32 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <span class="text-gray-400 dark:text-gray-500">画像なし</span>
                            </div>
                        @endif
                    </dd>
                </div>

                {{-- タイトル --}}
                <div class="col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">タイトル</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $image->title ?? 'タイトルなし' }}
                    </dd>
                </div>

                {{-- 種類 --}}
                <div class="col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">種類</dt>
                    <dd class="mt-1">
                        @php
                            $typeLabel = match($image->image_type) {
                                'before' => '施術前',
                                'after' => '施術後',
                                'progress' => '経過観察',
                                'other' => 'その他',
                                default => 'その他',
                            };
                            $typeColor = match($image->image_type) {
                                'before' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
                                'after' => 'bg-green-50 text-green-700 ring-green-600/20',
                                'progress' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                                default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-sm font-medium ring-1 ring-inset {{ $typeColor }}">
                            {{ $typeLabel }}
                        </span>
                    </dd>
                </div>

                {{-- 登録日 --}}
                <div class="col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">登録日</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $image->created_at?->format('Y/m/d H:i') ?? '日付なし' }}
                    </dd>
                </div>
            </div>
        </div>
    @endforeach

    @if ($images->isEmpty())
        <div class="text-center py-8 text-gray-500">
            画像がありません
        </div>
    @endif
</div>
