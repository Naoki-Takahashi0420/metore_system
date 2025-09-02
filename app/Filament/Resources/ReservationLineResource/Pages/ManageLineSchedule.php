<?php

namespace App\Filament\Resources\ReservationLineResource\Pages;

use App\Filament\Resources\ReservationLineResource;
use App\Models\ReservationLine;
use App\Models\ReservationLineSchedule;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;

class ManageLineSchedule extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;
    
    protected static string $resource = ReservationLineResource::class;
    
    protected static string $view = 'filament.resources.reservation-line-resource.pages.manage-line-schedule';
    
    public ReservationLine $record;
    
    public function mount($record): void
    {
        $this->record = ReservationLine::findOrFail($record);
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(ReservationLineSchedule::query()->where('line_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('日付')
                    ->date('Y/m/d')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('start_time')
                    ->label('開始時間')
                    ->time('H:i'),
                
                Tables\Columns\TextColumn::make('end_time')
                    ->label('終了時間')
                    ->time('H:i'),
                
                Tables\Columns\IconColumn::make('is_available')
                    ->label('利用可能')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('capacity_override')
                    ->label('容量上書き')
                    ->placeholder('デフォルト'),
                
                Tables\Columns\TextColumn::make('notes')
                    ->label('メモ')
                    ->limit(30),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->model(ReservationLineSchedule::class)
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('日付')
                            ->required(),
                        
                        Forms\Components\TimePicker::make('start_time')
                            ->label('開始時間')
                            ->required()
                            ->default('09:00'),
                        
                        Forms\Components\TimePicker::make('end_time')
                            ->label('終了時間')
                            ->required()
                            ->default('20:00'),
                        
                        Forms\Components\Toggle::make('is_available')
                            ->label('利用可能')
                            ->default(true),
                        
                        Forms\Components\TextInput::make('capacity_override')
                            ->label('容量上書き')
                            ->numeric()
                            ->placeholder("デフォルト: {$this->record->capacity}"),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('メモ')
                            ->rows(2),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['line_id'] = $this->record->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('日付')
                            ->required(),
                        
                        Forms\Components\TimePicker::make('start_time')
                            ->label('開始時間')
                            ->required(),
                        
                        Forms\Components\TimePicker::make('end_time')
                            ->label('終了時間')
                            ->required(),
                        
                        Forms\Components\Toggle::make('is_available')
                            ->label('利用可能'),
                        
                        Forms\Components\TextInput::make('capacity_override')
                            ->label('容量上書き')
                            ->numeric(),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('メモ')
                            ->rows(2),
                    ]),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'asc');
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            // \App\Filament\Widgets\LineInfoWidget::make([
            //     'record' => $this->record,
            // ]),
        ];
    }
}