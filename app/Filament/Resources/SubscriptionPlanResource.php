<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationLabel = 'サブスク設定';
    
    protected static ?string $modelLabel = 'サブスクプラン';
    
    protected static ?string $pluralModelLabel = 'サブスクプラン';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $navigationGroup = 'メニュー管理';
    
    protected static bool $shouldRegisterNavigation = false; // 廃止

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('プラン名')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('例: ゴールドプラン'),
                        
                        Forms\Components\Hidden::make('code')
                            ->default(fn() => 'PLAN_' . strtoupper(uniqid()))
                            ->dehydrated(),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(3)
                            ->maxLength(500),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('料金設定')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('月額料金')
                            ->numeric()
                            ->prefix('¥')
                            ->required(),
                        
                        Forms\Components\TextInput::make('max_reservations')
                            ->label('月間最大予約数')
                            ->numeric()
                            ->suffix('回')
                            ->helperText('空欄の場合は無制限'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('利用条件')
                    ->schema([
                        Forms\Components\TextInput::make('contract_months')
                            ->label('契約期間')
                            ->numeric()
                            ->suffix('ヶ月')
                            ->default(1)
                            ->required(),
                        
                        Forms\Components\TextInput::make('max_users')
                            ->label('最大利用人数')
                            ->numeric()
                            ->suffix('名')
                            ->helperText('空欄の場合は無制限'),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('メモ・備考')
                            ->rows(3)
                            ->placeholder('内部用のメモや注意事項など')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('コード')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('プラン名')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('料金')
                    ->money('JPY')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('contract_months')
                    ->label('契約期間')
                    ->formatStateUsing(fn ($state) => "{$state}ヶ月"),
                
                Tables\Columns\TextColumn::make('max_reservations')
                    ->label('月間最大予約数')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}回" : '無制限'),
                
                Tables\Columns\TextColumn::make('max_users')
                    ->label('最大利用人数')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}名" : '無制限')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('subscription_count')
                    ->label('契約数')
                    ->counts('subscriptions')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効状態'),
                
                Tables\Filters\SelectFilter::make('contract_months')
                    ->label('契約期間')
                    ->options([
                        1 => '1ヶ月',
                        3 => '3ヶ月',
                        6 => '6ヶ月',
                        12 => '12ヶ月',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('duplicate')
                    ->label('複製')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $newPlan = $record->replicate();
                        $newPlan->code = $record->code . '_COPY';
                        $newPlan->name = $record->name . ' (コピー)';
                        $newPlan->is_active = false;
                        $newPlan->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'view' => Pages\ViewSubscriptionPlan::route('/{record}'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}