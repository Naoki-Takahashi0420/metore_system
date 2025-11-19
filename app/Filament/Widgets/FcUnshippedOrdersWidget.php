<?php

namespace App\Filament\Widgets;

use App\Models\FcOrder;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class FcUnshippedOrdersWidget extends Widget
{
    protected static string $view = 'filament.widgets.fc-unshipped-orders';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function getOrders()
    {
        return FcOrder::query()
            ->whereIn('status', ['ordered', 'approved'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public static function canView(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // super_adminまたは本部店舗のみ表示
        return $user->hasRole('super_admin') || $user->store?->isHeadquarters();
    }
}
