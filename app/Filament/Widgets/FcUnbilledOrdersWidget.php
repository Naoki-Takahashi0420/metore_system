<?php

namespace App\Filament\Widgets;

use App\Models\FcOrder;
use App\Models\FcInvoice;
use App\Models\Store;
use Filament\Widgets\Widget;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class FcUnbilledOrdersWidget extends Widget
{
    protected static string $view = 'filament.widgets.fc-unbilled-orders';

    protected static ?int $sort = -3;

    protected int | string | array $columnSpan = 'full';

    public array $storesWithUnbilledOrders = [];
    public int $totalUnbilledCount = 0;

    public function mount(): void
    {
        $user = Auth::user();

        if (!$user || (!$user->hasRole('super_admin') && !$user->store?->isHeadquarters())) {
            return;
        }

        $this->loadUnbilledOrders();
    }

    protected function loadUnbilledOrders(): void
    {
        $fcStores = Store::where('fc_type', 'fc_store')
            ->orderBy('name')
            ->get();

        $storesData = [];

        foreach ($fcStores as $store) {
            $unbilledOrders = FcOrder::where('fc_store_id', $store->id)
                ->where('status', FcOrder::STATUS_DELIVERED)
                ->whereNull('fc_invoice_id')
                ->with(['items'])
                ->orderBy('delivered_at', 'asc')
                ->get();

            if ($unbilledOrders->count() > 0) {
                $storesData[] = [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'orders' => $unbilledOrders->map(fn($o) => [
                        'order_number' => $o->order_number,
                        'delivered_at' => $o->delivered_at?->format('m/d'),
                        'total_amount' => $o->total_amount,
                        'items_count' => $o->items->count(),
                    ])->toArray(),
                    'total' => $unbilledOrders->sum('total_amount'),
                    'count' => $unbilledOrders->count(),
                ];
            }
        }

        $this->storesWithUnbilledOrders = $storesData;
        $this->totalUnbilledCount = collect($storesData)->sum('count');
    }

    public function generateInvoiceForStore(int $storeId): void
    {
        $user = Auth::user();
        if (!$user->hasRole('super_admin') && !$user->store?->isHeadquarters()) {
            Notification::make()
                ->danger()
                ->title('権限がありません')
                ->send();
            return;
        }

        $store = Store::find($storeId);
        if (!$store) {
            Notification::make()
                ->danger()
                ->title('店舗が見つかりません')
                ->send();
            return;
        }

        $invoice = FcInvoice::createMonthlyInvoice($store);

        if ($invoice) {
            Notification::make()
                ->success()
                ->title("{$store->name}の請求書を生成しました")
                ->body("請求書番号: {$invoice->invoice_number}")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('edit')
                        ->label('編集してロイヤリティを追加')
                        ->url(route('filament.admin.resources.fc-invoices.edit', $invoice))
                ])
                ->send();

            // リロード
            $this->loadUnbilledOrders();
        } else {
            Notification::make()
                ->warning()
                ->title('生成対象がありません')
                ->body("{$store->name}に未請求の納品済み発注がありません")
                ->send();
        }
    }

    public static function canView(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->store?->isHeadquarters();
    }
}
