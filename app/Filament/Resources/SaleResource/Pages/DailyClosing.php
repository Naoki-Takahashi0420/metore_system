<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use App\Models\DailyClosing as DailyClosingModel;
use App\Models\Reservation;
use App\Models\CustomerTicket;
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
    public $unposted = []; // 未計上予約のDTO配列
    public $rowState = []; // 各行のpayment_methodやoverride_source/amountのUI状態
    
    public function mount(): void
    {
        $this->closingDate = today()->format('Y-m-d');
        $this->loadSalesData();
        $this->loadUnpostedReservations();
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

    /**
     * 未計上予約を読み込む
     */
    public function loadUnpostedReservations(): void
    {
        $reservations = Reservation::whereDate('reservation_date', $this->closingDate)
            ->where('store_id', auth()->user()->store_id ?? 1)
            ->where('status', 'completed')
            ->whereDoesntHave('sale')
            ->with(['customer', 'menu'])
            ->orderBy('start_time')
            ->get();

        $this->unposted = $reservations->map(function ($reservation) {
            // 自動判定: customer_ticket_id > customer_subscription_id > spot
            $source = 'spot';
            if ($reservation->customer_ticket_id) {
                $source = 'ticket';
            } elseif ($reservation->customer_subscription_id) {
                $source = 'subscription';
            }

            $paymentMethod = ($source === 'spot') ? 'cash' : 'other';
            $amount = ($source === 'spot') ? ($reservation->total_amount ?? 0) : 0;

            // 行の初期状態を設定
            $this->rowState[$reservation->id] = [
                'source' => $source,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
            ];

            return [
                'id' => $reservation->id,
                'time' => $reservation->start_time,
                'customer_name' => $reservation->customer?->name ?? '不明',
                'menu_name' => $reservation->menu?->name ?? '不明',
                'source' => $source,
                'amount' => $amount,
            ];
        })->toArray();
    }

    /**
     * 個別の予約を計上
     */
    public function postSale(int $reservationId): void
    {
        try {
            // 二重計上チェック
            if (Sale::where('reservation_id', $reservationId)->exists()) {
                Notification::make()
                    ->title('エラー')
                    ->body('この予約は既に計上済みです')
                    ->warning()
                    ->send();
                return;
            }

            $reservation = Reservation::findOrFail($reservationId);
            $state = $this->rowState[$reservationId] ?? null;

            if (!$state) {
                throw new \Exception('予約の状態が見つかりません');
            }

            $source = $state['source'];
            $method = $state['payment_method'];
            $amount = $state['amount'];

            // サブスク/回数券は強制的に0円
            if (in_array($source, ['subscription', 'ticket'])) {
                $amount = 0;
            }

            // スポットの場合は金額を更新
            if ($source === 'spot' && $amount != $reservation->total_amount) {
                $reservation->update(['total_amount' => $amount]);
            }

            DB::beginTransaction();

            // payment_sourceに応じて計上
            $sale = $reservation->completeAndCreateSale($method, $source);

            DB::commit();

            Notification::make()
                ->title('計上完了')
                ->body("予約番号 {$reservation->reservation_number} を計上しました")
                ->success()
                ->send();

            // データを再読み込み
            $this->loadSalesData();
            $this->loadUnpostedReservations();

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('エラー')
                ->body('計上処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * 全ての未計上予約を一括計上
     */
    public function postAll(): void
    {
        $successCount = 0;
        $errorCount = 0;

        foreach ($this->unposted as $res) {
            try {
                $this->postSale($res['id']);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                \Log::error('一括計上エラー', [
                    'reservation_id' => $res['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Notification::make()
            ->title('一括計上完了')
            ->body("成功: {$successCount}件、エラー: {$errorCount}件")
            ->success()
            ->send();

        // データを再読み込み
        $this->loadSalesData();
        $this->loadUnpostedReservations();
    }

    /**
     * 売上を取り消す
     */
    public function voidSale(int $saleId): void
    {
        try {
            DB::beginTransaction();

            $sale = Sale::with(['customerTicket'])->findOrFail($saleId);

            // 回数券の場合は返却
            if ($sale->customer_ticket_id) {
                $ticket = CustomerTicket::find($sale->customer_ticket_id);
                if ($ticket) {
                    $ticket->refund($sale->reservation_id, 1);
                }
            }

            // 売上を削除
            $sale->delete();

            DB::commit();

            Notification::make()
                ->title('取消完了')
                ->body("売上番号 {$sale->sale_number} を取り消しました")
                ->success()
                ->send();

            // データを再読み込み
            $this->loadSalesData();
            $this->loadUnpostedReservations();

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('エラー')
                ->body('取消処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
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