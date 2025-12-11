@php
    $colorClass = match($result ?? '-') {
        'サブスク' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300',
        '回数券' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        '次回予約' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        'キャンセル' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        '飛び' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300',
        '予約なし' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        default => 'bg-gray-50 text-gray-400 dark:bg-gray-800 dark:text-gray-500',
    };
@endphp
<span class="inline-block px-2 py-0.5 text-xs font-medium rounded {{ $colorClass }}">
    {{ $result ?? '-' }}
</span>
