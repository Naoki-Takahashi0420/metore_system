<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FcInvoiceResource\Pages;
use App\Models\FcInvoice;
use App\Models\FcPayment;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Services\FcNotificationService;
use Illuminate\Database\Eloquent\Builder;

class FcInvoiceResource extends Resource
{
    protected static ?string $model = FcInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'FC請求書';

    protected static ?string $modelLabel = 'FC請求書';

    protected static ?string $pluralModelLabel = 'FC請求書';

    protected static ?string $navigationGroup = 'FC本部管理';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // super_adminは全データ閲覧可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // 本部店舗のユーザーは全データ閲覧可能
        if ($user->store && $user->store->isHeadquarters()) {
            return $query;
        }

        // FC加盟店のユーザーは自店舗の請求書のみ閲覧可能
        if ($user->store && $user->store->isFcStore()) {
            return $query->where('fc_store_id', $user->store_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('請求書情報')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('請求書番号')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),
                        Forms\Components\Select::make('headquarters_store_id')
                            ->label('請求元本部')
                            ->options(
                                Store::where('fc_type', 'headquarters')
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->reactive()
                            ->searchable(),
                        Forms\Components\Select::make('fc_store_id')
                            ->label('請求先FC店舗')
                            ->options(function (Forms\Get $get) {
                                $headquartersId = $get('headquarters_store_id');
                                if (!$headquartersId) {
                                    return Store::where('fc_type', 'fc_store')
                                        ->pluck('name', 'id');
                                }
                                return Store::where('fc_type', 'fc_store')
                                    ->where('headquarters_store_id', $headquartersId)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'draft' => '下書き',
                                'issued' => '発行済み',
                                'sent' => '送付済み',
                                'paid' => '入金完了',
                                'cancelled' => 'キャンセル',
                            ])
                            ->disabled()
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('請求期間・日付')
                    ->schema([
                        Forms\Components\DatePicker::make('billing_period_start')
                            ->label('請求対象期間（開始）')
                            ->required(),
                        Forms\Components\DatePicker::make('billing_period_end')
                            ->label('請求対象期間（終了）')
                            ->required(),
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('発行日'),
                        Forms\Components\DatePicker::make('due_date')
                            ->label('支払期限'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('金額')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('小計（税抜）')
                            ->numeric()
                            ->required()
                            ->prefix('¥')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $subtotal = floatval($state ?? 0);
                                $taxRate = 10; // 10% tax
                                $taxAmount = $subtotal * ($taxRate / 100);
                                $totalAmount = $subtotal + $taxAmount;
                                $paidAmount = floatval($get('paid_amount') ?? 0);

                                $set('tax_amount', $taxAmount);
                                $set('total_amount', $totalAmount);
                                $set('outstanding_amount', $totalAmount - $paidAmount);
                            }),
                        Forms\Components\TextInput::make('tax_amount')
                            ->label('消費税')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('合計（税込）')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('入金済み金額')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('outstanding_amount')
                            ->label('未払い金額')
                            ->numeric()
                            ->prefix('¥')
                            ->default(0)
                            ->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('その他')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->rows(3)
                            ->maxLength(1000),
                        Forms\Components\FileUpload::make('pdf_path')
                            ->label('PDF請求書')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('fc-invoices')
                            ->disk('public')
                            ->visibility('private'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(new \Illuminate\Support\HtmlString('
                <div style="background: #fef3c7; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px solid #f59e0b;">
                    <div style="font-weight: bold; font-size: 18px; margin-bottom: 16px; color: #92400e;">📋 請求書処理フロー</div>

                    <div style="font-size: 15px; line-height: 2;">
                        <div><strong style="font-size: 16px;">①</strong> 納品完了 → <strong style="font-size: 16px;">②</strong> 請求書自動発行（加盟店に自動通知） → <strong style="font-size: 16px;">③</strong> 入金確認後「入金記録」ボタン → <strong style="font-size: 16px;">④</strong> 完了</div>
                    </div>

                    <div style="margin-top: 12px; padding: 12px; background: white; border-radius: 4px; font-size: 14px;">
                        💡 発注管理で「納品完了」にすると請求書が自動発行され、加盟店に通知されます
                    </div>
                </div>
            '))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('請求書番号')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fcStore.name')
                    ->label('請求先FC店舗')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('ステータス')
                    ->colors([
                        'secondary' => 'draft',
                        'primary' => fn ($state): bool => in_array($state, ['issued', 'sent']),
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => '下書き',
                        'issued' => '発行済み',
                        'sent' => '送付済み',
                        'paid' => '入金完了',
                        'cancelled' => 'キャンセル',
                        default => $state,
                    })
                    ->description(fn (FcInvoice $record): string => match ($record->status) {
                        'draft' => '→「発行」ボタンを押してください',
                        'issued' => '→ 入金確認後「入金記録」を押す',
                        'sent' => '→ 入金確認後「入金記録」を押す',
                        'paid' => '✓ 完了',
                        'cancelled' => 'キャンセル済み',
                        default => '',
                    }),
                Tables\Columns\TextColumn::make('billing_period_start')
                    ->label('請求期間')
                    ->formatStateUsing(fn ($state, $record) =>
                        $state->format('Y/m/d') . ' - ' . $record->billing_period_end->format('Y/m/d')
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('請求金額')
                    ->money('jpy')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('入金済み')
                    ->money('jpy')
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstanding_amount')
                    ->label('未払い')
                    ->money('jpy')
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('支払期限')
                    ->date('Y/m/d')
                    ->color(fn ($state, $record): string =>
                        $record->isOverdue() ? 'danger' : 'default'
                    )
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'draft' => '下書き',
                        'issued' => '発行済み',
                        'sent' => '送付済み',
                        'paid' => '入金完了',
                        'cancelled' => 'キャンセル',
                    ]),
                Tables\Filters\SelectFilter::make('fc_store_id')
                    ->label('FC店舗')
                    ->options(
                        Store::where('fc_type', 'fc_store')
                            ->pluck('name', 'id')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_pdf')
                    ->label('PDF表示')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->url(fn (FcInvoice $record): string => route('fc-invoice.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('issue')
                    ->label('発行')
                    ->icon('heroicon-o-document-check')
                    ->color('primary')
                    ->visible(function (FcInvoice $record): bool {
                        $user = auth()->user();
                        // 本部のユーザーまたはsuper_adminのみ表示
                        $canManage = $user->hasRole('super_admin') || 
                                   ($user->store && $user->store->isHeadquarters());
                        return $record->status === 'draft' && $canManage;
                    })
                    ->requiresConfirmation()
                    ->action(function (FcInvoice $record) {
                        $record->update([
                            'status' => 'issued',
                            'issue_date' => now(),
                        ]);

                        // FC店舗に請求書発行通知
                        try {
                            app(FcNotificationService::class)->notifyInvoiceIssued($record);
                        } catch (\Exception $e) {
                            \Log::error("FC請求書発行通知エラー: " . $e->getMessage());
                        }

                        Notification::make()
                            ->title('請求書を発行しました')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('record_payment')
                    ->label('入金記録')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(function (FcInvoice $record): bool {
                        $user = auth()->user();
                        // 本部のユーザーまたはsuper_adminのみ表示
                        $canManage = $user->hasRole('super_admin') || 
                                   ($user->store && $user->store->isHeadquarters());
                        // 発行済み（issued）または送付済み（sent）の場合のみ表示
                        $statusOk = in_array($record->status, ['issued', 'sent']);
                        return $statusOk && $canManage;
                    })
                    ->form(function (FcInvoice $record) {
                        return [
                            Forms\Components\TextInput::make('amount')
                                ->label('入金額')
                                ->numeric()
                                ->required()
                                ->prefix('¥')
                                ->default(intval($record->outstanding_amount))
                                ->helperText('未払い金額: ¥' . number_format($record->outstanding_amount))
                                ->step(1)
                                ->minValue(0),
                            Forms\Components\DatePicker::make('payment_date')
                                ->label('入金日')
                                ->required()
                                ->default(now()),
                            Forms\Components\Select::make('payment_method')
                                ->label('支払方法')
                                ->options([
                                    'bank_transfer' => '銀行振込',
                                    'cash' => '現金',
                                    'other' => 'その他',
                                ])
                                ->default('bank_transfer')
                                ->required(),
                            Forms\Components\Textarea::make('notes')
                                ->label('備考')
                                ->rows(2),
                        ];
                    })
                    ->action(function (FcInvoice $record, array $data) {
                        $amount = floatval($data['amount']);

                        FcPayment::create([
                            'fc_invoice_id' => $record->id,
                            'amount' => $amount,
                            'payment_date' => $data['payment_date'],
                            'payment_method' => $data['payment_method'],
                            'notes' => $data['notes'] ?? null,
                            'confirmed_by' => Auth::id(),
                        ]);

                        // FC店舗に入金確認通知
                        $record->refresh();
                        try {
                            app(FcNotificationService::class)->notifyPaymentReceived($record, $amount);
                        } catch (\Exception $e) {
                            \Log::error("FC入金確認通知エラー: " . $e->getMessage());
                        }

                        Notification::make()
                            ->title('入金を記録しました')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListFcInvoices::route('/'),
            'create' => Pages\CreateFcInvoice::route('/create'),
            'view' => Pages\ViewFcInvoice::route('/{record}'),
            'edit' => Pages\EditFcInvoice::route('/{record}/edit'),
        ];
    }
}
