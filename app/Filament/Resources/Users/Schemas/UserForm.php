<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name'),
                TextInput::make('name')
                    ->label('名前')
                    ->required(),
                TextInput::make('email')
                    ->label('メールアドレス')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at')
                    ->label('メール確認日時'),
                TextInput::make('password')
                    ->label('パスワード')
                    ->password()
                    ->required(),
                Select::make('role')
                    ->label('役割')
                    ->options([
                        'admin' => '管理者',
                        'manager' => 'マネージャー',
                        'staff' => 'スタッフ',
                        'customer' => '顧客',
                    ])
                    ->required()
                    ->default('staff'),
                Textarea::make('permissions')
                    ->label('権限')
                    ->columnSpanFull(),
                Textarea::make('specialties')
                    ->label('専門分野')
                    ->columnSpanFull(),
                TextInput::make('hourly_rate')
                    ->label('時給')
                    ->numeric()
                    ->suffix('円'),
                Toggle::make('is_active')
                    ->label('アクティブ')
                    ->default(true),
                DateTimePicker::make('last_login_at')
                    ->label('最終ログイン'),
            ]);
    }
}
