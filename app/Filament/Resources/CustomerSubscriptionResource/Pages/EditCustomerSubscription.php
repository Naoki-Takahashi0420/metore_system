<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;
    
    protected static ?string $title = 'サブスク契約詳細';
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_customer')
                ->label('顧客管理へ戻る')
                ->url(fn ($record) => route('filament.admin.resources.customers.edit', $record->customer_id))
                ->icon('heroicon-o-arrow-left'),
                
            // 決済失敗の切り替え
            Actions\Action::make('toggle_payment_failed')
                ->label(fn ($record) => $record->payment_failed ? '決済を正常に戻す' : '決済失敗として記録')
                ->color(fn ($record) => $record->payment_failed ? 'success' : 'danger')
                ->icon(fn ($record) => $record->payment_failed ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->form([
                    \Filament\Forms\Components\Select::make('payment_failed_reason')
                        ->label('失敗理由を選択してください')
                        ->options([
                            'card_expired' => 'カード期限切れ',
                            'limit_exceeded' => '限度額超過',
                            'insufficient' => '残高不足',
                            'card_error' => 'カードエラー',
                            'other' => 'その他',
                        ])
                        ->required()
                        ->visible(fn ($record) => !$record->payment_failed),
                    \Filament\Forms\Components\Textarea::make('payment_failed_notes')
                        ->label('対応メモ')
                        ->placeholder('どのような対応をしたか記録してください')
                        ->rows(3),
                ])
                ->action(function ($record, array $data) {
                    if ($record->payment_failed) {
                        // 決済を正常に戻す
                        $record->update([
                            'payment_failed' => false,
                            'payment_failed_at' => null,
                            'payment_failed_reason' => null,
                            'payment_failed_notes' => $data['payment_failed_notes'] ?? null,
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('決済状態を正常に戻しました')
                            ->success()
                            ->send();
                    } else {
                        // 決済失敗として記録
                        $record->update([
                            'payment_failed' => true,
                            'payment_failed_at' => now(),
                            'payment_failed_reason' => $data['payment_failed_reason'],
                            'payment_failed_notes' => $data['payment_failed_notes'] ?? null,
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('決済失敗を記録しました')
                            ->warning()
                            ->send();
                    }
                }),
                
            // 休止の切り替え
            Actions\Action::make('toggle_pause')
                ->label(fn ($record) => $record->is_paused ? 'サブスクを再開する' : 'サブスクを休止する')
                ->color(fn ($record) => $record->is_paused ? 'success' : 'warning')
                ->icon(fn ($record) => $record->is_paused ? 'heroicon-o-play' : 'heroicon-o-pause')
                ->visible(fn ($record) => !$record->payment_failed) // 決済失敗中は休止できない
                ->requiresConfirmation()
                ->modalHeading(fn ($record) => $record->is_paused ? 'サブスク再開の確認' : 'サブスク休止の確認')
                ->modalDescription(fn ($record) => 
                    $record->is_paused 
                        ? 'サブスクを再開します。次回から通常通り利用可能になります。'
                        : '6ヶ月間サブスクを休止します。この期間中は利用できません。将来の予約は自動的にキャンセルされます。'
                )
                ->action(function ($record) {
                    if ($record->is_paused) {
                        // 再開
                        $record->resume('manual');
                        
                        \Filament\Notifications\Notification::make()
                            ->title('サブスクを再開しました')
                            ->body('通常通り利用可能になりました')
                            ->success()
                            ->send();
                    } else {
                        // 休止
                        $record->pause(auth()->id(), '管理画面から手動休止');
                        
                        \Filament\Notifications\Notification::make()
                            ->title('サブスクを休止しました')
                            ->body("6ヶ月間休止します。{$record->pause_end_date->format('Y年m月d日')}に自動再開されます。")
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
