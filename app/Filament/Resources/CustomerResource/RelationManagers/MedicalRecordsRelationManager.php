<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MedicalRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalRecords';

    protected static ?string $title = 'カルテ・診療記録';

    protected static ?string $modelLabel = 'カルテ';

    protected static ?string $pluralModelLabel = 'カルテ';

    public function getTableHeader(): ?\Illuminate\Contracts\View\View
    {
        \Log::info('[DEBUG] getTableHeader called for customer: ' . $this->ownerRecord->id);
        return view('filament.resources.customer-resource.relation-managers.medical-records-header');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('record_date')
                    ->label('記録日')
                    ->required(),
                Forms\Components\TextArea::make('chief_complaint')
                    ->label('主訴')
                    ->columnSpanFull(),
                Forms\Components\TextArea::make('symptoms')
                    ->label('症状')
                    ->columnSpanFull(),
                Forms\Components\TextArea::make('diagnosis')
                    ->label('診断')
                    ->columnSpanFull(),
                Forms\Components\TextArea::make('treatment')
                    ->label('治療内容')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('record_date')
            ->header(view('filament.resources.customer-resource.relation-managers.medical-records-header'))
            ->columns([
                Tables\Columns\TextColumn::make('record_date')
                    ->label('記録日')
                    ->date('Y/m/d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reservation.menu.name')
                    ->label('施術メニュー')
                    ->default('-'),
                Tables\Columns\TextColumn::make('chief_complaint')
                    ->label('主訴')
                    ->limit(30),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('担当スタッフ'),
                Tables\Columns\TextColumn::make('next_visit_date')
                    ->label('次回来院予定')
                    ->date('Y/m/d')
                    ->color(function ($state) {
                        if (!$state) return null;
                        $days = now()->diffInDays($state, false);
                        if ($days < 0) return 'danger';
                        if ($days <= 7) return 'warning';
                        return null;
                    }),
            ])
            ->defaultSort('record_date', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('カルテ作成')
                    ->url(fn ($livewire) => route('filament.admin.resources.medical-records.create', [
                        'customer_id' => $livewire->ownerRecord->id
                    ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.medical-records.view', $record)),
                Tables\Actions\EditAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.medical-records.edit', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}