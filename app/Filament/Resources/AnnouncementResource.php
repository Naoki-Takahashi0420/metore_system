<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Filament\Resources\AnnouncementResource\RelationManagers;
use App\Models\Announcement;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'お知らせ管理';

    protected static ?string $modelLabel = 'お知らせ';

    protected static ?string $pluralModelLabel = 'お知らせ';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('タイトル')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('content')
                            ->label('本文')
                            ->required()
                            ->fileAttachments()
                            ->fileAttachmentsDirectory('announcements')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('priority')
                            ->label('優先度')
                            ->options([
                                'normal' => '普通',
                                'important' => '重要',
                                'urgent' => '緊急',
                            ])
                            ->default('normal')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('公開')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('配信設定')
                    ->schema([
                        Forms\Components\Radio::make('target_type')
                            ->label('対象範囲')
                            ->options([
                                'all' => '全店舗',
                                'specific_stores' => '特定店舗を選択',
                            ])
                            ->default('all')
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('stores')
                            ->label('対象店舗')
                            ->multiple()
                            ->relationship('stores', 'name')
                            ->preload()
                            ->visible(fn (callable $get) => $get('target_type') === 'specific_stores')
                            ->required(fn (callable $get) => $get('target_type') === 'specific_stores'),
                    ]),

                Forms\Components\Section::make('公開期間')
                    ->schema([
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('公開日時')
                            ->default(now())
                            ->required(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('有効期限')
                            ->helperText('空欄の場合は無期限')
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('タイトル')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\BadgeColumn::make('priority')
                    ->label('優先度')
                    ->colors([
                        'secondary' => 'normal',
                        'warning' => 'important',
                        'danger' => 'urgent',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'normal' => '普通',
                        'important' => '重要',
                        'urgent' => '緊急',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('target_type')
                    ->label('対象')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'all' => '全店舗',
                        'specific_stores' => '特定店舗',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('公開')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('公開日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('有効期限')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->placeholder('無期限'),

                Tables\Columns\TextColumn::make('reads_count')
                    ->label('既読数')
                    ->counts('reads')
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('作成者')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('priority')
                    ->label('優先度')
                    ->options([
                        'normal' => '普通',
                        'important' => '重要',
                        'urgent' => '緊急',
                    ]),

                Tables\Filters\SelectFilter::make('target_type')
                    ->label('対象')
                    ->options([
                        'all' => '全店舗',
                        'specific_stores' => '特定店舗',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('公開状態'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
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
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
