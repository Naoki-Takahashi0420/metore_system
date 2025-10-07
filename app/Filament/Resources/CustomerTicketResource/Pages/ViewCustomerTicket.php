<?php

namespace App\Filament\Resources\CustomerTicketResource\Pages;

use App\Filament\Resources\CustomerTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewCustomerTicket extends ViewRecord
{
    protected static string $resource = CustomerTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('顧客情報')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.full_name')
                            ->label('顧客名'),
                        Infolists\Components\TextEntry::make('customer.phone')
                            ->label('電話番号'),
                        Infolists\Components\TextEntry::make('store.name')
                            ->label('店舗'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('回数券情報')
                    ->schema([
                        Infolists\Components\TextEntry::make('plan_name')
                            ->label('回数券名'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('ステータス')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'active' => 'success',
                                'expired' => 'danger',
                                'used_up' => 'warning',
                                'cancelled' => 'secondary',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('total_count')
                            ->label('総回数')
                            ->formatStateUsing(fn ($state) => "{$state}回"),
                        Infolists\Components\TextEntry::make('used_count')
                            ->label('利用済み')
                            ->formatStateUsing(fn ($state) => "{$state}回"),
                        Infolists\Components\TextEntry::make('remaining_count')
                            ->label('残回数')
                            ->formatStateUsing(fn ($state) => "{$state}回")
                            ->color(fn ($state) => $state <= 2 ? 'warning' : 'success'),
                        Infolists\Components\TextEntry::make('purchase_price')
                            ->label('購入価格')
                            ->money('JPY'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('有効期限')
                    ->schema([
                        Infolists\Components\TextEntry::make('purchased_at')
                            ->label('購入日')
                            ->date('Y年m月d日'),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('有効期限')
                            ->date('Y年m月d日')
                            ->placeholder('無期限')
                            ->color(fn ($record) => $record->is_expiring_soon ? 'warning' : null),
                        Infolists\Components\TextEntry::make('days_until_expiry')
                            ->label('有効期限まで')
                            ->formatStateUsing(function ($record): string {
                                if (!$record->expires_at) {
                                    return '無期限';
                                }
                                $days = $record->days_until_expiry;
                                if ($days < 0) {
                                    return '期限切れ（' . abs($days) . '日経過）';
                                }
                                return "残り{$days}日";
                            })
                            ->color(fn ($record) => $record->is_expiring_soon ? 'warning' : 'success'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('利用履歴')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('usageHistory')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('used_at')
                                    ->label('利用日時')
                                    ->dateTime('Y/m/d H:i'),
                                Infolists\Components\TextEntry::make('used_count')
                                    ->label('回数')
                                    ->formatStateUsing(fn ($state) => "{$state}回"),
                                Infolists\Components\TextEntry::make('is_cancelled')
                                    ->label('状態')
                                    ->formatStateUsing(fn ($state) => $state ? '取消済み' : '利用済み')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'danger' : 'success'),
                                Infolists\Components\TextEntry::make('reservation.id')
                                    ->label('予約ID')
                                    ->placeholder('手動使用'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('備考')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('メモ')
                            ->placeholder('なし'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('作成日時')
                            ->dateTime('Y年m月d日 H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('更新日時')
                            ->dateTime('Y年m月d日 H:i'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }
}
