<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Livewire\Attributes\On;

class PaymentMethodBreakdownWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '60s';

    public ?int $selectedStoreId = null;

    public function mount(): void
    {
        $user = auth()->user();

        // 初期店舗設定
        if ($user->hasRole('super_admin')) {
            $firstStore = \App\Models\Store::first();
            $this->selectedStoreId = $firstStore?->id;
        } else {
            $this->selectedStoreId = $user->store_id;
        }
    }

    #[On('store-changed')]
    public function updateStore($storeId, $date = null): void
    {
        $this->selectedStoreId = $storeId;
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && !$user->hasRole('staff');
    }

    protected function getStats(): array
    {
        $stats = [];
        $today = Carbon::today();

        // 各支払方法ごとに金額を集計
        foreach (PaymentMethod::cases() as $method) {
            $query = Sale::where('payment_method', $method->value)
                ->where('status', 'completed')
                ->whereDate('sale_date', $today);

            if ($this->selectedStoreId) {
                $query->where('store_id', $this->selectedStoreId);
            }

            $amount = (int) $query->sum('total_amount');
            $config = config("payments.payment_methods.{$method->value}");
            $color = $config['color'] ?? 'gray';

            $stats[] = Stat::make($method->label(), '¥' . number_format($amount))
                ->description('本日の売上')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($color);
        }

        // 合計金額
        $totalQuery = Sale::where('status', 'completed')->whereDate('sale_date', $today);
        if ($this->selectedStoreId) {
            $totalQuery->where('store_id', $this->selectedStoreId);
        }
        $totalAmount = (int) $totalQuery->sum('total_amount');

        $stats[] = Stat::make('合計金額', '¥' . number_format($totalAmount))
            ->description('本日の売上')
            ->descriptionIcon('heroicon-m-currency-yen')
            ->color('success')
            ->extraAttributes(['class' => 'col-span-full border-2 border-green-500']);

        return $stats;
    }
}