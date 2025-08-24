<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MedicalRecordResource\Pages;
use App\Filament\Resources\MedicalRecordResource\RelationManagers;
use App\Models\MedicalRecord;
use App\Models\Customer;
use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class MedicalRecordResource extends Resource
{
    protected static ?string $model = MedicalRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'カルテ管理';
    
    protected static ?string $modelLabel = 'カルテ';
    
    protected static ?string $pluralModelLabel = 'カルテ';
    
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('customer_id')
                                    ->label('顧客')
                                    ->options(Customer::all()->mapWithKeys(function ($customer) {
                                        return [$customer->id => $customer->last_name . ' ' . $customer->first_name . ' (' . $customer->phone . ')'];
                                    }))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set) {
                                        // 顧客が選択されたら、その顧客の予約を取得
                                        if ($state) {
                                            $set('reservation_id', null);
                                        }
                                    }),
                                
                                Forms\Components\Select::make('reservation_id')
                                    ->label('関連予約')
                                    ->options(function ($get) {
                                        $customerId = $get('customer_id');
                                        if (!$customerId) {
                                            return [];
                                        }
                                        return Reservation::where('customer_id', $customerId)
                                            ->with('menu')
                                            ->orderBy('reservation_date', 'desc')
                                            ->get()
                                            ->mapWithKeys(function ($reservation) {
                                                $menuName = $reservation->menu ? $reservation->menu->name : 'メニューなし';
                                                return [$reservation->id => $reservation->reservation_date . ' - ' . $menuName];
                                            });
                                    })
                                    ->helperText('この診療記録に関連する予約を選択'),
                                
                                Forms\Components\DatePicker::make('record_date')
                                    ->label('記録日')
                                    ->default(now())
                                    ->required(),
                                
                                Forms\Components\Select::make('staff_id')
                                    ->label('担当スタッフ')
                                    ->relationship('staff', 'name')
                                    ->default(Auth::id())
                                    ->required(),
                                    
                                Forms\Components\Hidden::make('created_by')
                                    ->default(Auth::id()),
                            ]),
                    ]),
                
                Forms\Components\Section::make('診療内容')
                    ->schema([
                        Forms\Components\Textarea::make('chief_complaint')
                            ->label('主訴・お悩み')
                            ->placeholder('患者様の主な訴えやお悩みを記入')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('symptoms')
                            ->label('症状・現状')
                            ->placeholder('観察された症状や現在の状態を詳しく記入')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('diagnosis')
                            ->label('診断・所見')
                            ->placeholder('診断結果や所見を記入')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('treatment')
                            ->label('治療・施術内容')
                            ->placeholder('実施した治療や施術の詳細を記入')
                            ->rows(4)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('prescription')
                            ->label('指導')
                            ->placeholder('生活指導、アフターケア、トレーニング方法などを記入')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\FileUpload::make('images')
                            ->label('画像')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->directory('medical-records')
                            ->helperText('施術前後の写真、トレーニング資料などをアップロード')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('image_notes')
                            ->label('画像メモ')
                            ->placeholder('画像についての説明やメモ')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('その他の情報')
                    ->schema([
                        Forms\Components\Textarea::make('medical_history')
                            ->label('既往歴・医療履歴')
                            ->placeholder('過去の病歴やアレルギー情報など')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('備考・申し送り事項')
                            ->placeholder('次回の施術に向けた申し送りや特記事項')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\DatePicker::make('next_visit_date')
                            ->label('次回来店予定日')
                            ->helperText('推奨される次回来店日'),
                        
                        Forms\Components\DatePicker::make('actual_reservation_date')
                            ->label('実際の予約日')
                            ->helperText('顧客が実際に予約を取った日（自動入力）')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('date_difference_days')
                            ->label('差異（日数）')
                            ->helperText('推奨日との差異（自動計算）')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => $state ? ($state > 0 ? "+{$state}日" : "{$state}日") : null),
                        
                        Forms\Components\Select::make('reservation_status')
                            ->label('予約ステータス')
                            ->options([
                                'pending' => '予約待ち',
                                'booked' => '予約済み',
                                'completed' => '来店完了',
                                'cancelled' => 'キャンセル',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('record_date')
                    ->label('記録日')
                    ->date('Y/m/d')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('顧客名')
                    ->searchable(['customers.last_name', 'customers.first_name'])
                    ->formatStateUsing(function ($record) {
                        $customer = $record->customer;
                        return $customer ? $customer->last_name . ' ' . $customer->first_name : '-';
                    }),
                    
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('電話番号')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('chief_complaint')
                    ->label('主訴')
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->chief_complaint;
                    }),
                    
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('担当スタッフ')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('reservation.menu.name')
                    ->label('施術メニュー')
                    ->default('-'),
                    
                Tables\Columns\TextColumn::make('next_visit_date')
                    ->label('次回来店予定')
                    ->date('Y/m/d')
                    ->sortable()
                    ->color(function ($state) {
                        if (!$state) return null;
                        $days = now()->diffInDays($state, false);
                        if ($days < 0) return 'danger';
                        if ($days <= 7) return 'warning';
                        return null;
                    }),
                
                Tables\Columns\TextColumn::make('actual_reservation_date')
                    ->label('実際の予約日')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('未予約'),
                
                Tables\Columns\TextColumn::make('date_difference_days')
                    ->label('差異')
                    ->formatStateUsing(fn ($state) => $state ? ($state > 0 ? "+{$state}日" : "{$state}日") : '-')
                    ->color(function ($state) {
                        if ($state === null) return null;
                        if ($state == 0) return 'success';
                        if (abs($state) <= 3) return 'warning';
                        return 'danger';
                    })
                    ->toggleable(),
                
                Tables\Columns\BadgeColumn::make('reservation_status')
                    ->label('予約状況')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'pending' => '予約待ち',
                        'booked' => '予約済み',
                        'completed' => '来院完了',
                        'cancelled' => 'キャンセル',
                        default => '未設定',
                    })
                    ->colors([
                        'secondary' => 'pending',
                        'primary' => 'booked',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('record_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('顧客')
                    ->relationship('customer', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->last_name . ' ' . $record->first_name),
                    
                Tables\Filters\SelectFilter::make('staff_id')
                    ->label('担当スタッフ')
                    ->relationship('staff', 'name'),
                    
                Tables\Filters\Filter::make('next_visit_date')
                    ->label('次回来店予定あり')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('next_visit_date')),
                    
                Tables\Filters\Filter::make('overdue')
                    ->label('来店予定超過')
                    ->query(fn (Builder $query): Builder => $query->where('next_visit_date', '<', now())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('create_reservation')
                    ->label('予約作成')
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->url(fn ($record) => route('filament.admin.resources.reservations.create', [
                        'customer_id' => $record->customer_id
                    ]))
                    ->visible(fn ($record) => $record->next_visit_date && $record->next_visit_date->isFuture()),
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
            'index' => Pages\ListMedicalRecords::route('/'),
            'create' => Pages\CreateMedicalRecord::route('/create'),
            'view' => Pages\ViewMedicalRecord::route('/{record}'),
            'edit' => Pages\EditMedicalRecord::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('next_visit_date', '<', now())->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
    
    public static function getNavigationBadgeTooltip(): ?string
    {
        return '来店予定超過';
    }
}