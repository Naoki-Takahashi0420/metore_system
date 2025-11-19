<?php

namespace App\Filament\Widgets;

use App\Models\FcOrder;
use App\Models\FcInvoice;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class FcStatsOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.fc-stats-overview';

    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    // 自動検出を無効化（特定ページでのみ手動登録）
    protected static bool $isDiscovered = false;

    public int $thisMonthOrdersCount = 0;
    public float $thisMonthOrdersAmount = 0;
    public int $unshippedOrders = 0;
    public int $unpaidInvoicesCount = 0;
    public float $unpaidInvoicesAmount = 0;

    public function mount(): void
    {
        $user = Auth::user();

        // 本部またはsuper_adminのみデータを取得
        if (!$user || (!$user->hasRole('super_admin') && !$user->store?->isHeadquarters())) {
            return;
        }

        // 今月の発注件数・金額
        $thisMonthOrders = FcOrder::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();

        $this->thisMonthOrdersCount = $thisMonthOrders->count();
        $this->thisMonthOrdersAmount = $thisMonthOrders->sum('total_amount');

        // 未発送の発注
        $this->unshippedOrders = FcOrder::whereIn('status', ['ordered', 'approved'])
            ->count();

        // 未払いの請求書
        $unpaidInvoices = FcInvoice::where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->get();

        $this->unpaidInvoicesCount = $unpaidInvoices->count();
        $this->unpaidInvoicesAmount = $unpaidInvoices->sum('outstanding_amount');
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
