<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case PAYPAY = 'paypay';
    case LINE_PAY = 'line_pay';
    case OTHER = 'other';

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match($this) {
            self::CASH => '現金',
            self::CREDIT_CARD => 'クレジットカード',
            self::DEBIT_CARD => 'デビットカード',
            self::PAYPAY => 'PayPay',
            self::LINE_PAY => 'LINE Pay',
            self::OTHER => 'その他',
        };
    }

    /**
     * 色（Tailwindクラス）を取得
     */
    public function color(): string
    {
        return match($this) {
            self::CASH => 'success',
            self::CREDIT_CARD => 'primary',
            self::DEBIT_CARD => 'info',
            self::PAYPAY => 'warning',
            self::LINE_PAY => 'success',
            self::OTHER => 'gray',
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
            ],
            self::cases()
        );
    }
}
