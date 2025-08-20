<?php

namespace App\Filament\Resources\Reservations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reservation_number')
                    ->label('予約番号')
                    ->required(),
                Select::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name')
                    ->required(),
                Select::make('customer_id')
                    ->label('顧客')
                    ->relationship('customer', 'name')
                    ->required(),
                Select::make('staff_id')
                    ->label('スタッフ')
                    ->relationship('staff', 'name'),
                DatePicker::make('reservation_date')
                    ->label('予約日')
                    ->required(),
                TimePicker::make('start_time')
                    ->label('開始時刻')
                    ->required(),
                TimePicker::make('end_time')
                    ->label('終了時刻')
                    ->required(),
                Select::make('status')
                    ->label('ステータス')
                    ->options([
                        'pending' => '保留中',
                        'confirmed' => '確定',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                    ])
                    ->required()
                    ->default('pending'),
                TextInput::make('guest_count')
                    ->label('人数')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('total_amount')
                    ->label('合計金額')
                    ->required()
                    ->numeric()
                    ->suffix('円')
                    ->default(0),
                TextInput::make('deposit_amount')
                    ->label('保証金')
                    ->required()
                    ->numeric()
                    ->suffix('円')
                    ->default(0),
                Select::make('payment_method')
                    ->label('支払い方法')
                    ->options([
                        'cash' => '現金',
                        'card' => 'クレジットカード',
                        'transfer' => '銀行振込',
                    ]),
                Select::make('payment_status')
                    ->label('支払い状態')
                    ->options([
                        'unpaid' => '未支払い',
                        'partial' => '一部支払い',
                        'paid' => '支払い済み',
                        'refunded' => '返金済み',
                    ])
                    ->required()
                    ->default('unpaid'),
                Textarea::make('menu_items')
                    ->label('メニュー')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('メモ')
                    ->columnSpanFull(),
                Textarea::make('cancel_reason')
                    ->label('キャンセル理由')
                    ->columnSpanFull(),
                DateTimePicker::make('confirmed_at')
                    ->label('確定日時'),
                DateTimePicker::make('cancelled_at')
                    ->label('キャンセル日時'),
            ]);
    }
}
