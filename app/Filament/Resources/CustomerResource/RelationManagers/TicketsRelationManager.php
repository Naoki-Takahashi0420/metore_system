<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\TicketPlan;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = '回数券';

    protected static ?string $modelLabel = '回数券';

    protected static ?string $pluralModelLabel = '回数券';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->label('店舗')
                    ->relationship('store', 'name')
                    ->required()
                    ->reactive()
                    ->default(fn () => $this->ownerRecord->store_id)
                    ->disabled(fn ($operation) => $operation === 'edit')
                    ->helperText(fn ($operation) => $operation === 'edit' ? '店舗の変更はできません' : '回数券を発行する店舗'),

                Forms\Components\Select::make('ticket_plan_id')
                    ->label('回数券プラン')
                    ->options(function (callable $get) {
                        $storeId = $get('store_id') ?? $this->ownerRecord->store_id;
                        if (!$storeId) {
                            return [];
                        }
                        return TicketPlan::where('store_id', $storeId)
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(function ($plan) {
                                return [$plan->id => $plan->display_name];
                            });
                    })
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->visible(fn ($operation) => $operation === 'create')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $plan = TicketPlan::find($state);
                            if ($plan) {
                                $set('plan_name', $plan->name);
                                $set('total_count', $plan->ticket_count);
                                $set('purchase_price', $plan->price);
                            }
                        }
                    }),

                Forms\Components\TextInput::make('plan_name')
                    ->label('回数券名')
                    ->disabled()
                    ->visible(fn ($operation) => $operation === 'edit'),

                Forms\Components\TextInput::make('total_count')
                    ->label('総回数')
                    ->numeric()
                    ->suffix('回')
                    ->disabled(fn ($operation) => $operation === 'edit'),

                Forms\Components\TextInput::make('used_count')
                    ->label('利用済み回数')
                    ->numeric()
                    ->suffix('回')
                    ->default(0)
                    ->visible(fn ($operation) => $operation === 'edit')
                    ->helperText('手動調整が必要な場合のみ変更'),

                Forms\Components\TextInput::make('purchase_price')
                    ->label('購入価格')
                    ->numeric()
                    ->prefix('¥')
                    ->disabled(fn ($operation) => $operation === 'edit'),

                Forms\Components\DatePicker::make('purchased_at')
                    ->label('購入日')
                    ->displayFormat('Y年m月d日')
                    ->default(now())
                    ->required()
                    ->disabled(fn ($operation) => $operation === 'edit'),

                Forms\Components\Textarea::make('notes')
                    ->label('メモ・備考')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plan_name')
            ->columns([
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active' => '有効',
                        'expired' => '期限切れ',
                        'used_up' => '使い切り',
                        'cancelled' => 'キャンセル',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'active',
                        'danger' => 'expired',
                        'warning' => 'used_up',
                        'secondary' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('plan_name')
                    ->label('回数券名')
                    ->searchable(),

                Tables\Columns\TextColumn::make('remaining_count')
                    ->label('残回数')
                    ->formatStateUsing(fn ($record) =>
                        "{$record->remaining_count}/{$record->total_count}回"
                    )
                    ->color(fn ($record) => $record->remaining_count <= 2 ? 'warning' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('有効期限')
                    ->date('Y/m/d')
                    ->sortable()
                    ->placeholder('無期限')
                    ->color(fn ($record) => $record->is_expiring_soon ? 'warning' : null),

                Tables\Columns\TextColumn::make('purchased_at')
                    ->label('購入日')
                    ->date('Y/m/d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('購入価格')
                    ->money('JPY')
                    ->sortable(),
            ])
            ->defaultSort('purchased_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'active' => '有効',
                        'expired' => '期限切れ',
                        'used_up' => '使い切り',
                        'cancelled' => 'キャンセル',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('回数券発行')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['customer_id'] = $this->ownerRecord->id;
                        return $data;
                    })
                    ->successNotificationTitle('回数券を発行しました'),
            ])
            ->actions([
                Tables\Actions\Action::make('use')
                    ->label('使用')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->visible(fn ($record) => $record->canUse())
                    ->requiresConfirmation()
                    ->modalHeading('回数券を1回使用')
                    ->modalDescription(fn ($record) =>
                        "{$record->plan_name}を1回使用します。\n" .
                        "残回数: {$record->remaining_count}回 → " . ($record->remaining_count - 1) . "回"
                    )
                    ->modalSubmitActionLabel('使用する')
                    ->action(function ($record) {
                        $result = $record->use();

                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('回数券を使用しました')
                                ->body("残回数: {$record->fresh()->remaining_count}回")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('使用できませんでした')
                                ->body('回数券が期限切れか使い切りです')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('refund')
                    ->label('返却')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->used_count > 0)
                    ->requiresConfirmation()
                    ->modalHeading('回数券を1回返却')
                    ->modalDescription(fn ($record) =>
                        "{$record->plan_name}を1回返却します。\n" .
                        "残回数: {$record->remaining_count}回 → " . ($record->remaining_count + 1) . "回"
                    )
                    ->modalSubmitActionLabel('返却する')
                    ->action(function ($record) {
                        $result = $record->refund(null, 1);

                        if ($result) {
                            \Filament\Notifications\Notification::make()
                                ->title('回数券を返却しました')
                                ->body("残回数: {$record->fresh()->remaining_count}回")
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.customer-tickets.view', $record)),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
