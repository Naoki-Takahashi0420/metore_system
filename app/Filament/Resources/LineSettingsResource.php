<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LineSettingsResource\Pages;
use App\Filament\Resources\LineSettingsResource\RelationManagers;
use App\Models\LineSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LineSettingsResource extends Resource
{
    protected static ?string $model = LineSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationLabel = 'LINE設定';
    
    protected static ?string $modelLabel = 'LINE設定';
    
    protected static ?string $pluralModelLabel = 'LINE設定';
    
    protected static ?string $navigationGroup = 'LINE管理';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->visible(fn ($record) => !$record || !$record->is_system)
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('設定キー')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->disabled(fn ($record) => $record && $record->is_system),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('設定名')
                            ->required()
                            ->disabled(fn ($record) => $record && $record->is_system),
                            
                        Forms\Components\Select::make('category')
                            ->label('カテゴリ')
                            ->required()
                            ->options([
                                'notification' => '通知設定',
                                'campaign' => 'キャンペーン',
                                'system' => 'システム',
                                'manual' => 'マニュアル',
                                'general' => 'その他',
                            ])
                            ->disabled(fn ($record) => $record && $record->is_system),
                            
                        Forms\Components\Select::make('type')
                            ->label('設定タイプ')
                            ->required()
                            ->options([
                                'boolean' => 'ON/OFF',
                                'text' => 'テキスト',
                                'textarea' => '長文テキスト',
                                'select' => '選択肢',
                            ])
                            ->live()
                            ->disabled(fn ($record) => $record && $record->is_system),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->rows(2)
                            ->disabled(fn ($record) => $record && $record->is_system),
                    ])->columns(2),
                    
                Forms\Components\Section::make('設定値')
                    ->schema([
                        // Boolean type
                        Forms\Components\Toggle::make('value.enabled')
                            ->label('有効')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'boolean'),
                            
                        // Text type  
                        Forms\Components\TextInput::make('value.text')
                            ->label('テキスト')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'text'),
                            
                        // Textarea type
                        Forms\Components\Textarea::make('value.text')
                            ->label('長文テキスト')
                            ->rows(8)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'textarea'),
                            
                        // Select type - options
                        Forms\Components\KeyValue::make('options')
                            ->label('選択肢')
                            ->keyLabel('値')
                            ->valueLabel('表示名')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'select'),
                            
                        // Select type - selected value
                        Forms\Components\Select::make('value.selected')
                            ->label('選択値')
                            ->options(fn (Forms\Get $get) => $get('options') ?: [])
                            ->visible(fn (Forms\Get $get) => $get('type') === 'select'),
                    ]),
                    
                Forms\Components\Section::make('表示設定')
                    ->visible(fn ($record) => !$record || !$record->is_system)
                    ->schema([
                        Forms\Components\TextInput::make('sort_order')
                            ->label('表示順序')
                            ->numeric()
                            ->default(0),
                            
                        Forms\Components\Toggle::make('is_system')
                            ->label('システム設定')
                            ->helperText('システム設定項目として扱います（通常の管理者は変更不可）')
                            ->default(false)
                            ->visible(fn () => auth()->user()?->hasRole('super_admin')),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('設定名')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('key')
                    ->label('キー')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable(),
                    
                Tables\Columns\BadgeColumn::make('category')
                    ->label('カテゴリ')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'notification' => '通知設定',
                        'campaign' => 'キャンペーン',
                        'system' => 'システム',
                        'manual' => 'マニュアル',
                        'general' => 'その他',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'notification',
                        'warning' => 'campaign',
                        'danger' => 'system',
                        'info' => 'manual',
                        'secondary' => 'general',
                    ]),
                    
                Tables\Columns\BadgeColumn::make('type')
                    ->label('タイプ')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'boolean' => 'ON/OFF',
                        'text' => 'テキスト',
                        'textarea' => '長文',
                        'select' => '選択肢',
                        default => $state,
                    }),
                    
                Tables\Columns\TextColumn::make('current_value')
                    ->label('現在の値')
                    ->getStateUsing(function (LineSettings $record): string {
                        $value = $record->getValue();
                        
                        return match($record->type) {
                            'boolean' => $value ? '有効' : '無効',
                            'select' => $record->options[$value] ?? $value ?? '-',
                            'textarea' => mb_substr($value, 0, 30) . (mb_strlen($value) > 30 ? '...' : ''),
                            default => $value ?? '-',
                        };
                    }),
                    
                Tables\Columns\IconColumn::make('is_system')
                    ->label('システム')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil'),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('カテゴリ')
                    ->options([
                        'notification' => '通知設定',
                        'campaign' => 'キャンペーン',
                        'system' => 'システム',
                        'manual' => 'マニュアル',
                        'general' => 'その他',
                    ]),
                    
                Tables\Filters\SelectFilter::make('type')
                    ->label('タイプ')
                    ->options([
                        'boolean' => 'ON/OFF',
                        'text' => 'テキスト',
                        'textarea' => '長文',
                        'select' => '選択肢',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_system')
                    ->label('システム設定'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('詳細'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reset')
                    ->label('初期値に戻す')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (LineSettings $record) => !$record->is_system)
                    ->requiresConfirmation()
                    ->action(function (LineSettings $record) {
                        // Reset logic would go here
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('super_admin')),
                ]),
            ])
            ->defaultSort('category')
            ->groupedBulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => auth()->user()?->hasRole('super_admin')),
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
            'index' => Pages\ListLineSettings::route('/'),
            'create' => Pages\CreateLineSettings::route('/create'),
            'edit' => Pages\EditLineSettings::route('/{record}/edit'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
    
    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
