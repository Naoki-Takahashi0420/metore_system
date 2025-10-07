<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HelpChatLogResource\Pages;
use App\Filament\Resources\HelpChatLogResource\RelationManagers;
use App\Models\HelpChatLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HelpChatLogResource extends Resource
{
    protected static ?string $model = HelpChatLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'ヘルプ質問履歴';
    protected static ?string $navigationGroup = '設定';
    protected static ?int $navigationSort = 101;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\TextInput::make('page_name'),
                Forms\Components\Textarea::make('question')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('answer')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_resolved')
                    ->required(),
                Forms\Components\Textarea::make('feedback')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('context')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $state)
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('usage')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $state)
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('page_name')
                    ->label('ページ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('question')
                    ->label('質問')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_resolved')
                    ->label('解決')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('質問日時')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_resolved')
                    ->label('解決状況')
                    ->placeholder('全て')
                    ->trueLabel('解決済み')
                    ->falseLabel('未解決'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListHelpChatLogs::route('/'),
            'create' => Pages\CreateHelpChatLog::route('/create'),
            'edit' => Pages\EditHelpChatLog::route('/{record}/edit'),
        ];
    }

    // スーパー管理者のみアクセス可能
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }
}
