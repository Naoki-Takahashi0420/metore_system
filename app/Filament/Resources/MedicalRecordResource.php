<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MedicalRecordResource\Pages;
use App\Models\MedicalRecord;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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
                Forms\Components\Tabs::make('カルテ情報')
                    ->tabs([
                        // 基本情報タブ
                        Forms\Components\Tabs\Tab::make('基本情報')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('customer_id')
                                            ->label('顧客')
                                            ->options(Customer::all()->mapWithKeys(function ($customer) {
                                                $name = ($customer->last_name ?? '') . ' ' . ($customer->first_name ?? '') . ' (' . ($customer->phone ?? '') . ')';
                                                return [$customer->id => $name];
                                            }))
                                            ->searchable()
                                            ->required(),
                                        
                                        Forms\Components\Select::make('reservation_id')
                                            ->label('予約')
                                            ->options(function ($get) {
                                                if (!$get('customer_id')) {
                                                    return [];
                                                }
                                                return Reservation::where('customer_id', $get('customer_id'))
                                                    ->orderBy('reservation_date', 'desc')
                                                    ->get()
                                                    ->mapWithKeys(function ($reservation) {
                                                        $dateTime = ($reservation->reservation_date ? $reservation->reservation_date->format('Y/m/d') : '') . ' ' . ($reservation->reservation_time ?? '');
                                                        return [$reservation->id => $dateTime];
                                                    });
                                            })
                                            ->searchable(),
                                        
                                        Forms\Components\TextInput::make('handled_by')
                                            ->label('対応者')
                                            ->default(Auth::user()->name)
                                            ->required(),
                                        
                                        Forms\Components\DatePicker::make('treatment_date')
                                            ->label('施術日')
                                            ->default(now())
                                            ->required(),
                                    ]),
                            ]),
                        
                        // 顧客管理情報タブ（常に表示）
                        Forms\Components\Tabs\Tab::make('顧客管理情報')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('payment_method')
                                            ->label('支払い方法')
                                            ->options([
                                                'cash' => '現金',
                                                'credit' => 'クレジットカード',
                                                'paypay' => 'PayPay',
                                                'bank_transfer' => '銀行振込',
                                                'subscription' => 'サブスク',
                                            ]),
                                        
                                        Forms\Components\Select::make('reservation_source')
                                            ->label('予約媒体')
                                            ->options([
                                                'hp' => 'ホームページ',
                                                'phone' => '電話',
                                                'line' => 'LINE',
                                                'instagram' => 'Instagram',
                                                'referral' => '紹介',
                                                'walk_in' => '飛び込み',
                                            ]),
                                        
                                        Forms\Components\Textarea::make('visit_purpose')
                                            ->label('来店目的')
                                            ->rows(2),
                                        
                                        Forms\Components\Textarea::make('workplace_address')
                                            ->label('職場・住所')
                                            ->rows(2),
                                    ]),
                                
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Toggle::make('genetic_possibility')
                                            ->label('遺伝の可能性'),
                                        
                                        Forms\Components\Toggle::make('has_astigmatism')
                                            ->label('乱視'),
                                        
                                        Forms\Components\Textarea::make('eye_diseases')
                                            ->label('目の病気')
                                            ->placeholder('レーシック、白内障など')
                                            ->rows(2)
                                            ->columnSpan(3),
                                    ]),
                                
                                Forms\Components\Textarea::make('device_usage')
                                    ->label('スマホ・PC使用頻度')
                                    ->placeholder('1日何時間程度、仕事で使用など')
                                    ->rows(2),
                            ]),
                        
                        // 視力記録タブ（顧客に見せる）
                        Forms\Components\Tabs\Tab::make('視力記録')
                            ->schema([
                                Forms\Components\Repeater::make('vision_records')
                                    ->label('視力測定記録')
                                    ->schema([
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\Hidden::make('session')
                                                    ->default(1),
                                                
                                                Forms\Components\TextInput::make('display_session')
                                                    ->label('回数')
                                                    ->default('自動設定')
                                                    ->disabled()
                                                    ->dehydrated(false),
                                                
                                                Forms\Components\DatePicker::make('date')
                                                    ->label('測定日')
                                                    ->default(now())
                                                    ->required(),
                                                
                                                Forms\Components\TextInput::make('intensity')
                                                    ->label('強度')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(10)
                                                    ->placeholder('1-10')
                                                    ->helperText('1（弱）〜 10（強）'),
                                                
                                                Forms\Components\Select::make('duration')
                                                    ->label('時間')
                                                    ->options([
                                                        '15分' => '15分',
                                                        '30分' => '30分',
                                                        '45分' => '45分',
                                                        '60分' => '60分',
                                                        '75分' => '75分',
                                                        '90分' => '90分',
                                                        '105分' => '105分',
                                                        '120分' => '120分',
                                                    ]),
                                            ]),
                                        
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\TextInput::make('before_left')
                                                    ->label('施術前視力（左）')
                                                    ->placeholder('0.5'),
                                                
                                                Forms\Components\TextInput::make('before_right')
                                                    ->label('施術前視力（右）')
                                                    ->placeholder('0.5'),
                                                
                                                Forms\Components\TextInput::make('after_left')
                                                    ->label('施術後視力（左）')
                                                    ->placeholder('0.8'),
                                                
                                                Forms\Components\TextInput::make('after_right')
                                                    ->label('施術後視力（右）')
                                                    ->placeholder('0.8'),
                                            ]),
                                        
                                        Forms\Components\Textarea::make('public_memo')
                                            ->label('メモ（顧客に表示）')
                                            ->placeholder('効果の実感など')
                                            ->rows(2),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('視力記録を追加')
                                    ->collapsible()
                                    ->cloneable(),
                            ]),
                        
                        // 接客メモタブ（内部用）
                        Forms\Components\Tabs\Tab::make('接客メモ・引き継ぎ')
                            ->schema([
                                Forms\Components\Textarea::make('service_memo')
                                    ->label('接客メモ（内部用・顧客には非表示）')
                                    ->rows(4)
                                    ->placeholder('顧客の様子、対応時の注意点など'),
                                
                                Forms\Components\Textarea::make('next_visit_notes')
                                    ->label('次回引き継ぎ事項')
                                    ->rows(4)
                                    ->placeholder('次回予約時に確認すべきこと、注意点など'),
                                
                                Forms\Components\Textarea::make('notes')
                                    ->label('その他メモ')
                                    ->rows(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('顧客名')
                    ->formatStateUsing(fn ($record) => $record->customer ? (($record->customer->last_name ?? '') . ' ' . ($record->customer->first_name ?? '')) : '-')
                    ->searchable(['customers.last_name', 'customers.first_name'])
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('treatment_date')
                    ->label('施術日')
                    ->date('Y/m/d')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('session_number')
                    ->label('回数')
                    ->badge()
                    ->color('primary'),
                
                Tables\Columns\TextColumn::make('handled_by')
                    ->label('対応者')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('latest_vision')
                    ->label('最新視力')
                    ->getStateUsing(function (MedicalRecord $record) {
                        $latest = $record->getLatestVisionRecord();
                        if (!$latest) return '-';
                        return sprintf(
                            'L: %s→%s / R: %s→%s',
                            $latest['before_left'] ?? '-',
                            $latest['after_left'] ?? '-',
                            $latest['before_right'] ?? '-',
                            $latest['after_right'] ?? '-'
                        );
                    }),
                
                Tables\Columns\IconColumn::make('has_next_notes')
                    ->label('引継')
                    ->getStateUsing(fn ($record) => !empty($record->next_visit_notes))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('顧客')
                    ->options(Customer::all()->mapWithKeys(function ($customer) {
                        return [$customer->id => $customer->last_name . ' ' . $customer->first_name];
                    }))
                    ->searchable(),
                
                Tables\Filters\Filter::make('has_next_notes')
                    ->label('引き継ぎありのみ')
                    ->query(fn ($query) => $query->whereNotNull('next_visit_notes')->where('next_visit_notes', '!=', '')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print')
                    ->label('印刷')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record) => route('medical-record.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('treatment_date', 'desc');
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
}