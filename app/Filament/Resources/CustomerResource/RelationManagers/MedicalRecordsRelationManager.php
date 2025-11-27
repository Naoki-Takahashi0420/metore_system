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
                    ->sortable()
                    ->description(fn ($record) => $record->created_at ? '作成: ' . $record->created_at->format('H:i') : null),
                Tables\Columns\TextColumn::make('reservation.menu.name')
                    ->label('施術メニュー')
                    ->default('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('chief_complaint')
                    ->label('主訴')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->chief_complaint),
                Tables\Columns\TextColumn::make('reservation_source')
                    ->label('流入経路')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'web' => 'info',
                            'phone' => 'warning',
                            'walk_in' => 'success',
                            'referral' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->default('-'),
                Tables\Columns\TextColumn::make('visit_purpose')
                    ->label('来院目的')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->visit_purpose)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('handled_by')
                    ->label('対応者')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('支払方法')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'cash' => 'success',
                            'credit' => 'info',
                            'subscription' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('service_memo')
                    ->label('サービスメモ')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->service_memo)
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('workplace_address')
                    ->label('職場住所')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->workplace_address)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('device_usage')
                    ->label('デバイス使用')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->device_usage)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BooleanColumn::make('genetic_possibility')
                    ->label('遺伝可能性')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BooleanColumn::make('has_astigmatism')
                    ->label('乱視')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('eye_diseases')
                    ->label('眼疾患')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->eye_diseases)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('symptoms')
                    ->label('症状')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->symptoms)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('treatment')
                    ->label('施術内容')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->treatment)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('担当スタッフ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('naked_vision')
                    ->label('裸眼視力')
                    ->state(function ($record) {
                        if ($record->after_naked_left || $record->after_naked_right) {
                            $left = $record->after_naked_left ?? '-';
                            $right = $record->after_naked_right ?? '-';
                            return "L:{$left} R:{$right}";
                        }
                        return '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('corrected_vision')
                    ->label('矯正視力')
                    ->state(function ($record) {
                        if ($record->after_corrected_left || $record->after_corrected_right) {
                            $left = $record->after_corrected_left ?? '-';
                            $right = $record->after_corrected_right ?? '-';
                            return "L:{$left} R:{$right}";
                        }
                        return '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
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