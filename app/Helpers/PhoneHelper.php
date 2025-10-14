<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * 電話番号を正規化（ハイフンを削除）
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // 全角を半角に変換（数字とハイフン）
        $normalized = mb_convert_kana($phone, 'as');

        // ハイフン、スペース、全角スペースを削除
        $normalized = str_replace(['-', ' ', '　'], '', $normalized);

        return $normalized;
    }

    /**
     * 電話番号が有効な形式かチェック
     *
     * @param string|null $phone
     * @return bool
     */
    public static function isValid(?string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        $normalized = self::normalize($phone);

        // 10桁または11桁の数字のみ
        return preg_match('/^0[0-9]{9,10}$/', $normalized) === 1;
    }
}
