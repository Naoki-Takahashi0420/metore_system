<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSource;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected static string $view = 'filament.resources.sale-resource.pages.list-sales';

    public ?int $storeId = null;

    #[Url(as: 'from')]
    public ?string $dateFrom = null;

    #[Url(as: 'to')]
    public ?string $dateTo = null;

    public $stats = [];
    public $stores = [];

    public function mount(): void
    {
        // 初期値設定
        if (!$this->storeId) {
            $this->storeId = auth()->user()->store_id;
        }
        if (!$this->dateFrom) {
            $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        }
        if (!$this->dateTo) {
            $this->dateTo = Carbon::now()->toDateString();
        }

        // 店舗リスト取得
        $this->stores = Store::pluck('name', 'id')->toArray();

        parent::mount();
        $this->loadStats();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        // 店舗フィルタ
        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        // 日付範囲フィルタ
        if ($this->dateFrom && $this->dateTo) {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->endOfDay();
            $query->whereBetween('sale_date', [$from, $to]);
        }

        return $query;
    }

    // クイックボタン
    public function setToday(): void
    {
        $this->dateFrom = Carbon::today()->toDateString();
        $this->dateTo = Carbon::today()->toDateString();
        $this->loadStats();
        $this->dispatchChartUpdate();
    }

    public function setThisMonth(): void
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->toDateString();
        $this->loadStats();
        $this->dispatchChartUpdate();
    }

    public function setLastMonth(): void
    {
        $this->dateFrom = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::now()->subMonth()->endOfMonth()->toDateString();
        $this->loadStats();
        $this->dispatchChartUpdate();
    }

    public function setLast30Days(): void
    {
        $this->dateFrom = Carbon::now()->subDays(29)->toDateString();
        $this->dateTo = Carbon::now()->toDateString();
        $this->loadStats();
        $this->dispatchChartUpdate();
    }

    // フィルタ変更時
    public function updatedStoreId(): void
    {
        $this->loadStats();
        $this->dispatchChartUpdate();
    }

    public function updatedDateFrom(): void
    {
        $this->loadStats();
        $this->dispatchChartUpdate();
    }

    public function updatedDateTo(): void
    {
        $this->loadStats();
        $this->dispatchChartUpdate();
    }

    private function dispatchChartUpdate(): void
    {
        $this->dispatch('chart-update',
            labels: $this->stats['chart_labels'] ?? [],
            data: $this->stats['chart_data'] ?? []
        );
    }

    public function loadStats(): void
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        // 店舗設定の支払い方法を取得
        $store = Store::find($this->storeId);
        $storePaymentMethods = [];

        if ($store && $store->payment_methods && is_array($store->payment_methods)) {
            $storePaymentMethods = collect($store->payment_methods)->pluck('name')->toArray();
        }

        // 支払方法別売上（実際のDBの値を使用）
        $paymentMethodStats = [];

        // 期間中の売上を支払い方法でグループ化
        $salesByMethod = Sale::where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->selectRaw('payment_method, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        foreach ($salesByMethod as $sale) {
            $methodName = $sale->payment_method;

            // 店舗設定がある場合：設定にある支払い方法のみ表示
            if (!empty($storePaymentMethods)) {
                if (!in_array($methodName, $storePaymentMethods)) {
                    continue;
                }
            }

            // 色を決定（マッピング）
            $color = match($methodName) {
                '現金' => 'green',
                'クレジットカード' => 'blue',
                'デビットカード' => 'purple',
                'PayPay' => 'red',
                'LINE Pay' => 'green',
                'ステラ' => 'indigo',
                'ロボットペイメント' => 'orange',
                'スクエア' => 'blue',
                default => 'gray',
            };

            $paymentMethodStats[] = [
                'label' => $methodName,
                'amount' => (int) $sale->total,
                'color' => $color,
            ];
        }

        // 支払ソース別件数
        $sourceStats = [];
        foreach ([PaymentSource::SPOT, PaymentSource::SUBSCRIPTION, PaymentSource::TICKET] as $source) {
            $query = Sale::where('payment_source', $source->value)
                ->where('status', 'completed')
                ->whereBetween('sale_date', [$from, $to]);
            if ($this->storeId) {
                $query->where('store_id', $this->storeId);
            }
            $count = $query->count();
            $amount = (int) $query->sum('total_amount'); // 利用時の物販金額

            $stat = [
                'label' => $source->label(),
                'count' => $count,
                'amount' => $amount, // 物販金額
                'color' => $source->color(),
            ];

            // サブスクの場合：契約人数と今月入金見込みを追加
            if ($source === PaymentSource::SUBSCRIPTION) {
                $contractQuery = \App\Models\CustomerSubscription::where('status', 'active');
                if ($this->storeId) {
                    $contractQuery->where('store_id', $this->storeId);
                }
                $stat['contract_count'] = $contractQuery->count();
                $stat['expected_revenue'] = (int) $contractQuery->sum('monthly_price'); // 今月入金見込み
            }

            $sourceStats[] = $stat;
        }

        // 1. 施術スタッフ別売上（既存）
        $staffQuery = User::query()
            ->whereHas('salesAsStaff', function (Builder $query) use ($from, $to) {
                $query->where('status', 'completed')
                    ->whereBetween('sale_date', [$from, $to]);
                if ($this->storeId) {
                    $query->where('store_id', $this->storeId);
                }
            })
            ->withCount([
                'salesAsStaff as sales_count' => function (Builder $query) use ($from, $to) {
                    $query->where('status', 'completed')
                        ->whereBetween('sale_date', [$from, $to]);
                    if ($this->storeId) {
                        $query->where('store_id', $this->storeId);
                    }
                }
            ])
            ->withSum([
                'salesAsStaff as total_sales' => function (Builder $query) use ($from, $to) {
                    $query->where('status', 'completed')
                        ->whereBetween('sale_date', [$from, $to]);
                    if ($this->storeId) {
                        $query->where('store_id', $this->storeId);
                    }
                }
            ], 'total_amount')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        $topStaffBySales = $staffQuery->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'name' => $user->name,
                'count' => $user->sales_count,
                'amount' => (int) $user->total_sales,
            ];
        });

        // 2. 指名スタッフ別売上（reservations.staff_id経由）
        $topStaffByReservation = \DB::table('users')
            ->join('reservations', 'reservations.staff_id', '=', 'users.id')
            ->join('sales', 'sales.reservation_id', '=', 'reservations.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->when($this->storeId, fn($q) => $q->where('sales.store_id', $this->storeId))
            ->select([
                'users.id',
                'users.name',
                \DB::raw('COUNT(sales.id) as sales_count'),
                \DB::raw('SUM(sales.total_amount) as total_sales')
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'name' => $user->name,
                    'count' => $user->sales_count,
                    'amount' => (int) $user->total_sales,
                ];
            });

        // 3. 販売スタッフ別売上（回数券のみ、sold_by経由）
        $topStaffByTicketSales = \DB::table('users')
            ->join('customer_tickets', 'customer_tickets.sold_by', '=', 'users.id')
            ->where('customer_tickets.status', 'active')
            ->when($this->storeId, fn($q) => $q->where('customer_tickets.store_id', $this->storeId))
            ->whereBetween('customer_tickets.purchased_at', [$from, $to])
            ->select([
                'users.id',
                'users.name',
                \DB::raw('COUNT(customer_tickets.id) as ticket_count'),
                \DB::raw('SUM(customer_tickets.purchase_price) as total_sales')
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'name' => $user->name,
                    'count' => $user->ticket_count,
                    'amount' => (int) $user->total_sales,
                ];
            });

        // 合計
        $totalQuery = Sale::where('status', 'completed')->whereBetween('sale_date', [$from, $to]);
        if ($this->storeId) {
            $totalQuery->where('store_id', $this->storeId);
        }

        // 日別売上推移データ
        $dailySales = Sale::where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->selectRaw('DATE(sale_date) as date, SUM(total_amount) as amount, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $chartLabels = [];
        $chartData = [];
        $currentDate = $from->copy();

        while ($currentDate <= $to) {
            $dateStr = $currentDate->format('Y-m-d');
            $chartLabels[] = $currentDate->format('m/d');

            $sale = $dailySales->firstWhere('date', $dateStr);
            $chartData[] = $sale ? (int) $sale->amount : 0;

            $currentDate->addDay();
        }

        $this->stats = [
            'payment_methods' => $paymentMethodStats,
            'sources' => $sourceStats,
            'top_staff_by_sales' => $topStaffBySales,
            'top_staff_by_reservation' => $topStaffByReservation,
            'top_staff_by_ticket_sales' => $topStaffByTicketSales,
            'total_amount' => (int) $totalQuery->sum('total_amount'),
            'total_count' => $totalQuery->count(),
            'chart_labels' => $chartLabels,
            'chart_data' => $chartData,
        ];
    }
}
