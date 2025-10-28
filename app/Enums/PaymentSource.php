<?php

namespace App\Enums;

enum PaymentSource: string
{
    case SPOT = 'spot';
    case SUBSCRIPTION = 'subscription';
    case TICKET = 'ticket';
    case OTHER = 'other';

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match($this) {
            self::SPOT => 'スポット',
            self::SUBSCRIPTION => 'サブスク',
            self::TICKET => '回数券',
            self::OTHER => 'その他',
        };
    }

    /**
     * 色（Tailwindクラス）を取得
     */
    public function color(): string
    {
        return match($this) {
            self::SPOT => 'gray',
            self::SUBSCRIPTION => 'info',
            self::TICKET => 'success',
            self::OTHER => 'warning',
        };
    }

    /**
     * バッジ用の背景色（Tailwind）
     */
    public function badgeClass(): string
    {
        return match($this) {
            self::SPOT => 'bg-gray-100 text-gray-700',
            self::SUBSCRIPTION => 'bg-blue-100 text-blue-700',
            self::TICKET => 'bg-green-100 text-green-700',
            self::OTHER => 'bg-yellow-100 text-yellow-700',
        };
    }

    /**
     * 全選択肢を取得
     */
    public static function options(): array
    {
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'badgeClass' => $case->badgeClass(),
            ],
            self::cases()
        );
    }
}
