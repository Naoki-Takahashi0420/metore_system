<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use App\Models\DailyClosing as DailyClosingModel;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class DailyClosing extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static string $resource = SaleResource::class;

    protected static string $view = 'filament.resources.sale-resource.pages.daily-closing';
    
    protected static ?string $title = '日次精算';
    
    public $closingDate;
    public $openingCash = 50000; // デフォルト釣銭準備金
    public $actualCash;
    public $notes;
    
    public $salesData = [];
    
    public function mount(): void
    {
        $this->closingDate = today()->format('Y-m-d');
        $this->loadSalesData();
    }
    
    public function loadSalesData(): void
    {
        $sales = Sale::whereDate('sale_date', $this->closingDate)
            ->where('store_id', auth()->user()->store_id ?? 1)
            ->where('status', 'completed')
            ->get();
        
        $this->salesData = [
            'cash_sales' => $sales->where('payment_method', 'cash')->sum('total_amount'),
            'card_sales' => $sales->whereIn('payment_method', ['credit_card', 'debit_card'])->sum('total_amount'),
            'digital_sales' => $sales->whereIn('payment_method', ['paypay', 'line_pay'])->sum('total_amount'),
            'total_sales' => $sales->sum('total_amount'),
            'transaction_count' => $sales->count(),
            'customer_count' => $sales->unique('customer_id')->count(),
            'expected_cash' => $this->openingCash + $sales->where('payment_method', 'cash')->sum('total_amount'),
        ];
        
        // スタッフ別売上
        $this->salesData['sales_by_staff'] = $sales->groupBy('staff_id')->map(function ($staffSales) {
            return [
                'name' => $staffSales->first()->staff?->name ?? '不明',
                'amount' => $staffSales->sum('total_amount'),
                'count' => $staffSales->count(),
            ];
        });
        
        // メニュー別売上（売上明細から集計）
        $menuSales = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereDate('sales.sale_date', $this->closingDate)
            ->where('sales.store_id', auth()->user()->store_id ?? 1)
            ->where('sales.status', 'completed')
            ->select('sale_items.item_name', DB::raw('SUM(sale_items.amount) as total'), DB::raw('SUM(sale_items.quantity) as count'))
            ->groupBy('sale_items.item_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
            
        $this->salesData['top_menus'] = $menuSales;
    }
    
    public function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('精算情報')
                ->schema([
                    Forms\Components\DatePicker::make('closingDate')
                        ->label('精算日')
                        ->native(false)
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->loadSalesData()),
                    Forms\Components\TextInput::make('openingCash')
                        ->label('釣銭準備金')
                        ->numeric()
                        ->prefix('¥')
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->loadSalesData()),
                    Forms\Components\TextInput::make('actualCash')
                        ->label('実際の現金残高')
                        ->numeric()
                        ->prefix('¥')
                        ->helperText('レジ内の現金を数えて入力してください'),
                    Forms\Components\Textarea::make('notes')
                        ->label('備考')
                        ->rows(3),
                ]),
        ];
    }
    
    public function performClosing(): void
    {
        if (!$this->actualCash) {
            Notification::make()
                ->title('エラー')
                ->body('実際の現金残高を入力してください')
                ->danger()
                ->send();
            return;
        }
        
        $cashDifference = $this->actualCash - $this->salesData['expected_cash'];
        
        try {
            DB::beginTransaction();
            
            // 既存の精算レコードをチェック
            $existingClosing = DailyClosingModel::where('store_id', auth()->user()->store_id ?? 1)
                ->where('closing_date', $this->closingDate)
                ->first();
            
            if ($existingClosing) {
                Notification::make()
                    ->title('エラー')
                    ->body('この日の精算は既に完了しています')
                    ->danger()
                    ->send();
                return;
            }
            
            // 日次精算レコードを作成
            DailyClosingModel::create([
                'store_id' => auth()->user()->store_id ?? 1,
                'closing_date' => $this->closingDate,
                'opening_cash' => $this->openingCash,
                'cash_sales' => $this->salesData['cash_sales'],
                'card_sales' => $this->salesData['card_sales'],
                'digital_sales' => $this->salesData['digital_sales'],
                'total_sales' => $this->salesData['total_sales'],
                'expected_cash' => $this->salesData['expected_cash'],
                'actual_cash' => $this->actualCash,
                'cash_difference' => $cashDifference,
                'transaction_count' => $this->salesData['transaction_count'],
                'customer_count' => $this->salesData['customer_count'],
                'sales_by_staff' => $this->salesData['sales_by_staff']->toArray(),
                'sales_by_menu' => $this->salesData['top_menus']->toArray(),
                'status' => 'closed',
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'notes' => $this->notes,
            ]);
            
            DB::commit();
            
            Notification::make()
                ->title('日次精算完了')
                ->body('精算が正常に完了しました。差異: ¥' . number_format($cashDifference))
                ->success()
                ->send();
                
            $this->redirect(SaleResource::getUrl('index'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->title('エラー')
                ->body('精算処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('戻る')
                ->url(SaleResource::getUrl('index'))
                ->color('gray'),
        ];
    }
}