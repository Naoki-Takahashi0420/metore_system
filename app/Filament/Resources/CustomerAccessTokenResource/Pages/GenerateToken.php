<?php

namespace App\Filament\Resources\CustomerAccessTokenResource\Pages;

use App\Filament\Resources\CustomerAccessTokenResource;
use App\Models\Customer;
use App\Models\CustomerAccessToken;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;

class GenerateToken extends Page
{
    protected static string $resource = CustomerAccessTokenResource::class;
    protected static string $view = 'filament.resources.customer-access-token-resource.pages.generate-token';
    protected static ?string $title = 'トークン生成';
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('トークン生成設定')
                    ->description('既存顧客用の予約トークンを生成します')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('顧客')
                            ->options(Customer::all()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('トークンを発行する顧客を選択'),
                            
                        Forms\Components\Select::make('store_id')
                            ->label('店舗限定')
                            ->options(Store::where('is_active', true)->pluck('name', 'id'))
                            ->placeholder('全店舗で利用可能')
                            ->helperText('特定店舗のみで利用可能にする場合選択'),
                            
                        Forms\Components\Select::make('purpose')
                            ->label('用途')
                            ->options([
                                'existing_customer' => '既存顧客用',
                                'vip' => 'VIP顧客用',
                                'campaign' => 'キャンペーン用',
                                'referral' => '紹介特典用',
                            ])
                            ->default('existing_customer')
                            ->required(),
                            
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('有効期限')
                            ->default(Carbon::now()->addMonths(6))
                            ->minDate(now())
                            ->helperText('トークンの有効期限を設定'),
                            
                        Forms\Components\TextInput::make('max_usage')
                            ->label('最大使用回数')
                            ->numeric()
                            ->placeholder('無制限')
                            ->helperText('空欄の場合は無制限'),
                            
                        Forms\Components\KeyValue::make('metadata')
                            ->label('追加情報')
                            ->keyLabel('項目')
                            ->valueLabel('値')
                            ->addActionLabel('項目を追加')
                            ->helperText('割引率などの追加情報を設定できます'),
                    ]),
                    
                Forms\Components\Section::make('一括生成オプション')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('bulk_generate')
                            ->label('複数顧客に一括生成')
                            ->reactive(),
                            
                        Forms\Components\Select::make('customer_filter')
                            ->label('顧客フィルター')
                            ->visible(fn ($get) => $get('bulk_generate'))
                            ->options([
                                'all' => '全顧客',
                                'active' => 'アクティブな顧客のみ',
                                'subscription' => 'サブスク会員のみ',
                                'no_recent' => '30日以上来店なし',
                            ])
                            ->default('active'),
                    ]),
            ])
            ->statePath('data');
    }
    
    public function generate(): void
    {
        $data = $this->form->getState();
        
        try {
            if ($data['bulk_generate'] ?? false) {
                // 一括生成
                $customers = $this->getFilteredCustomers($data['customer_filter'] ?? 'all');
                $count = 0;
                
                foreach ($customers as $customer) {
                    CustomerAccessToken::generateFor($customer, 
                        isset($data['store_id']) ? Store::find($data['store_id']) : null, 
                        [
                            'purpose' => $data['purpose'],
                            'expires_at' => $data['expires_at'],
                            'max_usage' => $data['max_usage'] ?? null,
                            'metadata' => $data['metadata'] ?? null,
                        ]
                    );
                    $count++;
                }
                
                Notification::make()
                    ->title('トークン生成完了')
                    ->body("{$count}件のトークンを生成しました")
                    ->success()
                    ->send();
            } else {
                // 単一生成
                $customer = Customer::find($data['customer_id']);
                $token = CustomerAccessToken::generateFor($customer,
                    isset($data['store_id']) ? Store::find($data['store_id']) : null,
                    [
                        'purpose' => $data['purpose'],
                        'expires_at' => $data['expires_at'],
                        'max_usage' => $data['max_usage'] ?? null,
                        'metadata' => $data['metadata'] ?? null,
                    ]
                );
                
                Notification::make()
                    ->title('トークン生成完了')
                    ->body("URL: {$token->getReservationUrl()}")
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('copy')
                            ->label('URLをコピー')
                            ->color('gray')
                            ->extraAttributes([
                                'onclick' => "navigator.clipboard.writeText('{$token->getReservationUrl()}')",
                            ]),
                    ])
                    ->send();
            }
            
            $this->redirect(CustomerAccessTokenResource::getUrl('index'));
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('エラー')
                ->body('トークンの生成に失敗しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getFilteredCustomers(string $filter)
    {
        $query = Customer::query();
        
        switch ($filter) {
            case 'active':
                $query->whereHas('reservations', function ($q) {
                    $q->where('created_at', '>=', Carbon::now()->subMonths(3));
                });
                break;
                
            case 'subscription':
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('status', 'active')
                        ->where('expires_at', '>', now());
                });
                break;
                
            case 'no_recent':
                $query->whereDoesntHave('reservations', function ($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(30));
                });
                break;
        }
        
        return $query->get();
    }
}