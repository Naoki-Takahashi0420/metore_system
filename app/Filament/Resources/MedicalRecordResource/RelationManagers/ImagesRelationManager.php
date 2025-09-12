<?php

namespace App\Filament\Resources\MedicalRecordResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'attachedImages';
    
    protected static ?string $title = '画像管理';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('current_image')
                    ->label('現在の画像')
                    ->content(function ($record) {
                        if (!$record || !$record->file_path) {
                            return '画像なし';
                        }
                        $url = \Storage::disk('public')->url($record->file_path);
                        return new \Illuminate\Support\HtmlString(
                            '<img src="' . $url . '" style="max-width: 200px; max-height: 200px; border-radius: 8px;">'
                        );
                    })
                    ->visibleOn('edit'),
                    
                Forms\Components\FileUpload::make('file_path')
                    ->label(fn ($operation) => $operation === 'edit' ? '新しい画像（変更する場合）' : '画像ファイル')
                    ->image()
                    ->directory('medical-records')
                    ->disk('public')
                    ->maxSize(15360)
                    ->visibility('public')
                    ->preserveFilenames()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                    ->required(fn ($operation) => $operation === 'create'),
                    
                Forms\Components\TextInput::make('title')
                    ->label('タイトル'),
                    
                Forms\Components\Select::make('image_type')
                    ->label('画像タイプ')
                    ->options([
                        'before' => '施術前',
                        'after' => '施術後',
                        'progress' => '経過',
                        'reference' => '参考',
                        'other' => 'その他',
                    ])
                    ->default('other'),
                    
                Forms\Components\Textarea::make('description')
                    ->label('説明')
                    ->rows(2),
                    
                Forms\Components\TextInput::make('display_order')
                    ->label('表示順')
                    ->numeric()
                    ->default(0),
                    
                Forms\Components\Toggle::make('is_visible_to_customer')
                    ->label('顧客に表示')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('画像')
                    ->disk('public')
                    ->square()
                    ->size(80),
                    
                Tables\Columns\TextColumn::make('title')
                    ->label('タイトル')
                    ->searchable(),
                    
                Tables\Columns\SelectColumn::make('image_type')
                    ->label('タイプ')
                    ->options([
                        'before' => '施術前',
                        'after' => '施術後',
                        'progress' => '経過',
                        'reference' => '参考',
                        'other' => 'その他',
                    ]),
                    
                Tables\Columns\TextColumn::make('display_order')
                    ->label('表示順')
                    ->sortable(),
                    
                Tables\Columns\ToggleColumn::make('is_visible_to_customer')
                    ->label('顧客表示'),
            ])
            ->defaultSort('display_order', 'asc')
            ->reorderable('display_order')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('画像を追加'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(function ($record) {
                        $imageUrl = Storage::disk('public')->url($record->file_path);
                        return view('filament.resources.medical-record-resource.view-image', [
                            'imageUrl' => $imageUrl,
                            'record' => $record,
                        ]);
                    }),
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