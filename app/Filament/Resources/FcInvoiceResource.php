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

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // super_adminは常に表示
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // 本部またはFC加盟店のユーザーに表示
        if ($user->store) {
            return $user->store->isHeadquarters() || $user->store->isFcStore();
        }

        return false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // super_adminと本部のみ作成可能（FC加盟店は閲覧のみ）
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->store?->isHeadquarters() ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // super_adminと本部のみ編集可能（FC加盟店は閲覧のみ）
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->store?->isHeadquarters() ?? false;
    }

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
                // 請求書ヘッダー（表形式）
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Placeholder::make('invoice_number_display')
                                    ->label('請求書番号')
                                    ->content(fn ($record) => $record?->invoice_number ?? '（新規）'),
                                Forms\Components\Placeholder::make('status_display')
                                    ->label('ステータス')
                                    ->content(fn ($record) => $record?->status_label ?? '下書き'),
                                Forms\Components\Placeholder::make('total_display')
                                    ->label('請求金額')
                                    ->content(fn ($record) => $record ? '¥' . number_format($record->total_amount) : '¥0'),
                                Forms\Components\Placeholder::make('due_display')
                                    ->label('支払期限')
                                    ->content(fn ($record) => $record?->due_date?->format('Y/m/d') ?? '未設定'),
                            ]),
                    ])
                    ->visible(fn ($record) => $record !== null),

                // 基本情報（新規作成時のみ編集可能）
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('headquarters_store_id')
                                    ->label('請求元')
                                    ->options(Store::where('fc_type', 'headquarters')->pluck('name', 'id'))
                                    ->required()
                                    ->disabled(fn ($record) => $record !== null),
                                Forms\Components\Select::make('fc_store_id')
                                    ->label('請求先')
                                    ->options(Store::where('fc_type', 'fc_store')->pluck('name', 'id'))
                                    ->required()
                                    ->disabled(fn ($record) => $record !== null),
                                Forms\Components\DatePicker::make('billing_period_start')
                                    ->label('対象期間（開始）')
                                    ->required(),
                                Forms\Components\DatePicker::make('billing_period_end')
                                    ->label('対象期間（終了）')
                                    ->required(),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('支払期限'),
                                Forms\Components\Textarea::make('notes')
                                    ->label('備考')
                                    ->rows(2),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($record) => $record !== null),

                // 明細テーブル
                Forms\Components\Section::make('請求明細')
                    ->schema([
                        Forms\Components\ViewField::make('invoice_items')
                            ->label('')
                            ->view('livewire.fc-invoice-item-editor-form')
                            ->viewData(fn ($record) => [
                                'invoice' => $record,
                                'readonly' => !$record || $record->status !== 'draft'
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record !== null),
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
                    )
                    ->visible(function () {
                        $user = auth()->user();
                        return $user?->hasRole('super_admin') || $user?->store?->isHeadquarters();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(function () {
                        $user = auth()->user();
                        return $user?->hasRole('super_admin') || $user?->store?->isHeadquarters();
                    }),
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
            ->headerActions([
                Tables\Actions\Action::make('generate_monthly_invoices')
                    ->label('月次請求書を一括生成')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->visible(function (): bool {
                        $user = auth()->user();
                        return $user->hasRole('super_admin') ||
                               ($user->store && $user->store->isHeadquarters());
                    })
                    ->form([
                        Forms\Components\Select::make('target_month')
                            ->label('対象月')
                            ->options(function () {
                                $options = [];
                                for ($i = 0; $i < 6; $i++) {
                                    $month = now()->subMonths($i);
                                    $options[$month->format('Y-m')] = $month->format('Y年m月');
                                }
                                return $options;
                            })
                            ->default(now()->subMonth()->format('Y-m'))
                            ->required()
                            ->helperText('選択した月の納品済み発注から請求書を生成します'),
                        Forms\Components\Toggle::make('include_custom_items')
                            ->label('カスタム項目を追加するため下書きで作成')
                            ->default(true)
                            ->helperText('ONの場合、下書き状態で作成されます。明細編集後に「発行」してください。'),
                    ])
                    ->action(function (array $data) {
                        $targetMonth = \Carbon\Carbon::createFromFormat('Y-m', $data['target_month'])->startOfMonth();

                        $result = FcInvoice::generateMonthlyInvoicesForAllStores($targetMonth);

                        if (count($result['created']) > 0) {
                            $createdList = collect($result['created'])->map(function ($item) {
                                return "• {$item['store_name']}: {$item['invoice_number']} (¥" . number_format($item['total_amount']) . ")";
                            })->join("\n");

                            Notification::make()
                                ->title('月次請求書を生成しました')
                                ->body("作成: " . count($result['created']) . "件\n\n{$createdList}")
                                ->success()
                                ->duration(10000)
                                ->send();
                        } else {
                            Notification::make()
                                ->title('生成対象がありませんでした')
                                ->body('選択した月に未請求の納品済み発注がありません。')
                                ->warning()
                                ->send();
                        }

                        if (count($result['skipped']) > 0) {
                            $skippedList = collect($result['skipped'])->map(function ($item) {
                                return "• {$item['store_name']}: {$item['reason']}";
                            })->join("\n");

                            Notification::make()
                                ->title('スキップした店舗')
                                ->body($skippedList)
                                ->info()
                                ->duration(8000)
                                ->send();
                        }
                    })
                    ->modalHeading('月次請求書の一括生成')
                    ->modalDescription('前月の納品済み発注から、FC店舗ごとに請求書を生成します。カスタム項目（ロイヤリティ等）は生成後に追加してください。')
                    ->modalSubmitActionLabel('生成する'),
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
            'preview' => Pages\FcInvoicePreview::route('/preview'),
        ];
    }
}
