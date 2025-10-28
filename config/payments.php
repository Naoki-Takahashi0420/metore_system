<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentSource;

return [

    /*
    |--------------------------------------------------------------------------
    | 支払方法（Payment Methods）
    |--------------------------------------------------------------------------
    |
    | システムで利用可能な支払方法の設定
    |
    */

    'payment_methods' => [
        PaymentMethod::CASH->value => [
            'label' => '現金',
            'color' => 'success',
            'badge_class' => 'bg-green-100 text-green-700',
        ],
        PaymentMethod::CREDIT_CARD->value => [
            'label' => 'クレジットカード',
            'color' => 'primary',
            'badge_class' => 'bg-blue-100 text-blue-700',
        ],
        PaymentMethod::DEBIT_CARD->value => [
            'label' => 'デビットカード',
            'color' => 'info',
            'badge_class' => 'bg-cyan-100 text-cyan-700',
        ],
        PaymentMethod::PAYPAY->value => [
            'label' => 'PayPay',
            'color' => 'warning',
            'badge_class' => 'bg-red-100 text-red-700',
        ],
        PaymentMethod::LINE_PAY->value => [
            'label' => 'LINE Pay',
            'color' => 'success',
            'badge_class' => 'bg-green-100 text-green-700',
        ],
        PaymentMethod::OTHER->value => [
            'label' => 'その他',
            'color' => 'gray',
            'badge_class' => 'bg-gray-100 text-gray-700',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 支払いソース（Payment Sources）
    |--------------------------------------------------------------------------
    |
    | 予約起点の支払いソース（スポット/サブスク/回数券）
    |
    */

    'payment_sources' => [
        PaymentSource::SPOT->value => [
            'label' => 'スポット',
            'color' => 'gray',
            'badge_class' => 'bg-gray-100 text-gray-700',
        ],
        PaymentSource::SUBSCRIPTION->value => [
            'label' => 'サブスク',
            'color' => 'info',
            'badge_class' => 'bg-blue-100 text-blue-700',
        ],
        PaymentSource::TICKET->value => [
            'label' => '回数券',
            'color' => 'success',
            'badge_class' => 'bg-green-100 text-green-700',
        ],
        PaymentSource::OTHER->value => [
            'label' => 'その他',
            'color' => 'warning',
            'badge_class' => 'bg-yellow-100 text-yellow-700',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | デフォルト値
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'payment_method_spot' => PaymentMethod::CASH->value,
        'payment_method_subscription' => PaymentMethod::OTHER->value,
        'payment_method_ticket' => PaymentMethod::OTHER->value,
    ],

];
