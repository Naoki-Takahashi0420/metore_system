<?php

if (!function_exists('linkify_urls')) {
    /**
     * テキスト内のURLを自動的にリンク化し、新しいタブで開くようにする
     *
     * @param string $text
     * @return string
     */
    function linkify_urls($text)
    {
        if (empty($text)) {
            return $text;
        }

        // 既存の<a>タグにtarget="_blank"とrel="noopener noreferrer"を追加
        $text = preg_replace_callback(
            '/<a\s+([^>]*?)href=(["\'])([^"\']*?)\2([^>]*?)>/i',
            function ($matches) {
                $before = $matches[1];
                $url = $matches[3];
                $after = $matches[4];

                // target="_blank"が既に含まれているか確認
                if (!preg_match('/target\s*=\s*["\']_blank["\']/i', $before . $after)) {
                    $after .= ' target="_blank" rel="noopener noreferrer"';
                }

                return '<a ' . $before . 'href="' . $url . '"' . $after . '>';
            },
            $text
        );

        // プレーンテキストのURLをリンク化（<a>タグ、<img>タグ内以外）
        // HTMLタグをスキップしながらURLを検索
        $parts = preg_split('/(<[^>]+>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $index => $part) {
            // HTMLタグの場合はスキップ
            if (preg_match('/^<[^>]+>$/i', $part)) {
                continue;
            }

            // プレーンテキスト部分のURLをリンク化
            $parts[$index] = preg_replace_callback(
                '/(https?:\/\/[^\s<>"]+)/i',
                function ($matches) {
                    $url = $matches[0];
                    return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:text-primary-700 underline">' . $url . '</a>';
                },
                $part
            );
        }

        return implode('', $parts);
    }
}
