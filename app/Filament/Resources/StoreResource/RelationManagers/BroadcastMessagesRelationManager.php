<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use App\Jobs\ProcessBroadcastMessage;
use App\Models\BroadcastMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BroadcastMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'broadcastMessages';

    protected static ?string $title = '一斉送信';

    protected static ?string $modelLabel = '一斉送信';

    protected static ?string $pluralModelLabel = '一斉送信';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('subject')
                    ->label('件名')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('例：年末年始の営業についてのお知らせ'),

                Forms\Components\Textarea::make('message')
                    ->label('メッセージ本文')
                    ->required()
                    ->rows(8)
                    ->placeholder("例：{{customer_name}}様\n\nいつもご利用ありがとうございます。\n...")
                    ->helperText('使用可能な変数: {{customer_name}}, {{customer_last_name}}, {{customer_first_name}}, {{store_name}}'),

                Forms\Components\Radio::make('send_type')
                    ->label('送信タイミング')
                    ->options([
                        'immediate' => '今すぐ送信',
                        'scheduled' => '予約送信',
                    ])
                    ->default('immediate')
                    ->required()
                    ->reactive(),

                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label('送信予定日時')
                    ->visible(fn (callable $get) => $get('send_type') === 'scheduled')
                    ->required(fn (callable $get) => $get('send_type') === 'scheduled')
                    ->minDate(now())
                    ->native(false)
                    ->displayFormat('Y/m/d H:i'),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('件名')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'scheduled',
                        'primary' => 'sending',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => BroadcastMessage::getStatusLabels()[$state] ?? $state),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('予約日時')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('total_recipients')
                    ->label('送信数')
                    ->formatStateUsing(function ($record) {
                        if ($record->status === 'draft' || $record->status === 'scheduled') {
                            return '-';
                        }
                        return "{$record->success_count}/{$record->total_recipients}";
                    }),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('送信完了')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('作成者')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options(BroadcastMessage::getStatusLabels()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('新規一斉送信')
                    ->icon('heroicon-o-megaphone')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->label('件名')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例：年末年始の営業についてのお知らせ'),

                        Forms\Components\Textarea::make('message')
                            ->label('メッセージ本文')
                            ->required()
                            ->rows(8)
                            ->placeholder("例：{{customer_name}}様\n\nいつもご利用ありがとうございます。\n...")
                            ->helperText('使用可能な変数: {{customer_name}}, {{customer_last_name}}, {{customer_first_name}}, {{store_name}}'),

                        Forms\Components\Radio::make('send_type')
                            ->label('送信タイミング')
                            ->options([
                                'immediate' => '今すぐ送信',
                                'scheduled' => '予約送信',
                            ])
                            ->default('immediate')
                            ->required()
                            ->reactive(),

                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('送信予定日時')
                            ->visible(fn (callable $get) => $get('send_type') === 'scheduled')
                            ->required(fn (callable $get) => $get('send_type') === 'scheduled')
                            ->minDate(now())
                            ->native(false)
                            ->displayFormat('Y/m/d H:i'),

                        Forms\Components\Placeholder::make('preview')
                            ->label('送信対象')
                            ->content(function () {
                                $store = $this->getOwnerRecord();
                                $count = \App\Models\Customer::where('store_id', $store->id)
                                    ->where(function ($query) {
                                        $query->whereNotNull('line_user_id')
                                              ->orWhereNotNull('email');
                                    })
                                    ->count();
                                $lineCount = \App\Models\Customer::where('store_id', $store->id)
                                    ->whereNotNull('line_user_id')
                                    ->count();
                                $emailCount = \App\Models\Customer::where('store_id', $store->id)
                                    ->whereNull('line_user_id')
                                    ->whereNotNull('email')
                                    ->count();
                                return "合計: {$count}名 (LINE: {$lineCount}名, メール: {$emailCount}名)";
                            }),
                    ])
                    ->action(function (array $data): void {
                        $store = $this->getOwnerRecord();

                        $broadcast = BroadcastMessage::create([
                            'store_id' => $store->id,
                            'subject' => $data['subject'],
                            'message' => $data['message'],
                            'status' => $data['send_type'] === 'immediate'
                                ? BroadcastMessage::STATUS_SENDING
                                : BroadcastMessage::STATUS_SCHEDULED,
                            'scheduled_at' => $data['send_type'] === 'scheduled' ? $data['scheduled_at'] : null,
                            'created_by' => auth()->id(),
                        ]);

                        if ($data['send_type'] === 'immediate') {
                            // 即時送信
                            ProcessBroadcastMessage::dispatch($broadcast);
                            Notification::make()
                                ->title('一斉送信を開始しました')
                                ->success()
                                ->send();
                        } else {
                            // 予約送信
                            ProcessBroadcastMessage::dispatch($broadcast)
                                ->delay($broadcast->scheduled_at);
                            Notification::make()
                                ->title('一斉送信を予約しました')
                                ->body($broadcast->scheduled_at->format('Y/m/d H:i') . ' に送信されます')
                                ->success()
                                ->send();
                        }
                    })
                    ->modalHeading('新規一斉送信')
                    ->modalSubmitActionLabel('送信する')
                    ->modalWidth('lg'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('詳細')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('一斉送信詳細')
                    ->modalContent(fn (BroadcastMessage $record) => view('filament.modals.broadcast-message-detail', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('閉じる'),

                Tables\Actions\Action::make('cancel')
                    ->label('キャンセル')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BroadcastMessage $record) => $record->status === 'scheduled')
                    ->requiresConfirmation()
                    ->modalHeading('予約送信をキャンセル')
                    ->modalDescription('この予約送信をキャンセルしますか？')
                    ->action(function (BroadcastMessage $record): void {
                        $record->update(['status' => BroadcastMessage::STATUS_DRAFT]);
                        Notification::make()
                            ->title('予約送信をキャンセルしました')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (BroadcastMessage $record) => in_array($record->status, ['draft', 'scheduled'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => true),
                ]),
            ]);
    }
}
