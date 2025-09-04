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
    
    protected static ?string $navigationGroup = 'メニュー';

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
                        
                        Forms\Components\TextInput::make('code')
                            ->label('プランコード')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('例: GOLD_PLAN'),
                        
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
                            ->label('基本料金')
                            ->numeric()
                            ->prefix('¥')
                            ->required(),
                        
                        Forms\Components\Select::make('billing_cycle')
                            ->label('請求周期')
                            ->options([
                                'monthly' => '毎月',
                                'quarterly' => '3ヶ月ごと',
                                'semi_annual' => '6ヶ月ごと',
                                'annual' => '年一括',
                            ])
                            ->required(),
                        
                        Forms\Components\TextInput::make('trial_days')
                            ->label('お試し期間（日数）')
                            ->numeric()
                            ->default(0)
                            ->suffix('日間'),
                        
                        Forms\Components\TextInput::make('discount_rate')
                            ->label('割引率')
                            ->numeric()
                            ->suffix('%')
                            ->default(0),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('特典内容')
                    ->schema([
                        Forms\Components\Repeater::make('benefits')
                            ->label('特典リスト')
                            ->schema([
                                Forms\Components\TextInput::make('benefit')
                                    ->label('特典内容')
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->label('値/回数')
                                    ->placeholder('例: 月2回、20%OFF'),
                            ])
                            ->columns(2)
                            ->defaultItems(3),
                        
                        Forms\Components\KeyValue::make('features')
                            ->label('機能制限')
                            ->keyLabel('機能名')
                            ->valueLabel('制限値')
                            ->addActionLabel('機能を追加'),
                    ]),
                
                Forms\Components\Section::make('利用条件')
                    ->schema([
                        Forms\Components\TextInput::make('min_contract_months')
                            ->label('最低契約期間')
                            ->numeric()
                            ->suffix('ヶ月')
                            ->default(0),
                        
                        Forms\Components\TextInput::make('max_users')
                            ->label('最大利用人数')
                            ->numeric()
                            ->suffix('名')
                            ->helperText('空欄の場合は無制限'),
                        
                        Forms\Components\Textarea::make('terms')
                            ->label('利用規約')
                            ->rows(5),
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
                
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('請求周期')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'monthly' => '毎月',
                        'quarterly' => '3ヶ月',
                        'semi_annual' => '6ヶ月',
                        'annual' => '年額',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('trial_days')
                    ->label('お試し期間')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}日間" : 'なし'),
                
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
                
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->label('請求周期')
                    ->options([
                        'monthly' => '毎月',
                        'quarterly' => '3ヶ月',
                        'semi_annual' => '6ヶ月',
                        'annual' => '年額',
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