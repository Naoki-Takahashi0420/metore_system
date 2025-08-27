<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LineMessageTemplateResource\Pages;
use App\Filament\Resources\LineMessageTemplateResource\RelationManagers;
use App\Models\LineMessageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LineMessageTemplateResource extends Resource
{
    protected static ?string $model = LineMessageTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected static ?string $navigationLabel = 'LINEメッセージ設定';
    
    protected static ?string $modelLabel = 'LINEメッセージテンプレート';
    
    protected static ?string $pluralModelLabel = 'LINEメッセージテンプレート';
    
    protected static ?string $navigationGroup = 'LINE管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('メッセージキー')
                            ->helperText('システム内部で使用するキー（例: welcome, reminder, campaign_summer）')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('管理用名称')
                            ->helperText('管理画面で表示される名前')
                            ->required(),
                            
                        Forms\Components\Select::make('category')
                            ->label('カテゴリ')
                            ->required()
                            ->options([
                                'general' => '一般',
                                'welcome' => 'ウェルカム',
                                'reminder' => 'リマインダー',
                                'campaign' => 'キャンペーン',
                                'auto_reply' => '自動返信',
                            ])
                            ->default('general'),
                            
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->helperText('特定店舗のみに適用する場合は選択（空欄は全店舗共通）')
                            ->relationship('store', 'name')
                            ->nullable()
                            ->searchable(),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('有効')
                            ->default(true)
                            ->required(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('メッセージ内容')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->label('メッセージ本文')
                            ->helperText('変数は {{変数名}} の形式で記述（例: {{customer_name}}, {{reservation_date}}）')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull(),
                            
                        Forms\Components\KeyValue::make('variables')
                            ->label('使用可能変数')
                            ->helperText('このテンプレートで使用できる変数とその説明')
                            ->keyLabel('変数名')
                            ->valueLabel('説明')
                            ->columnSpanFull(),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('管理者向け説明')
                            ->helperText('このテンプレートの用途や使用方法の説明')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('テンプレート名')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('key')
                    ->label('キー')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                    
                Tables\Columns\BadgeColumn::make('category')
                    ->label('カテゴリ')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'general' => '一般',
                        'welcome' => 'ウェルカム',
                        'reminder' => 'リマインダー',
                        'campaign' => 'キャンペーン',
                        'auto_reply' => '自動返信',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'welcome',
                        'warning' => 'reminder',
                        'danger' => 'campaign',
                        'primary' => 'auto_reply',
                        'secondary' => 'general',
                    ]),
                    
                Tables\Columns\TextColumn::make('store.name')
                    ->label('店舗')
                    ->placeholder('全店舗共通')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('message')
                    ->label('メッセージ')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
                    
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
                        'general' => '一般',
                        'welcome' => 'ウェルカム',
                        'reminder' => 'リマインダー',
                        'campaign' => 'キャンペーン',
                        'auto_reply' => '自動返信',
                    ]),
                    
                Tables\Filters\SelectFilter::make('store')
                    ->relationship('store', 'name')
                    ->label('店舗'),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('有効状態'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('プレビュー'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('複製')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (LineMessageTemplate $record) {
                        $newTemplate = $record->replicate();
                        $newTemplate->key = $record->key . '_copy';
                        $newTemplate->name = $record->name . ' (コピー)';
                        $newTemplate->save();
                        
                        return redirect(static::getUrl('edit', ['record' => $newTemplate]));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
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
            'index' => Pages\ListLineMessageTemplates::route('/'),
            'create' => Pages\CreateLineMessageTemplate::route('/create'),
            'edit' => Pages\EditLineMessageTemplate::route('/{record}/edit'),
        ];
    }
}
